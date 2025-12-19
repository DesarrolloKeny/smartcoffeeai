from flask import Flask, request, jsonify, render_template, send_file, make_response
from ReporteModel import ReporteModel
from datetime import date, timedelta
from io import BytesIO
from flask_cors import CORS # Importamos CORS

app = Flask(__name__)
CORS(app) # Habilitamos CORS para toda la aplicación

# --- CONFIGURACIÓN DB ---
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'db': 'smartcoffee'
}
model = ReporteModel(**DB_CONFIG)

# Entrenar el modelo al iniciar la aplicación (se ejecuta una sola vez)
try:
    model.entrenar_modelo_ventas()
    print("Modelo de ventas entrenado con éxito al inicio.")
except Exception as e:
    print(f"ADVERTENCIA: Error al entrenar el modelo de ML: {e}")

# Función para obtener los KPIs básicos, usados por reporte.php
def get_kpis_data():
    hoy = str(date.today())
    manana = date.today() + timedelta(days=1)
    
    kpis = {
        'total_ventas_dia': 'N/A',
        'proyeccion_manana': 'N/A',
        'kpi_error': False
    }
    
    try:
        # 1. Ventas del día
        ventas_dia_data = model.get_ventas_rango(hoy, hoy)
        total_ventas_dia = sum(item['total'] for item in ventas_dia_data) if ventas_dia_data else 0
        kpis['total_ventas_dia'] = f"${total_ventas_dia:,.2f}"
        
        # 2. Proyección ML
        proyeccion_manana = model.predecir_ventas(manana)
        if isinstance(proyeccion_manana, (int, float)):
             kpis['proyeccion_manana'] = f"${proyeccion_manana:,.2f}"
        else:
             kpis['proyeccion_manana'] = str(proyeccion_manana) # En caso de error de ML
            
    except Exception as e:
        kpis['kpi_error'] = True
        kpis['total_ventas_dia'] = 'Error al consultar DB'
        kpis['proyeccion_manana'] = f"Error ML: {str(e)[:50]}..."
        app.logger.error(f"Error al calcular KPIs: {e}", exc_info=True)
        
    return kpis


@app.route('/reportes', methods=['GET', 'POST'])
def reportes():
    # 1. Manejo de Peticiones AJAX (POST) - Solicitud de Reporte
    if request.method == 'POST':
        response = {'success': False, 'data': None, 'message': 'Solicitud no procesada.'}
        try:
            data = request.json
            action = data.get('action')
            
            # Recolección de parámetros comunes
            desde = data.get('fecha_desde', str(date.today() - timedelta(days=7)))
            hasta = data.get('fecha_hasta', str(date.today()))
            limit = int(data.get('limit', 5))

            if action == 'ventas_rango':
                response['data'] = model.get_ventas_rango(desde, hasta)
                response['success'] = True
                
            elif action == 'top_productos':
                response['data'] = model.get_top_productos(limit)
                response['success'] = True

            elif action == 'menos_vendidos':
                response['data'] = model.get_menos_vendidos(limit)
                response['success'] = True
                
            elif action == 'inventario_general':
                response['data'] = model.get_inventario_general()
                response['success'] = True
                
            else:
                response['message'] = 'Acción AJAX no reconocida.'
        
        except Exception as e:
            response['message'] = f"Error en el servidor: {str(e)}"
            app.logger.error(f"Error en AJAX POST: {e}", exc_info=True)
        
        return jsonify(response)

    # 2. Manejo de Peticiones GET (Carga Inicial/Solicitud de KPIs)
    else: 
        if request.args.get('kpis') == 'true':
            kpis = get_kpis_data()
            return jsonify(kpis) 
        
        # Respuesta fallback para GET directo sin parámetro
        return jsonify({
            'success': True,
            'message': 'API de Reportes Flask activa. Use solicitudes POST o el parámetro ?kpis=true para obtener datos.'
        })


@app.route('/exportar/<reporte_name>/<tipo>', methods=['GET'])
def exportar_reporte(reporte_name, tipo):
    try:
        data = []
        headers = []
        titulo = ""
        
        # Obtener parámetros GET 
        desde = request.args.get('fecha_desde', str(date.today() - timedelta(days=7)))
        hasta = request.args.get('fecha_hasta', str(date.today()))
        limit = int(request.args.get('limit', 5))
        
        # Extensión dinámica
        extension = 'xlsx' if tipo == 'excel' else 'pdf'

        # 1. Obtener Datos
        if reporte_name == 'ventas_rango':
            data = model.get_ventas_rango(desde, hasta)
            headers = ['fecha', 'total'] 
            titulo = f"Reporte de Ventas del {desde} al {hasta}"
            filename = f"Ventas_Rango_{desde}_{hasta}.{extension}" # <-- Extensión corregida
        
        elif reporte_name == 'top_productos':
            data = model.get_top_productos(limit)
            headers = ['nombre', 'cantidad_vendida']
            titulo = f"Top {limit} Productos más Vendidos"
            filename = f"Top_Productos_{limit}.{extension}" # <-- Extensión corregida
        
        elif reporte_name == 'menos_vendidos':
            data = model.get_menos_vendidos(limit)
            headers = ['nombre', 'cantidad_vendida']
            titulo = f"Top {limit} Productos Menos Vendidos"
            filename = f"Menos_Vendidos_{limit}.{extension}" # <-- Extensión corregida
        
        elif reporte_name == 'inventario_general':
            data = model.get_inventario_general()
            headers = ['nombre', 'precio_venta', 'stock_actual'] 
            titulo = "Inventario General"
            filename = f"Inventario_General.{extension}" # <-- Extensión corregida
        
        else:
            return "Reporte no válido.", 400

        if not data:
            return "No hay datos para exportar.", 404
        
        # 2. Procesar y Exportar
        data_mapped = [{h: row[h] for h in headers} for row in data]
        display_headers = [h.replace('_', ' ').title() for h in headers]
        
        if tipo == 'pdf':
            pdf_output = model.exportar_pdf(titulo, data_mapped, display_headers, filename)
            return send_file(BytesIO(pdf_output), 
                             mimetype='application/pdf', 
                             as_attachment=True, 
                             download_name=filename)
                             
        elif tipo == 'excel':
            excel_output = model.exportar_excel(titulo, data_mapped, display_headers, filename)
            # Usamos make_response para asegurar que los headers se envíen correctamente
            response = make_response(excel_output)
            response.headers['Content-Type'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            response.headers['Content-Disposition'] = f'attachment; filename="{filename}"'
            return response

        return "Tipo de exportación no válido.", 400

    except Exception as e:
        app.logger.error(f"Error en Exportación GET: {e}", exc_info=True)
        return f"Error al generar el archivo: {str(e)}", 500

if __name__ == '__main__':
    # Asegurarse de usar 127.0.0.1 para evitar problemas de firewall/red local
    app.run(host='127.0.0.1', port=5000, debug=True)