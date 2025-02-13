// En resources/views/emails/restablecer-password.blade.php
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #15803d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Hola {{ $usuario->nombre }}</h2>
        
        <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en Cismedic.</p>
        
        <p>Para crear una nueva contraseña, haz clic en el siguiente enlace:</p>
        
        <a href="http://localhost:3000/restablecer-password?token_veririficador={{ $token }}" class="button">
            Restablecer Contraseña
        </a>
        
        <p>Este enlace expirará en 24 horas por motivos de seguridad.</p>
        
        <p>Si no solicitaste restablecer tu contraseña, puedes ignorar este correo. Tu cuenta está segura.</p>
        
        <div class="footer">
            <p>Saludos,<br>El equipo de Cismedic</p>
        </div>
    </div>
</body>
</html>