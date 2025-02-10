<?php

namespace App\Http\Controllers;

use App\Mail\ResultadosMedicos;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function buscarPacientes(Request $request)
    {
        $busqueda = $request->query('busqueda', '');
        
        $pacientes = DB::select("
            SELECT idUsuario, nombres, apellidos, dni, correo, telefono 
            FROM usuarios 
            WHERE rol = 'cliente' 
            AND (nombres LIKE ? OR apellidos LIKE ? OR dni LIKE ?)
            ORDER BY nombres ASC 
            LIMIT 10
        ", ["%{$busqueda}%", "%{$busqueda}%", "%{$busqueda}%"]);
        
        return response()->json($pacientes);
    }

    public function subirResultados(Request $request)
    {
        Log::info('Iniciando subida de resultados', ['request' => $request->all()]);

        try {
            // Validación
            $request->validate([
                'archivo' => 'required|file|mimes:pdf|max:10240',
                'idPaciente' => 'required_without:esPacienteNuevo',
                'fechaCita' => 'required|date',
                'esPacienteNuevo' => 'boolean',
                'metodoContacto' => 'required_if:esPacienteNuevo,true|in:email,whatsapp',
                'infoContacto' => 'required_if:esPacienteNuevo,true',
                'nombres' => 'required_if:esPacienteNuevo,true',
                'apellidos' => 'required_if:esPacienteNuevo,true',
                'dni' => 'required_if:esPacienteNuevo,true',
                'titulo' => 'required|string', // Nuevo campo obligatorio
                'observaciones' => 'required|string', // Ahora es obligatorio
            ]);

            DB::beginTransaction();

            if (!$request->hasFile('archivo')) {
                throw new \Exception('No se cargó ningún archivo');
            }

            $fechaCita = date('Y-m-d', strtotime($request->fechaCita));

            // Determinar ruta y guardar archivo
            if ($request->esPacienteNuevo) {
                $nombreCarpeta = Str::slug($request->nombres . ' ' . $request->apellidos . ' ' . $request->dni);
                $path = "resultados/pacientes_genericos/{$nombreCarpeta}/{$fechaCita}";
            } else {
                $path = "resultados/{$request->idPaciente}/{$fechaCita}";
            }

            $archivo = $request->file('archivo');
            $extension = $archivo->getClientOriginalExtension();
            $nombreArchivo = Str::random(40) . '.' . $extension;
            $rutaArchivo = $archivo->storeAs($path, $nombreArchivo, 'public');

            if (!$rutaArchivo) {
                throw new \Exception('Error al guardar el archivo');
            }

            // Insertar en base de datos
            $idResultado = DB::table('resultados_pacientes')->insertGetId([
                'idUsuario' => $request->esPacienteNuevo ? null : $request->idPaciente,
                'nombres' => $request->esPacienteNuevo ? $request->nombres : null,
                'apellidos' => $request->esPacienteNuevo ? $request->apellidos : null,
                'dni' => $request->esPacienteNuevo ? $request->dni : null,
                'ruta_archivo' => $rutaArchivo,
                'fecha_cita' => $fechaCita,
                'es_paciente_nuevo' => $request->esPacienteNuevo ? 1 : 0,
                'metodo_contacto' => $request->metodoContacto,
                'info_contacto' => $request->infoContacto,
                'titulo' => $request->titulo, // Guardar el título
                'observaciones' => $request->observaciones, // Guardar observaciones
            ]);

            // Enviar notificaciones
            $urlDescarga = asset('storage/' . $rutaArchivo);
            $datosCorreo = [
                'nombres' => $request->esPacienteNuevo ? $request->nombres : '',
                'apellidos' => $request->esPacienteNuevo ? $request->apellidos : '',
                'fechaCita' => $fechaCita,
                'rutaArchivo' => $rutaArchivo,
                'esNuevo' => $request->esPacienteNuevo,
                'titulo' => $request->titulo, // Incluir el título en el correo
                'observaciones' => $request->observaciones, // Incluir observaciones en el correo
            ];

            if ($request->esPacienteNuevo) {
                // Paciente nuevo - enviar correo
                Mail::to($request->infoContacto)->send(new ResultadosMedicos($datosCorreo));
            } else {
                // Paciente existente - obtener datos del usuario
                $usuario = DB::table('usuarios')
                    ->where('idUsuario', $request->idPaciente)
                    ->first();

                if ($usuario) {
                    $datosCorreo['nombres'] = $usuario->nombres;
                    $datosCorreo['apellidos'] = $usuario->apellidos;

                    Mail::to($usuario->correo)->send(new ResultadosMedicos($datosCorreo));
                }
            }

            DB::commit();

            return response()->json([
                'exito' => true,
                'mensaje' => 'Resultados subidos exitosamente',
                'idResultado' => $idResultado,
                'ruta' => $rutaArchivo
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($rutaArchivo)) {
                Storage::disk('public')->delete($rutaArchivo);
            }

            Log::error('Error al subir resultados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'exito' => false,
                'mensaje' => 'Error al subir resultados: ' . $e->getMessage()
            ], 500);
        }
    }

   
    public function listarResultadosAdmin(Request $request)
    {
        try {
            // Parámetros de búsqueda
            $search = $request->input('search');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
    
            // Consulta base uniendo con la tabla usuarios y filtrando por rol cliente
            $query = DB::table('resultados_pacientes as r')
                ->join('usuarios as u', 'r.idUsuario', '=', 'u.idUsuario')
                ->where('r.estado', 'activo')
                ->where('u.rol', 'cliente') // Filtrar directamente por el rol en la tabla usuarios
                ->select(
                    'r.*',
                    'u.nombres',
                    'u.apellidos',
                    'u.dni'
                );
    
            // Aplicar búsqueda si existe
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('u.dni', 'LIKE', "%{$search}%")
                      ->orWhere('u.nombres', 'LIKE', "%{$search}%")
                      ->orWhere('u.apellidos', 'LIKE', "%{$search}%")
                      ->orWhere('r.idResultados', 'LIKE', "%{$search}%");
                });
            }
    
            // Aplicar filtros de fecha
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('r.fecha_cita', [$fechaInicio, $fechaFin]);
            } elseif ($fechaInicio) {
                $query->where('r.fecha_cita', '>=', $fechaInicio);
            } elseif ($fechaFin) {
                $query->where('r.fecha_cita', '<=', $fechaFin);
            }
    
            // Ejecutar consulta
            $resultados = $query->orderBy('r.fecha_cita', 'desc')->get();
    
            // Formatear resultados
            $resultados->map(function ($resultado) {
                $resultado->url_descarga = asset('storage/' . $resultado->ruta_archivo);
                $resultado->observaciones = $resultado->observaciones ?? "No hay observaciones disponibles";
                $resultado->nombre_completo = trim($resultado->nombres . ' ' . $resultado->apellidos) ?: 'Paciente sin nombre';
                return $resultado;
            });
    
            return response()->json(['resultados' => $resultados]);
        } catch (\Exception $e) {
            Log::error('Error al obtener resultados: ' . $e->getMessage());
            return response()->json(['error' => 'Error al obtener resultados'], 500);
        }
    }


    public function eliminarResultado($id)
    {
        try {
            DB::beginTransaction();

            // Verificar si existe y no está eliminado
            $resultado = DB::table('resultados_pacientes')
                ->where('idResultados', $id)
                ->where('estado', '!=', 'eliminado') // Asegurarse de que no esté ya eliminado
                ->first();

            if (!$resultado) {
                throw new \Exception('Resultado no encontrado');
            }

            // Actualizar estado a eliminado
            DB::table('resultados_pacientes')
                ->where('idResultados', $id)
                ->update([
                    'estado' => 'eliminado',
                ]);

            DB::commit();

            return response()->json([
                'exito' => true,
                'mensaje' => 'Resultado marcado como eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar resultado: ' . $e->getMessage());
            return response()->json([
                'exito' => false,
                'mensaje' => 'Error al marcar el resultado como eliminado'
            ], 500);
        }
    }

    
}
