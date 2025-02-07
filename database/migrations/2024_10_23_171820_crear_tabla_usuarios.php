<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaUsuarios extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id('idUsuario'); // Clave primaria
            $table->string('rol', 255);
            $table->string('nombres', 255);
            $table->string('apellidos', 255);
            $table->string('correo', 255);
            $table->string('password', 255);
            $table->string('status', 255);
            $table->string('estado');
            $table->timestamp('fecha_creado')->useCurrent(); // Fecha de creación por defecto con CURRENT_TIMESTAMP
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('usuarios');
    }
}
