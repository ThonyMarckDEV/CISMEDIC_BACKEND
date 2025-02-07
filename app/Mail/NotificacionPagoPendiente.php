<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoPendiente extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $cita;
    public $pago;

    public function __construct($user, $cita, $pago)
    {
        $this->user = $user;
        $this->cita = $cita;
        $this->pago = $pago;
    }

    public function build()
    {
        return $this->subject('Pago pendiente para completar tu cita - Cismedic')
                    ->view('emails.notificacion_pago_pendiente')
                    ->with([
                        'user' => $this->user,
                        'cita' => $this->cita,
                        'pago' => $this->pago,
                    ]);
    }
}