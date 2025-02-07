<?php

namespace App\Http\Controllers;


use App\Models\Usuario;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;


class DashboardController extends Controller
{
        /**
     * @OA\Get(
     *     path="/api/obtenerInfoAdmins",
     *     tags={"DASHBOARD CONTROLLER"},
     *     summary="Obtener estadísticas de usuarios",
     *     description="Permite obtener estadísticas sobre los usuarios, incluyendo el número de usuarios activos, inactivos y el total de usuarios.",
     *     operationId="obtenerInfoAdmins",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas de usuarios obtenidas exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="stats",
     *                 type="object",
     *                 @OA\Property(property="active_users", type="integer", example=10),
     *                 @OA\Property(property="inactive_users", type="integer", example=5),
     *                 @OA\Property(property="total_users", type="integer", example=15)
     *             ),
     *             @OA\Property(property="message", type="string", example="User statistics retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function obtenerInfoAdmins()
    {
        $activeAdmins = Usuario::where('rol', 'admin')->where('estado', 'activo')->count();
        $inactiveAdmins = Usuario::where('rol', 'admin')->where('estado', 'inactivo')->count();
        $totalUsers = $activeAdmins + $inactiveAdmins;

        return response()->json([
            'stats' => [
                'active_users' => $activeAdmins,
                'inactive_users' => $inactiveAdmins,
                'total_users' => $totalUsers
            ],
            'message' => 'User statistics retrieved successfully'
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/logs",
     *     tags={"DASHBOARD CONTROLLER"},
     *     summary="Obtener registros de logs",
     *     description="Permite obtener los registros de logs filtrados por fecha, usuario, acción y rol. El acceso a los logs está restringido según el rol del usuario autenticado.",
     *     operationId="getLogs",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="fecha_desde",
     *         in="query",
     *         description="Fecha de inicio para filtrar los logs (formato YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="fecha_hasta",
     *         in="query",
     *         description="Fecha de fin para filtrar los logs (formato YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="usuario",
     *         in="query",
     *         description="Nombre del usuario para filtrar los logs",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="accion",
     *         in="query",
     *         description="Acción para filtrar los logs",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="rol",
     *         in="query",
     *         description="Rol para filtrar los logs",
     *         required=false,
     *         @OA\Schema(type="string", enum={"admin", "superadmin"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de logs y usuarios filtrados",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="logs",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="idUsuario", type="integer", example=1),
     *                     @OA\Property(property="accion", type="string", example="login"),
     *                     @OA\Property(property="fecha", type="string", format="date-time", example="2023-10-01T12:00:00Z"),
     *                     @OA\Property(property="nombres", type="string", example="Juan Pérez"),
     *                     @OA\Property(property="rol", type="string", example="admin")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="usuarios",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="idUsuario", type="integer", example=1),
     *                     @OA\Property(property="nombres", type="string", example="Juan Pérez"),
     *                     @OA\Property(property="rol", type="string", example="admin")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function getLogs(Request $request)
    {
        // Obtener el rol del usuario autenticado
        $userRole = auth()->user()->rol;
    
        $query = LogUser::select('logs.*', 'usuarios.nombres', 'usuarios.rol')
            ->join('usuarios', 'logs.idUsuario', '=', 'usuarios.idUsuario');
    
        // Filtros
        if ($request->fecha_desde) {
            $query->whereDate('logs.fecha', '>=', $request->fecha_desde);
        }
        if ($request->fecha_hasta) {
            $query->whereDate('logs.fecha', '<=', $request->fecha_hasta);
        }
        if ($request->usuario) {
            $query->where('usuarios.nombres', 'LIKE', '%' . $request->usuario . '%');
        }
        if ($request->accion) {
            $query->where('logs.accion', 'LIKE', '%' . $request->accion . '%');
        }
    
        // Filtrar logs según el rol seleccionado en el filtro
        if ($request->rol) {
            $query->where('usuarios.rol', $request->rol); // Filtra por el rol seleccionado
        } else {
            // Si no se selecciona un rol, aplicar el filtro según el rol del usuario autenticado
            if ($userRole === 'admin') {
                $query->where('usuarios.rol', 'admin'); // Solo logs de admin
            } elseif ($userRole === 'superadmin') {
                $query->whereIn('usuarios.rol', ['superadmin', 'admin']); // Logs de superadmin y admin
            }
        }
    
        $logs = $query->orderBy('logs.fecha', 'desc')->paginate(10);
    
        // Obtener usuarios según el rol del usuario autenticado
        $usuariosQuery = Usuario::select('idUsuario', 'nombres', 'rol');
        if ($userRole === 'admin') {
            $usuariosQuery->where('rol', 'admin'); // Solo usuarios con rol admin
        } elseif ($userRole === 'superadmin') {
            $usuariosQuery->whereIn('rol', ['superadmin', 'admin']); // Usuarios con rol superadmin y admin
        }
    
        return response()->json([
            'logs' => $logs,
            'usuarios' => $usuariosQuery->get()
        ]);
    }

}
