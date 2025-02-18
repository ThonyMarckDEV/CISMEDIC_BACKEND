<?php

namespace App\Http\Controllers;


use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SuperAdminController extends Controller
{

    public function index()
    {
        $payments = DB::table('historial_pagos as hp')
            ->join('historial_citas as hc', 'hp.idCita', '=', 'hc.idCita')
            ->join('usuarios as u', 'hc.idCliente', '=', 'u.idUsuario')
            ->select(
                'hp.idPago',
                'hp.monto',
                'hp.estado',
                'hp.fecha_pago',
                'hp.tipo_pago',
                'hp.tipo_comprobante',
                'hc.especialidad',
                'u.nombres as nombre_cliente',
                'u.apellidos as apellidos_cliente'
            )
            ->orderBy('hp.fecha_pago', 'desc')
            ->get();
    
        return response()->json($payments);
    }

    public function getStats()
{
        $topSpecialty = DB::table('historial_citas')
            ->select('especialidad', DB::raw('COUNT(*) as total'))
            ->groupBy('especialidad')
            ->orderBy('total', 'desc')
            ->first();

        $totalClients = DB::table('usuarios')
            ->where('rol', 'cliente')
            ->count();

        return response()->json([
            'topSpecialty' => $topSpecialty,
            'totalClients' => $totalClients
        ]);
    }

    public function listarDoctores(Request $request)
    {
        $search = $request->input('search');
        $specialty = $request->input('specialty');

        // Consulta base para obtener doctores activos
        $query = DB::table('usuarios')
            ->select(
                'usuarios.idUsuario',
                'usuarios.nombres',
                'usuarios.apellidos'
            )
            ->where('usuarios.rol', 'doctor')
            ->where('usuarios.estado', 'activo'); // Solo doctores activos

        // Filtro de búsqueda
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('usuarios.nombres', 'LIKE', "%$search%")
                    ->orWhere('usuarios.apellidos', 'LIKE', "%$search%")
                    ->orWhere(DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos)"), 'LIKE', "%$search%");
            });
        }

        // Obtener los doctores filtrados
        $doctors = $query->get();

        // Obtener las especialidades de cada doctor
        foreach ($doctors as $doctor) {
            $doctor->especialidades = DB::table('especialidades_usuarios')
                ->join('especialidades', 'especialidades.idEspecialidad', '=', 'especialidades_usuarios.idEspecialidad')
                ->where('especialidades_usuarios.idUsuario', $doctor->idUsuario)
                ->select('especialidades.idEspecialidad', 'especialidades.nombre')
                ->get();
        }

        // Filtrar por especialidad si se especifica
        if ($specialty) {
            $doctors = collect($doctors)->filter(function ($doctor) use ($specialty) {
                return $doctor->especialidades->contains('idEspecialidad', $specialty);
            })->values();
        }

        return response()->json($doctors);
    }

    public function removeEspecialidad(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'idUsuario' => 'required|numeric',
                'idEspecialidad' => 'required|numeric'
            ]);

            // Check if the assignment exists
            $exists = DB::table('especialidades_usuarios')
                ->where('idUsuario', $request->idUsuario)
                ->where('idEspecialidad', $request->idEspecialidad)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'error' => 'La asignación no existe'
                ], 404);
            }

            // Remove the assignment
            DB::table('especialidades_usuarios')
                ->where('idUsuario', $request->idUsuario)
                ->where('idEspecialidad', $request->idEspecialidad)
                ->delete();

            return response()->json([
                'message' => 'Especialidad removida correctamente'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al remover la especialidad',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function asignarEspecialidad(Request $request)
    {
        try {
            // Validar los datos de entrada
            $request->validate([
                'idUsuario' => 'required|numeric',
                'idEspecialidad' => 'required|numeric'
            ]);
    
            // Verificar si el usuario existe y es un doctor
            $userExists = DB::table('usuarios')
                ->where('idUsuario', $request->idUsuario)
                ->where('rol', 'doctor')
                ->exists();
    
            if (!$userExists) {
                return response()->json([
                    'error' => 'El usuario no existe o no es un doctor'
                ], 404);
            }
    
            // Verificar si la especialidad existe
            $specialtyExists = DB::table('especialidades')
                ->where('idEspecialidad', $request->idEspecialidad)
                ->exists();
    
            if (!$specialtyExists) {
                return response()->json([
                    'error' => 'La especialidad no existe'
                ], 404);
            }
    
            // Verificar si el doctor ya tiene una especialidad asignada
            $existingSpecialty = DB::table('especialidades_usuarios')
                ->where('idUsuario', $request->idUsuario)
                ->first();
    
            if ($existingSpecialty) {
                // Si ya tiene una especialidad, eliminar la asignación existente
                DB::table('especialidades_usuarios')
                    ->where('idUsuario', $request->idUsuario)
                    ->delete();
            }
    
            // Insertar la nueva asignación
            DB::table('especialidades_usuarios')->insert([
                'idUsuario' => $request->idUsuario,
                'idEspecialidad' => $request->idEspecialidad,
            ]);
    
            return response()->json([
                'message' => 'Especialidad asignada correctamente'
            ], 201);
    
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Error al asignar la especialidad',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    public function obtenerUsuarios(Request $request)
    {
        try {
            $query = DB::table('usuarios')
                ->select('idUsuario', 'username', 'nombres', 'apellidos', 'dni', 'correo', 'rol', 'telefono')
                ->where('rol', '!=', 'superadmin') // Excluir usuarios con el rol 'superadmin'
                ->where('estado', 'activo'); // Filtro para solo usuarios activos
    
            // Si se proporciona un término de búsqueda
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('dni', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('telefono', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('nombres', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('apellidos', 'LIKE', "%{$searchTerm}%");
                });
            }
    
            $usuarios = $query->get();
            
            return response()->json($usuarios->toArray());
        } catch (Exception $e) {
            Log::error('Error al obtener usuarios:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function actualizarUsuario(Request $request, $id)
    {
        $messages = [
            'nombres.required' => 'El nombre es obligatorio.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.regex' => 'Debe ingresar al menos dos apellidos separados por un espacio.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.size' => 'El DNI debe tener exactamente 8 caracteres.',
            'dni.unique' => 'El DNI ya está registrado.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'El correo debe tener un formato válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'telefono.size' => 'El teléfono debe tener 9 dígitos.',
            'telefono.regex' => 'El teléfono debe contener solo números.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol debe ser cliente, doctor o admin.',
        ];

        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => [
                'required',
                'regex:/^[a-zA-ZÀ-ÿ]+(\s[a-zA-ZÀ-ÿ]+)+$/'
            ],
            'dni' => 'required|string|size:8|unique:usuarios,dni,' . $id . ',idUsuario',
            'correo' => 'required|string|email|max:255|unique:usuarios,correo,' . $id . ',idUsuario',
            'telefono' => 'nullable|string|size:9|regex:/^\d{9}$/',
            'rol' => 'required|in:cliente,doctor,admin',
        ], $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $usuario = DB::table('usuarios')->where('idUsuario', $id)->first();
            if (!$usuario) {
                return response()->json(['message' => 'Usuario no encontrado'], 404);
            }

            // Generar nuevo username si los nombres o apellidos cambiaron
            $username = $usuario->username;
            if ($usuario->nombres !== $request->nombres || $usuario->apellidos !== $request->apellidos) {
                $nombres = explode(' ', $request->nombres);
                $apellidos = explode(' ', $request->apellidos);
                
                if (count($apellidos) < 2) {
                    return response()->json([
                        'errors' => ['apellidos' => 'Debe ingresar al menos dos apellidos.']
                    ], 422);
                }

                $username = strtoupper(substr($nombres[0], 0, 2) . substr($apellidos[0], 0, 3) . substr($apellidos[1], 0, 3));
            }

            $updateData = [
                'username' => $username,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'dni' => $request->dni,
                'correo' => $request->correo,
                'telefono' => $request->telefono,
                'rol' => $request->rol,
            ];

            // Si se proporcionó una nueva contraseña, agregarla a los datos de actualización
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = Hash::make($request->password);
            }

            $updated = DB::table('usuarios')
                ->where('idUsuario', $id)
                ->update($updateData);


            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'usuario' => $updated
            ]);

        } catch (Exception $e) {
            Log::error('Error al actualizar usuario:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function eliminarUsuario($id)
    {
        try {
            // Actualizar el estado a 'inactivo' en lugar de eliminar
            $updated = DB::table('usuarios')
                ->where('idUsuario', $id)
                ->update(['estado' => 'eliminado']);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuario desactivado exitosamente'
            ]);

        } catch (Exception $e) {
            Log::error('Error al desactivar usuario:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function registrarDatosPersonales(Request $request)
    {
        Log::info('Datos recibidos en registrarDatosPersonales:', $request->all());

        $messages = [
            'nombres.required' => 'El nombre es obligatorio.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.regex' => 'Debe ingresar al menos dos apellidos separados por un espacio.',
            'dni.required' => 'El DNI es obligatorio.',
            'dni.size' => 'El DNI debe tener exactamente 8 caracteres.',
            'dni.unique' => 'El DNI ya está registrado.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'El correo debe tener un formato válido.',
            'correo.unique' => 'El correo ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir al menos una mayúscula y un símbolo.',
            'telefono.size' => 'El teléfono debe tener 9 dígitos.',
            'telefono.regex' => 'El teléfono debe contener solo números.',
            'rol.required' => 'El rol es obligatorio.',
            'rol.in' => 'El rol debe ser cliente, doctor o admin.',
        ];

        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => [
                'required',
                'regex:/^[a-zA-ZÀ-ÿ]+(\s[a-zA-ZÀ-ÿ]+)+$/'
            ],
            'dni' => 'required|string|size:8',
            'correo' => 'required|string|email|max:255',
            'telefono' => 'nullable|string|size:9|regex:/^\d{9}$/',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>])[A-Za-z\d!@#$%^&*(),.?":{}|<>]{8,}$/',
            ],
            'rol' => 'required|in:cliente,doctor,admin',
        ], $messages);

        if ($validator->fails()) {
            Log::error('Errores de validación:', $validator->errors()->toArray());
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Verificar si el DNI o el correo ya están registrados
            $existingUser = DB::table('usuarios')
                ->where('dni', $request->dni)
                ->orWhere('correo', $request->correo)
                ->first();

            if ($existingUser) {
                // Si el usuario existe y está activo, no permitir el registro
                if ($existingUser->estado === 'activo') {
                    return response()->json([
                        'errors' => [
                            'dni' => 'El DNI ya está registrado y activo.',
                            'correo' => 'El correo ya está registrado y activo.',
                        ]
                    ], 422);
                }

                // Si el usuario existe pero está eliminado, permitir el registro
                if ($existingUser->estado === 'eliminado') {
                    Log::info('Usuario eliminado encontrado, procediendo con el registro:', (array)$existingUser);
                }
            }

            // Generar el username
            $nombres = explode(' ', $request->nombres);
            $apellidos = explode(' ', $request->apellidos);

            if (count($apellidos) < 2) {
                return response()->json([
                    'errors' => ['apellidos' => 'Debe ingresar al menos dos apellidos.']
                ], 422);
            }

            $username = strtoupper(substr($nombres[0], 0, 2) . substr($apellidos[0], 0, 3) . substr($apellidos[1], 0, 3));

            // Insertar el nuevo usuario
            $userId = DB::table('usuarios')->insertGetId([
                'username' => $username,
                'rol' => $request->rol,
                'experiencia' => null,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'dni' => $request->dni,
                'correo' => $request->correo,
                'edad' => null,
                'nacimiento' => null,
                'sexo' => null,
                'telefono' => $request->telefono,
                'password' => bcrypt($request->password),
                'estado' => 'activo', // Nuevo registro siempre será activo
                'status' => 'loggedOff',
                'emailVerified' => true,
                'verification_token' => Str::random(60),
            ]);

            $user = DB::table('usuarios')->where('idUsuario', $userId)->first();
            Log::info('Usuario creado exitosamente:', (array)$user);

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente.',
                'usuario' => $user
            ], 201);

        } catch (Exception $e) {
            Log::error('Error en la consulta de SQL:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos. Verifica los datos enviados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    //PArA ESPECIALIDADES

    public function obtenerEspecialidades(Request $request)
    {
        try {
            $query = DB::table('especialidades')
                ->select('idEspecialidad', 'nombre', 'descripcion', 'icono')
                ->where('estado', 'activo'); // Filtro para solo especialidades activas
    
            // Búsqueda por nombre o descripción
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('nombre', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('descripcion', 'LIKE', "%{$searchTerm}%");
                });
            }
    
            $especialidades = $query->get();
            
            return response()->json($especialidades->toArray());
        } catch (Exception $e) {
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

    public function actualizarEspecialidad(Request $request, $id)
    {
        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'icono.required' => 'El icono es obligatorio.',
        ];

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'icono' => 'required|string',
        ], $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $especialidad = DB::table('especialidades')->where('idEspecialidad', $id)->first();
            if (!$especialidad) {
                return response()->json(['message' => 'Especialidad no encontrada'], 404);
            }

            DB::table('especialidades')
                ->where('idEspecialidad', $id)
                ->update([
                    'nombre' => $request->nombre,
                    'descripcion' => $request->descripcion,
                    'icono' => $request->icono,
                ]);

            $updatedEspecialidad = DB::table('especialidades')->where('idEspecialidad', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Especialidad actualizada exitosamente',
                'especialidad' => $updatedEspecialidad
            ]);

        } catch (Exception $e) {
            Log::error('Error al actualizar especialidad:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar especialidad',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function eliminarEspecialidad($id)
    {
        try {
            // Iniciar una transacción para asegurar la consistencia de los datos
            DB::beginTransaction();
    
            // 1. Eliminar todos los usuarios asignados a la especialidad en la tabla `especialidades_usuarios`
            DB::table('especialidades_usuarios')
                ->where('idEspecialidad', $id)
                ->delete();
    
            // 2. Actualizar el estado de la especialidad a 'eliminado' en la tabla `especialidades`
            $updated = DB::table('especialidades')
                ->where('idEspecialidad', $id)
                ->update(['estado' => 'eliminado']);
    
            // Verificar si la especialidad fue encontrada y actualizada
            if (!$updated) {
                DB::rollBack(); // Revertir la transacción
                return response()->json([
                    'success' => false,
                    'message' => 'Especialidad no encontrada'
                ], 404);
            }
    
            // Confirmar la transacción
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Especialidad desactivada y usuarios desasignados exitosamente'
            ]);
    
        } catch (Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de error
            Log::error('Error al desactivar especialidad:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar especialidad',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function registrarEspecialidad(Request $request)
    {
        $messages = [
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'icono.required' => 'El icono es obligatorio.',
        ];

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'icono' => 'required|string'
        ], $messages);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $idEspecialidad = DB::table('especialidades')->insertGetId([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'icono' => $request->icono,
                'estado'=> 'activo',
            ]);

            $especialidad = DB::table('especialidades')->where('idEspecialidad', $idEspecialidad)->first();

            return response()->json([
                'success' => true,
                'message' => 'Especialidad registrada exitosamente.',
                'especialidad' => $especialidad
            ], 201);

        } catch (Exception $e) {
            Log::error('Error en la consulta de SQL:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos. Verifica los datos enviados.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
