<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoCompletado extends Mailable
{
    use SerializesModels;

    public $cliente;
    public $cita;
    public $pago;

    /**
     * Crear una nueva instancia de mensaje.
     *
     * @param object $cliente Datos del cliente
     * @param object $cita Datos de la cita
     * @param object $pago Datos del pago
     */
    public function __construct($cliente, $cita, $pago)
    {
        $this->cliente = $cliente;
        $this->cita = $cita;
        $this->pago = $pago;
    }

    /**
     * Construir el mensaje.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Pago Completado - Cita Confirmada')
                    ->view('emails.notificacion_pago_completado');
    }
}