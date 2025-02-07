<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    use HasFactory;

    // Tabla asociada
    protected $table = 'logs';

    protected $primaryKey = 'idLog';

    public $timestamps = false;


    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'idUsuario',
        'nombreUsuario',
        'rol',
        'accion',
        'fecha',
    ];

    // Relación con la tabla usuarios
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'idUsuario');
    }
}
