<?php

namespace App\Http\Controllers;

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

class SuperAdminController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/adminAgregar",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar un nuevo usuario administrador",
     *     description="Permite a un superadministrador agregar un nuevo usuario con rol de administrador.",
     *     operationId="agregarUsuario",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del nuevo usuario administrador",
     *         @OA\JsonContent(
     *             required={"nombres","apellidos","correo","password"},
     *             @OA\Property(property="nombres", type="string", example="Juan"),
     *             @OA\Property(property="apellidos", type="string", example="Pérez"),
     *             @OA\Property(property="correo", type="string", format="email", example="juan.perez@dominio.com"),
     *             @OA\Property(property="password", type="string", format="password", example="contraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuario agregado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario agregado exitosamente"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="rol", type="string", example="admin"),
     *                 @OA\Property(property="nombres", type="string", example="Juan"),
     *                 @OA\Property(property="apellidos", type="string", example="Pérez"),
     *                 @OA\Property(property="correo", type="string", format="email", example="juan.perez@dominio.com"),
     *                 @OA\Property(property="fecha_creado", type="string", format="date-time", example="2023-10-01T12:00:00Z"),
     *                 @OA\Property(property="status", type="string", example="loggedOff"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(property="errors", type="object", example={"correo": {"El campo correo es requerido."}})
     *         )
     *     )
     * )
     * @OA\SecurityScheme(
    *     securityScheme="bearerAuth",
    *     type="http",
    *     scheme="bearer",
    *     bearerFormat="JWT",
    *     description="Usar un token JWT en el encabezado Authorization como Bearer <token>"
    * )
     */
    public function agregarUsuario(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
        ]);
        
        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Obtener nombres y apellidos del nuevo usuario
        $nombres = $request->nombres;
        $apellidos = $request->apellidos;

        // Crear el usuario con valores predeterminados
        $user = Usuario::create([
            'rol' => 'admin', // Valor predeterminado
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $request->correo,
            'password' => bcrypt($request->password), // Encriptar la contraseña
            'fecha_creado' => now(), // Fecha actual
            'status' => 'loggedOff', // Valor predeterminado
            'estado' => 'activo',
        ]);

        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado

        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario agregó un nuevo administrador: $nombres $apellidos con correo: {$request->correo}";

        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);
        
        // Retornar una respuesta exitosa
        return response()->json([
            'success' => true,
            'message' => 'Usuario agregado exitosamente',
            'user' => $user,
        ], 201);
    }
        

        /**
     * @OA\Put(
     *     path="/api/listarUsuariosAdmin/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Editar un usuario existente",
     *     description="Permite a un usuario autorizado editar la información de un usuario existente, incluyendo nombres, apellidos, correo, contraseña y rol.",
     *     operationId="editarUsuario",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario que se desea editar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del usuario que se desean actualizar",
     *         @OA\JsonContent(
     *             @OA\Property(property="nombres", type="string", example="Juan"),
     *             @OA\Property(property="apellidos", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", format="email", example="juan.perez@dominio.com"),
     *             @OA\Property(property="password", type="string", format="password", example="nuevaContraseña123"),
     *             @OA\Property(property="role", type="string", example="admin")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario actualizado exitosamente"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="nombres", type="string", example="Juan"),
     *                 @OA\Property(property="apellidos", type="string", example="Pérez"),
     *                 @OA\Property(property="correo", type="string", format="email", example="juan.perez@dominio.com"),
     *                 @OA\Property(property="rol", type="string", example="admin"),
     *                 @OA\Property(property="fecha_creado", type="string", format="date-time", example="2023-10-01T12:00:00Z"),
     *                 @OA\Property(property="status", type="string", example="loggedOff"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "correo": {"El campo correo es requerido."},
     *                     "password": {"El campo password debe tener al menos 6 caracteres."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
     public function editarUsuario(Request $request, $id)
     {
         Log::info('Iniciando actualización de usuario', ['usuario_id' => $id, 'data' => $request->all()]);
     
         // Traducción de campos
         $request->merge([
             'correo' => $request->input('email'),
             'rol' => $request->input('role'),
         ]);
     
         $validator = Validator::make($request->all(), [
             'nombres' => 'sometimes|string|max:255',
             'apellidos' => 'sometimes|string|max:255',
             'correo' => 'sometimes|email|unique:usuarios,correo,' . $id . ',idUsuario',
             'password' => 'sometimes|string|min:6',
             'rol' => 'sometimes|string|max:255',
         ]);
     
         if ($validator->fails()) {
             Log::error('Validación fallida', ['errors' => $validator->errors()]);
             return response()->json([
                 'success' => false,
                 'message' => 'Error de validación',
                 'errors' => $validator->errors(),
             ], 422);
         }
     
         $user = Usuario::find($id);
         if (!$user) {
             Log::warning('Usuario no encontrado', ['usuario_id' => $id]);
             return response()->json([
                 'success' => false,
                 'message' => 'Usuario no encontrado',
             ], 404);
         }
     
         $usuarioId = auth()->id();
         $usuario = Usuario::find($usuarioId);
         $nombreUsuario = $usuario ? $usuario->nombres . ' ' . $usuario->apellidos : 'Desconocido';
     
         $camposActualizados = false;
     
         // Nombres
         if ($request->filled('nombres') && $user->nombres !== $request->nombres) {
             $nombreAntiguo = $user->nombres;
             $user->nombres = $request->nombres;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el nombre del usuario de '$nombreAntiguo' a '{$request->nombres}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Nombre actualizado', ['nuevo' => $request->nombres]);
             $camposActualizados = true;
         }
     
         // Apellidos
         if ($request->filled('apellidos') && $user->apellidos !== $request->apellidos) {
             $apellidoAntiguo = $user->apellidos;
             $user->apellidos = $request->apellidos;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el apellido del usuario de '$apellidoAntiguo' a '{$request->apellidos}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Apellido actualizado', ['nuevo' => $request->apellidos]);
             $camposActualizados = true;
         }
     
         // Correo
         if ($request->filled('correo') && $user->correo !== $request->correo) {
             $correoAntiguo = $user->correo;
             $user->correo = $request->correo;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el correo del usuario de '$correoAntiguo' a '{$request->correo}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Correo actualizado', ['nuevo' => $request->correo]);
             $camposActualizados = true;
         }
     
         // Contraseña
         if ($request->filled('password')) {
             $user->password = bcrypt($request->password);
             $user->save();
     
             $accion = "$nombreUsuario actualizó la contraseña del usuario.";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Contraseña actualizada');
             $camposActualizados = true;
         }
     
         // Rol
         if ($request->filled('rol') && $user->rol !== $request->rol) {
             $rolAntiguo = $user->rol;
             $user->rol = $request->rol;
             $user->save();
     
             $accion = "$nombreUsuario actualizó el rol del usuario de '$rolAntiguo' a '{$request->rol}'";
             $this->agregarLog($usuarioId, $accion);
             Log::info('Rol actualizado', ['nuevo' => $request->rol]);
             $camposActualizados = true;
         }
     
         // Respuesta
         if ($camposActualizados) {
             return response()->json([
                 'success' => true,
                 'message' => 'Usuario actualizado exitosamente',
                 'user' => $user,
             ]);
         }
     
         return response()->json([
             'success' => true,
             'message' => 'No se realizaron cambios',
             'user' => $user,
         ]);
     }
     
    /**
     * @OA\Get(
     *     path="/api/listarUsuarios",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar usuarios administradores",
     *     description="Permite a un superadministrador listar todos los usuarios con rol de administrador, con opciones de paginación, búsqueda y filtrado.",
     *     operationId="listarUsuarios",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Número de elementos por página (por defecto 10)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página (por defecto 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Término de búsqueda general (busca en nombres, apellidos, correo, rol y estado)",
     *         required=false,
     *         @OA\Schema(type="string", example="Juan")
     *     ),
     *     @OA\Parameter(
     *         name="nombres",
     *         in="query",
     *         description="Filtrar por nombres",
     *         required=false,
     *         @OA\Schema(type="string", example="Juan")
     *     ),
     *     @OA\Parameter(
     *         name="apellidos",
     *         in="query",
     *         description="Filtrar por apellidos",
     *         required=false,
     *         @OA\Schema(type="string", example="Pérez")
     *     ),
     *     @OA\Parameter(
     *         name="correo",
     *         in="query",
     *         description="Filtrar por correo electrónico",
     *         required=false,
     *         @OA\Schema(type="string", format="email", example="juan.perez@dominio.com")
     *     ),
     *     @OA\Parameter(
     *         name="rol",
     *         in="query",
     *         description="Filtrar por rol",
     *         required=false,
     *         @OA\Schema(type="string", example="admin")
     *     ),
     *     @OA\Parameter(
     *         name="estado",
     *         in="query",
     *         description="Filtrar por estado",
     *         required=false,
     *         @OA\Schema(type="string", example="activo")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="usuarios",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="idUsuario", type="integer", example=1),
     *                     @OA\Property(property="nombres", type="string", example="Juan"),
     *                     @OA\Property(property="apellidos", type="string", example="Pérez"),
     *                     @OA\Property(property="correo", type="string", format="email", example="juan.perez@dominio.com"),
     *                     @OA\Property(property="rol", type="string", example="admin"),
     *                     @OA\Property(property="estado", type="string", example="activo")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener los usuarios"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
     public function listarUsuarios(Request $request)
     {
         try {
             // Obtener los parámetros de la solicitud
             $perPage = $request->input('per_page', 10); // Número de elementos por página (por defecto 10)
             $page = $request->input('page', 1); // Página actual (por defecto 1)
             $search = $request->input('search', ''); // Término de búsqueda general
             $filters = $request->only(['nombres', 'apellidos', 'correo', 'rol', 'estado']); // Filtros específicos
     
             // Construir la consulta
             $query = Usuario::where('rol', 'admin');
     
             // Aplicar filtros dinámicos
             foreach ($filters as $field => $value) {
                 if ($value) {
                     $query->where($field, 'like', "%{$value}%");
                 }
             }
     
             // Aplicar búsqueda general
             if ($search) {
                 $query->where(function ($q) use ($search) {
                     $q->where('nombres', 'like', "%{$search}%")
                       ->orWhere('apellidos', 'like', "%{$search}%")
                       ->orWhere('correo', 'like', "%{$search}%")
                       ->orWhere('rol', 'like', "%{$search}%")
                       ->orWhere('estado', 'like', "%{$search}%");
                 });
             }
     
             // Paginar los resultados
             $usuarios = $query->select('idUsuario', 'nombres', 'apellidos', 'correo', 'rol', 'estado')
                               ->paginate($perPage, ['*'], 'page', $page);
     
             return response()->json([
                 'success' => true,
                 'usuarios' => $usuarios->items(), // Lista de usuarios
                 'pagination' => [
                     'total' => $usuarios->total(), // Total de usuarios
                     'per_page' => $usuarios->perPage(), // Elementos por página
                     'current_page' => $usuarios->currentPage(), // Página actual
                     'last_page' => $usuarios->lastPage(), // Última página
                     'from' => $usuarios->firstItem(), // Primer elemento de la página
                     'to' => $usuarios->lastItem(), // Último elemento de la página
                 ]
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Error al obtener los usuarios',
                 'error' => $e->getMessage()
             ], 500);
         }
     }
 
        /**
     * @OA\Put(
     *     path="/api/listarUsuariosAdmin/{id}/cambiar-estado",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Cambiar el estado de un usuario",
     *     description="Permite a un superadministrador cambiar el estado de un usuario entre 'activo' e 'inactivo'. Además, registra la acción en el log del sistema.",
     *     operationId="cambiarEstado",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario cuyo estado se desea cambiar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estado actualizado exitosamente"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="idUsuario", type="integer", example=1),
     *                 @OA\Property(property="nombres", type="string", example="Juan"),
     *                 @OA\Property(property="apellidos", type="string", example="Pérez"),
     *                 @OA\Property(property="correo", type="string", format="email", example="juan.perez@dominio.com"),
     *                 @OA\Property(property="rol", type="string", example="admin"),
     *                 @OA\Property(property="estado", type="string", example="inactivo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function cambiarEstado($id)
    {
        // Buscar el usuario por ID
        $user = Usuario::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado

        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;

        // Cambiar el estado
        $user->estado = ($user->estado === 'activo') ? 'inactivo' : 'activo';
        $user->save();

        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario cambió el estado del usuario: {$user->nombres} {$user->apellidos} (Correo: {$user->correo}) a {$user->estado}";

        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);

        // Retornar una respuesta exitosa con el estado actualizado
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'user' => [
                'idUsuario' => $user->idUsuario,
                'nombres' => $user->nombres,
                'apellidos' => $user->apellidos,
                'correo' => $user->correo,
                'rol' => $user->rol,
                'estado' => $user->estado, // Asegúrate de devolver el estado actualizado
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/categoriasproductos",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar categorías de productos activas",
     *     description="Permite a un superadministrador obtener una lista de todas las categorías de productos con estado 'activo'.",
     *     operationId="listarCategoriasProductos",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías obtenida exitosamente",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function listarCategoriasProductos()
    {
        // Filtrar categorías con estado "activo"
        $categorias = Categoria::where('estado', 'activo')->get();
        return response()->json($categorias);
    }


    /**
     * @OA\Post(
     *     path="/api/agregarProducto",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar un nuevo producto con modelos e imágenes",
     *     description="Permite a un superadministrador agregar un nuevo producto, incluyendo sus modelos e imágenes asociadas. Se validan los datos de entrada y se registra la acción en el log del sistema.",
     *     operationId="agregarProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del producto y sus modelos",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombreProducto", "estado", "idCategoria", "modelos"},
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto Ejemplo"),
     *                 @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción del producto"),
     *                 @OA\Property(property="estado", type="string", example="activo"),
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(
     *                     property="modelos",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"nombreModelo", "imagen"},
     *                         @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo"),
     *                         @OA\Property(property="imagen", type="string", format="binary", description="Imagen del modelo (formato: jpg, png, etc.)")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Producto agregado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Producto agregado correctamente"),
     *             @OA\Property(
     *                 property="producto",
     *                 type="object",
     *                 @OA\Property(property="idProducto", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto Ejemplo"),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción del producto"),
     *                 @OA\Property(property="estado", type="string", example="activo"),
     *                 @OA\Property(property="idCategoria", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "nombreProducto": {"El campo nombreProducto es obligatorio."},
     *                     "modelos.0.nombreModelo": {"El campo nombreModelo es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al agregar el producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function agregarProducto(Request $request)
    {
        Log::info('Iniciando proceso de agregar producto'); // Log de inicio

        try {
            // Verificar si ya existe un producto con el mismo nombre
            Log::info('Verificando si el producto ya existe');
            $productoExistente = Producto::where('nombreProducto', $request->nombreProducto)->first();

            if ($productoExistente) {
                Log::warning('Producto con el mismo nombre ya existe:', ['id' => $productoExistente->idProducto]);
                return response()->json([
                    'message' => 'Error: Ya existe un producto con el mismo nombre.',
                    'productoExistente' => $productoExistente,
                ], 409); // 409 Conflict es un código HTTP adecuado para este caso
            }

            // Validar el request
            Log::info('Validando request');
           // Actualizar la validación en el método
            $request->validate([
                'nombreProducto' => 'required',
                'descripcion' => 'nullable',
                'estado' => 'required',
                'idCategoria' => 'required|exists:categorias,idCategoria',
                'modelos' => 'required|array',
                'modelos.*.nombreModelo' => 'required',
                'modelos.*.imagenes' => 'required|array',
                'modelos.*.imagenes.*' => [
                    'required',
                    'file',
                    'mimes:jpeg,jpg,png,avif,webp',
                    'max:5120',
                ],
                'caracteristicas' => 'nullable|string|max:65535', // Para campo TEXT
            ]);

            Log::info('Request validado correctamente');

            DB::beginTransaction();
            Log::info('Iniciando transacción de base de datos');

           // Crear el producto
            Log::info('Creando producto');
            $producto = Producto::create([
                'nombreProducto' => $request->nombreProducto,
                'descripcion' => $request->descripcion,
                'estado' => $request->estado,
                'idCategoria' => $request->idCategoria,
            ]);
            Log::info('Producto creado:', ['id' => $producto->idProducto]);

            // Manejar características del producto
            if ($request->has('caracteristicas') && !empty($request->caracteristicas)) {
                Log::info('Procesando características del producto');
                CaracteristicaProducto::create([
                    'idProducto' => $producto->idProducto,
                    'caracteristicas' => $request->caracteristicas
                ]);
                Log::info('Características agregadas al producto');
            } else {
                // Agregar característica por defecto
                CaracteristicaProducto::create([
                    'idProducto' => $producto->idProducto,
                    'caracteristicas' => 'Sin características disponibles'
                ]);
                Log::info('Agregada característica por defecto');
            }

            // Crear modelos y manejar su stock
            foreach ($request->modelos as $modeloData) {
                Log::info('Creando modelo');
                $modelo = Modelo::create([
                    'idProducto' => $producto->idProducto,
                    'nombreModelo' => $modeloData['nombreModelo'],
                    'urlModelo' => null,
                    'estado' => 'activo'
                ]);
                Log::info('Modelo creado:', ['id' => $modelo->idModelo]);

          // Procesar las imágenes del modelo
          if (isset($modeloData['imagenes'])) {
            Log::info('Procesando imágenes del modelo');
            foreach ($modeloData['imagenes'] as $imagen) {
                // Obtener extensión
                $extension = strtolower($imagen->getClientOriginalExtension());
                
                // Obtener nombre original y limpiarlo AGRESIVAMENTE
                $nombreOriginal = pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME);
                
                // 1. Convertir a minúsculas
                $nombreLimpio = strtolower($nombreOriginal);
                // 2. Reemplazar + por espacios
                $nombreLimpio = str_replace('+', ' ', $nombreLimpio);
                // 3. Eliminar TODOS los caracteres especiales y números del inicio
                $nombreLimpio = preg_replace('/^[0-9_]+/', '', $nombreLimpio);
                // 4. Eliminar ABSOLUTAMENTE TODO lo que no sea letras, números o espacios
                $nombreLimpio = preg_replace('/[^a-z0-9\s]/', '', $nombreLimpio);
                // 5. Reemplazar espacios múltiples por uno solo
                $nombreLimpio = preg_replace('/\s+/', ' ', $nombreLimpio);
                // 6. Trim espacios
                $nombreLimpio = trim($nombreLimpio);
                // 7. Reemplazar espacios por guiones
                $nombreLimpio = str_replace(' ', '-', $nombreLimpio);
                
                // Si después de toda la limpieza el nombre está vacío, usar un nombre genérico
                if (empty($nombreLimpio)) {
                    $nombreLimpio = 'imagen';
                }
                
                // Crear nombre final con timestamp para evitar duplicados
                $nombreFinal = $nombreLimpio . '-' . time() . '.' . $extension;
                
                Log::info('Nombre original: ' . $imagen->getClientOriginalName());
                Log::info('Nombre limpio final: ' . $nombreFinal);

                $rutaImagen = 'imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $modelo->nombreModelo . '/' . $nombreFinal;

                // Resto del código para guardar la imagen...
                if (!Storage::disk('public')->exists('imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $modelo->nombreModelo)) {
                    Storage::disk('public')->makeDirectory('imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $modelo->nombreModelo);
                }

                Storage::disk('public')->putFileAs(
                    'imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $modelo->nombreModelo,
                    $imagen,
                    $nombreFinal
                );

                ImagenModelo::create([
                    'idModelo' => $modelo->idModelo,
                    'urlImagen' => $rutaImagen,
                    'descripcion' => 'Imagen del modelo ' . $modelo->nombreModelo,
                ]);
            }
        }

            }

            DB::commit();
            Log::info('Transacción completada correctamente');

            // Registrar la acción en el log
            $usuarioId = auth()->id();
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario agregó el producto: $producto->nombreProducto";
            $this->agregarLog($usuarioId, $accion);

            Log::info('Producto agregado correctamente');

            return response()->json([
                'message' => 'Producto agregado correctamente',
                'producto' => $producto,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación:', ['errors' => $e->errors()]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar el producto: ' . $e->getMessage());
            Log::error('Trace del error:', ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al agregar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function listarProductos(Request $request)
    {
        // Obtener los parámetros de la solicitud
        $categoriaId = $request->input('categoria');
        $texto = $request->input('texto');
        $idProducto = $request->input('idProducto');
        $perPage = $request->input('perPage', 6);
        $filters = json_decode($request->input('filters', '{}'), true);
    
        // Construir la consulta base
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria',
            'caracteristicasProducto', // Cambiar a la relación correcta
            'modelos' => function($query) {
                $query->with(['imagenes:idImagen,urlImagen,idModelo']); // Eliminado el filtro por estado
            }
        ])->select('productos.*'); // Seleccionar todos los campos de productos
    
        // Aplicar filtros
        if ($idProducto) {
            $query->where('idProducto', $idProducto);
        }
    
        if ($categoriaId) {
            $query->where('idCategoria', $categoriaId);
        }
    
        if ($texto) {
            $query->where('nombreProducto', 'like', '%' . $texto . '%');
        }
    
        if (!empty($filters)) {
            if (isset($filters['nombreProducto']) && $filters['nombreProducto'] !== '') {
                $query->where('nombreProducto', 'like', '%' . $filters['nombreProducto'] . '%');
            }
            if (isset($filters['descripcion']) && $filters['descripcion'] !== '') {
                $query->where('descripcion', 'like', '%' . $filters['descripcion'] . '%');
            }
            if (isset($filters['estado']) && $filters['estado'] !== '') {
                $query->where('estado', $filters['estado']);
            }
        }
    
        // Paginar resultados
        $productos = $query->paginate($perPage);
    
        // Transformar datos
        $productosData = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion ?: 'N/A',
                'estado' => $producto->estado,
                'idCategoria' => $producto->idCategoria,
                'categoria' => [
                    'idCategoria' => $producto->categoria ? $producto->categoria->idCategoria : null,
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría'
                ],
                'caracteristicas' => $producto->caracteristicasProducto ? $producto->caracteristicasProducto->caracteristicas : '',
                'modelos' => $producto->modelos->map(function($modelo) {
                    return [
                        'idModelo' => $modelo->idModelo,
                        'nombreModelo' => $modelo->nombreModelo,
                        'imagenes' => $modelo->imagenes->map(function($imagen) {
                            return [
                                'idImagen' => $imagen->idImagen,
                                'urlImagen' => $imagen->urlImagen
                            ];
                        })
                    ];
                })
            ];
        });
    
        return response()->json([
            'data' => $productosData,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ], 200);
    }

        /**
     * @OA\Get(
     *     path="/api/listarProductosCatalogo",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar productos del catálogo con filtros y paginación",
     *     description="Permite a un superadministrador listar productos del catálogo con opciones de filtrado por nombre, categoría y paginación. Solo se incluyen productos y categorías con estado 'activo'.",
     *     operationId="listarProductosCatalogo",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="nombre",
     *         in="query",
     *         description="Filtrar productos por nombre (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Producto Ejemplo")
     *     ),
     *     @OA\Parameter(
     *         name="categoria",
     *         in="query",
     *         description="Filtrar productos por nombre de categoría (coincidencia exacta)",
     *         required=false,
     *         @OA\Schema(type="string", example="Electrónica")
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Número de productos por página (por defecto 6, máximo 100)",
     *         required=false,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de productos obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idProducto", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto Ejemplo"),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción del producto"),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="modelos", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="idModelo", type="integer", example=1),
     *                     @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo"),
     *                     @OA\Property(property="imagenes", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="idImagen", type="integer", example=1),
     *                         @OA\Property(property="urlImagen", type="string", example="http://example.com/imagen.jpg")
     *                     ))
     *                 ))
     *             )),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=6),
     *             @OA\Property(property="total", type="integer", example=30)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(property="errors", type="object", example={
     *                 "nombre": {"El campo nombre debe ser una cadena de texto."},
     *                 "perPage": {"El campo perPage debe ser un número entero."}
     *             })
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function listarProductosCatalogo(Request $request)
    {
        // Validar parámetros de entrada
        $request->validate([
            'nombre' => 'nullable|string|max:255', // Filtro por nombre
            'categoria' => 'nullable|string|max:255', // Filtro por categoría
            'perPage' => 'nullable|integer|min:1|max:100', // Paginación
        ]);

        // Obtener parámetros validados
        $nombre = $request->input('nombre', '');  // Nombre del producto (si está presente)
        $categoriaNombre = $request->input('categoria', '');  // Nombre de la categoría (si está presente)
        $perPage = $request->input('perPage', 6);  // Cantidad de productos por página

        // Construir la consulta de productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria,estado',  // Relación con la categoría (incluir estado)
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo'  // Relación con las imágenes
                ]);
            }
        ]);

        // Filtrar solo productos activos y de categorías activas
        $query->where('estado', 'activo')
            ->whereHas('categoria', function($q) {
                $q->where('estado', 'activo');  // Filtrar categorías activas
            });

        if (!empty($nombre)) {
            $query->where('nombreProducto', 'LIKE', "%{$nombre}%");  // Búsqueda parcial
        }
            
        // Filtrar por categoría (unión directa para evitar productos sin categoría)
        if (!empty($categoriaNombre)) {
            $query->whereHas('categoria', function($q) use ($categoriaNombre) {
                $q->where('nombreCategoria', '=', $categoriaNombre)
                ->where('estado', 'activo');  // Coincidencia exacta con 'nombreCategoria' y estado activo
            });
        }

        // Ejecutar paginación
        $productos = $query->paginate($perPage);

        // Formatear la respuesta
        $productosData = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion ?: 'N/A',
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                'modelos' => $producto->modelos->map(function($modelo) {
                    return [
                        'idModelo' => $modelo->idModelo,
                        'nombreModelo' => $modelo->nombreModelo,
                        'imagenes' => $modelo->imagenes->map(function($imagen) {
                            return [
                                'idImagen' => $imagen->idImagen,
                                'urlImagen' => $imagen->urlImagen
                            ];
                        })
                    ];
                })
            ];
        });

        // Retornar JSON con productos filtrados
        return response()->json([
            'data' => $productosData,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ], 200);
    }


    public function listarProductosFavoritos(Request $request)
    {
        try {
            // Obtener parámetros validados
            $favoritos = $request->input('favoritos', []);
    
            // Construir la consulta de productos con relaciones
            $query = Producto::with([
                'categoria:idCategoria,nombreCategoria,estado',
                'modelos' => function($query) {
                    $query->with([
                        'imagenes:idImagen,urlImagen,idModelo'
                    ]);
                }
            ]);
    
            // Filtrar solo productos activos, de categorías activas y que estén en la lista de favoritos
            $query->where('estado', 'activo')
                ->whereHas('categoria', function($q) {
                    $q->where('estado', 'activo');
                })
                ->whereIn('nombreProducto', $favoritos);
    
            // Obtener todos los productos sin paginación
            $productos = $query->get();
    
            // Formatear la respuesta
            $productosData = $productos->map(function($producto) {
                return [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion ?: 'N/A',
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'modelos' => $producto->modelos->map(function($modelo) {
                        return [
                            'idModelo' => $modelo->idModelo,
                            'nombreModelo' => $modelo->nombreModelo,
                            'imagenes' => $modelo->imagenes->map(function($imagen) {
                                return [
                                    'idImagen' => $imagen->idImagen,
                                    'urlImagen' => $imagen->urlImagen
                                ];
                            })
                        ];
                    })
                ];
            });
    
            // Retornar JSON con todos los productos
            return response()->json([
                'data' => $productosData,
            ], 200);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Manejar errores de validación y devolver un JSON
            return response()->json([
                'error' => 'Error de validación',
                'messages' => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            // Manejar otros errores y devolver un JSON
            return response()->json([
                'error' => 'Error en el servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function listarProductosRelacionados($productoId)
    {
        try {
            // Verificar si el productoId está presente
            if (!$productoId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto ID es requerido'
                ], 400);
            }
    
            // Buscar el producto seleccionado
            $productoSeleccionado = Producto::find($productoId);
            if (!$productoSeleccionado) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado'
                ], 404);
            }
    
            // Obtener la categoría del producto seleccionado
            $categoriaId = $productoSeleccionado->idCategoria;
    
            // Consulta para obtener productos relacionados
            $query = Producto::with([
                'categoria:idCategoria,nombreCategoria,estado',
                'modelos' => function($query) {
                    $query->with([
                        'imagenes:idImagen,urlImagen,idModelo'
                    ]);
                }
            ]);
    
            $query->where('estado', 'activo')
                ->whereHas('categoria', function($q) {
                    $q->where('estado', 'activo');
                })
                ->where('idCategoria', $categoriaId)
                ->where('idProducto', '!=', $productoId);  // Excluir el producto actual
    
            // Obtener los productos relacionados
            $productos = $query->get();
    
            // Si no hay productos relacionados, devolver un array vacío
            if ($productos->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'productos' => []
                ]);
            }
    
            // Formatear los datos de los productos
            $productosData = $productos->map(function($producto) {
                return [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion ?: 'N/A',
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'modelos' => $producto->modelos->map(function($modelo) {
                        return [
                            'idModelo' => $modelo->idModelo,
                            'nombreModelo' => $modelo->nombreModelo,
                            'imagenes' => $modelo->imagenes->map(function($imagen) {
                                return [
                                    'idImagen' => $imagen->idImagen,
                                    'urlImagen' => $imagen->urlImagen
                                ];
                            })
                        ];
                    })
                ];
            });
    
            return response()->json([
                'status' => 'success',
                'productos' => $productosData
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener productos relacionados: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener productos relacionados. Por favor intente nuevamente más tarde.'
            ], 500);
        }
    }
    
    

    /**
     * @OA\Post(
     *     path="/api/editarModeloyImagen/{idModelo}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Editar un modelo y sus imágenes",
     *     description="Permite a un superadministrador editar un modelo existente, incluyendo su nombre, descripción y la gestión de imágenes (añadir nuevas o reemplazar existentes).",
     *     operationId="editarModeloYImagen",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idModelo",
     *         in="path",
     *         required=true,
     *         description="ID del modelo que se desea editar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del modelo y sus imágenes",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombreModelo"},
     *                 @OA\Property(property="nombreModelo", type="string", example="Modelo Actualizado"),
     *                 @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción actualizada"),
     *                 @OA\Property(
     *                     property="nuevasImagenes",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary", description="Nuevas imágenes para el modelo")
     *                 ),
     *                 @OA\Property(
     *                     property="idImagenesReemplazadas",
     *                     type="array",
     *                     @OA\Items(type="integer", example=1),
     *                     description="IDs de las imágenes existentes que se desean reemplazar"
     *                 ),
     *                 @OA\Property(
     *                     property="imagenesReemplazadas",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary", description="Nuevas imágenes para reemplazar las existentes")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modelo e imágenes actualizados correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo e imágenes actualizados correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Modelo no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "nombreModelo": {"El campo nombreModelo es obligatorio."},
     *                     "nuevasImagenes.0": {"El archivo debe ser una imagen válida."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar el modelo e imágenes"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function editarModeloYImagen(Request $request, $idModelo)
    {
        $modelo = Modelo::findOrFail($idModelo);
        $nombreModeloAntiguo = $modelo->nombreModelo;
        $nombreProducto = $modelo->producto->nombreProducto;

        $urlModelo = 'imagenes/productos/' . $nombreProducto . '/modelos/' . $request->nombreModelo;

        $modelo->update([
            'nombreModelo' => $request->nombreModelo,
            'descripcion' => $request->descripcion,
            'urlModelo' => $urlModelo,
        ]);

        // Registrar la acción de edición
        $usuarioId = auth()->id();
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario editó el modelo: $nombreModeloAntiguo a $modelo->nombreModelo";
        $this->agregarLog($usuarioId, $accion);

        // Función helper para sanitizar nombres de archivo
        $sanitizarNombre = function($nombreOriginal) {
            // 1. Convertir a minúsculas
            $nombreLimpio = strtolower($nombreOriginal);
            // 2. Reemplazar + por espacios
            $nombreLimpio = str_replace('+', ' ', $nombreLimpio);
            // 3. Eliminar números y caracteres especiales del inicio
            $nombreLimpio = preg_replace('/^[0-9_]+/', '', $nombreLimpio);
            // 4. Eliminar todos los caracteres especiales
            $nombreLimpio = preg_replace('/[^a-z0-9\s]/', '', $nombreLimpio);
            // 5. Reemplazar espacios múltiples por uno solo
            $nombreLimpio = preg_replace('/\s+/', ' ', $nombreLimpio);
            // 6. Trim espacios
            $nombreLimpio = trim($nombreLimpio);
            // 7. Reemplazar espacios por guiones
            $nombreLimpio = str_replace(' ', '-', $nombreLimpio);
            
            return empty($nombreLimpio) ? 'imagen' : $nombreLimpio;
        };

        // Procesar nuevas imágenes
        if ($request->hasFile('nuevasImagenes')) {
            foreach ($request->file('nuevasImagenes') as $imagen) {
                $extension = strtolower($imagen->getClientOriginalExtension());
                $nombreOriginal = pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME);
                $nombreLimpio = $sanitizarNombre($nombreOriginal);
                $nombreArchivo = $nombreLimpio . '-' . time() . '.' . $extension;
                
                $ruta = "imagenes/productos/{$nombreProducto}/modelos/{$modelo->nombreModelo}/{$nombreArchivo}";
                
                // Verificar si existe una imagen con el mismo nombre
                $imagenExistente = ImagenModelo::where('urlImagen', $ruta)->first();

                if ($imagenExistente) {
                    if (Storage::disk('public')->exists($imagenExistente->urlImagen)) {
                        Storage::disk('public')->delete($imagenExistente->urlImagen);
                    }
                    $imagenExistente->delete();
                }

                // Guardar la nueva imagen
                $imagen->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$modelo->nombreModelo}", $nombreArchivo, 'public');

                ImagenModelo::create([
                    'urlImagen' => $ruta,
                    'idModelo' => $modelo->idModelo,
                    'descripcion' => 'Nueva imagen añadida',
                ]);

                Log::info("Imagen procesada - Original: " . $imagen->getClientOriginalName() . " -> Nuevo: " . $nombreArchivo);
            }
        }

        // Reemplazo de imágenes existentes
        if ($request->has('idImagenesReemplazadas')) {
            foreach ($request->idImagenesReemplazadas as $index => $idImagen) {
                $imagenModelo = ImagenModelo::findOrFail($idImagen);
                $rutaAntigua = $imagenModelo->urlImagen;

                if ($request->hasFile("imagenesReemplazadas.{$index}")) {
                    $imagenReemplazada = $request->file("imagenesReemplazadas.{$index}");
                    
                    // Sanitizar nombre del archivo reemplazado
                    $extension = strtolower($imagenReemplazada->getClientOriginalExtension());
                    $nombreOriginal = pathinfo($imagenReemplazada->getClientOriginalName(), PATHINFO_FILENAME);
                    $nombreLimpio = $sanitizarNombre($nombreOriginal);
                    $nombreArchivoNuevo = $nombreLimpio . '-' . time() . '.' . $extension;

                    if (Storage::disk('public')->exists($rutaAntigua)) {
                        Storage::disk('public')->delete($rutaAntigua);
                    }

                    $rutaNueva = "imagenes/productos/{$nombreProducto}/modelos/{$modelo->nombreModelo}/{$nombreArchivoNuevo}";

                    $imagenReemplazada->storeAs(
                        "imagenes/productos/{$nombreProducto}/modelos/{$modelo->nombreModelo}",
                        $nombreArchivoNuevo,
                        'public'
                    );

                    $imagenModelo->update(['urlImagen' => $rutaNueva]);
                    
                    Log::info("Imagen reemplazada - Original: " . $imagenReemplazada->getClientOriginalName() . " -> Nuevo: " . $nombreArchivoNuevo);
                }
            }
        }

        return response()->json(['message' => 'Modelo e imágenes actualizados correctamente']);
    }

        /**
     * @OA\Post(
     *     path="/api/agregarModelo",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar un nuevo modelo a un producto",
     *     description="Permite a un superadministrador agregar un nuevo modelo a un producto existente. Se valida que el producto exista y se genera una ruta para el modelo basada en el nombre del producto.",
     *     operationId="agregarModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del modelo a agregar",
     *         @OA\JsonContent(
     *             required={"idProducto", "nombreModelo"},
     *             @OA\Property(property="idProducto", type="integer", example=1),
     *             @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Modelo agregado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="idModelo", type="integer", example=1),
     *             @OA\Property(property="idProducto", type="integer", example=1),
     *             @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo"),
     *             @OA\Property(property="urlModelo", type="string", example="imagenes/productos/Producto Ejemplo/modelos/Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "idProducto": {"El campo idProducto es obligatorio."},
     *                     "nombreModelo": {"El campo nombreModelo es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al agregar el modelo"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function agregarModelo(Request $request)
    {
        $request->validate([
            'idProducto' => 'required|exists:productos,idProducto',
            'nombreModelo' => 'required|string|max:255',
        ]);
        
        // Obtener el producto para usar su nombre
        $producto = Producto::findOrFail($request->idProducto);
        
        // Construir la ruta del modelo usando el nombre del producto
        $urlModelo = 'imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $request->nombreModelo;
        
        $modelo = Modelo::create([
            'idProducto' => $request->idProducto,
            'nombreModelo' => $request->nombreModelo,
            'urlModelo' => $urlModelo
        ]);

        return response()->json($modelo, 201);
    }


    /**
     * @OA\Delete(
     *     path="/api/EliminarModelo/{idModelo}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Eliminar un modelo y sus imágenes asociadas",
     *     description="Permite a un superadministrador eliminar un modelo y todas sus imágenes asociadas, tanto de la base de datos como del almacenamiento. Además, elimina el directorio donde se almacenaban las imágenes.",
     *     operationId="EliminarModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idModelo",
     *         in="path",
     *         required=true,
     *         description="ID del modelo que se desea eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modelo y sus imágenes eliminados correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo y sus imágenes eliminados correctamente"),
     *             @OA\Property(property="directory_deleted", type="string", example="imagenes/productos/Producto Ejemplo/modelos/Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Modelo no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al eliminar el modelo"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function EliminarModelo($idModelo)
    {
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Obtener el modelo
            $modelo = Modelo::findOrFail($idModelo);
            
            // Variable para almacenar la ruta del directorio
            $directorioABorrar = null;
            
            // Primer intento: Verificar si el modelo tiene urlModelo
            if (!empty($modelo->urlModelo)) {
                $directorioABorrar = $modelo->urlModelo;
            } 
            // Segundo intento: Buscar la ruta en las imágenes relacionadas
            else {
                $primeraImagen = ImagenModelo::where('idModelo', $idModelo)
                                            ->whereNotNull('urlImagen')
                                            ->first();
                                            
                if ($primeraImagen) {
                    // Remover 'public/' si existe y obtener el directorio padre
                    $path = str_replace('public/', '', $primeraImagen->urlImagen);
                    $directorioABorrar = dirname($path);
                }
            }
            
            // Eliminar todas las imágenes asociadas de la BD
            ImagenModelo::where('idModelo', $idModelo)->delete();
            
            // Eliminar el directorio si se encontró una ruta válida
            if ($directorioABorrar && Storage::disk('public')->exists($directorioABorrar)) {
                Storage::disk('public')->deleteDirectory($directorioABorrar);
            }
            
            // Eliminar el modelo físicamente de la base de datos
            $modelo->delete();
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'message' => 'Modelo, imágenes y directorio relacionados eliminados correctamente',
                'directory_deleted' => $directorioABorrar ?? 'No se encontró directorio para borrar'
            ]);
            
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al eliminar el modelo',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/eliminarImagenModelo/{idImagen}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Eliminar una imagen de un modelo",
     *     description="Permite a un superadministrador eliminar una imagen asociada a un modelo, tanto físicamente del almacenamiento como de la base de datos. Además, registra la acción en el log del sistema.",
     *     operationId="eliminarImagenModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idImagen",
     *         in="path",
     *         required=true,
     *         description="ID de la imagen que se desea eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Imagen eliminada correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Imagen eliminada correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Imagen no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Imagen no encontrada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al eliminar la imagen"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function eliminarImagenModelo($idImagen)
    {
        // Buscar la imagen en la base de datos
        $imagenModelo = ImagenModelo::findOrFail($idImagen);
        $rutaImagen = $imagenModelo->urlImagen;
    
        // Obtener el nombre del modelo y producto relacionados con la imagen
        $modelo = $imagenModelo->modelo;
        $producto = $modelo->producto;
        $nombreModelo = $modelo->nombreModelo;
        $nombreProducto = $producto->nombreProducto;
    
        // Eliminar archivo físico si existe (especificando el disco 'public')
        if (Storage::disk('public')->exists($rutaImagen)) {
            Storage::disk('public')->delete($rutaImagen);
            Log::info("Imagen eliminada correctamente de la ruta: {$rutaImagen}");
            
            // Registrar la acción en el log
            $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario eliminó la imagen del modelo $nombreModelo del producto $nombreProducto";
            $this->agregarLog($usuarioId, $accion);
        } else {
            Log::warning("La imagen no existe en el almacenamiento: {$rutaImagen}");
        }
    
        // Eliminar el registro de la imagen de la base de datos
        $imagenModelo->delete();
        Log::info("Registro eliminado de la base de datos: {$imagenModelo->idImagen}");
    
        return response()->json(['message' => 'Imagen eliminada correctamente']);
    }
    

    /**
     * @OA\Put(
     *     path="/api/actualizarProducto/{idProducto}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Actualizar un producto existente",
     *     description="Permite a un superadministrador actualizar los datos de un producto existente, incluyendo su nombre y descripción. Además, registra la acción en el log del sistema.",
     *     operationId="actualizarProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idProducto",
     *         in="path",
     *         required=true,
     *         description="ID del producto que se desea actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del producto que se desean actualizar",
     *         @OA\JsonContent(
     *             required={"nombreProducto"},
     *             @OA\Property(property="nombreProducto", type="string", example="Producto Actualizado"),
     *             @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción actualizada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Producto actualizado correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar el producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function actualizarProducto(Request $request, $idProducto)
    {
        try {
            DB::beginTransaction();
    
            // Validar la existencia del producto
            $producto = Producto::findOrFail($idProducto);
            
            // Guardar el nombre antiguo para el log
            $nombreProductoAntiguo = $producto->nombreProducto;
            
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'nombreProducto' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'idCategoria' => 'required|exists:categorias,idCategoria',
                'caracteristicas' => 'nullable|string',
                'estado' => 'nullable|in:activo,inactivo'
            ]);
    
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            // Actualizar datos del producto
            $producto->update([
                'nombreProducto' => $request->nombreProducto,
                'descripcion' => $request->descripcion,
                'idCategoria' => $request->idCategoria,
                'estado' => $request->estado ?? $producto->estado
            ]);
            
            // Actualizar o crear características
            if ($request->has('caracteristicas')) {
                CaracteristicaProducto::updateOrCreate(
                    ['idProducto' => $idProducto],
                    ['caracteristicas' => $request->caracteristicas]
                );
            }
            
            // Registrar en el log
            $usuarioId = auth()->id();
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario actualizó el producto: $nombreProductoAntiguo a {$producto->nombreProducto}";
            $this->agregarLog($usuarioId, $accion);
    
            DB::commit();
            
            return response()->json([
                'message' => 'Producto actualizado correctamente',
                'producto' => $producto->load(['caracteristicasProducto', 'categoria'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al actualizar el producto',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
        /**
     * @OA\Put(
     *     path="/api/cambiarEstadoProducto/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Cambiar el estado de un producto",
     *     description="Permite a un superadministrador cambiar el estado de un producto. Además, registra la acción en el log del sistema.",
     *     operationId="cambiarEstadoProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del producto cuyo estado se desea cambiar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nuevo estado del producto",
     *         @OA\JsonContent(
     *             required={"estado"},
     *             @OA\Property(property="estado", type="string", example="activo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estado actualizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "estado": {"El campo estado es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al cambiar el estado del producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function cambiarEstadoProducto(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
    
        // Guardar el estado anterior para el log
        $estadoAnterior = $producto->estado;
    
        // Actualizar el estado del producto
        $producto->estado = $request->estado;
        $producto->save();
    
        // Registrar la acción de cambio de estado en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario cambió el estado del producto {$producto->nombreProducto} de '$estadoAnterior' a '{$producto->estado}'";
        $this->agregarLog($usuarioId, $accion);
    
        return response()->json(['message' => 'Estado actualizado']);
    }
    

        /**
     * @OA\Post(
     *     path="/api/categorias",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar una nueva categoría",
     *     description="Permite a un superadministrador agregar una nueva categoría, incluyendo su nombre, descripción e imagen. Además, registra la acción en el log del sistema.",
     *     operationId="agregarCategorias",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos de la categoría y su imagen",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombreCategoria", "imagen"},
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", nullable=true, example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="imagen", type="string", format="binary", description="Imagen de la categoría (formato: jpeg, png, jpg, gif, svg, tamaño máximo: 5MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categoría agregada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Categoría agregada exitosamente"),
     *             @OA\Property(
     *                 property="categoria",
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="imagen", type="string", example="imagenes/categorias/Electrónica/imagen.jpg"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "nombreCategoria": {"El campo nombreCategoria es obligatorio."},
     *                     "imagen": {"El campo imagen es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al agregar la categoría"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function agregarCategorias(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'nombreCategoria' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:60',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5000', // Validación de imagen
        ]);
    
        // Obtener el nombre de la categoría
        $nombreCategoria = $request->input('nombreCategoria');
    
        // Verificar si ya existe una categoría con el mismo nombre
        $categoriaExistente = Categoria::where('nombreCategoria', $nombreCategoria)->first();
        if ($categoriaExistente) {
            return response()->json([
                'message' => 'Error: Ya existe una categoría con el mismo nombre.',
                'categoriaExistente' => $categoriaExistente,
            ], 409); // 409 Conflict
        }
    
        $descripcion = $request->input('descripcion', null);
    
        // Guardar la imagen en el directorio correspondiente
        $imagen = $request->file('imagen');
        $rutaImagen = 'imagenes/categorias/' . $nombreCategoria . '/' . $imagen->getClientOriginalName();
        Storage::disk('public')->putFileAs('imagenes/categorias/' . $nombreCategoria, $imagen, $imagen->getClientOriginalName());
    
        // Crear la categoría en la base de datos
        $categoria = Categoria::create([
            'nombreCategoria' => $nombreCategoria,
            'descripcion' => $descripcion,
            'imagen' => $rutaImagen, // Guardar la ruta de la imagen
            'estado' => 'activo'
        ]);
    
        return response()->json([
            'message' => 'Categoría agregada exitosamente',
            'categoria' => $categoria
        ], 200);
    }

    public function obtenerCategoriasProducto(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $limit = $request->input('limit', 5); // Límite de elementos por página, por defecto 5
    
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $estado = $request->input('estado', '');
        $searchTerm = $request->input('searchTerm', '');
    
        // Construir la consulta
        $query = Categoria::query();
    
        // Aplicar filtros
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($estado) {
            $query->where('estado', 'like', "%{$estado}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('descripcion', 'like', "%{$searchTerm}%")
                  ->orWhere('estado', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $categorias = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $categorias->items(), // Datos de la página actual
            'total' => $categorias->total(), // Total de registros
            'page' => $categorias->currentPage(), // Página actual
            'totalPages' => $categorias->lastPage(), // Total de páginas
        ]);
    }


        /**
     * @OA\Get(
     *     path="/api/obtenerCategorias",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Obtener categorías con filtros y paginación",
     *     description="Permite a un superadministrador obtener una lista de categorías con opciones de filtrado por ID, nombre, descripción, estado y búsqueda general. Además, incluye paginación.",
     *     operationId="obtenerCategorias",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página (por defecto 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Límite de elementos por página (por defecto 5)",
     *         required=false,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Parameter(
     *         name="idCategoria",
     *         in="query",
     *         description="Filtrar por ID de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="1")
     *     ),
     *     @OA\Parameter(
     *         name="nombreCategoria",
     *         in="query",
     *         description="Filtrar por nombre de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Electrónica")
     *     ),
     *     @OA\Parameter(
     *         name="descripcion",
     *         in="query",
     *         description="Filtrar por descripción de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Categoría de productos electrónicos")
     *     ),
     *     @OA\Parameter(
     *         name="estado",
     *         in="query",
     *         description="Filtrar por estado de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="activo")
     *     ),
     *     @OA\Parameter(
     *         name="searchTerm",
     *         in="query",
     *         description="Búsqueda general en ID, nombre, descripción y estado de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Electrónica")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )),
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="page", type="integer", example=1),
     *             @OA\Property(property="totalPages", type="integer", example=20)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al obtener las categorías"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function obtenerCategorias(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $limit = $request->input('limit', 5); // Límite de elementos por página, por defecto 5
    
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $estado = $request->input('estado', '');
        $searchTerm = $request->input('searchTerm', '');
    
        // Construir la consulta
        $query = Categoria::query();
    
        // Aplicar filtros
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($estado) {
            $query->where('estado', 'like', "%{$estado}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('descripcion', 'like', "%{$searchTerm}%")
                  ->orWhere('estado', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $categorias = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $categorias->items(), // Datos de la página actual
            'total' => $categorias->total(), // Total de registros
            'page' => $categorias->currentPage(), // Página actual
            'totalPages' => $categorias->lastPage(), // Total de páginas
        ]);
    }


        /**
     * @OA\Get(
     *     path="/api/listarCategorias",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar categorías con filtros y paginación",
     *     description="Permite a un superadministrador listar categorías con opciones de filtrado por ID, nombre, descripción y búsqueda general. Solo se incluyen categorías con estado 'activo'.",
     *     operationId="listarCategorias",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idCategoria",
     *         in="query",
     *         description="Filtrar por ID de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="1")
     *     ),
     *     @OA\Parameter(
     *         name="nombreCategoria",
     *         in="query",
     *         description="Filtrar por nombre de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Electrónica")
     *     ),
     *     @OA\Parameter(
     *         name="descripcion",
     *         in="query",
     *         description="Filtrar por descripción de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Categoría de productos electrónicos")
     *     ),
     *     @OA\Parameter(
     *         name="searchTerm",
     *         in="query",
     *         description="Búsqueda general en ID, nombre y descripción de categoría (búsqueda parcial)",
     *         required=false,
     *         @OA\Schema(type="string", example="Electrónica")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página (por defecto 1)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Número de elementos por página (por defecto 10)",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="imagen", type="string", example="http://example.com/imagen.jpg")
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="perPage", type="integer", example=10),
     *                 @OA\Property(property="currentPage", type="integer", example=1),
     *                 @OA\Property(property="lastPage", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function listarCategorias(Request $request)
    {
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $searchTerm = $request->input('searchTerm', '');

        // Parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $perPage = $request->input('perPage', 10); // Elementos por página, por defecto 10

        // Construir la consulta
        $query = Categoria::query();

        // Filtrar solo categorías con estado "activo"
        $query->where('estado', 'activo');

        // Aplicar filtros adicionales
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                ->orWhere('descripcion', 'like', "%{$searchTerm}%");
            });
        }

        // Seleccionar solo los campos necesarios
        $query->select('idCategoria', 'nombreCategoria', 'descripcion', 'imagen');

        // Paginar los resultados
        $categorias = $query->paginate($perPage, ['*'], 'page', $page);

        // Formatear la respuesta
        $response = [
            'data' => $categorias->items(), // Datos de la página actual
            'pagination' => [
                'total' => $categorias->total(), // Total de registros
                'perPage' => $categorias->perPage(), // Elementos por página
                'currentPage' => $categorias->currentPage(), // Página actual
                'lastPage' => $categorias->lastPage(), // Última página
            ],
        ];

        return response()->json($response);
    }


    /**
     * @OA\Get(
     *     path="/api/listarCategoriasFiltrador",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar categorías activas para filtrado",
     *     description="Permite a un superadministrador obtener una lista de todas las categorías con estado 'activo', seleccionando solo los campos necesarios (ID y nombre).",
     *     operationId="listarCategoriasFiltrador",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function listarCategoriasFiltrador(Request $request)
    {
        // Construir la consulta
        $query = Categoria::query();
    
        // Filtrar solo categorías con estado "activo"
        $query->where('estado', 'activo');
    
        // Seleccionar solo los campos necesarios
        $query->select('idCategoria', 'nombreCategoria');
    
        // Obtener los resultados, puedes paginarlos si es necesario
        $categorias = $query->get(); // Si no deseas paginación, usa ->get()
    
        // Formatear la respuesta
        $response = [
            'data' => $categorias, // Datos de las categorías
        ];
    
        return response()->json($response);
    }
    

       /**
     * @OA\Put(
     *     path="/api/cambiarEstadoCategoria/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Cambiar el estado de una categoría",
     *     description="Permite a un superadministrador cambiar el estado de una categoría entre 'activo' e 'inactivo'. Además, registra la acción en el log del sistema.",
     *     operationId="cambiarEstadoCategoria",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la categoría cuyo estado se desea cambiar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nuevo estado de la categoría",
     *         @OA\JsonContent(
     *             required={"estado"},
     *             @OA\Property(property="estado", type="string", enum={"activo", "inactivo"}, example="activo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estado actualizado correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoría no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Categoría no encontrada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "estado": {"El campo estado es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al cambiar el estado de la categoría"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function cambiarEstadoCategoria($id, Request $request)
    {
        // Validar el estado recibido
        $request->validate([
            'estado' => 'required|in:activo,inactivo',
        ]);
    
        // Buscar la categoría por ID
        $categoria = Categoria::findOrFail($id);
    
        // Obtener el ID del usuario autenticado desde el token
        $usuarioId = auth()->id(); // Obtiene el ID del usuario autenticado
    
        // Obtener el nombre completo del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
        // Cambiar el estado de la categoría
        $estadoAnterior = $categoria->estado;
        $categoria->estado = $request->estado;
        $categoria->save();
    
        // Definir la acción y mensaje para el log
        $accion = "$nombreUsuario cambió el estado de la categoría: {$categoria->nombreCategoria} de $estadoAnterior a {$categoria->estado}";
    
        // Llamada a la función agregarLog para registrar el log
        $this->agregarLog($usuarioId, $accion);
    
        // Devolver una respuesta exitosa
        return response()->json(['message' => 'Estado actualizado correctamente']);
    }
    

    /**
     * @OA\Put(
     *     path="/api/actualizarCategoria/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Actualizar una categoría existente",
     *     description="Permite a un superadministrador actualizar los datos de una categoría existente, incluyendo su nombre, descripción e imagen. Además, registra la acción en el log del sistema.",
     *     operationId="actualizarCategoria",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la categoría que se desea actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos de la categoría y su imagen",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="imagen", type="string", format="binary", description="Nueva imagen de la categoría (formato: jpeg, png, jpg, gif, svg, tamaño máximo: 5MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categoría actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Categoría actualizada exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="descripcion", type="string", example="Categoría de productos electrónicos"),
     *                 @OA\Property(property="imagen", type="string", example="imagenes/categorias/Electrónica/imagen.jpg"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoría no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Categoría no encontrada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al actualizar la categoría: Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function actualizarCategoria(Request $request, $id)
    {
        Log::info('Iniciando actualización de categoría', [
            'id' => $id,
            'request_all' => $request->all(),
            'files' => $request->hasFile('imagen') ? 'Tiene imagen' : 'No tiene imagen'
        ]);
    
        try {
            // Buscar la categoría
            $categoria = Categoria::where('idCategoria', $id)->first();
    
            if (!$categoria) {
                Log::error('Categoría no encontrada', ['id' => $id]);
                return response()->json(['error' => 'Categoría no encontrada'], 404);
            }
    
            // Obtener el usuario autenticado
            $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
            // Registro de la acción antes de la actualización
            $accionGeneral = "$nombreUsuario está actualizando los campos de la categoría: {$categoria->nombreCategoria}";
    
            // Actualización del nombre de la categoría
            if ($request->has('nombreCategoria') && $categoria->nombreCategoria !== $request->nombreCategoria) {
                $nombreCategoriaAntiguo = $categoria->nombreCategoria;
                $nuevoNombreCategoria = trim($request->nombreCategoria);
                $categoria->nombreCategoria = $nuevoNombreCategoria;
                $categoria->save();
    
                // Registro para el nombre de la categoría
                $accion = "$nombreUsuario actualizó el nombre de la categoría de '$nombreCategoriaAntiguo' a '$nuevoNombreCategoria'";
                $this->agregarLog($usuarioId, $accion);
                Log::info('Nombre de la categoría actualizado', ['nuevo_nombre' => $nuevoNombreCategoria]);
            }
    
            // Actualización de la descripción
            if ($request->has('descripcion') && $categoria->descripcion !== $request->descripcion) {
                $descripcionAntigua = $categoria->descripcion;
                $categoria->descripcion = $request->descripcion;
                $categoria->save();
    
                // Registro para la descripción
                $accion = "$nombreUsuario actualizó la descripción de la categoría de '$descripcionAntigua' a '{$categoria->descripcion}'";
                $this->agregarLog($usuarioId, $accion);
                Log::info('Descripción de la categoría actualizada', ['nueva_descripcion' => $categoria->descripcion]);
            }
    
             // Actualización de la imagen
            if ($request->hasFile('imagen')) {
                Log::info('Procesando nueva imagen');

                $oldFolder = $categoria->nombreCategoria;
                $newFolder = $categoria->nombreCategoria;
                $oldBasePath = "imagenes/categorias/{$oldFolder}";
                $newBasePath = "imagenes/categorias/{$newFolder}";

                // Eliminar la imagen anterior si existe
                if ($categoria->imagen && Storage::disk('public')->exists($categoria->imagen)) {
                    Storage::disk('public')->delete($categoria->imagen);
                    Log::info('Imagen anterior eliminada', ['path' => $categoria->imagen]);
                }

                // Guardar nueva imagen
                $imagePath = $request->file('imagen')->store("imagenes/categorias/{$newFolder}", 'public');
                $categoria->imagen = $imagePath;
                $categoria->save();

                // Registro para la imagen
                $nombreUsuario = auth()->user()->name ?? 'Usuario desconocido'; // Definir nombre de usuario
                $usuarioId = auth()->id() ?? null;
                $accion = "$nombreUsuario actualizó la imagen de la categoría.";
                $this->agregarLog($usuarioId, $accion);

                Log::info('Nueva imagen guardada', ['path' => $categoria->imagen]);
            }


            // Respuesta exitosa
            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => 'Error al actualizar la categoría: ' . $e->getMessage()
            ], 500);
        }
    }


        /**
     * @OA\Post(
     *     path="/api/usuarios/{id}/cambiar-password",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Cambiar la contraseña de un usuario",
     *     description="Permite a un superadministrador cambiar la contraseña de un usuario específico. Además, registra la acción en el log del sistema.",
     *     operationId="cambiarPassword",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del usuario cuya contraseña se desea cambiar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nueva contraseña del usuario",
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(property="password", type="string", format="password", example="nuevaContraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contraseña actualizada correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "password": {"El campo password es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al actualizar la contraseña")
     *         )
     *     )
     * )
     */
    public function cambiarPassword(Request $request, $id)
    {
        // Validar la entrada
        $request->validate([
            'password' => 'required|min:6', // Validar que la contraseña tenga al menos 6 caracteres
        ]);
    
        try {
            // Obtener el ID del usuario autenticado
            $usuarioId = auth()->id();
            
            // Obtener el usuario autenticado para obtener su nombre
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
    
            // Obtener el usuario al que se le cambiará la contraseña
            $usuarioCambiar = Usuario::findOrFail($id);
    
            // Actualizar la contraseña
            $usuarioCambiar->password = bcrypt($request->password);
            $usuarioCambiar->save();
    
            // Definir la acción para el log
            $accion = "$nombreUsuario cambió la contraseña del usuario: {$usuarioCambiar->nombres} {$usuarioCambiar->apellidos}";
    
            // Registrar el log
            $this->agregarLog($usuarioId, $accion);
    
            // Responder con éxito
            return response()->json([
                'success' => true,
                'message' => 'Contraseña actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            // En caso de error, responder con mensaje de error
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la contraseña',
            ], 500);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/password/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Actualizar la contraseña de un superadministrador",
     *     description="Permite a un superadministrador actualizar la contraseña de otro superadministrador. La contraseña debe tener al menos 8 caracteres y ser confirmada.",
     *     operationId="updatePasswordSuperAdmin",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del superadministrador cuya contraseña se desea actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos para actualizar la contraseña",
     *         @OA\JsonContent(
     *             required={"password", "password_confirmation"},
     *             @OA\Property(property="password", type="string", format="password", example="nuevaContraseña123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="nuevaContraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contraseña actualizada con éxito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Superadministrador no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Superadministrador no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "password": {"El campo password es obligatorio."},
     *                     "password_confirmation": {"El campo password confirmation es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar la contraseña"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function updatePasswordSuperAdmin(Request $request, $id)
    {
        // Validar entrada
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }

        try {
            $user = Usuario::findOrFail($id);

            // Actualizar contraseña
            $user->password = bcrypt($request->password);
            $user->save();

            return response()->json(['message' => 'Contraseña actualizada con éxito'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar la contraseña', 'error' => $e->getMessage()], 500);
        }
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

    public function obtenerCaracteristicas($idProducto)
    {
        $caracteristicas = CaracteristicaProducto::where('idProducto', $idProducto)->first();

        if (!$caracteristicas) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron características para este producto.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $caracteristicas,
        ]);
    }


}
