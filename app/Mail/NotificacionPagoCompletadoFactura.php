<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificacionPagoCompletadoFactura extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCliente;
    public $detallesComprobante;
    public $costoCita;
    public $pdfPath;
    public $ruc;
    public $idCita;

    public function __construct($nombreCliente, $detallesComprobante, $costoCita, $pdfPath, $ruc, $idCita)
    {
        $this->nombreCliente = $nombreCliente;
        $this->detallesComprobante = $detallesComprobante;
        $this->costoCita = $costoCita;
        $this->pdfPath = $pdfPath;
        $this->ruc = $ruc;
        $this->idCita = $idCita;
    }

    public function build()
    {
        return $this->view('emails.factura')
                    ->subject('Comprobante de Pago - Factura')
                    ->attach($this->pdfPath);
    }
}