<?php

namespace App\Http\Controllers;

use App\Mail\NotificacionCitaCancelada;
use App\Mail\NotificacionCitaCompletada;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class DoctorController extends Controller
{

   
    public function obtenerEspecialidades(Request $request)
    {
        try {
            // Obtener todas las especialidades activas desde la base de datos
            $especialidades = DB::table('especialidades')
                ->select('idEspecialidad', 'nombre', 'descripcion', 'icono')
                ->where('estado', 'activo') // Filtro para solo especialidades activas
                ->get();

            return response()->json($especialidades);
        } catch (\Exception $e) {
            Log::error('Error al obtener especialidades:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener especialidades',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listDoctors(Request $request)
    {
        $search = $request->input('search');
        $specialty = $request->input('specialty');
    
        $query = DB::table('usuarios')
            ->select(
                'usuarios.idUsuario',
                'usuarios.nombres',
                'usuarios.apellidos',
                'usuarios.perfil',
                'especialidades.nombre as especialidad'
            )
            ->join('especialidades_usuarios', 'usuarios.idUsuario', '=', 'especialidades_usuarios.idUsuario')
            ->join('especialidades', 'especialidades_usuarios.idEspecialidad', '=', 'especialidades.idEspecialidad')
            ->where('usuarios.rol', 'doctor');
    
        if ($specialty) {
            $query->where('especialidades.nombre', 'LIKE', "%$specialty%");
        }
    
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('usuarios.nombres', 'LIKE', "%$search%")
                    ->orWhere('usuarios.apellidos', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos)"), 'LIKE', "%$search%");
            });
        }
    
        $doctors = $query->get();
    
        return response()->json($doctors);
    }
    

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
                ->leftJoin('pagos as p', 'c.idCita', '=', 'p.idCita') // Pagos asociados
                ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario') // Familiares (si aplica)
                ->select(
                    'c.idCita',
                    'u_cliente.nombres as clienteNombre',
                    'u_cliente.apellidos as clienteApellidos',
                    'u_doctor.nombres as doctorNombre',
                    'u_doctor.apellidos as doctorApellidos',
                    'c.especialidad as especialidad',
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
            $estadoFiltro = $request->query('estado');
            $nombreFiltro = $request->query('nombre');
            $dniFiltro = $request->query('dni');
            $idCitaFiltro = $request->query('idCita');
            $fechaFiltro = $request->query('fecha');
            $horaFiltro = $request->query('hora');

            // Consulta base para obtener las citas del doctor
            $query = DB::table('historial_citas as c')
                ->join('usuarios as u_cliente', 'c.idCliente', '=', 'u_cliente.idUsuario')
                ->join('usuarios as u_doctor', 'c.idDoctor', '=', 'u_doctor.idUsuario')
                ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
                ->leftJoin('historial_pagos as p', 'c.idCita', '=', 'p.idCita')
                ->leftJoin('familiares_usuarios as fu', 'c.idFamiliarUsuario', '=', 'fu.idFamiliarUsuario')
                ->select(
                    'c.idCita',
                    'u_cliente.nombres as clienteNombre',
                    'u_cliente.apellidos as clienteApellidos',
                    'u_doctor.nombres as doctorNombre',
                    'u_doctor.apellidos as doctorApellidos',
                    'c.especialidad as especialidad',
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
                ->where('c.idDoctor', $idDoctor);

            // Aplicar filtro por estado si se proporciona
            if ($estadoFiltro && in_array($estadoFiltro, ['completada', 'cancelada'])) {
                $query->where('c.estado', $estadoFiltro);
            } else {
                $query->whereIn('c.estado', ['completada', 'cancelada']);
            }

            // Aplicar filtro por nombre del PACIENTE de manera segura
            if ($nombreFiltro) {
                $nombreSeguro = str_replace(['%', '_'], ['\%', '\_'], $nombreFiltro);
                $query->where(function ($q) use ($nombreSeguro) {
                    $q->whereRaw('LOWER(CONCAT(IFNULL(fu.nombre, u_cliente.nombres), " ", IFNULL(fu.apellidos, u_cliente.apellidos))) COLLATE utf8mb4_general_ci LIKE ?', ['%' . strtolower($nombreSeguro) . '%']);
                });
            }

            // Aplicar filtro por DNI (coincidencia exacta)
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
                $query->where('hd.hora_inicio', $horaFiltro);
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
        try {
            // Validar los datos de entrada
            $request->validate([
                'estado' => 'required|in:completada,cancelada',
                'motivoCancelacion' => 'nullable|string',
            ]);

            // Verificar que la cita exista y pertenezca al doctor
            $cita = DB::table('citas as c')
                ->join('horarios_doctores as hd', 'c.idHorario', '=', 'hd.idHorario')
                ->select(
                    'c.idCita',
                    'c.idCliente',
                    'c.idFamiliarUsuario',
                    'c.idDoctor',
                    'c.idHorario',
                    'c.especialidad',
                    'hd.fecha',
                    'hd.hora_inicio'
                )
                ->where('c.idCita', $idCita)
                ->where('c.idDoctor', $idDoctor)
                ->first();

            if (!$cita) {
                return response()->json(['error' => 'Cita no encontrada o no tienes permisos'], 404);
            }

            // Obtener los datos del cliente y el doctor para el correo
            $cliente = DB::table('usuarios')
                ->select('idUsuario', 'nombres', 'apellidos', 'correo')
                ->where('idUsuario', $cita->idCliente)
                ->first();

            if (!$cliente || !isset($cliente->correo)) {
                return response()->json(['error' => 'Cliente no encontrado o datos incompletos'], 404);
            }

            $doctor = DB::table('usuarios')
                ->select('idUsuario', 'nombres', 'apellidos')
                ->where('idUsuario', $cita->idDoctor)
                ->first();

            // Preparar los datos para el correo
            $citaData = [
                'idCita' => $cita->idCita,
                'cliente_nombre' => $cliente->nombres . ' ' . $cliente->apellidos,
                'doctor_nombre' => $doctor->nombres . ' ' . $doctor->apellidos,
                'especialidad' => $cita->especialidad,
                'fecha' => $cita->fecha,
                'hora' => $cita->hora_inicio,
                'motivo' => $request->input('motivoCancelacion'),
            ];

             // Obtener los pagos asociados antes de iniciar la transacción
            $pagos = DB::table('pagos')
                ->where('idCita', $idCita)
                ->get();

            // Iniciar transacción
            DB::beginTransaction();
            try {
                // 1. Mover la cita al historial
                $motivoCancelacion = $request->input('estado') === 'cancelada' ? $request->input('motivoCancelacion') : null;

                DB::table('historial_citas')->insert([
                    'idCita' => $cita->idCita,
                    'idCliente' => $cita->idCliente,
                    'idFamiliarUsuario' => $cita->idFamiliarUsuario,
                    'idDoctor' => $cita->idDoctor,
                    'idHorario' => $cita->idHorario,
                    'especialidad' => $cita->especialidad,
                    'estado' => $request->input('estado'),
                    'motivo' => $motivoCancelacion,
                ]);

                  // 2. Actualizar el estado del horario si la cita es completada
                if ($request->input('estado') === 'completada') {
                    $horarioUpdated = DB::table('horarios_doctores')
                        ->where('idHorario', $cita->idHorario)
                        ->update(['estado' => 'eliminado']);
    
                    if ($horarioUpdated === 0) {
                        throw new \Exception('No se pudo actualizar el estado del horario');
                    }
    
                    Log::info('Estado del horario actualizado a "eliminado"', [
                        'idHorario' => $cita->idHorario
                    ]);
                }
    
                // 3. DESPUÉS mover los pagos al historial si existen
                if ($pagos->isNotEmpty()) {
                    Log::info('Moviendo pagos al historial...');
    
                    foreach ($pagos as $pago) {
                        Log::info('Intentando mover pago:', ['idPago' => $pago->idPago]);
    
                        // Obtener todos los campos del pago original
                        $pagoData = (array) $pago;
    
                        // Agregar fecha_movimiento
                        $pagoData['fecha_movimiento'] = now();
    
                        // Remover cualquier campo que no exista en la tabla historial_pagos
                        unset($pagoData['created_at']);
                        unset($pagoData['updated_at']);
    
                        $inserted = DB::table('historial_pagos')->insert($pagoData);
                        if (!$inserted) {
                            throw new \Exception('Error al mover el pago ' . $pago->idPago . ' al historial');
                        }
                        Log::info('Pago movido exitosamente:', ['idPago' => $pago->idPago]);
                    }
                }
    
                // 4. Eliminar los pagos originales si existen
                if ($pagos->isNotEmpty()) {
                    Log::info('Eliminando pagos originales...');
    
                    $pagosDeleted = DB::table('pagos')
                        ->where('idCita', $idCita)
                        ->delete();
    
                    if ($pagosDeleted === 0) {
                        throw new \Exception('No se pudieron eliminar los pagos originales');
                    }
    
                    Log::info('Pagos originales eliminados:', ['cantidad' => $pagosDeleted]);
                }
    
                // 5. Eliminar la cita original
                $citaDeleted = DB::table('citas')
                    ->where('idCita', $idCita)
                    ->delete();
    
                if ($citaDeleted === 0) {
                    throw new \Exception('No se pudo eliminar la cita original');
                }

                // 2. Enviar correo según el estado
                if ($request->input('estado') === 'completada') {
                    Mail::to($cliente->correo)->send(new NotificacionCitaCompletada($citaData));
                } elseif ($request->input('estado') === 'cancelada') {
                    Mail::to($cliente->correo)->send(new NotificacionCitaCancelada($citaData));
                }

                // 3. Confirmar la transacción
                DB::commit();

                return response()->json([
                    'message' => 'Cita actualizada y notificación enviada correctamente',
                    'idCita' => $idCita,
                ], 200);
            } catch (\Exception $e) {
                DB::rollback();

                Log::error('Error en la transacción:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'idCita' => $idCita
                ]);
                return response()->json([
                    'error' => 'Error al procesar la actualización',
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


    //FUNCIONES PARA HORARIOS
    
    public function listarHorarios($idDoctor)
    {
        try {
            $currentTime = Carbon::now();
            $today = Carbon::today()->format('Y-m-d');
            $margenTiempo = config('app.horario_margen', 20);
    
            // Obtener todas las citas para el doctor
            $bookedSlots = DB::table('citas')
                ->join('horarios_doctores', 'citas.idHorario', '=', 'horarios_doctores.idHorario')
                ->leftJoin('pagos', 'citas.idCita', '=', 'pagos.idCita')
                ->where('citas.idDoctor', $idDoctor)
                ->select(
                    'horarios_doctores.fecha',
                    'horarios_doctores.hora_inicio',
                    'citas.estado as estadoCita'
                )
                ->get();
    
            // Obtener todos los slots disponibles para el doctor
            $availableSlots = DB::table('horarios_doctores')
                ->where('idDoctor', $idDoctor)
                ->where('estado', 'activo')
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
    
                // Buscar si el slot tiene una cita asociada
                $bookedSlot = $bookedSlots->first(function ($bookedSlot) use ($slot) {
                    return $bookedSlot->fecha === $slot->fecha && $bookedSlot->hora_inicio === $slot->hora_inicio;
                });
    
                // Si el slot tiene una cita
                if ($bookedSlot) {
                    // Si la cita está completada, no listar el slot
                    if ($bookedSlot->estadoCita === 'completada') {
                        return null;
                    }
    
                    // Para citas canceladas
                    if ($bookedSlot->estadoCita === 'cancelado') {
                        // Si es una fecha futura, listar como disponible
                        if ($slot->fecha > $today) {
                            $slot->estadoCita = 'disponible';
                            return $slot;
                        }
    
                        // Si es hoy, verificar el margen de tiempo
                        if ($slot->fecha === $today) {
                            $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->fecha . ' ' . $slot->hora_inicio);
                            $margenDateTime = $slotDateTime->copy()->subMinutes($margenTiempo);
    
                            return $currentTime->lessThan($margenDateTime) 
                                ? tap($slot, function($s) { $s->estadoCita = 'disponible'; })
                                : null;
                        }
                    }
    
                    // Para citas pagadas o con pago pendiente
                    if (in_array($bookedSlot->estadoCita, ['pagado', 'pago pendiente'])) {
                        $slot->estadoCita = 'ocupado';
                        return $slot;
                    }
                }
    
                // Para slots sin citas en el día actual
                if ($slot->fecha === $today) {
                    $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $slot->fecha . ' ' . $slot->hora_inicio);
                    $margenDateTime = $slotDateTime->copy()->subMinutes($margenTiempo);
    
                    if ($currentTime->greaterThanOrEqualTo($margenDateTime)) {
                        return null;
                    }
                }
    
                // Slot disponible
                $slot->estadoCita = 'disponible';
                return $slot;
            })->filter();
    
            return response()->json($availableSlots->values()->toArray());
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
              // Actualizar el estado del horario a "eliminado"
              $updated = DB::table('horarios_doctores')
                  ->where('idHorario', $idHorario)
                  ->update(['estado' => 'eliminado']);
      
              // Verificar si se realizó la actualización
              if ($updated === 0) {
                  return response()->json([
                      'error' => 'No se encontró el horario o ya está eliminado'
                  ], 404);
              }
      
              return response()->json([
                  'message' => 'Horario marcado como eliminado exitosamente'
              ], 200);
          } catch (\Exception $e) {
              Log::error('Error al marcar horario como eliminado:', [
                  'error' => $e->getMessage(),
                  'trace' => $e->getTraceAsString()
              ]);
              return response()->json([
                  'error' => 'Error al marcar horario como eliminado',
                  'details' => $e->getMessage()
              ], 500);
          }
      }

      //PARA EL PERFIL
      public function actualizarFotoPerfil(Request $request, $idDoctor)
      {
          // Validar la solicitud
          $request->validate([
              'foto' => 'required|image|max:2048'
          ]);
      
          // Buscar al usuario por su ID
          $usuario = DB::table('usuarios')->where('idUsuario', $idDoctor)->first();
          if (!$usuario) {
              return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
          }
      
          // Verificar si hay un archivo en la solicitud
          if ($request->hasFile('foto')) {
              $path = "profiles/$idDoctor";
      
              // Eliminar la imagen anterior si existe
              if ($usuario->perfil && Storage::disk('public')->exists($usuario->perfil)) {
                  Storage::disk('public')->delete($usuario->perfil);
              }
      
              // Guardar la nueva imagen en el disco 'public'
              $filename = $request->file('foto')->store($path, 'public');
      
              // Actualizar la ruta en la base de datos
              DB::table('usuarios')
                  ->where('idUsuario', $idDoctor)
                  ->update(['perfil' => $filename]);
      
              // Devolver la respuesta JSON
              return response()->json([
                  'success' => true,
                  'message' => 'Foto actualizada correctamente',
                  'filename' => basename($filename),
                  'url' => url("storage/$filename") // URL pública del archivo
              ]);
          }
      
          // Si no se cargó ninguna imagen
          return response()->json(['success' => false, 'message' => 'No se cargó la imagen'], 400);
      }
  
    public function actualizarIdiomas(Request $request, $idDoctor)
    {
        // Validar la solicitud
        $request->validate([
            'idiomas' => 'required|array',
            'idiomas.*' => 'required|string'
        ]);

         // Verificar si el usuario existe
         if (!$idDoctor) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }
    

        // Eliminar idiomas actuales del doctor
        DB::table('idiomas_doctor')
            ->where('idDoctor', $idDoctor)
            ->delete();

        // Insertar nuevos idiomas
        foreach ($request->idiomas as $idioma) {
            DB::table('idiomas_doctor')->insert([
                'idDoctor' => $idDoctor,
                'idioma' => $idioma
            ]);
        }

        return response()->json([
            'mensaje' => 'Idiomas actualizados correctamente',
            'idiomas' => $request->idiomas
        ]);
    }
  
    public function actualizarEducacion(Request $request, $idDoctor)
    {
        // Validar la solicitud
        $request->validate([
            'educacion' => 'required|array',
            'educacion.*.titulo' => 'required|string',
            'educacion.*.institucion' => 'required|string',
            'educacion.*.anio' => 'required|string'
        ]);

       // Verificar si el usuario existe
        if (!$idDoctor) {
            return response()->json([
                'error' => 'Usuario no encontrado'
            ], 404);
        }
    
        // Eliminar educación actual del doctor
        DB::table('educacion_doctor')
            ->where('idDoctor', $idDoctor)
            ->delete();
    
        // Insertar nueva educación
        foreach ($request->educacion as $edu) {
            DB::table('educacion_doctor')->insert([
                'idDoctor' => $idDoctor,
                'titulo' => $edu['titulo'],
                'institucion' => $edu['institucion'],
                'anio' => $edu['anio']
            ]);
        }
    
        return response()->json([
            'mensaje' => 'Educación actualizada correctamente',
            'educacion' => $request->educacion
        ]);
    }
  
    // DoctorController.php
    public function actualizarExperiencia(Request $request, $idDoctor)
    {
        try {
            // Verificar si el usuario existe
            if (!$idDoctor) {
                return response()->json([
                    'error' => 'Usuario no encontrado'
                ], 404);
            }

            DB::table('usuarios')
                ->where('idUsuario', $idDoctor)
                ->update(['experiencia' => $request->experiencia]);

            return response()->json([
                'message' => 'Experiencia actualizada correctamente',
                'experiencia' => $request->experiencia
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la experiencia'
            ], 500);
        }
    }

    public function actualizarNacimiento(Request $request, $idDoctor)
    {
        try {
            // Calcular edad basada en la fecha de nacimiento
            $nacimiento = new DateTime($request->nacimiento);
            $hoy = new DateTime();
            $edad = $nacimiento->diff($hoy)->y;
    
            // Actualizar tanto la fecha de nacimiento como la edad
            DB::table('usuarios')
                ->where('idUsuario', $idDoctor)
                ->update([
                    'nacimiento' => $request->nacimiento,
                    'edad' => $edad
                ]);
                
            return response()->json([
                'message' => 'Fecha de nacimiento y edad actualizadas correctamente',
                'edad' => $edad
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar la fecha de nacimiento y edad'
            ], 500);
        }
    }

    
    public function obtenerPerfil($idDoctor)
    {
        // Obtener datos del usuario
        $usuario = DB::table('usuarios')
            ->where('idUsuario', $idDoctor)
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
    
        // Obtener idiomas del doctor
        $idiomas = DB::table('idiomas_doctor')
            ->where('idDoctor', $idDoctor)
            ->pluck('idioma');
    
        // Contar la cantidad de idiomas
        $cantidadIdiomas = $idiomas->count();
    
        // Obtener educación del doctor
        $educacion = DB::table('educacion_doctor')
            ->where('idDoctor', $idDoctor)
            ->get();
    
        // Obtener la especialidad del doctor
        $especialidad = DB::table('especialidades as e')
            ->join('especialidades_usuarios as eu', 'e.idEspecialidad', '=', 'eu.idEspecialidad')
            ->where('eu.idUsuario', $idDoctor)
            ->select('e.nombre as especialidad')
            ->first();
    
        return response()->json([
            'nombre' => $usuario->nombres . ' ' . $usuario->apellidos,
            'foto_perfil' => $usuario->perfil,
            'especialidad' => $especialidad ? $especialidad->especialidad : null,
            'experiencia' => $usuario->experiencia,
            'idiomas' => $idiomas,
            'cantidadIdiomas' => $cantidadIdiomas,
            'educacion' => $educacion,
            'nacimiento' => $fechaNacimiento,  // Usamos la fecha ajustada
            'edad' => $edad
        ]);
    }
    

}
