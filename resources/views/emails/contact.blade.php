<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo mensaje de contacto - RCI Muebles</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        
        .email-container {
            background-image: url('https://talararci.thonymarckdev.online/storage/imagenes/logo/blanco.jpg');
            background-size: cover;
            background-position: center;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #E4A853;
            padding-bottom: 20px;
        }
        
        .logo {
            width: 150px;
            height: auto;
        }
        
        h1 {
            color: #8B4513;
            font-size: 24px;
            margin: 20px 0;
        }
        
        .content {
            background-color: transparent;
            padding: 20px;
            border-radius: 8px;
        }
        
        .field {
            margin-bottom: 20px;
        }
        
        .label {
            color: #E4A853;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .value {
            color: #666 !important;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            -webkit-text-fill-color: #000000;
            text-shadow: 0 0 0 #000000;
        }
        
        .message-content {
            white-space: pre-line;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #E4A853;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://talararci.thonymarckdev.online/storage/imagenes/logo/rcilogofondoblanco.jpg" alt="RCI Muebles" class="logo">
            <h1>Nuevo mensaje de contacto</h1>
        </div>
        
        <div class="content">
            <div class="field">
                <span class="label">Nombre:</span>
                <div class='value'>{{ $name }}</div>
            </div>
            
            <div class="field">
                <span class="label">Correo:</span>
                <div class="value">{{ $email }}</div>
            </div>
            
            <div class="field">
                <span class="label">Mensaje:</span>
                <div class='value'>{{ $messageContent }}</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático de RCI Muebles</p>
            <p>Calidad sin límites a cómodos precios</p>
        </div>
    </div>
</body>
</html>