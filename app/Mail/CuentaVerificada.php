<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CuentaVerificada extends Mailable
{
    use SerializesModels;

    public $usuario;

    /**
     * Crear una nueva instancia de mensaje.
     *
     * @return void
     */
    public function __construct($usuario)
    {
        $this->usuario = $usuario;
    }

    /**
     * Construir el mensaje.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Cuenta Verificada Exitosamente')
                    ->view('emails.cuenta_verificada');
    }
}
