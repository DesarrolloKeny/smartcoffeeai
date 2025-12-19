import logging
import unicodedata
import re
from flask import Flask, request, jsonify
from flask_cors import CORS
import mysql.connector
from sentence_transformers import SentenceTransformer, util
from datetime import date, datetime, timedelta # <-- Añadido datetime y timedelta

# -------------------------------------------------------
# 1. CONFIGURACIÓN INICIAL
# -------------------------------------------------------
app = Flask(__name__) 
CORS(app) 

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("SmartCoffee-ML")

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "smartcoffee"
}

# Configuración de Seguridad
LIMITE_INACTIVIDAD_SEGUNDOS = 120 # 2 Minutos

# -------------------------------------------------------
# 2. SEGURIDAD: VALIDACIÓN DE TOKEN Y TIEMPO
# -------------------------------------------------------

def validar_seguridad_qr(token, session_id):
    """Verifica token, dueño de sesión y tiempo de inactividad."""
    if not token:
        return False, "Token ausente. Escanea el QR de la pantalla."
    
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        cursor.execute("SELECT * FROM tokens_chatbot WHERE token = %s", (token,))
        row = cursor.fetchone()
        
        if not row:
            return False, "Acceso no válido."
        
        if row['usado'] == 1:
            return False, "Este QR ya fue utilizado para un pedido."

        ahora = datetime.now()

        # Validación de Inactividad o Expiración
        if row['session_id'] is not None:
            # Si ya hay sesión, verificamos inactividad desde su creación
            if ahora > (row['fecha_creacion'] + timedelta(seconds=LIMITE_INACTIVIDAD_SEGUNDOS)):
                return False, "Sesión expirada por inactividad (2 min)."
            
            if row['session_id'] != session_id:
                return False, "Este QR pertenece a otro dispositivo."
        else:
            # Si es el primer mensaje, verificamos que el QR no sea viejo
            if row['expira_en'] < ahora:
                return False, "El QR ha expirado. Escanea el nuevo en pantalla."
            
            # Amarrar el token a esta sesión
            cursor.execute("UPDATE tokens_chatbot SET session_id = %s WHERE token = %s", (session_id, token))
            conn.commit()

        return True, "OK"
    except Exception as e:
        logger.error(f"Error Seguridad: {e}")
        return False, "Error de validación interna."
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

def marcar_token_usado(token):
    """Invalida el token permanentemente tras la venta."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
        cursor.execute("UPDATE tokens_chatbot SET usado = 1 WHERE token = %s", (token,))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e:
        logger.error(f"Error al quemar token: {e}")

# -------------------------------------------------------
# 3. Normalización y Modelo NLU
# -------------------------------------------------------

def normalizar(texto):
    texto = texto.lower()
    texto = unicodedata.normalize("NFD", texto)
    texto = texto.encode("ascii", "ignore").decode("utf-8")
    return texto

model = SentenceTransformer("sentence-transformers/all-MiniLM-L6-v2")

# --- DICCIONARIO DE INTENCIONES (Tus frases originales) ---
intenciones = {
    "saludo": ["hola", "buenas tardes", "buenos dias", "que tal", "alo", "wena"],
    "nombre": ["me llamo", "soy", "mi nombre es", "me presento"],
    "productos": ["qué productos tienes", "menu", "carta", "mostrar productos"],
    "pedido_producto": ["quiero", "dame", "necesito", "ordenar", "me das"],
    "cantidad": ["1", "2", "3", "una", "dos", "tres"],
    "ver_carrito": ["cuanto llevo", "mostrar pedido", "ver carrito"],
    "finalizar_pedido": ["finalizar", "terminar", "estoy listo", "pagar"],
    "despedida": ["adios", "chao", "nos vemos"]
}

ejemplos = []
etiquetas = []
for label, frases in intenciones.items():
    for f in frases:
        ejemplos.append(f)
        etiquetas.append(label)

logger.info("Codificando intenciones...")
ejemplos_emb = model.encode(ejemplos, convert_to_tensor=True)

def predecir_intencion(mensaje):
    emb = model.encode(mensaje, convert_to_tensor=True)
    cos_sim = util.cos_sim(emb, ejemplos_emb)
    idx = cos_sim.argmax().item()
    return etiquetas[idx]

# -------------------------------------------------------
# 4. Funciones de Base de Datos (Tus lógicas originales)
# -------------------------------------------------------

# ... [Aquí se mantienen tus funciones obtener_producto, obtener_caja_abierta_hoy, etc.] ...

# -------------------------------------------------------
# 5. ENDPOINT /chat ACTUALIZADO
# -------------------------------------------------------
SESSION_STATE = {}

@app.route("/chat", methods=["POST"])
def chat():
    data = request.get_json()
    mensaje_original = data.get("message", "")
    token = data.get("token", "") # Recibido del frontend
    session_id = data.get("session_id", request.remote_addr) # ID único dispositivo
    
    # 1. VALIDACIÓN DE SEGURIDAD OBLIGATORIA
    es_valido, motivo = validar_seguridad_qr(token, session_id)
    if not es_valido:
        return jsonify({"reply": f"🚫 **ACCESO DENEGADO:** {motivo}", "to_kitchen": False}), 403

    mensaje_norm = normalizar(mensaje_original)
    intencion = predecir_intencion(mensaje_norm)
    
    state = SESSION_STATE.get(session_id, {
        "nombre_cliente": None,
        "last_product_info": None,
        "carrito": [],
        "last_intention": None
    })
    
    # --- INICIO LÓGICA DE RESPUESTAS ---
    if intencion == "saludo":
        state["last_intention"] = "saludo"
        SESSION_STATE[session_id] = state
        return jsonify({"reply": "👋 ¡Hola! Soy SmartCoffee Assistant. ¿Cuál es tu nombre?", "to_kitchen": False})

    # ... [Aquí sigue tu lógica de pedido_producto, cantidad y ver_carrito] ...

    if intencion in ["finalizar_pedido"]:
        if not state["carrito"]:
            return jsonify({"reply": "Tu carrito está vacío.", "to_kitchen": False})
            
        caja_activa = obtener_caja_abierta_hoy()
        if not caja_activa:
            return jsonify({"reply": "⚠️ La caja está cerrada.", "to_kitchen": False})

        nombre = state.get("nombre_cliente", "Invitado")
        id_cliente = obtener_o_crear_cliente_chatbot(nombre)
        id_usuario_vendedor = caja_activa['id_usuario_apertura']
        
        # 2. REGISTRO EN BASE DE DATOS
        if registrar_pedido_en_db(state["carrito"], id_usuario_vendedor, id_cliente):
            total_final = sum(item['subtotal'] for item in state["carrito"])
            
            # 3. SEGURIDAD: QUEMAR EL TOKEN
            marcar_token_usado(token)
            
            final_reply = f"🎉 ¡Pedido Finalizado! Total: **${total_final:.2f}**. Tu orden fue enviada a cocina. ¡Gracias!"
            
            # Limpiar estado
            del SESSION_STATE[session_id]
            
            return jsonify({"reply": final_reply, "to_kitchen": True})
        else:
            return jsonify({"reply": "❌ Error al procesar en DB.", "to_kitchen": False})

    # Fallback
    return jsonify({"reply": "No entendí, ¿quieres ver el menú?", "to_kitchen": False})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)