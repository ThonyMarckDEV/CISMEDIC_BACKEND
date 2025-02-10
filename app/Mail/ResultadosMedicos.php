<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResultadosMedicos extends Mailable
{
    use Queueable, SerializesModels;

    public $nombres;
    public $apellidos;
    public $fechaCita;
    public $rutaArchivo;
    public $esNuevo;
    public $whatsappLink;

    /**
     * Create a new message instance.
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->nombres = $data['nombres'];
        $this->apellidos = $data['apellidos'];
        $this->fechaCita = $data['fechaCita'];
        $this->rutaArchivo = $data['rutaArchivo'] ?? null;
        $this->esNuevo = $data['esNuevo'];
        $this->whatsappLink = $data['whatsappLink'] ?? null;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email = $this->view('emails.resultados-medicos')
            ->subject('Resultados de tu Consulta Médica');

        // Adjuntar el archivo si es un paciente nuevo
        if ($this->esNuevo && $this->rutaArchivo) {
            $email->attach(storage_path('app/public/' . $this->rutaArchivo), [
                'as' => 'resultados_medicos.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $email;
    }
}