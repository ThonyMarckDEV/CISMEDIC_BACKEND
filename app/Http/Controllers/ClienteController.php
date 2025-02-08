<?php

namespace App\Http\Controllers;

use App\Mail\NotificacionPagoPendiente;
use App\Models\CaracteristicaProducto;
use App\Models\ImagenModelo;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;
use App\Models\Modelo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Mail\ConfirmacionCita;
use App\Mail\ConfirmacionPago;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ClienteController extends Controller
{
     // Obtener todas las especialidades

     public function getEspecialidades()
     {
         try {
             $especialidades = DB::table('especialidades')
                 ->where('estado', 'activo')
                 ->get();
             
             return response()->json($especialidades);
         } catch (\Exception $e) {
             return response()->json([
                 'error' => 'Error al obtener especialidades',
                 'details' => $e->getMessage()
             ], 500);
         }
     }
     
     public function getDoctoresPorEspecialidad($idEspecialidad)
     {
         try {
             $doctores = DB::table('usuarios')
                 ->join('especialidades_usuarios', 'usuarios.idUsuario', '=', 'especialidades_usuarios.idUsuario')
                 ->where('usuarios.rol', 'doctor')
                 ->where('usuarios.estado', 'activo')
                 ->where('especialidades_usuarios.idEspecialidad', $idEspecialidad)
                 ->select('usuarios.idUsuario', 'usuarios.nombres', 'usuarios.apellidos')
                 ->get();
     
             return response()->json($doctores);
         } catch (\Exception $e) {
             return response()->json([
                 'error' => 'Error al obtener doctores',
                 'details' => $e->getMessage()
             ], 500);
         }
     }
     

    public function getHorariosDisponibles($idDoctor, $fecha)
    {
        try {
            // Validar que se proporcionen el ID del doctor y la fecha
            if (!$idDoctor || !$fecha) {
                return response()->json([
                    'error' => 'Se requieren el ID del doctor y la fecha'
                ], 400);
            }
    
            // Obtener la fecha y hora actual
            $fechaActual = now()->format('Y-m-d');
            $horaActual = now()->format('H:i:s');
    
            // Margen de tiempo en minutos (configurable)
            $margenTiempo = config('app.horario_margen', 20); // Por defecto: 20 minutos
    
            // Determinar si la fecha solicitada es hoy o futura
            $esFechaFutura = $fecha > $fechaActual;
    
            // Calcular la hora mínima permitida (hora actual + margen de tiempo)
            $horaMinimaPermitida = now()->addMinutes($margenTiempo)->format('H:i:s');
    
            // Obtener todos los horarios disponibles para el doctor en la fecha dada
            $horarios = DB::table('horarios_doctores')
                ->where('idDoctor', $idDoctor)
                ->where('fecha', $fecha)
                ->where('estado', 'activo') // Asegurarse de que el horario esté activo
                ->when(!$esFechaFutura, function ($query) use ($horaMinimaPermitida) {
                    // Si la fecha es hoy, excluir horarios que ya pasaron o están dentro del margen de tiempo
                    return $query->where('hora_inicio', '>', $horaMinimaPermitida);
                })
                ->whereNotIn('idHorario', function ($query) use ($idDoctor, $fecha) {
                    // Subconsulta para excluir los horarios que ya están ocupados por citas con estado "pago pendiente", "completada" o "pagado"
                    $query->select('idHorario')
                        ->from('citas')
                        ->where('idDoctor', $idDoctor)
                        ->where('fecha', $fecha)
                        ->whereIn('estado', ['pago pendiente', 'completada', 'pagado']);
                })
                ->orWhere(function ($query) use ($idDoctor, $fecha, $esFechaFutura, $horaActual, $margenTiempo) {
                    // Incluir horarios cancelados si la fecha es futura o si la fecha es hoy pero aún no han pasado más de `$margenTiempo` minutos desde su hora de inicio
                    $query->where('idDoctor', $idDoctor)
                        ->where('fecha', $fecha)
                        ->where('estado', 'activo') // Asegurarse de que el horario esté activo
                        ->whereIn('idHorario', function ($subQuery) use ($idDoctor, $fecha, $esFechaFutura, $horaActual, $margenTiempo) {
                            $subQuery->select('idHorario')
                                ->from('citas')
                                ->where('idDoctor', $idDoctor)
                                ->where('fecha', $fecha)
                                ->where('estado', 'cancelada')
                                ->when(!$esFechaFutura, function ($subQuery) use ($horaActual, $margenTiempo) {
                                    // Si la fecha es hoy, calcular la hora límite (hora_inicio - margenTiempo)
                                    return $subQuery->whereRaw("ADDTIME(hora_inicio, '-{$margenTiempo} MINUTE') <= ?", [$horaActual]);
                                });
                        });
                })
                ->select(
                    'idHorario',
                    'hora_inicio',
                    'costo'  // Añadimos el campo 'costo'
                )
                ->orderBy('hora_inicio', 'asc') // Ordenar los horarios por hora de inicio
                ->get();
    
            // Registrar los horarios encontrados para depuración
            Log::info('Horarios encontrados:', ['horarios' => $horarios]);
    
            // Devolver los horarios disponibles en formato JSON
            return response()->json([
                'horarios' => $horarios
            ]);
        } catch (\Exception $e) {
            // Registrar cualquier error que ocurra durante la ejecución
            Log::error('Error en getHorariosDisponibles:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            // Devolver un mensaje de error en caso de excepción
            return response()->json([
                'error' => 'Error al obtener horarios disponibles',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    public function getWeekSchedule(Request $request, $doctorId)
    {
        try {
            // Parsear la fecha proporcionada en la solicitud
            $date = Carbon::parse($request->query('date'));
            $weekStart = $date->copy()->startOfWeek();
            $weekEnd = $date->copy()->endOfWeek();
    
            // Definir el rango de fechas para incluir siempre fechas futuras
            $startDate = $weekStart->format('Y-m-d');
            $endDate = '2100-12-31'; // Fecha muy futura para incluir todos los slots disponibles
    
            // Margen de tiempo en minutos (configurable)
            $margenTiempo = config('app.horario_margen', 20); // Por defecto: 20 minutos
    
            // Obtener todas las citas pagadas, pendientes o completadas para el doctor en el rango de fechas
            $bookedSlots = DB::table('citas')
                ->join('horarios_doctores', 'citas.idHorario', '=', 'horarios_doctores.idHorario')
                ->leftJoin('pagos', 'citas.idCita', '=', 'pagos.idCita') // Left join para incluir citas sin pago
                ->where('citas.idDoctor', $doctorId)
                ->whereBetween('horarios_doctores.fecha', [$startDate, $endDate])
                ->whereIn('citas.estado', ['pagado', 'pago pendiente', 'completada']) // Incluir citas pagadas, pendientes o completadas
                ->select(
                    'horarios_doctores.fecha',
                    'horarios_doctores.hora_inicio',
                    'citas.estado' // Añadimos el estado de la cita
                )
                ->get();
    
            // Obtener todos los slots disponibles para el doctor en el rango de fechas
            $availableSlots = DB::table('horarios_doctores')
                ->where('idDoctor', $doctorId)
                ->where('estado', 'activo') // Asegurarse de que el horario esté activo
                ->whereBetween('fecha', [$startDate, $endDate])
                ->select(
                    'fecha',
                    'hora_inicio'
                )
                ->get();
    
            // Filtrar los slots disponibles eliminando los que están reservados y excluyendo fechas pasadas
            $currentTime = Carbon::now(); // Obtener la hora actual
            $today = Carbon::today()->format('Y-m-d'); // Obtener la fecha de hoy
    
            $availableSlots = $availableSlots->reject(function ($slot) use ($bookedSlots, $margenTiempo, $currentTime, $today) {
                // Excluir slots cuya fecha ya pasó
                if ($slot->fecha < $today) {
                    return true;
                }
    
                // Verificar si el slot está ocupado por una cita pagada, pendiente o completada
                if ($bookedSlots->contains(function ($bookedSlot) use ($slot) {
                    return $bookedSlot->fecha === $slot->fecha && $bookedSlot->hora_inicio === $slot->hora_inicio;
                })) {
                    return true;
                }
    
                // Para slots del día actual, aplicar el margen de tiempo si la cita está cancelada
                if ($slot->fecha === $today) {
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->fecha . ' ' . $slot->hora_inicio);
                    $margenDateTime = $slotDateTime->copy()->subMinutes($margenTiempo);
    
                    if ($currentTime->greaterThanOrEqualTo($margenDateTime)) {
                        return true;
                    }
                }
    
                return false;
            });
    
            // Devolver la respuesta con los slots disponibles y el rango de la semana
            return response()->json([
                'availableSlots' => $availableSlots,
                'weekStart' => $weekStart->format('Y-m-d'),
                'weekEnd' => $weekEnd->format('Y-m-d')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener el horario del doctor',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function agendarCita(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'idCliente' => 'required|integer|exists:usuarios,idUsuario',
            'idFamiliarUsuario' => 'nullable|integer|exists:familiares_usuarios,idFamiliarUsuario', // Nuevo campo
            'idDoctor' => 'required|integer|exists:usuarios,idUsuario',
            'idHorario' => 'required|integer|exists:horarios_doctores,idHorario',
            'especialidad' => 'required|integer|exists:especialidades,idEspecialidad',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        try {
            // Verificar si el horario está activo
            $horario = DB::table('horarios_doctores')
                ->where('idHorario', $request->idHorario)
                ->first();

            if (!$horario || $horario->estado !== 'activo') {
                return response()->json([
                    'error' => 'El horario seleccionado ya no está disponible.'
                ], 409); // Código HTTP 409: Conflict
            }

            // Obtener el nombre de la especialidad
            $especialidadnombre = DB::table('especialidades')
                ->where('idEspecialidad', $request->especialidad)
                ->value('nombre');

            // Insertar la cita en la tabla `citas`
            DB::table('citas')->insert([
                'idCliente' => $request->idCliente,
                'idFamiliarUsuario' => $request->idFamiliarUsuario, // Insertar el ID del familiar
                'idDoctor' => $request->idDoctor,
                'idHorario' => $request->idHorario,
                'especialidad' => $especialidadnombre,
                'estado' => 'pago pendiente',
                'motivo' => null
            ]);

            // Obtener el ID de la cita recién creada
            $idCita = DB::getPdo()->lastInsertId();

            // Obtener los datos del cliente, doctor y horario
            $cliente = DB::table('usuarios')->where('idUsuario', $request->idCliente)->first();
            $doctor = DB::table('usuarios')->where('idUsuario', $request->idDoctor)->first();

            // Crear un objeto con los datos de la cita para el correo
            $citaData = [
                'doctor_nombre' => $doctor->nombres . ' ' . $doctor->apellidos,
                'fecha' => $horario->fecha,
                'hora' => $horario->hora_inicio,
                'especialidad' => $especialidadnombre,
                'estado' => 'pago pendiente',
            ];

            // Obtener el costo desde la tabla `horarios_doctores`
            $costo = $horario->costo;

            // Crear un objeto con los datos del pago
            $pagoData = [
                'idCita' => $idCita,
                'costo' => $costo,
            ];

            // Enviar el correo de confirmación de la cita
            Mail::to($cliente->correo)->send(new ConfirmacionCita($cliente, $citaData));

            // Enviar el correo de notificación de pago pendiente
            Mail::to($cliente->correo)->send(new NotificacionPagoPendiente($cliente, $citaData, (object)$pagoData));

            return response()->json([
                'message' => 'Cita agendada exitosamente',
                'idCita' => $idCita,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al agendar la cita: ' . $e->getMessage());
            return response()->json(['error' => 'Error al agendar la cita: ' . $e->getMessage()], 500);
        }
    }

    // Función para registrar el pago de una cita
    public function registrarPago(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'idCita' => 'required|integer|exists:citas,idCita',
            'monto' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Insertar el pago en la tabla `pagos`
        try {
            DB::table('pagos')->insert([
                'idCita' => $request->idCita,
                'monto' => $request->monto,
                'estado' => 'pendiente', // Estado inicial
                'fecha_pago' => null, // Fecha de pago inicialmente nula
                'tipo_pago' => null, // Tipo de pago inicialmente nulo
                'hora_generacion' => now()->format('H:i:s'), // Registrar la hora de generación del pago
                'tipo_comprobante' => null,
                'ruc'=>null
            ]);

            return response()->json([
                'message' => 'Pago registrado exitosamente',
                'nota' => 'OJO: Si no realizas el pago en los 10 minutos posteriores, tu cita se cancelará automáticamente.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al registrar el pago: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarComprobante(Request $request)
    {
        try {
            // Validación de los datos de entrada
            $validator = Validator::make($request->all(), [
                'idPago' => 'required|integer|exists:pagos,idPago',
                'tipo_comprobante' => 'required|in:boleta,factura',
                'ruc' => 'nullable|required_if:tipo_comprobante,factura|string|size:11',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener el pago desde la base de datos
            $pago = DB::table('pagos')->where('idPago', $request->idPago)->first();

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pago no existe'
                ], 404);
            }

            // Verificar que el pago está en estado pendiente
            if ($pago->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden modificar pagos pendientes'
                ], 400);
            }

            // Actualizar el tipo de comprobante y el RUC en la base de datos
            $rucValue = ($request->tipo_comprobante === 'factura') ? $request->ruc : null;

            DB::table('pagos')
                ->where('idPago', $request->idPago)
                ->update([
                    'tipo_comprobante' => $request->tipo_comprobante,
                    'ruc' => $rucValue
                ]);

            // Retornar respuesta exitosa
            $pagoActualizado = DB::table('pagos')->where('idPago', $request->idPago)->first();

            return response()->json([
                'success' => true,
                'message' => 'Comprobante actualizado correctamente',
                'data' => $pagoActualizado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el comprobante: ' . $e->getMessage()
            ], 500);
        }
    }


    public function obtenerCitas($userId)
    {
        $appointments = DB::table('citas as c')
            ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario')
            ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario')
            ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
            ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario')
            ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad')
            ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita')
            ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario') // Unión con familiares_usuarios
            ->select(
                'c.idCita',
                'u_cliente.nombres as clienteNombre',
                'u_cliente.apellidos as clienteApellidos',
                'u_doctor.nombres as doctorNombre',
                'u_doctor.apellidos as doctorApellidos',
                'e.nombre as especialidad',
                'hd.fecha',
                'hd.hora_inicio as horaInicio',
                'hd.costo',
                'c.estado',
                'c.motivo', // Agregar el campo motivoCancelacion
                'p.idPago',
                DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'), // Obtener el DNI del familiar o del cliente
                DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'), // Nombre del paciente (familiar o cliente)
                DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos') // Apellidos del paciente (familiar o cliente)
            )
            ->where('c.idCliente', $userId)
            ->orderByRaw("
                CASE 
                    WHEN c.estado = 'pago pendiente' THEN 1
                    WHEN c.estado = 'pagado' THEN 2
                    WHEN c.estado = 'completada' THEN 3
                    WHEN c.estado = 'cancelada' THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('hd.fecha', 'asc')
            ->orderBy('hd.hora_inicio', 'asc')
            ->get();

        return response()->json($appointments);
    }

    // public function obtenerHistorialCitasCliente($idCliente, Request $request)
    // {
    //     try {
    //         if (!$idCliente) {
    //             return response()->json([
    //                 'error' => 'ID del cliente no proporcionado'
    //             ], 400);
    //         }
    
    //         $estadoFiltro = $request->query('estado');
    //         $nombrePacienteFiltro = $request->query('nombrePaciente');
    //         $dniFiltro = $request->query('dni');
    //         $idCitaFiltro = $request->query('idCita');
    //         $fechaFiltro = $request->query('fecha');
    //         $horaFiltro = $request->query('hora');
    
    //         $query = DB::table('historial_citas as c')
    //             ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario')
    //             ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario')
    //             ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
    //             ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario')
    //             ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad')
    //             ->leftJoin('historial_pagos as p', 'c.idCita', '=', 'p.idCita')
    //             ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario')
    //             ->select(
    //                 'c.idCita',
    //                 'u_cliente.nombres as clienteNombre',
    //                 'u_cliente.apellidos as clienteApellidos',
    //                 'u_doctor.nombres as doctorNombre',
    //                 'u_doctor.apellidos as doctorApellidos',
    //                 'e.nombre as especialidad',
    //                 'hd.fecha',
    //                 'hd.hora_inicio as horaInicio',
    //                 'hd.costo',
    //                 'c.estado',
    //                 'c.motivo',
    //                 'p.idPago',
    //                 DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'),
    //                 DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'),
    //                 DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos')
    //             )
    //             ->where('c.idCliente', $idCliente);
    
    //         if ($estadoFiltro && in_array($estadoFiltro, ['completada', 'cancelada'])) {
    //             $query->where('c.estado', $estadoFiltro);
    //         } else {
    //             $query->whereIn('c.estado', ['completada', 'cancelada']);
    //         }
    
    //         // Corrección del filtro por nombre del paciente
    //         if ($nombrePacienteFiltro) {
    //             $nombrePacienteFiltro = strtolower($nombrePacienteFiltro);
    //             $query->whereRaw("LOWER(COALESCE(fu.nombre, u_cliente.nombres)) COLLATE utf8mb4_general_ci LIKE ? OR LOWER(COALESCE(fu.apellidos, u_cliente.apellidos)) COLLATE utf8mb4_general_ci LIKE ?", 
    //                 ["%{$nombrePacienteFiltro}%", "%{$nombrePacienteFiltro}%"]);
    //         }
    
    //         // Corrección del filtro por DNI
    //         if ($dniFiltro) {
    //             $query->whereRaw("COALESCE(fu.dni, u_cliente.dni) COLLATE utf8mb4_general_ci = ?", [$dniFiltro]);
    //         }
    
    //         if ($idCitaFiltro) {
    //             $query->where('c.idCita', $idCitaFiltro);
    //         }
    
    //         if ($fechaFiltro) {
    //             $query->where('hd.fecha', $fechaFiltro);
    //         }
    
    //         if ($horaFiltro) {
    //             $query->where('hd.hora_inicio', 'like', "%$horaFiltro%");
    //         }
    
    //         $appointments = $query
    //             ->orderBy('hd.fecha', 'asc')
    //             ->orderBy('hd.hora_inicio', 'asc')
    //             ->get();
    
    //         return response()->json($appointments);
    //     } catch (\Exception $e) {
    //         Log::error('Error al obtener las citas del cliente:', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json([
    //             'error' => 'Error al obtener las citas del cliente',
    //             'details' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function obtenerHistorialCitasCliente($idCliente, Request $request)
    {
        try {
            // Validar que se proporcione un ID de cliente
            if (!$idCliente) {
                return response()->json([
                    'error' => 'ID del cliente no proporcionado'
                ], 400);
            }
    
            // Obtener los filtros de la solicitud
            $estadoFiltro = $request->query('estado');
            $nombrePacienteFiltro = $request->query('nombrePaciente');
            $dniFiltro = $request->query('dni');
            $idCitaFiltro = $request->query('idCita');
            $fechaFiltro = $request->query('fecha');
            $horaFiltro = $request->query('hora');
    
            // Consulta base para obtener las citas del cliente
            $query = DB::table('historial_citas as c')
                ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario')
                ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario')
                ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
                ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario')
                ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad')
                ->leftJoin('historial_pagos as p', 'c.idCita', '=', 'p.idCita')
                ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario')
                ->select(
                    'c.idCita',
                    'u_cliente.nombres as clienteNombre',
                    'u_cliente.apellidos as clienteApellidos',
                    'u_doctor.nombres as doctorNombre',
                    'u_doctor.apellidos as doctorApellidos',
                    'e.nombre as especialidad',
                    'hd.fecha',
                    'hd.hora_inicio as horaInicio',
                    'hd.costo',
                    'c.estado',
                    'c.motivo',
                    'p.idPago',
                    DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'),
                    DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'),
                    DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos')
                )
                ->where('c.idCliente', $idCliente);
    
            // Aplicar filtro por estado si se proporciona
            if ($estadoFiltro && in_array($estadoFiltro, ['completada', 'cancelada'])) {
                $query->where('c.estado', $estadoFiltro);
            } else {
                $query->whereIn('c.estado', ['completada', 'cancelada']);
            }
    
            // Aplicar filtro por nombre del PACIENTE de manera segura
        if ($nombrePacienteFiltro) {
            $nombreSeguro = str_replace(['%', '_'], ['\%', '\_'], $nombrePacienteFiltro);
            $query->where(function ($q) use ($nombreSeguro) {
                $q->whereRaw('LOWER(CONCAT(IFNULL(fu.nombre, u_cliente.nombres), " ", IFNULL(fu.apellidos, u_cliente.apellidos))) COLLATE utf8mb4_general_ci LIKE ?', ['%' . strtolower($nombreSeguro) . '%']);
            });
        }
    
            // Corrección del filtro por DNI
            if ($dniFiltro) {
                $query->where(function ($q) use ($dniFiltro) {
                    $q->whereRaw('IFNULL(fu.dni, u_cliente.dni) COLLATE utf8mb4_general_ci = ?', [$dniFiltro]);
                });
            }
    
            // Aplicar filtro por ID de cita si se proporciona
            if ($idCitaFiltro) {
                $query->where('c.idCita', $idCitaFiltro);
            }
    
            // Aplicar filtro por fecha si se proporciona
            if ($fechaFiltro) {
                $query->where('hd.fecha', $fechaFiltro);
            }
    
            // Aplicar filtro por hora si se proporciona
            if ($horaFiltro) {
                $query->where('hd.hora_inicio', 'like', "%$horaFiltro%");
            }
    
            // Ordenar por fecha y hora
            $appointments = $query
                ->orderBy('hd.fecha', 'asc')
                ->orderBy('hd.hora_inicio', 'asc')
                ->get();
    
            return response()->json($appointments);
        } catch (\Exception $e) {
            Log::error('Error al obtener las citas del cliente:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener las citas del cliente',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function obtenerHistorialPagosCliente($idCliente, Request $request)
    {
        try {
            // Validar que se proporcione un ID de cliente
            if (!$idCliente) {
                return response()->json([
                    'error' => 'ID del cliente no proporcionado'
                ], 400);
            }

            // Obtener los filtros de la solicitud
            $estadoFiltro = $request->query('estado');
            $idPagoFiltro = $request->query('idPago');
            $fechaFiltro = $request->query('fecha');
            $nombreFiltro = $request->query('nombre'); // Nuevo filtro: nombre del cliente
            $dniFiltro = $request->query('dni'); // Nuevo filtro: DNI del cliente

            // Consulta base para obtener los pagos del cliente
            $query = DB::table('historial_pagos as p')
                ->join('historial_citas as c', 'p.idCita', '=', 'c.idCita')
                ->join('usuarios as u', 'c.idCliente', '=', 'u.idUsuario') // Cliente
                ->join('usuarios as d', 'c.idDoctor', '=', 'd.idUsuario') // Doctor
                ->join('especialidades_usuarios as eu', 'd.idUsuario', '=', 'eu.idUsuario') // Relación doctor-especialidad
                ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad') // Especialidad
                ->join('horarios_doctores as h', 'c.idHorario', '=', 'h.idHorario') // Horario
                ->select(
                    'p.idPago',
                    'p.monto',
                    'p.estado',
                    'p.hora_generacion',
                    'p.fecha_pago',
                    'p.tipo_pago',
                    'p.tipo_comprobante',
                    'p.ruc',
                    'p.fecha_movimiento',
                    'c.idCita',
                    'h.fecha as fecha_cita',
                    'h.hora_inicio as hora_cita',
                    'u.nombres as clienteNombre',
                    'u.apellidos as clienteApellidos',
                    'u.dni',
                    'e.nombre as especialidad'
                )
                ->where('c.idCliente', $idCliente);

            // Aplicar filtro por estado si se proporciona
            if ($estadoFiltro && in_array($estadoFiltro, ['pagado', 'pendiente'])) {
                $query->where('p.estado', $estadoFiltro);
            }

            // Aplicar filtro por ID de pago si se proporciona
            if ($idPagoFiltro) {
                $query->where('p.idPago', $idPagoFiltro);
            }

            // Aplicar filtro por fecha si se proporciona
            if ($fechaFiltro) {
                $query->where('p.fecha_pago', $fechaFiltro);
            }

            // Aplicar filtro por nombre del cliente (parcial o completo)
            if ($nombreFiltro) {
                $query->where(function ($q) use ($nombreFiltro) {
                    $q->where('u.nombres', 'like', "%{$nombreFiltro}%")
                    ->orWhere('u.apellidos', 'like', "%{$nombreFiltro}%");
                });
            }

            // Aplicar filtro por DNI del cliente
            if ($dniFiltro) {
                $query->where('u.dni', 'like', "%{$dniFiltro}%");
            }

            // Ordenar por fecha de pago
            $payments = $query
                ->orderBy('p.fecha_pago', 'desc')
                ->get();

            return response()->json($payments);
        } catch (\Exception $e) {
            Log::error('Error al obtener los pagos del cliente:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener los pagos del cliente',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    
    public function cancelarCitaCliente(Request $request, $idCita)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'motivo' => 'required|string',
                'idCliente' => 'required|integer',
            ]);
    
            // Verificar que la cita exista y pertenezca al cliente
            $cita = DB::table('citas')
                ->where('idCita', $idCita)
                ->where('idCliente', $request->input('idCliente'))
                ->first();
    
            if (!$cita) {
                return response()->json(['error' => 'Cita no encontrada o no tienes permisos'], 404);
            }
    
            // Obtener los pagos asociados a la cita
            $pagos = DB::table('pagos')
                ->where('idCita', $idCita)
                ->get();
    
            // Iniciar transacción
            DB::beginTransaction();
            try {
                // 1. Mover la cita a la tabla historial_citas
                DB::table('historial_citas')->insert([
                    'idCita' => $cita->idCita,
                    'idCliente' => $cita->idCliente,
                    'idFamiliarUsuario' => $cita->idFamiliarUsuario,
                    'idDoctor' => $cita->idDoctor,
                    'idHorario' => $cita->idHorario,
                    'especialidad' => $cita->especialidad,
                    'estado' => 'cancelada',
                    'motivo' => $request->input('motivo'),
                ]);
    
                // 2. Eliminar los pagos asociados (si existen)
                if ($pagos->isNotEmpty()) {
                    DB::table('pagos')
                        ->where('idCita', $idCita)
                        ->delete();
                }
    
                // 3. Eliminar la cita original de la tabla citas
                DB::table('citas')
                    ->where('idCita', $idCita)
                    ->delete();
    
                // Confirmar la transacción
                DB::commit();
    
                return response()->json([
                    'message' => 'Cita cancelada y movida al historial correctamente',
                    'idCita' => $idCita,
                    'pagos_eliminados' => $pagos->count()
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();
    
                Log::error('Error en la transacción:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'idCita' => $idCita
                ]);
                return response()->json([
                    'error' => 'Error al procesar la cancelación de la cita',
                    'details' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error en validación:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error en la validación de datos',
                'details' => $e->getMessage()
            ], 400);
        }
    }


  // Listar familiares de un usuario
  public function listarFamiliares($idUsuario)
  {
      $familiares = DB::table('familiares_usuarios')
          ->where('idUsuario', $idUsuario)
          ->where('estado', 'Activo')
          ->get();
      return response()->json($familiares);
  }

    // Crear un nuevo familiar
    public function crearFamiliar(Request $request)
    {
        // Validación inicial de los datos
        $validator = Validator::make($request->all(), [
            'idUsuario' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'dni' => 'required|string|max:20',
            'edad' => 'required|integer',
            'sexo' => 'required|in:Masculino,Femenino,Otro',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        // Verificar si el usuario ya tiene 4 familiares
        $countFamiliares = DB::table('familiares_usuarios')
            ->where('idUsuario', $request->idUsuario)
            ->where('estado', 'Activo') // Solo contar familiares activos
            ->count();

        if ($countFamiliares >= 4) {
            return response()->json([
                'message' => 'No se pueden agregar más familiares. Límite máximo alcanzado.'
            ], 400);
        }

        // Insertar el nuevo familiar si pasa la validación
        $idFamiliarUsuario = DB::table('familiares_usuarios')->insertGetId([
            'idUsuario' => $request->idUsuario,
            'nombre' => $request->nombre,
            'apellidos' => $request->apellidos,
            'dni' => $request->dni,
            'edad' => $request->edad,
            'sexo' => $request->sexo,
            'estado' => 'Activo'
        ]);

        // Obtener el familiar recién creado
        $familiar = DB::table('familiares_usuarios')
            ->where('idFamiliarUsuario', $idFamiliarUsuario)
            ->first();

        return response()->json($familiar, 201);
    }

  // Actualizar un familiar
  public function actualizarFamiliar(Request $request, $idFamiliarUsuario)
  {
      $familiar = DB::table('familiares_usuarios')
          ->where('idFamiliarUsuario', $idFamiliarUsuario)
          ->first();

      if (!$familiar) {
          return response()->json(['message' => 'Familiar no encontrado'], 404);
      }

      $validator = Validator::make($request->all(), [
          'nombre' => 'sometimes|string|max:255',
          'apellidos' => 'sometimes|string|max:255',
          'dni' => 'sometimes|string|max:20',
          'edad' => 'sometimes|integer',
          'sexo' => 'sometimes|in:Masculino,Femenino,Otro',
      ]);

      if ($validator->fails()) {
          return response()->json($validator->errors(), 400);
      }

      DB::table('familiares_usuarios')
          ->where('idFamiliarUsuario', $idFamiliarUsuario)
          ->update([
              'nombre' => $request->nombre ?? $familiar->nombre,
              'apellidos' => $request->apellidos ?? $familiar->apellidos,
              'dni' => $request->dni ?? $familiar->dni,
              'edad' => $request->edad ?? $familiar->edad,
              'sexo' => $request->sexo ?? $familiar->sexo
          ]);

      $familiarActualizado = DB::table('familiares_usuarios')
          ->where('idFamiliarUsuario', $idFamiliarUsuario)
          ->first();
      return response()->json($familiarActualizado);
  }

  // Borrado lógico de un familiar
  public function eliminarFamiliar($idFamiliarUsuario)
  {
      $familiar = DB::table('familiares_usuarios')
          ->where('idFamiliarUsuario', $idFamiliarUsuario)
          ->first();

      if (!$familiar) {
          return response()->json(['message' => 'Familiar no encontrado'], 404);
      }

      DB::table('familiares_usuarios')
          ->where('idFamiliarUsuario', $idFamiliarUsuario)
          ->update(['estado' => 'Eliminado']);

      return response()->json(['message' => 'Familiar eliminado']);
  }

    //Funciones para el context
    public function cantidadCitas($idCliente) {
        try {
            // Validar que el ID del doctor esté presente
            if (!$idCliente) {
                return response()->json([
                    'error' => 'ID del doctor no encontrado en el token'
                ], 400);
            }
    
            $cantidad = DB::table('citas')
            ->where('idCliente', $idCliente)
            ->whereIn('estado', ['pago pendiente', 'pagado'])
            ->count();

            return response()->json(['cantidad' => $cantidad]);
        } catch (\Exception $e) {
            Log::error('Error al obtener la cantidad de citas del doctor:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener la cantidad de citas del doctor',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function cantidadPagos($idCliente) {
        try {

            // Validar que el ID del doctor esté presente
            if (!$idCliente) {
                return response()->json([
                    'error' => 'ID del doctor no encontrado en el token'
                ], 400);
            }
    
            // Consulta para contar los pagos pendientes relacionados con el idCliente
            $cantidad = DB::table('pagos')
                ->join('citas', 'pagos.idCita', '=', 'citas.idCita') // Unir pagos con citas
                ->where('citas.idCliente', $idCliente) // Filtrar por el idCliente del usuario autenticado
                ->where('pagos.estado', 'pendiente') // Filtrar pagos pendientes
                ->count();
    
            // Retornar la cantidad de pagos pendientes
            return response()->json(['cantidad' => $cantidad]);
        } catch (\Exception $e) {
            // Manejar errores
            Log::error('Error al obtener la cantidad de pagos:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener la cantidad de pagos',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    //PARA EL PERFIL
   
    public function obtenerPerfil($idCliente)
    {
        // Obtener datos del usuario
        $usuario = DB::table('usuarios')
            ->where('idUsuario', $idCliente)
            ->first();

        // Verificar si el usuario existe
        if (!$usuario) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }

        // Calcular edad y ajustar fecha de nacimiento
        $edad = null;
        $fechaNacimiento = null;
        
        if ($usuario->nacimiento) {
            // Crear fecha de nacimiento y agregar un día para compensar
            $nacimiento = new DateTime($usuario->nacimiento);
            $nacimiento->modify('+1 day');
            
            // Calcular edad
            $hoy = new DateTime();
            $edad = $nacimiento->diff($hoy)->y;
            
            // Formatear fecha para el JSON
            $fechaNacimiento = $nacimiento->format('Y-m-d');
        }

        return response()->json([
            'nombre' => $usuario->nombres . ' ' . $usuario->apellidos,
            'foto_perfil' => $usuario->perfil,
            'email' => $usuario->correo,
            'telefono' => $usuario->telefono,
            'nacimiento' => $fechaNacimiento,
            'sexo' => $usuario->sexo,
            'dni' => $usuario->dni,
            'edad' => $edad
        ]);
    }

    // También necesitamos ajustar la función de actualización para mantener la consistencia
    public function actualizarDatos(Request $request, $idCliente)
    {
        $request->validate([
            'email' => 'required|email',
            'telefono' => 'required|string',
            'nacimiento' => 'required|date',
            'sexo' => 'required|in:M,F'
        ]);

        try {
            // Ajustar la fecha antes de guardar
            $nacimiento = new DateTime($request->nacimiento);
            $nacimiento->modify('-1 day'); // Restamos un día antes de guardar
            
            // Calcular edad
            $hoy = new DateTime();
            $edad = $nacimiento->diff($hoy)->y;

            // Actualizar datos del usuario
            DB::table('usuarios')
                ->where('idUsuario', $idCliente)
                ->update([
                    'correo' => $request->email,
                    'telefono' => $request->telefono,
                    'nacimiento' => $nacimiento->format('Y-m-d'),
                    'sexo' => $request->sexo,
                    'edad' => $edad
                ]);

            return response()->json([
                'message' => 'Datos actualizados correctamente',
                'edad' => $edad
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar los datos'
            ], 500);
        }
    }

    public function actualizarFotoPerfil(Request $request, $idCliente)
    {
        // Validar la solicitud
        $request->validate([
            'foto' => 'required|image|max:2048'
        ]);

        // Buscar al usuario por su ID
        $usuario = DB::table('usuarios')->where('idUsuario', $idCliente)->first();
        if (!$usuario) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        // Verificar si hay un archivo en la solicitud
        if ($request->hasFile('foto')) {
            $path = "profiles/$idCliente";

            // Eliminar la imagen anterior si existe
            if ($usuario->perfil && Storage::disk('public')->exists($usuario->perfil)) {
                Storage::disk('public')->delete($usuario->perfil);
            }

            // Guardar la nueva imagen
            $filename = $request->file('foto')->store($path, 'public');

            // Actualizar la ruta en la base de datos
            DB::table('usuarios')
                ->where('idUsuario', $idCliente)
                ->update(['perfil' => $filename]);

            return response()->json([
                'success' => true,
                'message' => 'Foto actualizada correctamente',
                'ruta' => $filename
            ]);
        }

        return response()->json(['success' => false, 'message' => 'No se cargó la imagen'], 400);
    }

}
