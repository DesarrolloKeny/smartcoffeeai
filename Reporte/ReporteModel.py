import mysql.connector
from datetime import date
from sklearn.linear_model import LinearRegression
import numpy as np
import pandas as pd
from fpdf import FPDF 
import io

class ReporteModel:
    def __init__(self, host, user, password, db):
        self.config = {
            'host': host,
            'user': user,
            'password': password,
            'db': db
        }
        self.model = None # Para almacenar el modelo de Machine Learning

    def _get_connection(self):
        """Intenta establecer y devolver la conexión a la DB."""
        try:
            return mysql.connector.connect(**self.config)
        except mysql.connector.Error as err:
            raise Exception(f"Error de conexión a la base de datos: {err}")

    # --- FUNCIONES DE OBTENCIÓN DE DATOS ---

    def get_ventas_rango(self, fecha_desde, fecha_hasta):
        """Obtiene el total de ventas diarias en un rango de fechas."""
        conn = self._get_connection()
        cursor = conn.cursor(dictionary=True)
        query = """
        SELECT DATE(fecha) AS fecha, SUM(total) AS total
        FROM ventas
        WHERE fecha BETWEEN %s AND %s
        GROUP BY DATE(fecha)
        ORDER BY fecha;
        """
        cursor.execute(query, (fecha_desde, fecha_hasta))
        data = cursor.fetchall()
        cursor.close()
        conn.close()
        return data

    def get_top_productos(self, limit=5):
        """Obtiene los N productos más vendidos."""
        conn = self._get_connection()
        cursor = conn.cursor(dictionary=True)
        query = """
        SELECT p.nombre, SUM(dv.cantidad) AS cantidad_vendida
        FROM detalle_venta dv
        JOIN productos p ON dv.id_producto = p.id_producto
        GROUP BY p.nombre
        ORDER BY cantidad_vendida DESC
        LIMIT %s;
        """
        cursor.execute(query, (limit,))
        data = cursor.fetchall()
        cursor.close()
        conn.close()
        return data
        
    def get_menos_vendidos(self, limit=5):
        """Obtiene los N productos menos vendidos (pero que tienen alguna venta)."""
        conn = self._get_connection()
        cursor = conn.cursor(dictionary=True)
        query = """
        SELECT p.nombre, SUM(dv.cantidad) AS cantidad_vendida
        FROM detalle_venta dv
        JOIN productos p ON dv.id_producto = p.id_producto
        GROUP BY p.nombre
        ORDER BY cantidad_vendida ASC
        LIMIT %s;
        """
        cursor.execute(query, (limit,))
        data = cursor.fetchall()
        cursor.close()
        conn.close()
        return data

    def get_inventario_general(self):
        """Obtiene la lista de productos con su stock actual y precio."""
        conn = self._get_connection()
        cursor = conn.cursor(dictionary=True)
        query = """
        SELECT nombre, stock AS stock_actual, precio AS precio_venta
        FROM productos
        ORDER BY nombre;
        """
        cursor.execute(query)
        data = cursor.fetchall()
        cursor.close()
        conn.close()
        return data

    # --- MACHINE LEARNING (PREDICCIÓN) ---

    def entrenar_modelo_ventas(self):
        """Entrena un modelo de regresión lineal simple con datos históricos."""
        conn = self._get_connection()
        cursor = conn.cursor(dictionary=True)
        
        query = """
        SELECT DATE(fecha) AS fecha, SUM(total) AS total_ventas
        FROM ventas
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY DATE(fecha)
        ORDER BY fecha;
        """
        cursor.execute(query)
        data = cursor.fetchall()
        cursor.close()
        conn.close()
        
        if not data:
            self.model = None
            raise Exception("No hay suficientes datos históricos para entrenar el modelo (mínimo 90 días de ventas).")
            
        df = pd.DataFrame(data)
        df['fecha'] = pd.to_datetime(df['fecha'])
        
        # Usar días transcurridos como característica
        min_date = df['fecha'].min()
        df['dias_transcurridos'] = (df['fecha'] - min_date).dt.days
        
        X = df[['dias_transcurridos']].values
        Y = df['total_ventas'].values
        
        # Entrenar el modelo
        self.model = LinearRegression()
        self.model.fit(X, Y)
        

    def predecir_ventas(self, fecha_prediccion):
        """Predice las ventas para una fecha específica usando el modelo entrenado."""
        if self.model is None:
            return "Modelo no entrenado."

        conn = self._get_connection()
        cursor = conn.cursor()
        
        # Obtener la fecha mínima utilizada en el entrenamiento
        query_min_date = "SELECT MIN(DATE(fecha)) FROM ventas;"
        cursor.execute(query_min_date)
        min_date_db = cursor.fetchone()[0]
        cursor.close()
        conn.close()
        
        if not min_date_db:
            return "No hay datos de inicio para la predicción."

        # Calcular el 'días transcurridos' para la fecha de predicción
        min_date = pd.to_datetime(min_date_db)
        target_date = pd.to_datetime(fecha_prediccion)
        dias_transcurridos = (target_date - min_date).days
        
        # Realizar la predicción
        pred_input = np.array([[dias_transcurridos]])
        prediccion = self.model.predict(pred_input)[0]
        
        # Asegurar que la predicción no sea negativa
        return max(0, float(prediccion))

    # --- FUNCIONES DE EXPORTACIÓN ---

    def exportar_pdf(self, titulo, data, headers, filename):
        """Genera un reporte PDF a partir de los datos."""
        pdf = FPDF()
        pdf.add_page()
        pdf.set_font("Arial", "B", 16)
        pdf.cell(0, 10, titulo, 0, 1, "C")
        pdf.ln(5)

        pdf.set_font("Arial", "B", 10)
        col_width = pdf.w / (len(headers) + 1) 

        # Encabezados
        for header in headers:
            pdf.cell(col_width, 7, header, 1, 0, "C")
        pdf.ln()

        pdf.set_font("Arial", "", 10)
        # Datos
        for row in data:
            for key in row:
                value = str(row[key])
                # Formato de moneda para totales y precios
                if key in ['total', 'precio_venta']:
                    try:
                        value = f"${float(value):,.2f}"
                    except ValueError:
                        pass
                        
                pdf.cell(col_width, 7, value, 1, 0, "C")
            pdf.ln()
            
        buffer = io.BytesIO()
        # Usar pdf.output(dest='S') para obtener el contenido como string y luego codificar a bytes
        buffer.write(pdf.output(dest='S').encode('latin-1'))
        buffer.seek(0)
        return buffer.getvalue()

    def exportar_excel(self, titulo, data, headers, filename):
        """Genera un reporte Excel (XLSX) a partir de los datos."""
        df = pd.DataFrame(data)
        
        # Asignar los nombres de encabezado amigables antes de la exportación
        df.columns = headers 
        
        output = io.BytesIO()
        # Usar openpyxl como motor
        with pd.ExcelWriter(output, engine='openpyxl') as writer:
            df.to_excel(writer, sheet_name=titulo[:31], index=False)
            
        output.seek(0)
        return output.getvalue()