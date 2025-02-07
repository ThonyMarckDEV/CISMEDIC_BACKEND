<?php


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
    
        Route::post('/webhook/mercadopago', [PaymentController::class, 'recibirPago']);

        Route::post('/procesar-citas-expiradas', [TaskController::class, 'procesarCitasExpiradas']);


//================================================================================================
    //RUTAS  AUTH PROTEGIDAS par todos los roles

    Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () {

        Route::post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('update-activity', [AuthController::class, 'updateLastActivity']);

        Route::post('/check-status', [AuthController::class, 'checkStatus']);

    });

//================================================================================================
    //RUTAS PROTEGIDAS A
    // RUTAS PARA ADMINISTRADOR VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:superadmin'])->group(function () { 

      
    });


   // RUTAS PARA CLEINTE VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:cliente'])->group(function () { 

        //Rutas para la sidebar del context
        Route::get('/pagos/cantidad/{idCliente}', [ClienteController::class, 'cantidadPagos']);
        Route::get('/citas/cantidad/{idCliente}', [ClienteController::class, 'cantidadCitas']);

       // Rutas para Agendar Cita
        Route::get('/especialidades', [ClienteController::class, 'getEspecialidades']);
        Route::get('/doctores/especialidad/{idEspecialidad}', [ClienteController::class, 'getDoctoresPorEspecialidad']);
        Route::get('/horarios-disponibles/{idDoctor}/{fecha}', [ClienteController::class, 'getHorariosDisponibles']);
        Route::post('/agendar-cita', [ClienteController::class, 'agendarCita']);
        Route::post('/registrar-pago', [ClienteController::class, 'registrarPago']);

        Route::get('/doctor-schedule/{doctorId}/week', [ClienteController::class, 'getWeekSchedule']); 

        //Rutas para listar las citas
        Route::get('/citas/{userId}', [ClienteController::class, 'obtenerCitas']);
        Route::put('/cancelar-cita/{idCita}', [ClienteController::class, 'cancelarCitaCliente']);

        Route::get('/cliente/historialcitas/{userId}', [ClienteController::class, 'obtenerHistorialCitasCliente']);
        Route::get('/cliente/historialpagos/{userId}', [ClienteController::class, 'obtenerHistorialPagosCliente']);

        //Rutas para mercado pago
        Route::post('/payment/preference', [PaymentController::class, 'createPreference']);
        Route::post('/actualizar-comprobante', [ClienteController::class, 'actualizarComprobante']);

        //Ruta descargar boleta por idCita
        Route::get('/descargar-boleta/{idCita}', [PaymentController::class, 'descargarBoleta']);

        
        //PARA FAMIKARIES EN CLIENTE
        Route::get('familiares/listar/{idUsuario}', [ClienteController::class, 'listarFamiliares']); // Listar familiares
        Route::post('familiares/crear/', [ClienteController::class, 'crearFamiliar']); // Crear familiar
        Route::put('familiares/actualizar/{id}', [ClienteController::class, 'actualizarFamiliar']); // Actualizar familiar
        Route::delete('familiares/eliminar/{id}', [ClienteController::class, 'eliminarFamiliar']); // Borrado lógico

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
        Route::get('horarios-doctores/listar/{idDoctor}', [DoctorController::class, 'listarHorarios']);


    });


//================================================================================================

