<!DOCTYPE html>
<html>
<head>
    <title>Comprobante de Pago - Factura</title>
</head>
<body>
    <h1>Comprobante de Pago - Factura</h1>
    <p>Estimado/a {{ $nombreCliente }},</p>
    <p>Adjunto encontrará el comprobante de pago en formato factura por la cita {{ $idCita }}.</p>
    <p>Detalles del cliente:</p>
    <p>Cliente: {{ $nombreCliente }}</p>
    <p>RUC: {{ $ruc }}</p>
    <p>Detalles del pago:</p>
    <ul>
        <li>Especialidad: {{ $detallesComprobante['especialidad'] }}</li>
        <li>Doctor: {{ $detallesComprobante['doctor'] }}</li>
        <li>Fecha: {{ $detallesComprobante['fecha'] }}</li>
        <li>Hora de inicio: {{ $detallesComprobante['hora_inicio'] }}</li>
        <li>Monto: S/ {{ $costoCita }}</li>
        <li>RUC: {{ $ruc }}</li>
    </ul>
    <p>Gracias por su preferencia.</p>
</body>
</html>