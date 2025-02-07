<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pagos extends Model
{
    use HasFactory;

    protected $table = 'pagos';
    protected $primaryKey = 'idPago';
    protected $fillable = ['idCita', 'monto', 'estado' , 'fecha_pago' , 'tipo_pago'];
}
