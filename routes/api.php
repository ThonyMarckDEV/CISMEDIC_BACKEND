<?php


use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\DB;

//RUTAS
//================================================================================================
        //RUTAS  AUTH

        //RUTA PARA QUE LOS USUAIOS SE LOGEEN POR EL CONTROLLADOR AUTHCONTROLLER
        Route::post('/login', [AuthController::class, 'login']);

        Route::post('/send-message', [AuthController::class, 'sendContactEmail']);

        // Paso 1:Verificar DNI
        Route::post('/verificar-dni', [AuthController::class, 'verificarDni']);

        // Paso 2: Registro de datos personales
        Route::post('/registrar-usuario', [AuthController::class, 'registrarDatosPersonales']);

        // Ruta para verificar el token de correo
        Route::post('verificar-token', [AuthController::class, 'verificarCorreo']);

        // Ruta para enviar correo de verificación
        Route::post('/solicitar-restablecer-password', [AuthController::class, 'solicitarRestablecerPassword']);
        Route::post('/verificar-token-password', [AuthController::class, 'verificarTokenPassword']);
        Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword']);


    
        Route::post('/webhook/mercadopago', [PaymentController::class, 'recibirPago']);

        //CRON 

        Route::post('/procesar-citas-expiradas', [TaskController::class, 'procesarCitasExpiradas']);

        //Rutas para listar medicos
        Route::get('/listarStaff', [DoctorController::class, 'listDoctors']);
        Route::get('/listarespecialidadesStaff', [ClienteController::class, 'getEspecialidades']);
        Route::get('/perfildoctor/{idDoctor}', [DoctorController::class, 'obtenerPerfil']);

        Route::get('/especialidadeshome', [DoctorController::class, 'obtenerEspecialidades']);

//================================================================================================
    //RUTAS  AUTH PROTEGIDAS par todos los roles


    Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () {

        Route::post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('update-activity', [AuthController::class, 'updateLastActivity']);

        Route::post('/check-status', [AuthController::class, 'checkStatus']);

    });


    //RUTAS  AUTH para  roles admin y  cliente y superadmin

    Route::middleware(['auth.jwt', 'checkRolesMWACS'])->group(function () {

        // Rutas para Agendar Cita
        Route::get('/especialidades', [ClienteController::class, 'getEspecialidades']);
        Route::get('/doctores/especialidad/{idEspecialidad}', [ClienteController::class, 'getDoctoresPorEspecialidad']);
        Route::get('/horarios-disponibles/{idDoctor}/{fecha}', [ClienteController::class, 'getHorariosDisponibles']);
        Route::get('/doctor-schedule/{doctorId}/week', [ClienteController::class, 'getWeekSchedule']); 

    });

    //RUTAS  AUTH para  roles  superadmin y cliente

    Route::middleware(['auth.jwt', 'checkRolesSC'])->group(function () {

        Route::post('/cambiar-password', [ClienteController::class, 'cambiarPassword']);


    });




//================================================================================================
    //RUTAS PROTEGIDAS A
    // RUTAS PARA SUPERADMINISTRADOR VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:superadmin'])->group(function () { 

        //Rutas gestionar usuarios
        Route::post('/registrousuarios', [SuperAdminController::class, 'registrarDatosPersonales']);
        Route::get('/obtenerusuarios', [SuperAdminController::class, 'obtenerUsuarios']);
        Route::put('/actualizarusuarios/{id}', [SuperAdminController::class, 'actualizarUsuario']);
        Route::delete('/eliminarusuarios/{id}', [SuperAdminController::class, 'eliminarUsuario']);

        //Rutas gestionar especialidades
        Route::get('/obtenerespecialidades', [SuperAdminController::class, 'obtenerEspecialidades']);
        // Registrar una nueva especialidad
        Route::post('/registrarespecialidad', [SuperAdminController::class, 'registrarEspecialidad']);
        // Actualizar una especialidad existente
        Route::put('/actualizarespecialidad/{id}', [SuperAdminController::class, 'actualizarEspecialidad']);
        // Eliminar una especialidad
        Route::delete('/eliminarespecialidad/{id}', [SuperAdminController::class, 'eliminarEspecialidad']);



        Route::get('/listarDoctores', [SuperAdminController::class, 'listarDoctores']);
        Route::post('/asignarespecialidad', [SuperAdminController::class, 'asignarEspecialidad']);
        Route::delete('/removerespecialidad', [SuperAdminController::class, 'removeEspecialidad']);

        //Rutas dashboard
        Route::get('/payment-history', [SuperAdminController::class, 'index']);
        Route::get('/dashboard-stats', [SuperAdminController::class, 'getStats']);
    });


    // RUTAS PARA ADMINISTRADOR VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:admin'])->group(function () { 

          //Rutas para la sidebar del context
          Route::get('/pacientes/search', [AdminController::class, 'buscarPacientes']);
          Route::post('/subir-resultados', [AdminController::class, 'subirResultados']);

          // Rutas para admin
            Route::get('/admin/resultados', [AdminController::class, 'listarResultadosAdmin']);
            Route::delete('/admin/resultados/{id}', [AdminController::class, 'eliminarResultado']);
            
    
    });


   // RUTAS PARA CLEINTE VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:cliente'])->group(function () { 

        //Rutas para la sidebar del context
        Route::get('/pagos/cantidad/{idCliente}', [ClienteController::class, 'cantidadPagos']);
        Route::get('/citas/cantidad/{idCliente}', [ClienteController::class, 'cantidadCitas']);

     
        Route::post('/agendar-cita', [ClienteController::class, 'agendarCita']);
        Route::post('/registrar-pago', [ClienteController::class, 'registrarPago']);

    
        //Rutas para listar las citas
        Route::get('/citas/{userId}', [ClienteController::class, 'obtenerCitas']);
        Route::put('/cancelar-cita/{idCita}', [ClienteController::class, 'cancelarCitaCliente']);
        Route::get('/proxima-cita/{userId}', [ClienteController::class, 'obtenerCitaProxima']);

        Route::get('/cliente/historialcitas/{userId}', [ClienteController::class, 'obtenerHistorialCitasCliente']);
        Route::get('/cliente/historialpagos/{userId}', [ClienteController::class, 'obtenerHistorialPagosCliente']);

        //Rutas para mercado pago
        Route::post('/payment/preference', [PaymentController::class, 'createPreference']);
        Route::post('/actualizar-comprobante', [ClienteController::class, 'actualizarComprobante']);

        //Ruta descargar boleta por idCita
        Route::get('/descargar-boleta/{idCita}', [PaymentController::class, 'descargarBoleta']);
        //Ruta descargar resultados de laboratorio por idResultado
        Route::get('/descargar-resultado/{idResultado}', [ClienteController::class, 'descargarResultado']);
        
        //PARA FAMIKARIES EN CLIENTE
        Route::get('familiares/listar/{idUsuario}', [ClienteController::class, 'listarFamiliares']); // Listar familiares
        Route::post('familiares/crear/', [ClienteController::class, 'crearFamiliar']); // Crear familiar
        Route::put('familiares/actualizar/{id}', [ClienteController::class, 'actualizarFamiliar']); // Actualizar familiar
        Route::delete('familiares/eliminar/{id}', [ClienteController::class, 'eliminarFamiliar']); // Borrado lógico

        //RUTAS PARA EL PERFIL CLIENTE
        // Obtener perfil del cliente
        Route::get('/cliente/perfil/{idCliente}', [ClienteController::class, 'obtenerPerfil']);
        // Actualizar foto de perfil
        Route::post('/cliente/actualizar-foto/{idCliente}', [ClienteController::class, 'actualizarFotoPerfil']);
        // Actualizar datos del cliente
        Route::post('/cliente/actualizar-datos/{idCliente}', [ClienteController::class, 'actualizarDatos']);

        //Ruta listar resultados

        Route::get('/cliente/resultados/{idUsuario}', [ClienteController::class, 'obtenerResultados']);

    });


    
    // RUTAS PARA CLEINTE VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:doctor'])->group(function () { 

        
        //Ruta para listar las citas del doctor
        Route::get('/doctor/citas/{idDoctor}', [DoctorController::class, 'obtenerCitasDoctor']);

        //Ruta para listar las cantidad de citas del doctor
        Route::get('/doctor/citas/cantidad/{idDoctor}', [DoctorController::class, 'cantidadCitasDoctor']);

        Route::put('/citas/{idCita}/actualizar-estado/{idDoctor}', [DoctorController::class, 'actualizarEstadoCita']);

        //listar historial de citas
        Route::get('/doctor/historialcitas/{userId}', [DoctorController::class, 'obtenerHistorialCitasDoctor']);

        //Horarios Rutas

        Route::post('horarios-doctores/crear', [DoctorController::class, 'crearHorario']);
        Route::put('horarios-doctores/actualizar/{idHorario}', [DoctorController::class, 'actualizarHorario']);
        Route::delete('horarios-doctores/eliminar/{idHorario}', [DoctorController::class, 'eliminarHorario']);
        //LISTAR HORARIOS EN MIS HORARIOS CALENMDARIO y EN INICIO CALENDARIO
        Route::get('horarios-doctores/listar/{idDoctor}', [DoctorController::class, 'listarHorarios']);

        //PERFIL
        Route::post('/doctor/actualizar-foto/{idDoctor}', [DoctorController::class, 'actualizarFotoPerfil']);
        Route::post('/doctor/actualizar-idiomas/{idDoctor}', [DoctorController::class, 'actualizarIdiomas']);
        Route::post('/doctor/actualizar-educacion/{idDoctor}', [DoctorController::class, 'actualizarEducacion']);
        Route::get('/doctor/perfil/{idDoctor}', [DoctorController::class, 'obtenerPerfil']);
        Route::post('/doctor/actualizar-experiencia/{idDoctor}', [DoctorController::class, 'actualizarExperiencia']);
        Route::post('/doctor/actualizar-nacimiento/{idDoctor}', [DoctorController::class, 'actualizarNacimiento']);

    });


//================================================================================================

