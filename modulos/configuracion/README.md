# ⚙️ Módulo de Configuración

## ¿Qué hace este módulo?
Es el panel de control del sistema donde configuras todos los parámetros y tabuladores que usa GreenRoads para funcionar. Básicamente aquí defines los precios, rangos, márgenes y configuraciones generales.

## Archivos del módulo:
- **`parametros.php`** - Panel principal de configuración con tabs para parámetros y tabuladores

## ¿Qué configuraciones maneja?

### Parámetros del Sistema:
Configuraciones generales organizadas por categorías como:
- **Precio instalación por m²** - Cuánto cobrar por metro cuadrado de instalación
- **Garantía default** - Años de garantía que se dan por defecto
- **Configuraciones generales** - Otras configuraciones del sistema

### Tabuladores:
Tablas de precios y rangos para diferentes conceptos:
- **Precio Instalación** - Rangos de precios según metros cuadrados
- **Clavos** - Costos de clavos según rangos
- **Pegamento** - Costos de pegamento según rangos  
- **Margen de Utilidad** - Porcentajes de ganancia según rangos
- **Polvillo** - Costos de polvillo según rangos

## ¿Con qué se conecta?
- **Base de datos**: Tablas `parametros_sistema` y `tabuladores`
- **PHP backend**: Scripts en `scripts/configuracion/` para manejar tabuladores
- **CSS**: `css/parametros.css` para el diseño específico
- **JavaScript**: `scripts/configuracion/parametros.js` y `tabulador.js` para funcionalidad

## ¿Cómo funciona?

### Parámetros:
1. Se muestran organizados por categorías en subtabs
2. Cada parámetro tiene su descripción y tipo de dato
3. Solo los marcados como "editable" se pueden modificar
4. Guarda cambios individualmente por parámetro

### Tabuladores:
1. Se organizan por tipo en subtabs
2. Puedes agregar, editar y eliminar rangos de precios
3. Cada rango tiene mínimo, máximo, valor y descripción
4. Se pueden activar/desactivar según necesidad

## Validaciones:
- Solo permite editar parámetros marcados como editables
- Valida tipos de datos (números, decimales, boolean)
- Confirma cambios antes de guardar
- Valida rangos en tabuladores para que no se traslapen

## Datos importantes que maneja:
- **precio_instalacion_m2** - Precio base por metro cuadrado
- **garantia_default_anios** - Años de garantía estándar
- Rangos de precios para cotizaciones automatizadas
- Márgenes de utilidad por volumen de venta

Este módulo es crítico porque define cómo se calculan los precios en las cotizaciones y qué configuraciones usa todo el sistema.
