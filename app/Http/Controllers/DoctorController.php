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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class DoctorController extends Controller
{
    public function obtenerCitasDoctor($idDoctor)
    {
        try {
            // Validar que se proporcione un ID de doctor
            if (!$idDoctor) {
                return response()->json([
                    'error' => 'ID del doctor no proporcionado'
                ], 400);
            }

            // Consulta para obtener las citas del doctor
            $appointments = DB::table('citas as c')
                ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario') // Cliente
                ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario') // Doctor
                ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario') // Horario del doctor
                ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario') // Especialidad del doctor
                ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad') // Nombre de la especialidad
                ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita') // Pagos asociados
                ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario') // Familiares (si aplica)
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
                    'p.idPago',
                    DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'), // DNI del familiar o cliente
                    DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'), // Nombre del paciente (familiar o cliente)
                    DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos') // Apellidos del paciente (familiar o cliente)
                )
                ->where('c.idDoctor', $idDoctor) // Filtrar por el ID del doctor
                ->where('c.estado', 'pagado') // Filtrar solo citas con estado "pagado"
                ->orderBy('hd.fecha', 'asc') // Ordenar por fecha ascendente
                ->orderBy('hd.hora_inicio', 'asc') // Ordenar por hora ascendente
                ->get();

            return response()->json($appointments);
        } catch (\Exception $e) {
            Log::error('Error al obtener las citas del doctor:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener las citas del doctor',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // public function obtenerHistorialCitasDoctor($idDoctor, Request $request)
    // {
    //     try {
    //         // Validar que se proporcione un ID de doctor
    //         if (!$idDoctor) {
    //             return response()->json([
    //                 'error' => 'ID del doctor no proporcionado'
    //             ], 400);
    //         }

    //         // Obtener el estado de filtro (si se proporciona)
    //         $estadoFiltro = $request->query('estado'); // Ejemplo: ?estado=completada

    //         // Consulta base para obtener las citas del doctor
    //         $query = DB::table('citas as c')
    //             ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario') // Cliente
    //             ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario') // Doctor
    //             ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario') // Horario del doctor
    //             ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario') // Especialidad del doctor
    //             ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad') // Nombre de la especialidad
    //             ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita') // Pagos asociados
    //             ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario') // Familiares (si aplica)
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
    //                 DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'), // DNI del familiar o cliente
    //                 DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'), // Nombre del paciente (familiar o cliente)
    //                 DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos') // Apellidos del paciente (familiar o cliente)
    //             )
    //             ->where('c.idDoctor', $idDoctor); // Filtrar por el ID del doctor

    //         // Aplicar filtro por estado si se proporciona
    //         if ($estadoFiltro && in_array($estadoFiltro, ['completada', 'cancelada'])) {
    //             $query->where('c.estado', $estadoFiltro);
    //         } else {
    //             // Si no se proporciona un estado válido, mostrar solo "completada" y "cancelada"
    //             $query->whereIn('c.estado', ['completada', 'cancelada']);
    //         }

    //         // Ordenar por fecha y hora
    //         $appointments = $query
    //             ->orderBy('hd.fecha', 'asc')
    //             ->orderBy('hd.hora_inicio', 'asc')
    //             ->get();

    //         return response()->json($appointments);
    //     } catch (\Exception $e) {
    //         Log::error('Error al obtener las citas del doctor:', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json([
    //             'error' => 'Error al obtener las citas del doctor',
    //             'details' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function obtenerHistorialCitasDoctor($idDoctor, Request $request)
{
    try {
        // Validar que se proporcione un ID de doctor
        if (!$idDoctor) {
            return response()->json([
                'error' => 'ID del doctor no proporcionado'
            ], 400);
        }

        // Obtener los filtros de la solicitud
        $estadoFiltro = $request->query('estado'); // Ejemplo: ?estado=completada
        $nombreFiltro = $request->query('nombre'); // Ejemplo: ?nombre=Juan
        $dniFiltro = $request->query('dni'); // Ejemplo: ?dni=12345678
        $idCitaFiltro = $request->query('idCita'); // Ejemplo: ?idCita=1
        $fechaFiltro = $request->query('fecha'); // Ejemplo: ?fecha=2023-10-01
        $horaFiltro = $request->query('hora'); // Ejemplo: ?hora=14:00

        // Consulta base para obtener las citas del doctor
        $query = DB::table('citas as c')
            ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario') // Cliente
            ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario') // Doctor
            ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario') // Horario del doctor
            ->join('especialidades_usuarios as eu', 'u_doctor.idUsuario', '=', 'eu.idUsuario') // Especialidad del doctor
            ->join('especialidades as e', 'eu.idEspecialidad', '=', 'e.idEspecialidad') // Nombre de la especialidad
            ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita') // Pagos asociados
            ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario') // Familiares (si aplica)
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
                DB::raw('IFNULL(fu.dni, u_cliente.dni) as dni'), // DNI del familiar o cliente
                DB::raw('IFNULL(fu.nombre, u_cliente.nombres) as pacienteNombre'), // Nombre del paciente (familiar o cliente)
                DB::raw('IFNULL(fu.apellidos, u_cliente.apellidos) as pacienteApellidos') // Apellidos del paciente (familiar o cliente)
            )
            ->where('c.idDoctor', $idDoctor); // Filtrar por el ID del doctor

        // Aplicar filtro por estado si se proporciona
        if ($estadoFiltro && in_array($estadoFiltro, ['completada', 'cancelada'])) {
            $query->where('c.estado', $estadoFiltro);
        } else {
            // Si no se proporciona un estado válido, mostrar solo "completada" y "cancelada"
            $query->whereIn('c.estado', ['completada', 'cancelada']);
        }

        // Aplicar filtro por nombre si se proporciona
        if ($nombreFiltro) {
            $query->where(function($q) use ($nombreFiltro) {
                $q->where('u_cliente.nombres', 'like', "%$nombreFiltro%")
                  ->orWhere('fu.nombre', 'like', "%$nombreFiltro%");
            });
        }

        // Aplicar filtro por DNI si se proporciona
        if ($dniFiltro) {
            $query->where(function($q) use ($dniFiltro) {
                $q->where('u_cliente.dni', 'like', "%$dniFiltro%")
                  ->orWhere('fu.dni', 'like', "%$dniFiltro%");
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
        Log::error('Error al obtener las citas del doctor:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'error' => 'Error al obtener las citas del doctor',
            'details' => $e->getMessage()
        ], 500);
    }
}

    public function cantidadCitasDoctor($idDoctor)
    {
        try {
            // Validar que el ID del doctor esté presente
            if (!$idDoctor) {
                return response()->json([
                    'error' => 'ID del doctor no encontrado en el token'
                ], 400);
            }
    
            // Contar las citas asociadas al doctor con estados "pago pendiente" o "pagado"
            $cantidad = DB::table('citas')
                ->where('idDoctor', $idDoctor) // Filtrar por ID del doctor
                ->whereIn('estado', ['pagado']) // Filtrar por estados válidos
                ->count();
    
            // Devolver la cantidad de citas
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


    public function actualizarEstadoCita(Request $request, $idCita, $idDoctor)
    {
        // Validar los datos de entrada
        $request->validate([
            'estado' => 'required|in:completada,cancelada',
            'motivoCancelacion' => 'nullable|string', // Campo opcional para cancelaciones
        ]);

        // Verificar que la cita exista y pertenezca al usuario autenticado
        $cita = DB::table('citas')
            ->where('idCita', $idCita)
            ->where('idDoctor', $idDoctor)
            ->first();

        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada o no tienes permisos'], 404);
        }

        // Preparar los datos para actualizar
        $dataToUpdate = ['estado' => $request->input('estado')];

        // Si el estado es "cancelada", verificar y agregar el motivo
        if ($request->input('estado') === 'cancelada') {
            $motivoCancelacion = $request->input('motivoCancelacion');

            if (!$motivoCancelacion) {
                return response()->json(['error' => 'El motivo de cancelación es requerido'], 400);
            }

            $dataToUpdate['motivo'] = $motivoCancelacion;
        }

        // Actualizar el estado de la cita
        DB::table('citas')
            ->where('idCita', $idCita)
            ->update($dataToUpdate);

        return response()->json(['message' => 'Estado de la cita actualizado correctamente'], 200);
    }

    //FUNCIONES PARA HORARIOS
    
    public function listarHorarios($idDoctor)
    {
        try {
            // Obtener la fecha y hora actual
            $currentTime = Carbon::now();
            $today = Carbon::today()->format('Y-m-d');
            // Margen de tiempo en minutos (configurable)
            $margenTiempo = config('app.horario_margen', 20); // Por defecto: 20 minutos

            // Obtener todas las citas para el doctor, excluyendo las completadas
            $bookedSlots = DB::table('citas')
                ->join('horarios_doctores', 'citas.idHorario', '=', 'horarios_doctores.idHorario')
                ->leftJoin('pagos', 'citas.idCita', '=', 'pagos.idCita') // Left join para incluir citas sin pago
                ->where('citas.idDoctor', $idDoctor)
                ->whereIn('citas.estado', ['pagado', 'pago pendiente', 'cancelado']) // Incluir citas pagadas, pendientes o canceladas
                ->select(
                    'horarios_doctores.fecha',
                    'horarios_doctores.hora_inicio',
                    'citas.estado as estadoCita' // Añadimos el estado de la cita como "estadoCita"
                )
                ->get();

            // Obtener todos los slots disponibles para el doctor
            $availableSlots = DB::table('horarios_doctores')
                ->where('idDoctor', $idDoctor)
                ->where('estado', 'activo') // Asegurarse de que el horario esté activo
                ->select(
                    'idHorario',
                    'fecha',
                    'hora_inicio',
                    'costo',
                    'estado'
                )
                ->get();

            // Filtrar los slots disponibles
            $availableSlots = $availableSlots->map(function ($slot) use ($bookedSlots, $margenTiempo, $currentTime, $today) {
                // Excluir slots cuya fecha ya pasó
                if ($slot->fecha < $today) {
                    return null;
                }

                // Verificar si el slot está ocupado por una cita pagada, pendiente o cancelada
                $isBooked = $bookedSlots->contains(function ($bookedSlot) use ($slot) {
                    return $bookedSlot->fecha === $slot->fecha && $bookedSlot->hora_inicio === $slot->hora_inicio;
                });

                // Obtener el estado de la cita relacionada (si existe)
                $bookedSlot = $bookedSlots->first(function ($bookedSlot) use ($slot) {
                    return $bookedSlot->fecha === $slot->fecha && $bookedSlot->hora_inicio === $slot->hora_inicio;
                });

                // Si el slot está ocupado, verificar el estado de la cita
                if ($isBooked) {
                    $estadoCita = $bookedSlot->estadoCita;

                    // Si la cita está completada, no listar el slot
                    if ($estadoCita === 'completada') {
                        return null;
                    }

                    // Si la cita está cancelada, aplicar el margen de tiempo
                    if ($estadoCita === 'cancelado') {
                        $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->fecha . ' ' . $slot->hora_inicio);
                        $margenDateTime = $slotDateTime->copy()->subMinutes($margenTiempo);

                        // Si ya pasó el margen de tiempo, no listar el slot
                        if ($currentTime->greaterThanOrEqualTo($margenDateTime)) {
                            return null;
                        }

                        // Si no ha pasado el margen, listar como disponible
                        $slot->estadoCita = 'disponible';
                        return $slot;
                    }

                    // Si la cita está pagada o pendiente, listar como ocupado
                    $slot->estadoCita = 'ocupado';
                    return $slot;
                }

                // Si no está ocupado, aplicar el margen de tiempo para slots del día actual
                if ($slot->fecha === $today) {
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->fecha . ' ' . $slot->hora_inicio);
                    $margenDateTime = $slotDateTime->copy()->subMinutes($margenTiempo);

                    // Si ya pasó el margen de tiempo, no listar el slot
                    if ($currentTime->greaterThanOrEqualTo($margenDateTime)) {
                        return null;
                    }
                }

                // Si no está ocupado y no ha pasado el margen, listar como disponible
                $slot->estadoCita = 'disponible';
                return $slot;
            })->filter(); // Filtrar los slots nulos

            // Convertir la colección a un array antes de devolverla
            $availableSlotsArray = $availableSlots->values()->toArray();

            // Devolver los slots disponibles como un array
            return response()->json($availableSlotsArray);
        } catch (\Exception $e) {
            Log::error('Error al listar horarios:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al listar horarios',
                'details' => $e->getMessage()
            ], 500);
        }
    }


      // Crear un nuevo horario
      public function crearHorario(Request $request)
      {
          try {
              // Validar los datos recibidos
              $validatedData = $request->validate([
                  'idDoctor' => 'required|integer',
                  'fecha' => 'required|date',
                  'hora_inicio' => 'required|date_format:H:i:s',
                  'costo' => 'required|numeric',
              ]);
      
              // Agregar el estado "activo" manualmente
              $validatedData['estado'] = 'activo';
      
              // Insertar el nuevo horario en la base de datos
              $idHorario = DB::table('horarios_doctores')->insertGetId($validatedData);
      
              return response()->json([
                  'message' => 'Horario creado exitosamente',
                  'idHorario' => $idHorario
              ], 201);
          } catch (\Exception $e) {
              Log::error('Error al crear horario:', [
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              return response()->json([
                  'error' => 'Error al crear horario',
                  'details' => $e->getMessage()
              ], 500);
          }
      }
  
      // Actualizar un horario existente
      public function actualizarHorario($idHorario, Request $request)
      {
          try {
              $validatedData = $request->validate([
                  'fecha' => 'required|date',
                  'hora_inicio' => 'required|date_format:H:i:s',
                  'costo' => 'required|numeric',
                  'estado' => 'required|string|in:activo,inactivo',
              ]);
  
              DB::table('horarios_doctores')
                  ->where('idHorario', $idHorario)
                  ->update($validatedData);
  
              return response()->json([
                  'message' => 'Horario actualizado exitosamente'
              ], 200);
          } catch (\Exception $e) {
              Log::error('Error al actualizar horario:', [
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              return response()->json([
                  'error' => 'Error al actualizar horario',
                  'details' => $e->getMessage()
              ], 500);
          }
      }
  
      // Eliminar un horario
      public function eliminarHorario($idHorario)
      {
          try {
              DB::table('horarios_doctores')
                  ->where('idHorario', $idHorario)
                  ->delete();
  
              return response()->json([
                  'message' => 'Horario eliminado exitosamente'
              ], 200);
          } catch (\Exception $e) {
              Log::error('Error al eliminar horario:', [
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              return response()->json([
                  'error' => 'Error al eliminar horario',
                  'details' => $e->getMessage()
              ], 500);
          }
      }
}
