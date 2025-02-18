<?php

namespace App\Http\Controllers;


use App\Mail\NotificacionPagoCompletado;
use App\Mail\NotificacionPagoCompletadoBoleta;
use App\Mail\NotificacionPagoCompletadoFactura;
use App\Models\Facturacion;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use Exception;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Dompdf\Dompdf;
use Dompdf\Options;

class PaymentController extends Controller
{
    public function __construct()
    {
        // Agrega las credenciales de MercadoPago
        MercadoPagoConfig::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
    }

    public function createPreference(Request $request)
    {
        // Validar los datos recibidos
        $request->validate([
            'idCita' => 'required|integer',
            'monto' => 'required|numeric',
            'correo' => 'required|email'
        ]);
    
        // Obtener los datos del request
        $idCita = $request->input('idCita');
        $monto = $request->input('monto');
    
        // Verificar si la cita existe y está en estado "pago pendiente"
        $cita = DB::table('citas')
            ->where('idCita', $idCita)
            ->where('estado', 'pago pendiente')
            ->first();
    
        if (!$cita) {
            return response()->json([
                'success' => false,
                'message' => 'La cita no existe o no está en estado "pago pendiente".'
            ], 404);
        }
    
        // Crear una instancia del cliente de preferencias de MercadoPago
        $client = new PreferenceClient();

        // $currentUrlBase = 'https://cismedic.vercel.app'; // DOMINIO DEL FRONT

         $currentUrlBase = 'https://thonymarckdev.vercel.app'; // DOMINIO DEL FRONT
        

        //$currentUrlBase = 'http://localhost:3000'; // DOMINIO DEL FRONT
    
        // URLs de retorno
        $backUrls = [
            "success" => "{$currentUrlBase}/cliente/mispagos?status=approved&external_reference={$idCita}&payment_type=online",
            "failure" => "{$currentUrlBase}/cliente/mispagos?status=failure&external_reference={$idCita}",
            "pending" => "{$currentUrlBase}/cliente/mispagos?status=pending&external_reference={$idCita}"
        ];
    
        // Configurar los ítems para MercadoPago
        $items = [
            [
                "id" => $idCita,
                "title" => "Pago de cita médica",
                "quantity" => 1,
                "unit_price" => (float)$monto,
                "currency_id" => "PEN" // Ajusta según tu moneda
            ]
        ];
    
        // Configurar la preferencia con los datos necesarios
        $preferenceData = [
            "items" => $items,
            "payer" => [
                "email" => $request->input('correo')
            ],
            "back_urls" => $backUrls,
            "auto_return" => "approved", // Automáticamente vuelve al front-end cuando el pago es aprobado
            "binary_mode" => true, // Usar modo binario para más seguridad
            "external_reference" => $idCita
        ];
    
        try {
            // Crear la preferencia en MercadoPago
            $preference = $client->create($preferenceData);
    
            // Verificar si se creó la preferencia correctamente
            if (isset($preference->id)) {
                // Responder con el punto de inicio del pago
                return response()->json([
                    'success' => true,
                    'init_point' => $preference->init_point,
                    'preference_id' => $preference->id // Para el modal
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la preferencia en MercadoPago'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la preferencia: ' . $e->getMessage()
            ]);
        }
    }

    public function recibirPago(Request $request)
    {
        try {
            $id = $request->input('data')['id'] ?? null;
            $type = $request->input('type') ?? null;
    
            if (!$id || $type !== 'payment') {
                Log::warning('ID del pago o tipo no válido.');
                return response()->json(['error' => 'ID del pago o tipo no válido'], 400);
            }
    
            // Consultar a la API de Mercado Pago
            $url = "https://api.mercadopago.com/v1/payments/{$id}";
            $client = new Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
                ],
            ]);
    
            $pago = json_decode($response->getBody(), true);
            $estado_pago = trim(strtolower($pago['status'])); // Aseguramos formato uniforme
            $metodo_pago = $pago['payment_method_id'] ?? null;
            $externalReference = $pago['external_reference'];
    
            // Verificar si existe un pago asociado a la cita
            $pagoModel = DB::table('pagos')
                ->where('idCita', $externalReference)
                ->first();
    
            if (!$pagoModel) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado para esta cita'], 200);
            }
    
            if ($pagoModel->estado === 'pagado') {
                return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'], 200);
            }
    
            // Obtener el tipo de comprobante y el RUC (si es factura)
            $tipoComprobante = $pagoModel->tipo_comprobante;
            $ruc = $tipoComprobante === 'factura' ? $pagoModel->ruc : null;
    
            // Actualizar el estado del pago
            DB::table('pagos')
                ->where('idCita', $externalReference)
                ->update([
                    'estado' => 'pagado',
                    'tipo_pago' => $metodo_pago,
                    'fecha_pago' => now()
                ]);
    
            // Obtener los datos de la cita
            $cita = DB::table('citas')
                ->where('idCita', $externalReference)
                ->first();
    
            if (!$cita) {
                return response()->json(['success' => false, 'message' => 'Cita no encontrada'], 404);
            }
    
            // Obtener el costo de la cita desde la tabla horarios_doctores
            $horarioDoctor = DB::table('horarios_doctores')
                ->where('idHorario', $cita->idHorario)
                ->first();
    
            if (!$horarioDoctor) {
                return response()->json(['success' => false, 'message' => 'No se encontró el horario del doctor asociado a la cita'], 404);
            }
    
            $costoCita = $horarioDoctor->costo;
    
            // Actualizar el estado de la cita
            if ($estado_pago === 'approved') {
                DB::table('citas')
                    ->where('idCita', $externalReference)
                    ->update(['estado' => 'pagado']);
            }
    
            // Obtener el correo del cliente principal
            $clientePrincipal = DB::table('usuarios')
                ->where('idUsuario', $cita->idCliente)
                ->select('correo', DB::raw("CONCAT(nombres, ' ', apellidos) as nombre_completo"))
                ->first();
    
            if (!$clientePrincipal) {
                return response()->json(['success' => false, 'message' => 'Cliente principal no encontrado'], 404);
            }
    
            // Obtener el nombre completo del cliente o del familiar
            $clienteNombreCompleto = '';
            if ($cita->idFamiliarUsuario) {
                // Obtener los datos del familiar
                $familiar = DB::table('familiares_usuarios')
                    ->where('idFamiliarUsuario', $cita->idFamiliarUsuario)
                    ->selectRaw("CONCAT(nombre, ' ', apellidos) as nombre_completo")
                    ->value('nombre_completo');
    
                if (!$familiar) {
                    return response()->json(['success' => false, 'message' => 'Familiar no encontrado'], 404);
                }
    
                $clienteNombreCompleto = $familiar;
            } else {
                // Usar el nombre completo del cliente principal
                $clienteNombreCompleto = $clientePrincipal->nombre_completo;
            }
    
            // Detalles del comprobante
            $detallesComprobante = [
                'especialidad' => $cita->especialidad,
                'doctor' => DB::table('usuarios')
                    ->where('idUsuario', $cita->idDoctor)
                    ->selectRaw("CONCAT(nombres, ' ', apellidos) as nombre_completo") // Concatenar nombres y apellidos
                    ->value('nombre_completo'), // Obtener el valor concatenado
                'fecha' => $horarioDoctor->fecha,
                'hora_inicio' => $horarioDoctor->hora_inicio,
                'monto' => $costoCita,
            ];
    
            // Generar el PDF según el tipo de comprobante
            if ($tipoComprobante === 'boleta') {
                // Crear la ruta del directorio usando el idCita
                $pdfDirectory = "storage/comprobantesMedicos/Boletas/citas/{$cita->idCita}/";
                $pdfFileName = "boleta_" . date('Ymd_His') . "_" . $cita->idCita . ".pdf";
                $pdfPath = $pdfDirectory . $pdfFileName;
    
                if (!file_exists($pdfDirectory)) {
                    mkdir($pdfDirectory, 0755, true);
                }
    
                $this->generateBoletaPDF(
                    $pdfPath,
                    $clienteNombreCompleto,
                    $detallesComprobante,
                    $costoCita
                );
    
                // Enviar correo con la boleta al cliente principal
                Mail::to($clientePrincipal->correo)->send(new NotificacionPagoCompletadoBoleta(
                    $clienteNombreCompleto,
                    $detallesComprobante,
                    $costoCita,
                    $pdfPath,
                    $cita->idCita
                ));
            } elseif ($tipoComprobante === 'factura') {
                $pdfDirectory = "storage/comprobantesMedicos/Facturas/citas/{$cita->idCita}/";
                $pdfFileName = "factura_" . date('Ymd_His') . "_" . $cita->idCita . ".pdf";
                $pdfPath = $pdfDirectory . $pdfFileName;
    
                if (!file_exists($pdfDirectory)) {
                    mkdir($pdfDirectory, 0755, true);
                }
    
                $this->generateFacturaPDF(
                    $pdfPath,
                    $clienteNombreCompleto, // Concatenar nombres y apellidos del cliente o familiar
                    $detallesComprobante,
                    $costoCita,
                    $ruc,
                );
    
                // Enviar correo con la factura al cliente principal
                Mail::to($clientePrincipal->correo)->send(new NotificacionPagoCompletadoFactura(
                    $clienteNombreCompleto,
                    $detallesComprobante,
                    $costoCita,
                    $pdfPath,
                    $ruc, // Usamos el RUC para la factura
                    $cita->idCita
                ));
            }
    
            return response()->json(['success' => true, 'message' => 'Estado de pago y cita actualizados correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al procesar el webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    protected function generateBoletaPDF($pdfPath, $nombreCliente, $detallesComprobante, $costoCita) {
        // Crear un nuevo PDF con formato personalizado
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        
        // Propiedades del documento
        $pdf->SetTitle('Comprobante de Pago - Clínica');
        $pdf->SetAuthor('Cismedic');
        
        // Colores personalizados
        $primaryColor = [28, 40, 51];    // Azul oscuro-grisáceo
        $accentColor = [45, 136, 89];   // Verde 700
        $textColor = [44, 62, 80];       // Gris oscuro
        
        // Márgenes para un espaciado elegante
        $pdf->SetMargins(25, 20, 25);
        $pdf->SetAutoPageBreak(true, 25);
        
        // Encabezado con logo
        $pdf->Image(public_path('storage/logo/logo.png'), 25, 20, 40);
        
        // Información de la empresa (alineada a la derecha)
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$textColor);
        $pdf->SetXY(120, 20);
        $pdf->Cell(70, 6, 'Cismedic Centro Medico', 0, 1, 'R');
        $pdf->SetXY(120, 26);
        $pdf->Cell(70, 6, 'Jose Galvez 415, Sechura 20691', 0, 1, 'R');
        $pdf->SetXY(120, 32);
        $pdf->Cell(70, 6, 'Tel: +51 968 103 600', 0, 1, 'R');
        
        // Título del documento
        $pdf->SetY(70);
        $pdf->SetFont('Helvetica', 'B', 24);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'COMPROBANTE DE PAGO', 0, 1, 'C');
        
        // Línea separadora elegante
        $pdf->SetDrawColor(...$accentColor);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(25, 85, 185, 85);
        
        // Sección de información del cliente
        $pdf->SetY(95);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'INFORMACION DEL PACIENTE', 0, 1, 'L');
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$textColor);
        $pdf->Cell(40, 7, 'Paciente:', 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, $nombreCliente, 0, 1);
        
        // Sección de detalles del servicio
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'DETALLE DEL SERVICIO', 0, 1, 'L');
        
        // Detalles del servicio
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$textColor);
        foreach ($detallesComprobante as $key => $value) {
            $pdf->Cell(40, 7, ucfirst($key) . ':', 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(0, 7, $value, 0, 1);
            $pdf->SetFont('Helvetica', '', 10);
        }
        
        // Sección del monto total con un recuadro elegante
        $pdf->Ln(10);
        $pdf->SetFillColor(...$primaryColor);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(140, 12, 'MONTO TOTAL', 0, 0, 'R', true);
        $pdf->Cell(30, 12, 'S/ ' . number_format($costoCita, 2), 0, 1, 'R', true);
        
        // Pie de página
        $pdf->SetY(-50);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Gracias por confiar en Cismedic', 0, 1, 'C');
        
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$textColor);
        $pdf->Cell(0, 6, 'Este documento es un comprobante valido de su pago', 0, 1, 'C');
        
        // Guardar el PDF
        $pdf->Output('F', $pdfPath);
    }


    protected function generateFacturaPDF($pdfPath, $nombreCliente, $detallesComprobante, $costoCita, $ruc) {
        // Crear un nuevo PDF con formato personalizado
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        
        // Propiedades del documento
        $pdf->SetTitle('Factura - Clínica');
        $pdf->SetAuthor('Cismedic');
        
        // Colores personalizados
        $primaryColor = [28, 40, 51];    // Azul oscuro-grisáceo
        $accentColor = [45, 136, 89];   // Verde 700
        $textColor = [44, 62, 80];       // Gris oscuro
        
        // Márgenes para un espaciado elegante
        $pdf->SetMargins(25, 20, 25);
        $pdf->SetAutoPageBreak(true, 25);
        
        // Encabezado con logo
        $pdf->Image(public_path('storage/logo/logo.png'), 25, 20, 40);
        
        // Información de la empresa (alineada a la derecha)
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$textColor);
        $pdf->SetXY(120, 20);
        $pdf->Cell(70, 6, 'Cismedic Centro Medico', 0, 1, 'R');
        $pdf->SetXY(120, 26);
        $pdf->Cell(70, 6, 'Jose Galvez 415, Sechura 20691', 0, 1, 'R');
        $pdf->SetXY(120, 32);
        $pdf->Cell(70, 6, 'Tel: +51 968 103 600', 0, 1, 'R');
        
        // Título del documento
        $pdf->SetY(70);
        $pdf->SetFont('Helvetica', 'B', 24);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'FACTURA', 0, 1, 'C');
        
        // Línea separadora elegante
        $pdf->SetDrawColor(...$accentColor);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(25, 85, 185, 85);
        
        // Sección de información del cliente
        $pdf->SetY(95);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'INFORMACION DEL CLIENTE', 0, 1, 'L');
        
        // Datos del cliente
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$textColor);
        
        // Nombre del cliente
        $pdf->Cell(40, 7, 'Cliente:', 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, $nombreCliente, 0, 1);
        
        // RUC
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(40, 7, 'RUC:', 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, $ruc, 0, 1);
        
        // Sección de detalles del servicio
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->Cell(0, 10, 'DETALLE DEL SERVICIO', 0, 1, 'L');
        
        // Detalles del servicio
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(...$textColor);
        foreach ($detallesComprobante as $key => $value) {
            $pdf->Cell(40, 7, ucfirst($key) . ':', 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(0, 7, $value, 0, 1);
            $pdf->SetFont('Helvetica', '', 10);
        }
        
        // Sección del monto total con un recuadro elegante
        $pdf->Ln(10);
        $pdf->SetFillColor(...$primaryColor);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(140, 12, 'MONTO TOTAL', 0, 0, 'R', true);
        $pdf->Cell(30, 12, 'S/ ' . number_format($costoCita, 2), 0, 1, 'R', true);
        
        // Pie de página
        $pdf->SetY(-50);
        $pdf->SetTextColor(...$primaryColor);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 10, 'Gracias por confiar en Cismedic', 0, 1, 'C');
        
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$textColor);
        $pdf->Cell(0, 6, 'Este documento es una factura valida de su pago', 0, 1, 'C');
        
        // Guardar el PDF
        $pdf->Output('F', $pdfPath);
    }

        public function descargarBoleta($idCita)
        {
            try {
                // Buscar el pago correspondiente a la cita en la tabla 'pagos'
                $pago = DB::table('pagos')->where('idCita', $idCita)->first();

                // Si no se encuentra en 'pagos', buscar en 'historial_pagos' como segunda opción
                if (!$pago) {
                    $pago = DB::table('historial_pagos')->where('idCita', $idCita)->first();

                    // Si no se encuentra en ninguna de las dos tablas, devolver error
                    if (!$pago) {
                        Log::error("No se encontró el pago para la cita #{$idCita}");
                        return response()->json([
                            'error' => "No se encontró el pago para la cita #{$idCita}"
                        ], 404);
                    }
                }

                // Determinar la ruta según el tipo de comprobante
                $tipoComprobante = $pago->tipo_comprobante;
                $path = public_path("storage/comprobantesMedicos/" . ($tipoComprobante === 'boleta' ? 'Boletas' : 'Facturas') . "/citas/{$idCita}");

                // Buscar archivos PDF en el directorio
                $pdfs = glob($path . "/*.pdf");

                // Log para debug
                Log::info("Buscando PDFs en: " . $path);

                if (empty($pdfs)) {
                    Log::error("No se encontraron PDFs en el directorio");
                    return response()->json([
                        'error' => "No se encontró el comprobante para la cita #{$idCita}"
                    ], 404);
                }

                // Obtener el archivo más reciente
                $archivo = $pdfs[0];
                Log::info("Archivo encontrado: " . $archivo);

                // Verificar que el archivo es legible
                if (!is_readable($archivo)) {
                    Log::error("Archivo no legible: " . $archivo);
                    return response()->json([
                        'error' => 'El comprobante existe pero no se puede acceder'
                    ], 403);
                }

                // Devolver el archivo
                return response()->file($archivo, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . basename($archivo) . '"',
                    'Cache-Control' => 'no-cache'
                ]);

            } catch (\Exception $e) {
                Log::error("Error al descargar boleta para cita #{$idCita}: " . $e->getMessage());

                return response()->json([
                    'error' => 'Error al procesar la descarga del comprobante',
                    'message' => $e->getMessage()
                ], 500);
            }
        }
}







