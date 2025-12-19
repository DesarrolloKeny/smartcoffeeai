<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCoffee</title>
     
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1a1a1a;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
        }
        .container {
            text-align: center;
            background: #2d2d2d;
            padding: 40px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border: 2px solid #444;
        }
        h1 { margin-bottom: 10px; color: #f39c12; font-size: 2.5em; }
        p { color: #aaa; margin-bottom: 30px; }
        #qrcode {
            background: white;
            padding: 20px;
            border-radius: 15px;
            display: inline-block;
            margin-bottom: 25px;
        }
        .timer-container {
            font-size: 1.2em;
            color: #ecf0f1;
        }
        #seconds {
            font-weight: bold;
            color: #e74c3c;
            font-size: 1.5em;
        }
        .logo { font-weight: bold; font-style: italic; color: #f39c12; margin-bottom: 20px; font-size: 1.2em; }
    </style>
</head>
<body>

    <div class="container">
        <div class="logo">SMARTCOFFEE POS</div>
        <h1>¡Escanea y Pide!</h1>
        <p>Usa tu celular para ver el menú y hacer tu pedido rápidamente.</p>
        
        <div id="qrcode"></div>
        
        <div class="timer-container">
            El código se actualizará en <span id="seconds">15</span> segundos
        </div>
    </div>

    <script>
        const qrContainer = document.getElementById("qrcode");
        const secondsText = document.getElementById("seconds");
        let qrcode = new QRCode(qrContainer, { width: 250, height: 250 });
        let timeLeft = 15;

        async function refreshQR() {
            try {
                const response = await fetch('generar_token.php');
                const data = await response.json();
                
                if (data.status === 'success') {
                    qrcode.clear();
                    qrcode.makeCode(data.url);
                }
            } catch (error) {
                console.error("Error obteniendo el token:", error);
            }
        }

        // Intervalo del contador
        setInterval(() => {
            timeLeft--;
            secondsText.innerText = timeLeft;
            
            if (timeLeft <= 0) {
                refreshQR();
                timeLeft = 15;
            }
        }, 1000);

        // Carga inicial
        refreshQR();
    </script>
</body>
</html>