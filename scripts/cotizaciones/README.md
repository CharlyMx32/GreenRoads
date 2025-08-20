# Estructura de Scripts para Cotizaciones

Este archivo documenta la organización y separación de lógica implementada para el sistema de cotizaciones.

## 🧮 Ejemplo Completo de Cálculo - Sistema GreenRoads

### Proyecto: Jardín Residencial con Terreno Irregular

#### 1. Cálculo del Área Total
**Terreno Irregular con 3 formas:**
1. **Rectángulo principal:** 4m (ancho) × 6m (largo) = 24m²
2. **Triángulo esquina:** (3m × 4m) ÷ 2 = 6m²
3. **Círculo decorativo:** π × (1.5m)² = 7.07m²

**Área Total = 24m² + 6m² + 7.07m² = 37.07m²**

#### 2. Cálculo Rollos de Pasto
**Datos del rollo seleccionado:**
- Producto: Pasto Premium Verde
- Área requerida: 37.07m²
- Costo base: $65.00/m² (desde tabla `productos.costo_base`)

**Cálculo directo:**
```
Costo rollos = 37.07m² × $65.00/m² = $2,409.55
```

#### 3. Descuento por Volumen
**Según tabulador área 37.07m² (rango 31-50m², tabla `tabuladores` tipo 'descuento_volumen'):** 14% descuento
```
Descuento = $2,409.55 × 14% = $337.34
Precio rollos con descuento = $2,409.55 - $337.34 = $2,072.21
```

#### 4. Materiales Automáticos
**Tipo de instalación:** Tierra
**Proceso de cálculo:**
1. **Área 37.07m² busca en tabulador tipo 'clavos'** → Rango 21.00-50.00m² = 4.00 kg TOTAL
2. **Cantidad necesaria:** 4.00 kg (valor fijo del tabulador para este rango)
3. **Sistema busca productos tipo 'clavos' (FIFO)**
4. **Obtiene costo_base del producto:** $45.00/kg (desde tabla `productos.costo_base`)
5. **Cálculo final:** 4.00 kg × $45.00/kg = $180.00

**Total materiales automáticos = $180.00**

#### 5. Productos Manuales
**Agregados por el usuario:**
**Proceso de cálculo:**
1. **Usuario selecciona:** Herramienta cortadora especial
2. **Sistema obtiene costo_base:** $350.00/unidad (desde tabla `productos.costo_base`)
3. **Cálculo:** 1 unidad × $350.00 = $350.00

4. **Usuario selecciona:** Grapas de refuerzo 
5. **Sistema obtiene costo_base:** $0.75/pieza (desde tabla `productos.costo_base`)
6. **Cálculo:** 200 piezas × $0.75 = $150.00

**Total productos manuales = $500.00**

#### 6. Extras Seleccionados
**Servicios adicionales (tabla `extras`):**
- Colocación de malla geotextil: $30.00
- Bordes de contención: $50.00

**Total extras = $80.00**

#### 7. Precio de Instalación
**Según tabulador área 37.07m² (rango 1-50m², tabla `tabuladores` tipo 'precio_instalacion'):** $150.00/m²
```
Costo instalación = 37.07m² × $150.00/m² = $5,560.50
```

#### 8. Mano de Obra Adicional
**Según tabulador área 37.07m² (rango 21-50m², tabla `tabuladores` tipo 'mano_obra'):** $45.00/m²
```
Costo mano de obra = 37.07m² × $45.00/m² = $1,668.15
```

#### 9. Cálculo de Subtotal
```
Subtotal = $2,072.21 (rollos) + $180.00 (materiales auto) + 
          $500.00 (productos) + $80.00 (extras) + 
          $5,560.50 (instalación) + $1,668.15 (mano obra)
Subtotal = $10,060.86
```

#### 10. Cálculo de IVA (16%)
```
IVA = $10,060.86 × 0.16 = $1,609.74
```

#### 11. Total Final
```
Total = $10,060.86 + $1,609.74 = $11,670.60
```

### 📊 Resumen Final de la Cotización

| Concepto | Cálculo | Valor |
|----------|---------|-------|
| **Área Total** | 24m² + 6m² + 7.07m² | **37.07m²** |
| **Rollos de Pasto:** | | |
| - Costo base | 37.07m² × $65.00/m² | $2,409.55 |
| - Descuento (14%) | $2,409.55 × 14% | -$337.34 |
| - **Total rollos** | $2,409.55 - $337.34 | **$2,072.21** |
| **Materiales Automáticos** | 4.00 kg clavos FIFO | **$180.00** |
| **Productos Manuales** | Cortadora + Grapas | **$500.00** |
| **Extras** | Malla geotextil + Bordes | **$80.00** |
| **Instalación** | 37.07m² × $150/m² | **$5,560.50** |
| **Mano de Obra** | 37.07m² × $45/m² | **$1,668.15** |
| **SUBTOTAL** | Suma de todos los componentes | **$10,060.86** |
| **IVA (16%)** | $10,060.86 × 0.16 | **$1,609.74** |
| **TOTAL COTIZACIÓN** | Subtotal + IVA | **$11,670.60** |

### 🔄 Flujo del Sistema en Tiempo Real

1. **Usuario dibuja terreno** → JavaScript calcula área automáticamente
2. **Selecciona producto** → `obtener_precio_inventario.php` retorna $65/m²
3. **Elige tipo "tierra"** → `calcular_materiales.php` calcula materiales
4. **Agrega productos** → Se suman directamente al cálculo
5. **Selecciona extras** → Se agregan precios fijos
6. **Sistema consulta tabuladores** → Obtiene precios por área
7. **Calcula en tiempo real** → `totales.js` actualiza interfaz
8. **Usuario guarda** → `guardar_cotizacion.php` procesa todo

### 📁 Archivos Involucrados en este Cálculo

- `scripts/cotizaciones/core/calculos.js` → Cálculo de área irregular
- `php/cotizaciones/obtener_precio_inventario.php` → Precio de rollos ($65/m² desde `productos.costo_base`)
- `php/cotizaciones/calcular_materiales.php` → Materiales automáticos (FIFO desde inventario + `productos.costo_base`)
- `php/cotizaciones/guardar_cotizacion.php` → Productos manuales (usando `productos.costo_base`)
- `includes/funciones_tabuladores.php` → Precios por área (instalación, mano obra, cantidades materiales)
- `scripts/cotizaciones/core/totales.js` → Cálculo final en tiempo real

### 💾 Datos de Base de Datos Utilizados

**Tabla `productos`:**
- `costo_base`: $65.00/m² (precio base del pasto por metro cuadrado)
- `costo_base`: Precio unitario para materiales (ej: $45.00/kg para clavos)
- `costo_base`: Precio unitario para productos manuales (ej: $350.00/unidad cortadora)

**Tabla `tabuladores` (cantidades TOTALES por rango de área):**
- Tipo 'precio_instalacion': ID 1 (1-50m² = $150.00/m²)
- Tipo 'descuento_volumen': ID 6 (31-50m² = 14% descuento)
- Tipo 'mano_obra': ID 10 (21-50m² = $45.00/m²)
- Tipo 'clavos': ID 28 (21-50m² = 4.00 kg TOTAL) → Define CANTIDAD TOTAL para todo el rango

**Tabla `movimientos_inventario` (sistema FIFO):**
- `tipo_movimiento`: 'entrada' / 'salida'
- `cantidad`: Stock disponible por producto
- Sistema ordena por: mayor stock disponible → menor `costo_base`

**Tabla `extras` (servicios adicionales disponibles):**
- ID 4: Desmonte o retiro de maleza ($15.00/m²)
- ID 5: Nivelación de terreno ($25.00/m²)
- ID 6: Colocación de malla geotextil ($30.00/m²)
- ID 7: Compactación del terreno ($20.00/m²)
- ID 8: Bordes de contención ($50.00/m²)
- ID 9: Retiro de pasto anterior ($18.00/m²)

---

## Estructura de Carpetas

```
scripts/cotizaciones/
├── core/                           # Lógica central del sistema
│   ├── parametros.js              # Manejo de parámetros del sistema
│   ├── calculos.js                # Cálculos de área y precios
│   └── totales.js                 # Cálculo de totales y resúmenes
├── componentes/                    # Componentes de UI y funcionalidad
│   ├── terreno.js                 # Manejo del terreno (regular/irregular)
│   ├── rollos.js                  # Gestión de rollos de pasto
│   ├── productos.js               # Gestión de productos adicionales
│   └── cargar_datos_edicion.js    # Carga de datos para modo edición
├── formulario/                     # Validación y envío de formularios
│   ├── envio.js                   # Envío de cotizaciones (nueva/editar)
│   └── validacion_edicion.js      # Validaciones específicas para edición
├── nueva_cotizacion.js            # Script principal para nueva cotización
├── editar_cotizacion.js           # Script principal para editar cotización
├── formas_irregulares.js          # Manejo de formas geométricas
├── canvas_terreno.js              # Canvas para diseño de terreno
└── modal_canvas.js                # Modal del canvas
```

## Archivos Principales

### nueva_cotizacion.js
- Punto de entrada para crear nuevas cotizaciones
- Importa y configura todos los módulos necesarios
- Inicializa eventos y configuraciones por defecto

### editar_cotizacion.js
- Punto de entrada para editar cotizaciones existentes
- Extiende la funcionalidad de nueva_cotizacion.js
- Incluye lógica específica para cargar datos existentes
- Usa validaciones especializadas para edición
- Campos no modificables: garantía (solo lectura)
- Preserva valores existentes durante la inicialización

## Componentes Modulares

### Core (Lógica Central)
- **parametros.js**: Carga y manejo de parámetros del sistema
- **calculos.js**: Funciones de cálculo (área, precios, descuentos)
- **totales.js**: Cálculo de subtotales, IVA y totales finales

### Componentes (UI y Funcionalidad)
- **terreno.js**: Toggle entre terreno regular/irregular, cálculos de área
- **rollos.js**: Manejo de selección de rollos, colores y cantidades
- **productos.js**: Gestión de productos adicionales
- **cargar_datos_edicion.js**: Funciones específicas para cargar datos en modo edición

### Formulario (Validación y Envío)
- **envio.js**: Función unificada de envío que detecta modo nueva/edición
- **validacion_edicion.js**: Validaciones específicas para el modo edición

## Características de la Separación

### Reutilización
- Los módulos core y componentes son reutilizables entre nueva y edición
- La lógica común está centralizada
- Cada archivo tiene una responsabilidad específica

### Mantenimiento
- Cambios en lógica de negocio se hacen en un solo lugar
- Fácil identificación de dónde hacer modificaciones
- Código más legible y organizado

### Escalabilidad
- Fácil agregar nuevas funcionalidades
- Estructura preparada para nuevos tipos de cotización
- Separación clara entre lógica y presentación

## Uso en PHP

### Nueva Cotización
```php
<script type="module" src="../../scripts/cotizaciones/nueva_cotizacion.js"></script>
```

### Editar Cotización
```php
<script type="module" src="../../scripts/cotizaciones/editar_cotizacion.js"></script>
```

## Variables Globales

El sistema usa algunas variables globales para comunicación entre módulos:

- `window.modoEdicion`: Indica si está en modo edición
- `window.idCotizacion`: ID de la cotización en modo edición
- `window.dibujoTerreno`: Datos del canvas guardado
- `window.formasIrregulares`: Formas geométricas para terreno irregular

## Flujo de Inicialización

1. Cargar parámetros del sistema
2. Configurar eventos del DOM
3. Cargar datos existentes (solo en edición)
4. Inicializar cálculos y totales
5. Configurar interfaz de usuario

Este diseño modular facilita el mantenimiento y la adición de nuevas funcionalidades al sistema de cotizaciones.
