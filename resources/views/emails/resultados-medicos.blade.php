<!-- resources/views/emails/resultados-medicos.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #2d7d4d;
        }
        .content {
            margin-bottom: 30px;
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2d7d4d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Resultados de tu Consulta Médica</h2>
        </div>
        
        <div class="content">
            <p>Estimado(a) {{ $nombres }} {{ $apellidos }},</p>
            
            @if($esNuevo)
                <p>Adjunto encontrarás los resultados de tu consulta médica realizada el {{ \Carbon\Carbon::parse($fechaCita)->format('d/m/Y') }}.</p>
                
                <p>Por favor, revisa el documento adjunto y no dudes en contactarnos si tienes alguna pregunta.</p>
            @else
                <p>Los resultados de tu consulta médica del {{ \Carbon\Carbon::parse($fechaCita)->format('d/m/Y') }} ya están disponibles en tu cuenta.</p>
                
                <p>Puedes acceder a ellos siguiendo estos pasos:</p>
                <ol>
                    <li>Ingresa a tu cuenta</li>
                    <li>Ve al menú "Inicio"</li>
                    <li>Selecciona "Mis Resultados"</li>
                </ol>

                <p>Ahí encontrarás todos tus resultados organizados por fecha.</p>
            @endif
            
            <p>Gracias por confiar en nosotros para tu atención médica.</p>
        </div>
        
        <div class="footer">
            <p>Este es un correo automático, por favor no responder.</p>
            <p>Si tienes alguna pregunta, contáctanos directamente a través de nuestros canales oficiales.</p>
        </div>
    </div>
</body>
</html>