<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Pago - Cismedic</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }

        .receipt-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-top: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1a5f7a;
            padding-bottom: 20px;
        }

        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }

        h1 {
            color: #1a5f7a;
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }

        .client-details, .payment-details {
            margin: 20px 0;
        }

        .details-title {
            color: #1a5f7a;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .details-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #1a5f7a;
        }

        ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        li {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .label {
            font-weight: 600;
            color: #495057;
        }

        .value {
            color: #212529;
        }

        .total {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #1a5f7a;
            text-align: right;
            font-size: 18px;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: #6c757d;
            font-size: 14px;
        }

        @media print {
            body {
                background: white;
            }
            .receipt-container {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <img src="https://cismedictunnel.thonymarckdev.online/storage/logo/logo.png" alt="Cismedic Logo" class="logo">
            <h1>Comprobante de Pago - Boleta</h1>
        </div>

        <div class="client-details">
            <div class="details-title">Detalles del Cliente</div>
            <div class="details-content">
                <p><span class="label">Cliente:</span> <span class="value">{{ $nombreCliente }}</span></p>
                <p><span class="label">N° de Cita:</span> <span class="value">{{ $idCita }}</span></p>
            </div>
        </div>

        <div class="payment-details">
            <div class="details-title">Detalles del Servicio</div>
            <div class="details-content">
                <ul>
                    <li>
                        <span class="label">Especialidad:</span>
                        <span class="value">{{ $detallesComprobante['especialidad'] }}</span>
                    </li>
                    <li>
                        <span class="label">Doctor:</span>
                        <span class="value">{{ $detallesComprobante['doctor'] }}</span>
                    </li>
                    <li>
                        <span class="label">Fecha:</span>
                        <span class="value">{{ $detallesComprobante['fecha'] }}</span>
                    </li>
                    <li>
                        <span class="label">Hora de inicio:</span>
                        <span class="value">{{ $detallesComprobante['hora_inicio'] }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="total">
            <span class="label">Monto Total:</span>
            <span class="value">S/ {{ $costoCita }}</span>
        </div>

        <div class="footer">
            <p>Gracias por confiar en Cismedic</p>
            <p>Este documento es un comprobante válido de su pago</p>
        </div>
    </div>
</body>
</html>