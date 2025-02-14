<?php

namespace App\Http\Controllers;


use App\Mail\RestablecerPassword;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Google_Client;

//MODELOS
use App\Models\ActividadUsuario;
use App\Models\Usuario;
use App\Models\Log as LogUser;

//MAILS
use App\Mail\VerificarCorreo;
use App\Mail\CuentaVerificada;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
* @OA\Info(
*    title="RCI-BACKEND API DOCUMENTATION", 
*    version="1.0",
*    description="API DOCUMENTATION"
* )
*
* @OA\Server(url="https://talararci.thonymarckdev.online")
*/
class AuthController extends Controller
{

    public function solicitarRestablecerPassword(Request $request)
    {
        try {
            $request->validate([
                'correo' => 'required|email',
            ]);

            $usuario = Usuario::where('correo', $request->correo)->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No encontramos una cuenta con ese correo electrónico.',
                ], 404);
            }

            // Generar token único
            $token = Str::random(60);
            $usuario->verification_token = $token;
            $usuario->save();

            // Enviar correo con el enlace
            Mail::to($usuario->correo)->send(new RestablecerPassword($usuario, $token));

            return response()->json([
                'success' => true,
                'message' => 'Se ha enviado un enlace a tu correo electrónico.',
            ], 200);

        } catch (Exception $e) {
            Log::error('Error en solicitud de restablecimiento de contraseña:', [
                'error' => $e->getMessage(),
                'correo' => $request->correo
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la solicitud.',
            ], 500);
        }
    }

    public function verificarTokenPassword(Request $request)
    {
        try {
            $request->validate([
                'token_veririficador' => 'required|string',
            ]);

            $usuario = Usuario::where('verification_token', $request->token_veririficador)->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no válido o expirado.',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token válido.',
            ], 200);

        } catch (Exception $e) {
            Log::error('Error verificando token de restablecimiento:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el token.',
            ], 500);
        }
    }

    public function restablecerPassword(Request $request)
    {
        try {
            $request->validate([
                'token_veririficador' => 'required|string',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>]).*$/'
                ],
            ], [
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.regex' => 'La contraseña debe contener al menos una mayúscula y un símbolo.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
            ]);
    
            $usuario = Usuario::where('verification_token', $request->token_veririficador)->first();
    
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no válido o expirado.',
                ], 400);
            }
    
            $usuario->password = Hash::make($request->password);
            $usuario->verification_token = null;
            $usuario->save();
    
            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente.',
            ], 200);
    
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error al restablecer contraseña:', ['error' => $e->getMessage()]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al restablecer la contraseña.',
            ], 500);
        }
    }

    public function verificarDni(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'dni' => 'required|string|size:8',
            'nacimiento' => 'required|date|before:today',
        ], [
            'dni.required' => 'El DNI es obligatorio.',
            'dni.size' => 'El DNI debe tener exactamente 8 caracteres.',
            'nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
            'nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        // Calcular la edad a partir de la fecha de nacimiento
        $fechaNacimiento = Carbon::parse($request->nacimiento);
        $edad = $fechaNacimiento->age;
    
        // Validar que el usuario tenga al menos 18 años
        if ($edad < 18) {
            return response()->json([
                'errors' => [
                    'nacimiento' => 'Debes tener al menos 18 años para registrarte.'
                ]
            ], 422);
        }
    
        // Verificar si el DNI ya está registrado
        $existingDni = Usuario::where('dni', $request->dni)->first();
        if ($existingDni) {
            return response()->json([
                'errors' => [
                    'dni' => 'El DNI ya está registrado.'
                ]
            ], 409);
        }
    
        return response()->json([
            'success' => true,
            'message' => 'DNI verificado correctamente.',
            'dni' => $request->dni,
            'nacimiento' => $request->nacimiento,
        ], 200);
    }
    
    
    public function registrarDatosPersonales(Request $request)
    {
        DB::beginTransaction(); // Inicia una transacción
    
        try {
            // Log para verificar los datos recibidos
            Log::info('Datos recibidos en registerUser:', $request->all());
    
            // Mensajes de validación personalizados
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
                'nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
                'nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.regex' => 'La contraseña debe incluir al menos una mayúscula y un símbolo.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
            ];
    
            // Reglas de validación
            $validator = Validator::make($request->all(), [
                'nombres' => 'required|string|max:255',
                'apellidos' => [
                    'required',
                    'regex:/^[a-zA-ZÀ-ÿ]+(\s[a-zA-ZÀ-ÿ]+)+$/'
                ],
                'dni' => 'required|string|size:8|unique:usuarios',
                'correo' => 'required|string|email|max:255|unique:usuarios',
                'nacimiento' => 'required|date|before:today',
                'telefono' => 'nullable|string|size:9|regex:/^\d{9}$/',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:255',
                    'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>_])[A-Za-z\d!@#$%^&*(),.?":{}|<>_]{8,}$/',
                    'confirmed'
                ]
            ], $messages);
    
            // Si la validación falla, retorna los errores
            if ($validator->fails()) {
                Log::error('Errores de validación en registerUser:', $validator->errors()->toArray());
                return response()->json(['errors' => $validator->errors()], 422);
            }
    
            // Generar el username a partir de nombres y apellidos
            $nombres = explode(' ', $request->nombres);
            $apellidos = explode(' ', $request->apellidos);
    
            // Validar que haya suficientes elementos para el username
            if (count($apellidos) < 2) {
                return response()->json(['errors' => ['apellidos' => 'Debe ingresar al menos dos apellidos.']], 422);
            }
    
            $username = strtoupper(substr($nombres[0], 0, 2) . substr($apellidos[0], 0, 3) . substr($apellidos[1], 0, 3));
    
            // Calcular la edad a partir de la fecha de nacimiento
            $edad = Carbon::parse($request->nacimiento)->age;
    
            // Registrar el usuario
            $user = Usuario::create([
                'username' => $username, // Username generado
                'rol' => 'cliente', // Rol por defecto
                'experiencia' => null,
                'nombres' => $request->nombres,
                'apellidos' => $request->apellidos,
                'dni' => $request->dni,
                'correo' => $request->correo,
                'edad' => $edad, // Edad calculada
                'nacimiento' => $request->nacimiento,
                'sexo' => null,
                'telefono' => $request->telefono ?? null,
                'password' => bcrypt($request->password),
                'estado' => 'activo',
                'status' => 'loggedOff', // Status por defecto
                'verification_token' => Str::random(60), // Genera un token único
            ]);
    
            // Log para confirmar que el usuario se creó correctamente
            Log::info('Usuario creado exitosamente:', $user->toArray());
    

            // URL para verificar el correo
            $verificationUrl = "https://cismedic.vercel.app/verificar-correo-token?token_veririficador={$user->verification_token}";

            // URL para verificar el correo
            //$verificationUrl = "https://thonymarckdev.vercel.app/verificar-correo-token?token_veririficador={$user->verification_token}";
    
            // Enviar el correo
            Mail::to($user->correo)->send(new VerificarCorreo($user, $verificationUrl));
    
            DB::commit(); // Confirma la transacción si todo está bien
    
            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente. Verifica tu correo.',
            ], 201);
    
        } catch (QueryException $e) {
            DB::rollBack(); // Revierte la transacción en caso de error
    
            Log::error('Error en la consulta de SQL en registerUser:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos. Verifica los datos enviados.',
                'error' => $e->getMessage(),
            ], 500);
    
        } catch (Exception $e) {
            DB::rollBack(); // Revierte la transacción en caso de error
    
            Log::error('Error inesperado en registerUser:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el usuario. Por favor, inténtalo de nuevo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/verificar-token",
     *     summary="Verificar correo electrónico",
     *     description="Este endpoint se utiliza para verificar el correo electrónico de un usuario utilizando un token de verificación.",
     *     operationId="verificarCorreo",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token_veririficador"},
     *             @OA\Property(property="token_veririficador", type="string", description="Token de verificación enviado al correo del usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Correo verificado exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Correo verificado exitosamente."),
     *             @OA\Property(property="token", type="string", nullable=true, example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MX0.sVjK...") 
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Token no válido o ya utilizado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token no válido o ya utilizado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al verificar el correo.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al verificar el correo.")
     *         )
     *     ),
     * )
     */
    public function verificarCorreo(Request $request)
    {
        try {
            // Validar la solicitud
            $request->validate([
                'token_veririficador' => 'required|string',
            ]);
    
            // Buscar usuario por el token de verificación
            $usuario = Usuario::where('verification_token', $request->token_veririficador)->first();
    
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no válido o ya utilizado.',
                ], 400);
            }
    

            $usuario->emailVerified = true;
            $usuario->verification_token = null; // Eliminar el token después de usarlo
            $usuario->save();

            // Enviar notificación de cuenta verificada
            Mail::to($usuario->correo)->send(new CuentaVerificada($usuario));
    
            return response()->json([
                'success' => true,
                'message' => 'Correo verificado exitosamente.',
            ], 200);
    
        } catch (Exception $e) {
            Log::error('Error verificando el correo', ['error' => $e->getMessage()]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el correo.',
            ], 500);
        }
    }

    /**
     * Login de usuario
     * 
     * Este endpoint permite a los usuarios autenticarse en el sistema utilizando su correo electrónico y contraseña.
     * Si las credenciales son válidas, se genera un token JWT que el usuario puede utilizar para acceder a otros endpoints protegidos.
     * Además, se registra la actividad del usuario y se actualiza su estado a "loggedOn".
     *
     * @OA\Post(
     *     path="/api/login",
     *     tags={"AUTH CONTROLLER"},
     *     summary="Login de usuario",
     *     description="Permite a los usuarios autenticarse en el sistema y obtener un token JWT.",
     *     operationId="login",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Credenciales del usuario",
     *         @OA\JsonContent(
     *             required={"correo","password"},
     *             @OA\Property(property="correo", type="string", format="email", example="usuario@dominio.com"),
     *             @OA\Property(property="password", type="string", format="password", example="contraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token generado con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El campo correo es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Credenciales inválidas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuario inactivo",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario inactivo. Por favor, contacte al administrador.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al generar el token",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No se pudo crear el token")
     *         )
     *     )
     * )
     */
    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'correo' => 'required|email',
    //         'password' => 'required|string|min:6',
    //     ]);
    
    //     $credentials = [
    //         'correo' => $request->input('correo'),
    //         'password' => $request->input('password')
    //     ];
    
    //     try {
    //         $usuario = Usuario::where('correo', $credentials['correo'])->first();
    
    //         if (!$usuario) {
    //             return response()->json(['error' => 'Usuario no encontrado'], 404);
    //         }
    
    //         // Validar si el correo está verificado
    //         if ($usuario->emailVerified === 0) {
    //             return response()->json(['error' => 'Por favor, verifique su cuenta para poder ingresar.'], 403);
    //         }
    
    //         if ($usuario->estado === 'inactivo') {
    //             return response()->json(['error' => 'Usuario inactivo'], 403);
    //         }
    
    //         // Generar nuevo token
    //         if (!$token = JWTAuth::attempt(['correo' => $credentials['correo'], 'password' => $credentials['password']])) {
    //             return response()->json(['error' => 'Credenciales inválidas'], 401);
    //         }
    
    //         // IMPORTANTE: Primero invalidamos todas las sesiones existentes
    //         $this->invalidarSesionesAnteriores($usuario->idUsuario);
    
    //         $dispositivo = $this->obtenerDispositivo();
    
    //         // Crear o actualizar el registro de actividad con el nuevo token
    //         ActividadUsuario::updateOrCreate(
    //             ['idUsuario' => $usuario->idUsuario],
    //             [
    //                 'last_activity' => now(),
    //                 'dispositivo' => $dispositivo,
    //                 'jwt' => $token,
    //                 'session_active' => true
    //             ]
    //         );
    
    //         // Actualizar estado del usuario
    //         $usuario->update(['status' => 'loggedOn']);
    
    //         // Log de la acción
    //         $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    //         $this->agregarLog($usuario->idUsuario, "$nombreUsuario inició sesión desde: $dispositivo");
    
    //         return response()->json([
    //             'token' => $token,
    //             'message' => 'Login exitoso, sesiones anteriores cerradas'
    //         ]);
    
    //     } catch (JWTException $e) {
    //         Log::error('Error en login: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error al crear token'], 500);
    //     }
    // }

    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = [
            'correo' => $request->input('correo'),
            'password' => $request->input('password')
        ];

        try {
            $usuario = Usuario::where('correo', $credentials['correo'])->first();

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // Verificar si la cuenta está eliminada
            if ($usuario->estado === 'eliminado') {
                return response()->json([
                    'error' => 'Su cuenta esta eliminada. Por favor regístrese nuevamente.',
                    'accountDeleted' => true
                ], 403);
            }

            // Validar si el correo está verificado
            if ($usuario->emailVerified === 0) {
                return response()->json(['error' => 'Por favor, verifique su cuenta para poder ingresar.'], 403);
            }

            if ($usuario->estado === 'inactivo') {
                return response()->json(['error' => 'Usuario inactivo'], 403);
            }

            // Generar nuevo token
            if (!$token = JWTAuth::attempt(['correo' => $credentials['correo'], 'password' => $credentials['password']])) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            // IMPORTANTE: Primero invalidamos todas las sesiones existentes
            $this->invalidarSesionesAnteriores($usuario->idUsuario);

            $dispositivo = $this->obtenerDispositivo();

            // Crear o actualizar el registro de actividad con el nuevo token
            ActividadUsuario::updateOrCreate(
                ['idUsuario' => $usuario->idUsuario],
                [
                    'last_activity' => now(),
                    'dispositivo' => $dispositivo,
                    'jwt' => $token,
                    'session_active' => true
                ]
            );

            // Actualizar estado del usuario
            $usuario->update(['status' => 'loggedOn']);

            // Log de la acción
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $this->agregarLog($usuario->idUsuario, "$nombreUsuario inició sesión desde: $dispositivo");

            return response()->json([
                'token' => $token,
                'message' => 'Login exitoso, sesiones anteriores cerradas'
            ]);

        } catch (JWTException $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return response()->json(['error' => 'Error al crear token'], 500);
        }
    }
    
    private function invalidarSesionesAnteriores($idUsuario)
    {
        try {
            $actividad = ActividadUsuario::where('idUsuario', $idUsuario)->first();
            
            if ($actividad && $actividad->jwt) {
                // Invalidar en JWT
                try {
                    JWTAuth::setToken($actividad->jwt);
                    JWTAuth::invalidate(true);
                } catch (\Exception $e) {
                    Log::error('Error al invalidar JWT: ' . $e->getMessage());
                }
    
                // Marcar como inactiva en la base de datos
                $actividad->update([
                    'session_active' => false,
                    'jwt' => null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error al invalidar sesiones: ' . $e->getMessage());
        }
    }
    
  

    // Función para obtener el dispositivo
    private function obtenerDispositivo()
    {
        return request()->header('User-Agent');  // Obtiene el User-Agent del encabezado de la solicitud
    }




    /**
     * Cerrar sesión del usuario
     * 
     * Este endpoint permite a los usuarios cerrar sesión en el sistema. Revoca el token JWT actual
     * y actualiza el estado del usuario a "loggedOff". Además, registra la acción en el log de actividades.
     *
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión del usuario",
     *     description="Este endpoint se utiliza para cerrar sesión de un usuario y revocar su token JWT.",
     *     operationId="logout",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="ID del usuario que desea cerrar sesión",
     *         @OA\JsonContent(
     *             required={"idUsuario"},
     *             @OA\Property(property="idUsuario", type="integer", example=1, description="ID del usuario que desea cerrar sesión.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario deslogueado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario deslogueado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El campo idUsuario es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No autorizado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo encontrar el usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al desloguear al usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo desloguear al usuario.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        // Validar que el ID del usuario esté presente y sea un entero
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);
 
        // Buscar el usuario por su ID
        $user = Usuario::where('idUsuario', $request->idUsuario)->first();
 
        if ($user) {
            try {
                // Iniciar transacción
                DB::beginTransaction();
 
                // Obtener el token actual de la tabla actividad_usuario
                $actividad = ActividadUsuario::where('idUsuario', $request->idUsuario)->first();
                
                if ($actividad && $actividad->jwt) {
                    try {
                        // Configurar el token en JWTAuth
                        $token = $actividad->jwt;
                        JWTAuth::setToken($token);
 
                        // Verificar que el token sea válido antes de intentar invalidarlo
                        if (JWTAuth::check()) {
                            // Invalidar el token y forzar su expiración
                            JWTAuth::invalidate(true);
                        }
                    } catch (JWTException $e) {
                        Log::error('Error al invalidar token: ' . $e->getMessage());
                    } catch (\Exception $e) {
                        Log::error('Error general con el token: ' . $e->getMessage());
                    }
                }
 
                // Actualizar el estado del usuario a "loggedOff"
                $user->status = 'loggedOff';
                $user->save();
 
                // Limpiar el JWT en la tabla actividad_usuario
                if ($actividad) {
                    $actividad->jwt = null;
                    $actividad->save();
                }
 
                // Obtener el nombre completo del usuario
                $nombreUsuario = $user->nombres . ' ' . $user->apellidos;
 
                // Definir la acción y mensaje para el log
                $accion = "$nombreUsuario cerró sesión";
 
                // Llamada a la función agregarLog para registrar el log
                $this->agregarLog($user->idUsuario, $accion);
 
                // Confirmar transacción
                DB::commit();
 
                return response()->json([
                    'success' => true, 
                    'message' => 'Usuario deslogueado correctamente'
                ], 200);
 
            } catch (\Exception $e) {
                // Revertir transacción en caso de error
                DB::rollBack();
 
                return response()->json([
                    'success' => false, 
                    'message' => 'No se pudo desloguear al usuario',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
 
        return response()->json([
            'success' => false, 
            'message' => 'No se pudo encontrar el usuario'
        ], 404);
    }
 

 
    public function refreshToken(Request $request)
    {
        try {
            $oldToken = JWTAuth::getToken();  // Obtener el token actual
            
            Log::info('Refrescando token: Token recibido', ['token' => (string) $oldToken]);
            
            // Decodificar el token para obtener el payload
            $decodedToken = JWTAuth::getPayload($oldToken);  // Utilizamos getPayload para obtener el payload
            $userId = $decodedToken->get('idUsuario');  // Usamos get() para acceder a 'idUsuario'
            
            // Refrescar el token
            $newToken = JWTAuth::refresh($oldToken);
            
            // Actualizar el campo jwt en la tabla actividad_usuario
            $actividadUsuario = ActividadUsuario::updateOrCreate(
                ['idUsuario' => $userId],  // Si ya existe, se actualizará por el idUsuario
                ['jwt' => $newToken]  // Actualizar el campo jwt con el nuevo token
            );
            
            Log::info('JWT actualizado en la actividad del usuario', ['userId' => $userId, 'jwt' => $newToken]);
            
            return response()->json(['accessToken' => $newToken], 200);
        } catch (JWTException $e) {
            Log::error('Error al refrescar el token', ['error' => $e->getMessage()]);
            
            return response()->json(['error' => 'No se pudo refrescar el token'], 500);
        }
    }
 


    /**
     * @OA\Post(
     *     path="/api/update-activity",
     *     summary="Actualizar la última actividad del usuario",
     *     description="Este endpoint actualiza la fecha de la última actividad del usuario especificado.",
     *     operationId="updateLastActivity",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuya última actividad se actualizará.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Actividad actualizada correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Last activity updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="ID de usuario requerido")
     *         )
     *     )
     * )
     */
    public function updateLastActivity(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);

        $user = Usuario::find($request->idUsuario);
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        
        $user->activity()->updateOrCreate(
            ['idUsuario' => $user->idUsuario],
            ['last_activity' => now()]
        );
        
        return response()->json(['message' => 'Last activity updated'], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/check-status",
     *     summary="Verificar el estado del usuario y la validez del token",
     *     description="Este endpoint verifica el estado del usuario y la validez del token JWT proporcionado. 
     *                  Compara el token JWT del encabezado de autorización con el token almacenado en la base de datos 
     *                  para el usuario especificado. Si el token no coincide o el usuario no tiene un registro de actividad, 
     *                  se devuelve un error.",
     *     operationId="checkStatus",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuyo estado se desea verificar.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="El token es válido y coincide con el almacenado en la base de datos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Solicitud inválida. El ID de usuario no fue proporcionado o no es un entero válido.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="ID de usuario no proporcionado o inválido")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El token proporcionado no coincide con el token almacenado en la base de datos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="El token no coincide con el almacenado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No se encontró un registro de actividad para el usuario especificado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     )
     * )
     */
    public function checkStatus(Request $request)
    {
        // Validar que el idUsuario esté presente
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);

        $idUsuario = $request->input('idUsuario');
        $token = $request->bearerToken(); // Obtener el token JWT del encabezado Authorization

        // Buscar el registro de actividad del usuario en la base de datos
        $actividadUsuario = ActividadUsuario::where('idUsuario', $idUsuario)->first();

        // Si no hay un registro de actividad, devolver un error
        if (!$actividadUsuario) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Verificar si el token en la base de datos es diferente al token actual
        if ($actividadUsuario->jwt !== $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token no coincide con el almacenado'
            ], 403);
        }

        // Si el token es válido, devolver el estado y el token actual
        return response()->json([
            'status' => 'success',
            'token' => $actividadUsuario->jwt  // Devuelves el token almacenado en la base de datos
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/send-message",
     *     summary="Enviar mensaje de contacto",
     *     description="Este endpoint permite a los usuarios enviar un mensaje de contacto al administrador.",
     *     operationId="sendContactEmail",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del mensaje de contacto",
     *         @OA\JsonContent(
     *             required={"name", "email", "message"},
     *             @OA\Property(property="name", type="string", description="Nombre del remitente", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="Correo electrónico del remitente", example="johndoe@example.com"),
     *             @OA\Property(property="message", type="string", description="Mensaje de contacto", example="Hola, tengo una consulta sobre los productos.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mensaje enviado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example="Mensaje enviado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error en los datos enviados.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El nombre es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al enviar el mensaje.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al enviar el mensaje. Inténtalo más tarde.")
     *         )
     *     )
     * )
     */
     public function sendContactEmail(Request $request)
     {
         $request->validate([
             'name' => 'required|string|max:255',
             'email' => 'required|email',
             'message' => 'required|string',
         ]);
 
         // Configura los datos del correo
         $data = [
             'name' => $request->name,
             'email' => $request->email,
             'messageContent' => $request->message,
         ];
 
         // Envía el correo
         Mail::send('emails.contact', $data, function($message) use ($request) {
             $message->to('thonymarck385213xd@gmail.com', 'Administrador')
                     ->subject('Nuevo mensaje de contacto');
             $message->from($request->email, $request->name);
         });
 
         return response()->json(['success' => 'Mensaje enviado correctamente.']);
     }

    // Función para agregar un log directamente desde el backend
    public function agregarLog($usuarioId, $accion)
    {
        // Obtener el usuario por id
        $usuario = Usuario::find($usuarioId);

        if ($usuario) {
            // Crear el log
            $log = LogUser::create([
                'idUsuario' => $usuario->idUsuario,
                'nombreUsuario' => $usuario->nombres . ' ' . $usuario->apellidos,
                'rol' => $usuario->rol,
                'accion' => $accion,
                'fecha' => now(),
            ]);

            return response()->json(['message' => 'Log agregado correctamente', 'log' => $log], 200);
        }

        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

}
