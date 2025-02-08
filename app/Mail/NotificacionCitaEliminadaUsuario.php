<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionCitaEliminadaUsuario extends Mailable
{
    use Queueable, SerializesModels;

    public $idCita; // ID de la cita cancelada
    public $citaData; // Datos de la cita (cliente, doctor, fecha, etc.)

    /**
     * Create a new message instance.
     *
     * @param int $idCita
     * @param array $citaData
     */
    public function __construct($idCita, $citaData)
    {
        $this->idCita = $idCita;
        $this->citaData = $citaData;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Notificación: Cita Cancelada - CISMEDIC')
                    ->view('emails.notificacion-cita-eliminada');
    }
}