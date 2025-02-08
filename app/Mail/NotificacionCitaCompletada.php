<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionCitaCompletada extends Mailable
{
    use Queueable, SerializesModels;

    public $citaData;

    /**
     * Create a new message instance.
     *
     * @param array $citaData
     */
    public function __construct($citaData)
    {
        $this->citaData = $citaData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Notificación: Cita Completada - CISMEDIC')
                    ->view('emails.notificacion-cita-completada');
    }
}