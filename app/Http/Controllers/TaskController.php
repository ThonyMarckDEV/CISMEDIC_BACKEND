<?php

namespace App\Http\Controllers;

use App\Mail\NotificacionCitaEliminada;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TaskController extends Controller
{

    public function procesarCitasExpiradas(Request $request)
    {
        // Verificar el código de seguridad
        $codigoRecibido = $request->input('codigo');
        $codigoCorrecto = Config::get('app.cron_code', env('CRON_CODE'));

        if ($codigoRecibido !== $codigoCorrecto) {
            Log::warning("Intento de acceso no autorizado al cron job.");
            return response()->json(['error' => 'Acceso no autorizado'], 403);
        }

        // Calcular el límite de tiempo: hora actual - 10 minutos
        $limiteTiempo = Carbon::now()->subMinutes(10)->format('H:i:s'); // Solo hora para comparar con `hora_generacion`

        // Obtener citas expiradas que cumplen con las condiciones
        $citasExpiradas = DB::table('citas as c')
            ->join('usuarios as u', 'c.idCliente', '=', 'u.idUsuario')
            ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita')
            ->where('c.estado', 'pago pendiente') // Solo citas en estado "pago pendiente"
            ->where('c.estado', '!=', 'cancelada') // Excluir citas en estado "cancelada"
            ->where(function ($query) {
                $query->whereNull('p.idPago') // No tiene pago asociado
                    ->orWhere('p.estado', '!=', 'pagado'); // O el pago no está en estado "pagado"
            })
            ->whereRaw("TIME(p.hora_generacion) <= ?", [$limiteTiempo]) // Comparar solo la hora
            ->select('c.idCita', 'u.correo')
            ->get();

        foreach ($citasExpiradas as $cita) {
            // Obtener los datos de la cita
            $citaData = $this->obtenerDatosCita($cita->idCita);

            // Enviar notificación antes de eliminar
            $this->enviarCorreo($cita->correo, $cita->idCita, $citaData);

            // Eliminar la cita
            DB::table('citas')
                ->where('idCita', $cita->idCita)
                ->delete();

            Log::info("Cita con ID {$cita->idCita} eliminada y correo enviado a {$cita->correo}.");
        }

        return response()->json(['message' => 'Citas procesadas correctamente']);
    }
    
    private function obtenerDatosCita($idCita)
{
    $cita = DB::table('citas as c')
        ->join('usuarios as cliente', 'c.idCliente', '=', 'cliente.idUsuario')
        ->join('usuarios as doctor', 'c.idDoctor', '=', 'doctor.idUsuario')
        ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
        ->join('especialidades_usuarios as eu', 'hd.idDoctor', '=', 'eu.idUsuario')
        ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad')
        ->where('c.idCita', $idCita)
        ->where('hd.estado', 'activo') // Validar que el horario esté activo
        ->where('e.estado', 'activo') // Validar que la especialidad esté activa
        ->select(
            'cliente.nombres as cliente_nombres',
            'cliente.apellidos as cliente_apellidos',
            'doctor.nombres as doctor_nombres',
            'doctor.apellidos as doctor_apellidos',
            'hd.fecha as fecha',
            'hd.hora_inicio as hora_inicio',
            'e.nombre as especialidad_nombre'
        )
        ->first();

    if (!$cita) {
        throw new \Exception("No se encontraron datos para la cita con ID {$idCita}");
    }

    return [
        'cliente_nombre' => $cita->cliente_nombres . ' ' . $cita->cliente_apellidos,
        'doctor_nombre' => $cita->doctor_nombres . ' ' . $cita->doctor_apellidos,
        'fecha' => $cita->fecha,
        'hora' => $cita->hora_inicio,
        'especialidad' => $cita->especialidad_nombre,
    ];
}
    
private function enviarCorreo($correo, $idCita, $citaData)
{
    Mail::to($correo)->send(new NotificacionCitaEliminada($idCita, $citaData));
}

}







