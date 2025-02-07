<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioDoctor extends Model
{
    use HasFactory;

    protected $table = 'horarios_doctores';
    protected $primaryKey = 'idHorario';
    protected $fillable = ['idDoctor', 'fecha', 'dia_semana', 'hora_inicio'];
}
