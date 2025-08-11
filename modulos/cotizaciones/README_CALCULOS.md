# Sistema de Cálculos de Cotizaciones - GreenRoads

## Descripción General

Este documento detalla completamente el sistema de cálculos que se realizan en el módulo de cotizaciones, explicando cada componente que contribuye al total final, sus fuentes de datos y la lógica matemática implementada.

## Arquitectura del Sistema

### Archivos Principales
- **Frontend Principal**: `scripts/cotizaciones/core/totales.js`
- **Backend Guardado**: `php/cotizaciones/guardar_cotizacion.php`
- **Configuración**: `scripts/cotizaciones/core/parametros.js`
- **Precios Rollos**: `php/cotizaciones/obtener_precio_inventario.php`
- **Materiales Auto**: `php/cotizaciones/calcular_materiales.php`
- **Costos Productos**: `php/productos/obtener_costo.php`

## Componentes del Cálculo Total

El total de una cotización se compone de **10 elementos principales**:

### 1. COSTO DE ROLLOS DE PASTO 🌱

#### Fuente de Datos:
- **Con Inventario**: `inventario_rollos.costo_unitario` (costo total del rollo) + `inventario_rollos.area_m2` (área total del rollo)
- **Sin Inventario**: `productos.costo_base` (costo base) + área estándar de 200m²

#### Lógica de Cálculo:
```javascript
// Obtener información del rollo específico
const infoRollo = await obtenerInfoRollo(idProducto, idColor, areaUsada);

// Calcular proporción usada del rollo
const proporcionUsada = areaUsada / infoRollo.areaTotalRollo;

// Cálculo: proporción × costo_total_rollo
const costoReal = proporcionUsada * infoRollo.costoTotalRollo;
```

#### Ejemplo Práctico:
```
Área cotizada: 9 m²
Área total del rollo: 200 m²
Costo total del rollo: $1,000.00

Cálculo: (9 ÷ 200) × $1,000 = 0.045 × $1,000 = $45.00
```

#### Proceso Detallado:
1. **Consulta inventario**: Busca rollos disponibles del producto y color específico
2. **Si hay inventario**: 
   - Usa `costo_unitario` (representa el costo total del rollo completo)
   - Usa `area_m2` (representa el área total del rollo completo)
3. **Si no hay inventario**: 
   - Usa `costo_base` de la tabla productos
   - Asume área estándar de 200m² esto hay que verlo 
4. **Aplicación**: `(área_usada ÷ área_total_rollo) × costo_total_rollo = costo_proporcional`

#### Importante:
- **El costo del rollo NO cambia** aunque se corte
- **Solo se cobra la proporción** del área solicitada
- **Cada rollo** puede tener diferente costo y área total
- **Los rollos cortados** mantienen el mismo costo unitario original

#### Endpoint: `php/cotizaciones/obtener_precio_inventario.php`

---

### 2. DESCUENTO POR VOLUMEN 📊

#### Fuente de Datos:
- **Tabla**: `tabulador_precios` (tipo = 'descuento_volumen')

#### Lógica de Cálculo:
```javascript
// Obtener porcentaje según área total del proyecto
const descuentoVolumenPorcentaje = obtenerValorTabulador('descuento_volumen', area) / 100;

// Aplicar descuento SOLO al costo de rollos
const descuentoRollos = costoRollosReal * descuentoVolumenPorcentaje;
const precioRollosConDescuento = costoRollosReal - descuentoRollos;
```

#### Ejemplo de Tabulador:
```
Área (m²)    | Descuento
0-100        | 0%
101-300      | 5%
301-500      | 10%
501-1000     | 15%
1000+        | 20%
```

#### Aplicación:
- **Solo afecta a rollos**: No se aplica a productos, extras o instalación
- **Por área total**: Se basa en el área total del proyecto, no por rollo individual
- **Acumulativo**: Mayor área = mayor descuento

---

### 3. MATERIALES AUTOMÁTICOS 🔧

#### Fuente de Datos:
- **Archivo**: `php/cotizaciones/calcular_materiales.php`
- **Basado en**: Tipo de instalación + área total

#### Lógica de Cálculo:
```php
// Determinar materiales según tipo de instalación
$materialesNecesarios = calcularMaterialesNecesarios($tipo_instalacion, $area);

// Para cada material calculado automáticamente
foreach ($materialesNecesarios as $material) {
    $costo = $cantidad_necesaria * $productos['costo_base'];
}
```

### 4. PRODUCTOS MANUALES 🛠️

#### Fuente de Datos:
- **Tabla**: `productos.costo_base`
- **Selección**: Manual por el usuario en interfaz

#### Lógica de Cálculo:
```javascript
// Para cada producto agregado manualmente
document.querySelectorAll('#productos_container .product-item').forEach(item => {
    const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
    const cantidad = parseFloat(input.value) || 0;
    const subtotal = precio * cantidad;
    costoProductos += subtotal;
});
```

#### Ejemplos de Productos Manuales:
- **Herramientas especiales**: Cortadoras, niveladoras
- **Accesorios**: Clavos, tornillos, grapas
- **Adhesivos adicionales**: Para casos especiales
- **Elementos decorativos**: Bordes, separadores
- **Equipos de seguridad**: Señalización, protecciones

#### Diferencia con Automáticos:
- **Manuales**: El usuario decide qué agregar y en qué cantidad
- **Automáticos**: El sistema calcula según tipo de instalación

---

### 5. EXTRAS 🎯

#### Fuente de Datos:
- **Tabla**: `extras`
- **Selección**: Checkboxes en interfaz de cotización

#### Lógica de Cálculo:
```javascript
// Suma directa de extras seleccionados (precio fijo)
document.querySelectorAll('.extra-check:checked').forEach(extra => {
    const precio = parseFloat(extra.dataset.precio);
    extras += precio;
});
```

#### Características:
- **Precio fijo**: No depende del área
- **Opcionales**: El cliente decide si los requiere
- **Configurables**: Admin puede agregar/modificar desde panel

---

### 6. PRECIO DE INSTALACIÓN 💼

#### Fuente de Datos:
- **Tabla**: `tabulador_precios` (tipo = 'precio_instalacion')

#### Lógica de Cálculo:
```javascript
// Precio por m² según área total del proyecto
const precioInstalacion = obtenerValorTabulador('precio_instalacion', area);
const costoInstalacion = area * precioInstalacion;
```

#### Ejemplo de Tabulador:
```
Área (m²)    | Precio/m²
0-50         | $120.00
51-100       | $110.00
101-300      | $100.00
301-500      | $90.00
501-1000     | $80.00
1000+        | $70.00
```

#### Factores que Determina:
- **Complejidad básica** del trabajo
- **Economías de escala** (mayor área = menor precio/m²)
- **Costos fijos** distribuidos

---

### 7. MANO DE OBRA ADICIONAL 👷

#### Fuente de Datos:
- **Tabla**: `tabulador_precios` (tipo = 'mano_obra')

#### Lógica de Cálculo:
```javascript
// Costo adicional por m² según complejidad del proyecto
const manoObraPorM2 = obtenerValorTabulador('mano_obra', area);
const costoManoObra = area * manoObraPorM2;
```

#### Ejemplo de Tabulador:
```
Área (m²)    | Mano Obra/m²
0-100        | $25.00
101-300      | $20.00
301-500      | $18.00
501-1000     | $15.00
1000+        | $12.00
```

---

### 8. SUBTOTAL �

#### Cálculo del Subtotal:
```javascript
const subtotal = precioRollosConDescuento +   // Rollos con descuento aplicado
                costoProductos +               // Productos manuales
                costoMaterialesAuto +          // Materiales automáticos  
                extras +                       // Servicios extras
                costoInstalacion +             // Instalación básica
                costoManoObra;                 // Mano de obra 
```

#### Orden de Cálculo:
1. **Rollos**: Costo base sin descuento
2. **Descuento**: Se aplica solo a rollos
3. **Productos**: Manuales + automáticos
4. **Servicios**: Instalación + mano de obra
5. **Extras**: Servicios adicionales
6. **Suma**: Todos los componentes

---

### 9. IVA (IMPUESTO) 💰

#### Fuente de Datos:
- **Tabla**: `parametros_sistema.iva_porcentaje`
- **Control**: Checkbox "Aplicar IVA" en interfaz

#### Lógica de Cálculo:
```javascript
// IVA aplicable (configurable y opcional)
const aplicarIva = document.getElementById('aplicar_iva')?.checked ?? true;
const iva = aplicarIva ? subtotal * parametrosSistema.ivaPorcentaje : 0;
```

#### Configuración:
- **Default México**: 16%
- **Opcional**: El usuario puede desactivar IVA
- **Base**: Se aplica sobre el subtotal completo

---

### 10. TOTAL FINAL 🎯

#### Cálculo Final:
```javascript
const total = subtotal + iva;
```

#### Resumen del Flujo:
```
1. Rollos (con proporción)        = $X,XXX.XX
2. Descuento por volumen         = -$XXX.XX
3. Materiales automáticos        = $XXX.XX
4. Productos manuales            = $XXX.XX
5. Extras                        = $XXX.XX
6. Instalación                   = $XXX.XX
7. Mano de obra                  = $XXX.XX
   ─────────────────────────────────────────
   SUBTOTAL                      = $X,XXX.XX
8. IVA (16%)                     = $XXX.XX
   ─────────────────────────────────────────
   TOTAL FINAL                   = $X,XXX.XX
```

## Sistema de Tabuladores

### Estructura de Base de Datos:
```sql
CREATE TABLE tabulador_precios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('precio_instalacion', 'descuento_volumen', 'mano_obra'),
    nombre VARCHAR(100) NOT NULL,
    rango_min DECIMAL(10,2) NOT NULL,
    rango_max DECIMAL(10,2) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Tipos de Tabuladores:

#### 1. Precio de Instalación (`precio_instalacion`)
- **Propósito**: Definir precio base por m² según volumen del proyecto
- **Aplicación**: `área_total × precio_por_m²`
- **Lógica**: Mayor área = menor precio por m² (economía de escala)

#### 2. Descuento por Volumen (`descuento_volumen`)
- **Propósito**: Aplicar descuentos a rollos según área total
- **Unidad**: Porcentaje (%)
- **Aplicación**: `costo_rollos × (descuento% ÷ 100)`
- **Lógica**: Incentivar proyectos grandes

#### 3. Mano de Obra (`mano_obra`)
- **Propósito**: Costo adicional
- **Aplicación**: `área_total × mano_obra_por_m²`
- **Lógica**: Complejidad técnica adicional

### Configuración de Rangos:
```javascript
// Función para obtener valor según área
function obtenerValorTabulador(tipo, area) {
    const tabulador = parametrosSistema[tipo];
    const rango = tabulador.find(t => 
        t.activo && 
        area >= parseFloat(t.rango_min) && 
        area <= parseFloat(t.rango_max)
    );
    return rango ? parseFloat(rango.valor) : 0;
}
```

## Flujo de Datos Completo

### 1. Inicialización del Sistema
```
Carga de página → parametros.js
├── cargarParametrosSistema()
├── fetch('obtener_parametros.php') → IVA, garantías
├── fetch('obtener_tabulador.php?tipo=precio_instalacion')
├── fetch('obtener_tabulador.php?tipo=descuento_volumen')
└── fetch('obtener_tabulador.php?tipo=mano_obra')
```

### 2. Cálculo de Rollos (Tiempo Real)
```
Usuario selecciona rollo → obtenerInfoRollo()
└── fetch('obtener_precio_inventario.php')
    ├── Consulta: inventario_rollos (si hay stock)
    │   └── Retorna: costo_total_rollo, area_total_rollo
    └── Consulta: productos.costo_base (si no hay stock)
        └── Retorna: costo_base, area_estandar=200m²
```

### 3. Materiales Automáticos
```
Usuario cambia tipo instalación → calcularMaterialesAutomaticos()
└── fetch('calcular_materiales.php')
    ├── Determina materiales por tipo
    ├── Calcula cantidades por área
    └── Retorna productos con productos.costo_base
```

### 4. Actualización en Tiempo Real
```
Cualquier cambio → actualizarTotales()
├── Recalcula todos los componentes
├── Aplica tabuladores según área actual
├── Actualiza subtotal, IVA y total
└── Refresca interfaz con nuevos valores
```

### 5. Guardado de Cotización
```
Usuario guarda → fetch('guardar_cotizacion.php')
├── Valida todos los datos
├── Procesa rollos con reserva/corte automático
├── Guarda detalle con precios calculados
└── Confirma transacción
```

## Casos Especiales y Manejo de Errores

### Sin Inventario Disponible
```
Flujo:
1. Buscar en inventario_rollos (producto + color)
2. Si no encuentra → usar productos.costo_base
3. Asumir área estándar de 200m²
4. Registrar en log para seguimiento
5. Continuar cotización normalmente
6. Marcar para revisión posterior
```

### Área Fuera de Rangos de Tabulador
```
Flujo:
1. Si área < rango_min → usar valor del rango más bajo
2. Si área > rango_max → usar valor del rango más alto  
3. Generar log de advertencia
4. Notificar para revisión manual
5. Continuar con valor de borde
```

### Productos Descontinuados o Sin Precio
```
Flujo:
1. Usar último costo_base conocido
2. Marcar cotización para revisión
3. Generar alerta administrativa
4. Log detallado del problema
5. Permitir continuar con precio base
```

### Rollos Insuficientes
```
Flujo:
1. Verificar disponibilidad total
2. Si hay parcial → usar precio promedio disponible
3. Si no hay nada → usar costo_base del producto
4. Reservar lo disponible
5. Generar nota de faltante
6. Continuar con precio estimado
```

### Actualización de Precios
1. **Productos**: 
   - Actualizar `productos.costo_base` desde panel admin
   - Efecto inmediato en nuevas cotizaciones
   
2. **Tabuladores**: 
   - Modificar desde "Configuración → Tabuladores"
   - Cambios afectan cálculos en tiempo real
   
3. **IVA**: 
   - Cambiar en "Configuración → Parámetros del Sistema"
   - Aplica a todas las cotizaciones nuevas
