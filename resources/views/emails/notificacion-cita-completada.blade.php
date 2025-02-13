<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Cita Completada - CISMEDIC</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #2E7D32; /* Verde médico */
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Notificación de Cita Completada</h1>
        <p>Hola <strong>{{ $citaData['cliente_nombre'] }}</strong>,</p>
        <p>Tu cita con ID <strong>{{ $citaData['idCita'] }}</strong> ha sido marcada como completada.</p>
        <h3>Detalles de la Cita:</h3>
        <p><strong>Doctor:</strong> {{ $citaData['doctor_nombre'] }}</p>
        <p><strong>Especialidad:</strong> {{ $citaData['especialidad'] }}</p>
        <p><strong>Fecha:</strong> {{ $citaData['fecha'] }}</p>
        <p><strong>Hora:</strong> {{ $citaData['hora'] }}</p>
        <p>Gracias por confiar en nosotros. Puedes consultar los detalles de esta cita en tu historial.</p>
        <div class="footer">
            <p>Saludos,</p>
            <p>El equipo de <strong>CISMEDIC</strong></p>
        </div>
    </div>
</body>
</html>