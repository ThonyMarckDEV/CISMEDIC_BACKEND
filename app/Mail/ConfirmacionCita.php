<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmacionCita extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $cita;

    public function __construct($user, $cita)
    {
        $this->user = $user;
        $this->cita = $cita;
    }

    public function build()
    {
        return $this->subject('Confirmación de Cita - Cismedic')
                    ->view('emails.confirmacion_cita')
                    ->with([
                        'user' => $this->user,
                        'cita' => $this->cita,
                    ]);
    }
}