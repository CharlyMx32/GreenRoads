# 🛠️ Módulo de Extras

## ¿Qué hace este módulo?
Maneja los servicios adicionales que se pueden ofrecer junto con las instalaciones de pasto. Son extras que complementan el servicio principal y se incluyen en las cotizaciones.

## Archivos del módulo:
- **`extras.php`** - Lista todos los extras con opciones de gestión
- **`agregar.php`** - Formulario para crear nuevos extras

## ¿Qué datos maneja?
Cada extra tiene:
- **Nombre** (obligatorio) - Nombre del servicio extra
- **Precio** (obligatorio) - Costo fijo del servicio  
- **Descripción** (opcional) - Explicación de qué incluye
- **Estado** - Activo/inactivo para habilitar o deshabilitar

## Ejemplos de extras típicos:
- Preparación de terreno
- Instalación de sistema de riego
- Limpieza posterior
- Garantía extendida
- Transporte de materiales
- Nivelación especializada

## ¿Con qué se conecta?
- **Base de datos**: Tabla `extras`
- **PHP backend**: Scripts en `php/extras/` (agregar.php, cambiar_estado.php, eliminar.php)
- **CSS**: `css/extras/extras.css` y `css/extras/agregar_extra.css`
- **JavaScript**: `scripts/extras/extras.js`
- **Cotizaciones**: Los extras se seleccionan durante la creación de cotizaciones

## ¿Cómo funciona?

### Lista de extras:
1. Muestra todos los extras ordenados por estado (activos primero)
2. Cada extra tiene opciones para editar, activar/desactivar y eliminar
3. Estados visuales claros (verde=activo, gris=inactivo)

### Gestión de extras:
- **Agregar**: Formulario simple con validaciones
- **Editar**: Modifica datos existentes  
- **Cambiar estado**: Activa/desactiva sin eliminar
- **Eliminar**: Borrado lógico (cambia estado a "eliminado")

### En cotizaciones:
1. Los extras activos aparecen como checkboxes
2. Se seleccionan los que aplican al proyecto
3. Su precio se suma automáticamente al total
4. Quedan registrados en la cotización

## Validaciones que hace:
- Nombre obligatorio
- Precio numérico positivo obligatorio
- Confirmación antes de cambiar estados
- Confirmación antes de eliminar
- Previene eliminación accidental

## Estados posibles:
- **Activo** - Disponible para cotizaciones
- **Inactivo** - No disponible pero no eliminado
- **Eliminado** - Borrado lógico, no aparece en listas

## Permisos:
- Solo usuarios con rol de admin pueden acceder a este módulo
- Se valida la sesión y permisos antes de mostrar cualquier contenido

Este módulo es importante porque permite personalizar las cotizaciones con servicios adicionales, aumentando las opciones de venta y permitiendo adaptar las propuestas a las necesidades específicas de cada cliente.
