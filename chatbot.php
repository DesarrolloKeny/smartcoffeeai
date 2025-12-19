<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCoffee Assistant</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">

    <style>
        /* -------------------------------------- */
        /* RESET + VARIABLES                      */
        /* -------------------------------------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --accent: #34a0a4; /* Verde/Azul principal */
            --accent-light: #52c7c9;
            --bg: #e7ecef;
            --bot-bg: #ffffff;
            --user-bg: var(--accent);
            --header-bg: var(--accent);
        }

        body {
            background: var(--bg);
            font-family: 'Roboto', Arial, sans-serif;
            height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            overflow: hidden;
        }

        /* -------------------------------------- */
        /* CONTENEDOR PRINCIPAL                   */
        /* -------------------------------------- */
        #chatbox {
            background: #fdfdfd;
            width: 100%;
            height: 100%;
            max-width: 480px;
            display: flex;
            flex-direction: column;
            border-radius: 0;
            overflow: hidden;
            box-shadow: none;
        }

        @media (min-width: 600px) {
            #chatbox {
                height: 90vh;
                max-height: 750px;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            }
        }

        /* -------------------------------------- */
        /* HEADER                                  */
        /* -------------------------------------- */
        #chatHeader {
            /* Estilos originales del CSS principal */
            background: var(--header-bg);
            padding: 16px 20px;
            color: white;
            font-size: 1.3rem;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            /* Estilos para el logo SVG y texto */
            display: flex; 
            align-items: center; 
            gap: 10px;
            border-bottom: 2px solid #1c75bc; /* Línea de color de marca añadida */
            padding: 10px 15px; /* Ajuste para el nuevo padding */
            font-size: 1.2em; /* Ajuste para el nuevo font-size */
            font-weight: bold; /* Ajuste para el nuevo font-weight */
        }

        #assistantLogo {
            /* Estilos originales */
            width: 32px;
            height: 32px;
            filter: drop-shadow(0 0 4px rgba(0,0,0,0.3));
            /* Estilos del SVG */
            width: 28px; /* Reducido ligeramente */
            height: 28px;
            margin-right: 10px; /* Espacio entre el logo y el texto */
            
            /* El relleno del SVG ahora usa la variable --accent para coherencia */
        }

        /* -------------------------------------- */
        /* MENSAJES                                */
        /* -------------------------------------- */
        #messages {
            flex-grow: 1;
            padding: 15px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .message {
            max-width: 80%;
            padding: 12px 16px;
            margin: 10px 0;
            border-radius: 20px;
            font-size: 0.95rem;
            line-height: 1.4rem;
            animation: fadeIn 0.3s ease;
            word-wrap: break-word;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .bot {
            background: var(--bot-bg);
            margin-right: auto;
            border-bottom-left-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }

        .user {
            background: var(--user-bg);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 6px;
        }

        /* Tarjeta */
        .card {
            padding: 15px;
            border: 2px solid var(--accent);
            border-radius: 12px;
            margin-top: 5px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-bottom: 8px;
            font-size: 1.1rem;
            color: var(--accent);
        }

        /* -------------------------------------- */
        /* TYPING INDICATOR                        */
        /* -------------------------------------- */
        #typing {
            display: none;
            margin: 5px 0;
        }

        .dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #888;
            border-radius: 50%;
            margin-right: 4px;
            animation: blink 1.4s infinite;
        }

        .dots span:nth-child(2) { animation-delay: 0.2s; }
        .dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes blink {
            0% { opacity: 0.2; transform: translateY(0); }
            50% { opacity: 1; transform: translateY(-2px); }
            100% { opacity: 0.2; transform: translateY(0); }
        }

        /* -------------------------------------- */
        /* INPUT BAR                               */
        /* -------------------------------------- */
        #inputContainer {
            padding: 10px;
            background: white;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        #userInput {
            flex-grow: 1;
            padding: 10px 16px;
            border-radius: 20px;
            border: 1px solid #ccc;
            font-size: 1rem;
            resize: none;
            max-height: 150px;
            overflow-y: hidden;
            transition: box-shadow 0.3s;
        }

        #userInput:focus {
            outline: none;
            box-shadow: 0 0 5px var(--accent-light);
        }

        button {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: 0.2s;
        }

        button:hover {
            background: var(--accent-light);
            transform: scale(1.05);
        }
    </style>

</head>
<body>

    <div id="chatbox">

        <div id="chatHeader">
    
            <svg id="assistantLogo" xmlns="http://www.w3.org/2000/svg" 
                 width="32" height="32" viewBox="0 0 24 24" 
                 fill="var(--accent)" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                
                <path d="M18 8h1a4 4 0 0 1 0 8h-1"/>
                <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>
                <line x1="6" y1="2" x2="6" y2="4"/>
                <line x1="10" y1="2" x2="10" y2="4"/>
                <line x1="14" y1="2" x2="14" y2="4"/>
            </svg>
            SmartCoffee Assistant
        </div>
        
        <div id="messages">
            <div class="message bot">☕ ¡Hola! Soy <b>SmartCoffee Assistant</b>. ¿En qué puedo ayudarte hoy?</div>

            <div class="message bot" id="typing">
                <div class="dots">
                    <span></span><span></span><span></span>
                </div>
            </div>
        </div>

        <div id="inputContainer">
            <textarea id="userInput" placeholder="Escribe un mensaje..." rows="1" oninput="autoExpand(this)"></textarea>
            <button onclick="sendMessage()">
                <svg width="22" height="22" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
            </button>
        </div>

    </div>

<script>
    function autoExpand(field) {
        field.style.height = 'auto';
        field.style.height = Math.min(field.scrollHeight, 150) + "px";
    }

    async function sendMessage() {
        const input = document.getElementById("userInput");
        const msg = input.value.trim();
        if (!msg) return;

        const chat = document.getElementById("messages");

        chat.insertAdjacentHTML("beforeend", `<div class="message user">${msg}</div>`);
        chat.scrollTop = chat.scrollHeight;

        input.value = "";
        input.style.height = "40px";

        // Mostrar "escribiendo…"
        document.getElementById("typing").style.display = "block";

        try {
            const res = await fetch("http://127.0.0.1:5000/chat", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ message: msg })
            });

            const data = await res.json();

            document.getElementById("typing").style.display = "none";

            if (data.order) {
                chat.insertAdjacentHTML("beforeend",
                    `<div class="message bot">
                        <div class="card">
                            <h3>${data.order}</h3>
                            <p>${data.reply.replace(/\n/g, "<br>")}</p>
                        </div>
                    </div>`
                );
            } else {
                chat.insertAdjacentHTML("beforeend",
                    `<div class="message bot">${data.reply}</div>`
                );
            }

            chat.scrollTop = chat.scrollHeight;

        } catch (e) {
            document.getElementById("typing").style.display = "none";
            chat.insertAdjacentHTML("beforeend",
                `<div class="message bot">⚠️ Error al conectar con el asistente.</div>`
            );
        }
    }
    // chatbot.html - Script de auto-cierre
let timeoutInactividad;

function iniciarTemporizador() {
    clearTimeout(timeoutInactividad);
    // 120000 ms = 2 minutos
    timeoutInactividad = setTimeout(() => {
        alert("Tu sesión ha expirado por inactividad de 2 minutos. Por seguridad, escanea el QR nuevamente.");
        window.location.href = "about:blank"; // Limpia la pantalla
    }, 120000); 
}

// Reiniciar el contador con cualquier toque o tecla
document.onmousemove = iniciarTemporizador;
document.onkeypress = iniciarTemporizador;
document.ontouchstart = iniciarTemporizador;

iniciarTemporizador(); // Arranca al cargar
</script>

</body>
</html>