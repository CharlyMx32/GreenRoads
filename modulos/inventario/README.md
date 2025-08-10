# 📦 Módulo de Inventario

## ¿Qué hace este módulo?
Controla y gestiona todo el stock de productos de la empresa. Maneja dos tipos de inventario: productos por unidad (clavos, pegamento, etc.) y productos por rollos (pasto sintético con colores específicos).

## Archivos del módulo:
- **`lista.php`** - Vista principal del inventario con todos los productos y cantidades
- **`inventariar.php`** - Selección de productos para hacer primer ingreso de inventario
- **`inventariar_rollos.php`** - Formulario específico para rollos de pasto con colores
- **`inventariar_unidad.php`** - Formulario para productos por unidad
- **`editar_cantidad.php`** - Editar inventario de productos por unidad
- **`editar_rollos.php`** - Gestionar inventario de rollos  
- **`editar_rollo_color.php`** - Editar rollos específicos por color

## Tipos de inventario que maneja:

### Productos por Rollo:
- **Pasto sintético** con diferentes modelos y colores
- Cada rollo tiene: largo, ancho, área en m², color específico
- Se rastrea individualmente cada rollo
- Muestra detalles por color: cantidad total, número de rollos, promedio

### Productos por Unidad:
- **Clavos, pegamento, herramientas, etc.**
- Se maneja cantidad total por producto
- Historial de movimientos (entradas/salidas)

## ¿Con qué se conecta?

### Base de datos:
- `inventario_rollos` - Rollos individuales con colores
- `movimientos_inventario` - Historial de entradas/salidas por unidad
- `productos` - Catálogo de productos
- `colores` - Colores disponibles para rollos
- `modelos` - Modelos de pasto

### JavaScript:
- `js/buscador.js` - Búsqueda en tiempo real
- Scripts específicos para cada formulario

### CSS:
- `css/inventario/` - Estilos específicos para cada vista

### PHP Backend:
- `php/inventario/` - Scripts para procesar formularios

## ¿Cómo funciona?

### Lista de inventario:
1. **Vista general** - Todos los productos con cantidades actuales
2. **Códigos de color** - Verde (buen stock), amarillo (poco stock), rojo (agotado)
3. **Detalles expandibles** - Para rollos muestra breakdown por color
4. **Filtros y búsqueda** - Encuentra productos rápidamente

### Proceso de inventariar:
1. **Primer ingreso** - Solo productos que no tienen inventario
2. **Tipo automático** - Detecta si es rollo o unidad
3. **Formulario específico** - Según el tipo de producto
4. **Validaciones** - Cantidades, medidas, colores válidos

### Gestión de rollos:
- **Individual** - Cada rollo se registra por separado
- **Con colores** - Asignación de color específico
- **Dimensiones** - Largo x ancho = área automática
- **Estado** - Disponible, reservado, instalado, etc.

### Gestión por unidad:
- **Movimientos** - Entradas, salidas, reservas, liberaciones
- **Cálculo automático** - Stock actual basado en movimientos
- **Historial completo** - Trazabilidad de cambios

## Estados de productos:
- **Disponible** - En stock listo para usar
- **Reservado** - Apartado para cotización específica
- **Instalado** - Ya utilizado en proyecto
- **Dañado** - No utilizable
- **Agotado** - Sin existencias

## Validaciones importantes:
- Cantidades positivas obligatorias
- Dimensiones válidas para rollos
- Colores asignados correctamente
- Productos existentes antes de inventariar
- Prevención de duplicados

## Features especiales:
- **Vista expandible** - Detalle por colores en rollos
- **Indicadores visuales** - Estados de stock con colores
- **Búsqueda inteligente** - Por nombre, modelo, tipo
- **Cálculos automáticos** - Áreas, promedios, totales
- **Trazabilidad completa** - Historial de todos los movimientos

## Datos que calcula:
- **Stock disponible** por producto
- **Área total** de rollos por color
- **Promedio de área** por rollo
- **Cantidad de rollos** disponibles
- **Última actualización** de inventario

Este módulo es crítico para el negocio porque permite controlar exactamente qué hay disponible para cotizar y vender, evita sobrevender productos agotados y mantiene un control preciso del capital invertido en inventario.
