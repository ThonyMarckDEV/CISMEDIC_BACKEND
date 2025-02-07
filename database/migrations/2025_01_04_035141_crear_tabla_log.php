<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLog extends Migration
{
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id('idLog');  // ID del log
            $table->unsignedBigInteger('idUsuario');  // Relación con usuarios
            $table->string('nombreUsuario');  // Nombre completo del usuario
            $table->string('rol');  // Rol del usuario
            $table->text('fecha');  // Fecha de la acción
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');  // Relación con la tabla usuarios
        });
    }

    public function down()
    {
        Schema::dropIfExists('logs');
    }
}
