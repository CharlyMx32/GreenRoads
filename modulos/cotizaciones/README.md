# 💰 Módulo de Cotizaciones

## ¿Qué hace este módulo?
Es el corazón del sistema, donde se crean, manejan y procesan todas las cotizaciones para clientes. Permite calcular precios automáticamente según el área, productos seleccionados y configuraciones del sistema.

## Archivos del módulo:
- **`lista.php`** - Tabla con todas las cotizaciones y sus estados
- **`nueva_cotizacion.php`** - Formulario mega completo para crear cotizaciones  
- **`detalle.php`** - Vista detallada de una cotización específica
- **`editar_cotizacion.php`** - Para editar cotizaciones (en desarrollo)

## ¿Qué datos maneja?

### Nueva Cotización:
- **Cliente** - Selección o creación de nuevo cliente
- **Terreno** - Forma (rectángulo, triángulo, círculo) y dimensiones
- **Terreno irregular** - Múltiples formas combinadas
- **Rollos de pasto** - Productos tipo rollo con colores y áreas
- **Otros productos** - Productos por unidad (clavos, pegamento, etc.)
- **Extras** - Servicios adicionales con precios fijos
- **Instalación** - Tipo (tierra, concreto, mixto)
- **Canvas de dibujo** - Para hacer un diseño ilustrativo del terreno

### Datos calculados automáticamente:
- Área total del terreno
- Precios según tabuladores
- IVA configurable
- Total general

## Estados de cotizaciones:
- **Pendiente** - Recién creada, se puede editar y cambiar estado
- **Aceptada** - Cliente aceptó, no se puede modificar
- **Rechazada** - Cliente rechazó
- **Cancelada** - Se canceló por algún motivo

## ¿Con qué se conecta?

### Base de datos:
- `cotizaciones` - Datos generales
- `detalle_cotizacion` - Productos cotizados  
- `cotizacion_extras` - Extras seleccionados
- `clientes` - Info del cliente
- `productos`, `inventario_rollos` - Inventario disponible
- `tabuladores` - Para cálculos de precios

### JavaScript:
- `scripts/cotizaciones/nueva_cotizacion.js` - Lógica del formulario
- `scripts/cotizaciones/canvas_terreno.js` - Canvas para dibujar
- `scripts/cotizaciones/formas_irregulares.js` - Terrenos complejos
- `scripts/cotizaciones/lista.js` - Funciones de la lista

### CSS:
- `css/cotizaciones/cotizaciones.css` - Estilos específicos

### PHP Backend:
- `php/cotizaciones/` - Scripts para guardar, editar, cambiar estados

## ¿Cómo funciona?

### Proceso de cotización:
1. **Seleccionar cliente** (o crear uno nuevo)
2. **Definir terreno** - Regular o irregular con formas múltiples
3. **Dibujar en canvas** - Ilustración visual del proyecto  
4. **Seleccionar rollos** - Con colores específicos y áreas
5. **Agregar productos** - Cantidades según necesidad
6. **Extras opcionales** - Servicios adicionales
7. **Calcular automáticamente** - Precios según configuración
8. **Guardar** - Se crea en estado "pendiente"

### Gestión de estados:
- Solo las pendientes se pueden editar
- Los cambios de estado son irreversibles
- Se valida disponibilidad de inventario
- Se pueden reservar rollos específicos

## Validaciones importantes:
- Cliente obligatorio
- Área mínima del terreno  
- Disponibilidad de inventario
- Precios actualizados según tabuladores
- Confirmaciones antes de cambiar estados

## Features especiales:
- **Canvas interactivo** - Para dibujar el diseño del terreno
- **Cálculo automático** - Precios se actualizan en tiempo real
- **Gestión de inventario** - Verifica disponibilidad de rollos
- **Terrenos complejos** - Soporte para formas irregulares múltiples
- **Responsive** - Funciona bien en móviles y tablets

Este módulo es súper completo y es donde se genera la mayor parte del valor del negocio, automatizando cálculos complejos y facilitando el proceso de ventas.
