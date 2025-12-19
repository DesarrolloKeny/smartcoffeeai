import mysql.connector
import json
import pandas as pd
import numpy as np
from sklearn.linear_model import LinearRegression
from mlxtend.frequent_patterns import apriori, association_rules
from datetime import datetime
import warnings

# Silenciar advertencias para un JSON limpio
warnings.filterwarnings("ignore")

def get_ml_metrics():
    # Configuración de tu base de datos smartcoffee
    db_config = {
        'user': 'root', 
        'password': '', 
        'host': '127.0.0.1', 
        'database': 'smartcoffee', 
        'charset': 'utf8mb4'
    }
    
    results = {
        'prediccionHoy': 0.0, 
        'prediccionSemanal': 0.0, 
        'prediccionMensual': 0.0,
        'prediccionUnidades': [], 
        'sugerencias': [], 
        'error': None
    }

    try:
        conn = mysql.connector.connect(**db_config)

        # --- 1. PREDICCIÓN DE VENTAS TOTALES ---
        df_v = pd.read_sql("SELECT DATE(fecha) as d, SUM(total) as t FROM ventas GROUP BY d", conn)
        if len(df_v) >= 2:
            df_v['ts'] = pd.to_datetime(df_v['d']).map(datetime.timestamp).values.reshape(-1, 1)
            model = LinearRegression().fit(df_v[['ts']], df_v['t'])
            ahora = datetime.now().timestamp()
            results['prediccionHoy'] = round(float(max(0, model.predict([[ahora]])[0])), 2)
            results['prediccionSemanal'] = round(float(max(0, model.predict([[ahora + 604800]])[0])), 2)
            results['prediccionMensual'] = round(float(max(0, model.predict([[ahora + 2592000]])[0])), 2)

        # --- 2. PREDICCIÓN DE UNIDADES (Tabla: detalle_venta) ---
        query_u = """
            SELECT p.nombre, DATE(v.fecha) as d, SUM(dv.cantidad) as cant
            FROM detalle_venta dv
            JOIN ventas v ON dv.id_venta = v.id_venta
            JOIN productos p ON dv.id_producto = p.id_producto
            GROUP BY p.nombre, d
        """
        df_u = pd.read_sql(query_u, conn)
        if not df_u.empty:
            top_prods = df_u.groupby('nombre')['cant'].sum().nlargest(3).index
            for prod in top_prods:
                df_p = df_u[df_u['nombre'] == prod]
                if len(df_p) >= 2:
                    df_p['ts'] = pd.to_datetime(df_p['d']).map(datetime.timestamp).values.reshape(-1, 1)
                    m = LinearRegression().fit(df_p[['ts']], df_p['cant'])
                    pred = m.predict([[datetime.now().timestamp()]])[0]
                    results['prediccionUnidades'].append({
                        'nombre': prod, 
                        'prediccion': int(max(1, round(float(pred))))
                    })

        # --- 3. ANÁLISIS DE CANASTA (Apriori - Tabla: detalle_venta) ---
        query_b = """
            SELECT dv.id_venta, p.nombre 
            FROM detalle_venta dv 
            JOIN productos p ON dv.id_producto = p.id_producto
        """
        df_b = pd.read_sql(query_b, conn)
        
        if not df_b.empty and df_b['id_venta'].nunique() > 1:
            basket = (df_b.groupby(['id_venta', 'nombre'])['nombre']
                      .count().unstack().reset_index().fillna(0)
                      .set_index('id_venta'))
            basket = basket.applymap(lambda x: 1 if x > 0 else 0)

            # Si hay más de un producto diferente en total, ejecutamos Apriori
            if basket.shape[1] > 1:
                frequent_itemsets = apriori(basket, min_support=0.01, use_colnames=True)
                if not frequent_itemsets.empty:
                    rules = association_rules(frequent_itemsets, metric="confidence", min_threshold=0.1)
                    rules = rules.sort_values('confidence', ascending=False).head(3)
                    for _, row in rules.iterrows():
                        results['sugerencias'].append({
                            'principal': list(row['antecedents'])[0],
                            'sugerido': list(row['consequents'])[0],
                            'probabilidad': round(float(row['confidence'] * 100), 1)
                        })

        conn.close()
    except Exception as e:
        results['error'] = str(e)
    
    print(json.dumps(results))

if __name__ == "__main__":
    get_ml_metrics()