<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Correo - Cismedic</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f8f8;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
        }
        /* Container and Layout */
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        /* Typography */
        h1 {
            font-size: 24px;
            font-weight: 300;
            color: #15803d; /* green-700 */
            margin: 0 0 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }
        h3 {
            font-size: 18px;
            color: #1a1a1a;
            margin: 25px 0 15px 0;
            font-weight: 500;
        }
        p {
            margin-bottom: 15px;
            font-size: 16px;
            color: #4a4a4a;
        }
        /* Buttons and Links */
        .button, a {
            display: inline-block;
            background-color: #15803d; /* green-700 */
            color: #ffffff !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-size: 14px;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .button:hover, a:hover {
            background-color: #166534; /* green-800 for darker hover state */
            transform: translateY(-1px);
        }
        /* Utility Classes */
        .highlight {
            color: #15803d; /* green-700 */
            font-weight: 500;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eaeaea;
            color: #999;
            font-size: 14px;
        }
        /* Logo and Branding */
        .logo {
            text-align: center;
            margin-bottom: 40px;
            font-size: 28px;
            font-weight: 700;
            color: #15803d; /* green-700 */
            letter-spacing: 2px;
        }
        /* Responsive Design */
        @media screen and (max-width: 600px) {
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">CISMEDIC</div>
        <h1>¡Hola, {{ $user->nombres }}!</h1>
        <p>Gracias por registrarte en nuestra plataforma. Por favor, haz clic en el botón de abajo para verificar tu correo electrónico:</p>
        <a href="{{ $url }}" class="button">Verificar correo</a>
        <p>Si no solicitaste esta verificación, ignora este correo.</p>
        <p class="footer">Saludos,<br>El equipo de CISMEDIC</p>
    </div>
</body>
</html>