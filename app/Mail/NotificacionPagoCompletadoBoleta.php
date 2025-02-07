<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoCompletadoBoleta extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCliente;
    public $detallesComprobante;
    public $costoCita;
    public $pdfPath;
    public $idCita;

    public function __construct($nombreCliente, $detallesComprobante, $costoCita, $pdfPath, $idCita)
    {
        $this->nombreCliente = $nombreCliente;
        $this->detallesComprobante = $detallesComprobante;
        $this->costoCita = $costoCita;
        $this->pdfPath = $pdfPath;
        $this->idCita = $idCita;
    }

    public function build()
    {
        return $this->view('emails.boleta')
                    ->subject('Comprobante de Pago - Boleta')
                    ->attach($this->pdfPath);
    }
}