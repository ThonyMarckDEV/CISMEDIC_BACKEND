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
                'observaciones' => 'nullable|string', // Campo opcional
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
                'observaciones' => $request->observaciones, // Agregar observaciones
            ]);
    
            // Enviar notificaciones
            $urlDescarga = asset('storage/' . $rutaArchivo);
            $datosCorreo = [
                'nombres' => $request->esPacienteNuevo ? $request->nombres : '',
                'apellidos' => $request->esPacienteNuevo ? $request->apellidos : '',
                'fechaCita' => $fechaCita,
                'rutaArchivo' => $rutaArchivo,
                'esNuevo' => $request->esPacienteNuevo
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
    
}
