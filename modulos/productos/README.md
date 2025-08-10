# 🎯 Módulo de Productos

## ¿Qué hace este módulo?
Gestiona el catálogo completo de productos que maneja la empresa. Es donde se definen todos los artículos que se pueden inventariar y cotizar, desde rollos de pasto sintético hasta accesorios como clavos y pegamento.

## Archivos del módulo:
- **`lista.php`** - Tabla con todos los productos registrados
- **`agregar_producto.php`** - Formulario para crear nuevos productos
- **`editar_producto.php`** - Modificar información de productos existentes

## ¿Qué datos maneja?

### Información básica:
- **Nombre** (obligatorio) - Nombre del producto
- **Descripción** (opcional) - Detalles del producto
- **Tipo de producto** - Categoría (pasto, accesorio, herramienta, etc.)
- **Unidad de medida** - m², pieza, litro, etc.
- **Imagen** - Foto del producto
- **Estado** - Activo/inactivo/eliminado

### Para pasto sintético (rollos):
- **Modelo** - Tipo específico de pasto
- **Tipo de inventario** - "rollo" para manejo individual
- **Colores asignados** - Qué colores están disponibles

### Para otros productos (unidades):
- **Tipo de inventario** - "unidad" para manejo por cantidad
- **Sin modelo** - No aplica para accesorios

## Tipos de productos que maneja:
- **Pasto sintético** - Rollos con modelos y colores específicos
- **Clavos** - Para instalación en diferentes superficies
- **Pegamento** - Adhesivos para instalación
- **Herramientas** - Equipos de trabajo
- **Accesorios** - Materiales complementarios

## ¿Con qué se conecta?

### Base de datos:
- `productos` - Tabla principal
- `tipo_productos` - Categorías de productos
- `unidades` - Unidades de medida
- `modelos` - Modelos específicos de pasto
- `producto_colores` - Relación producto-color

### Módulos relacionados:
- **Inventario** - Los productos se inventarían aquí
- **Cotizaciones** - Se seleccionan para cotizar
- **Configuración** - Algunos parámetros afectan productos

### PHP Backend:
- `php/productos/` - Scripts para agregar, editar, eliminar

## ¿Cómo funciona?

### Lista de productos:
1. **Vista completa** - Todos los productos ordenados por estado
2. **Búsqueda en tiempo real** - Filtro por nombre o descripción
3. **Estados visuales** - Activos se ven normal, inactivos en gris
4. **Acciones** - Editar o cambiar estado

### Agregar producto:
1. **Información básica** - Nombre, descripción, tipo
2. **Configuración específica** - Según el tipo seleccionado
3. **Modelo** - Solo para pasto sintético
4. **Imagen** - Upload de foto del producto
5. **Validaciones** - Nombre único, campos requeridos

### Editar producto:
- Modifica información básica
- Cambia imagen si es necesario
- No permite cambiar tipo o configuración crítica
- Mantiene historial de cambios

## Estados de productos:
- **Activo** - Disponible para inventariar y cotizar
- **Inactivo** - No disponible pero no eliminado
- **Eliminado** - Borrado lógico del sistema

## Validaciones importantes:
- Nombre único por producto
- Tipo de producto válido
- Unidad de medida asignada
- Imagen en formato correcto
- Modelo solo para tipos que lo requieren

## Diferencias por tipo:

### Pasto sintético (rollos):
- Requiere modelo específico
- Se puede asignar múltiples colores
- Inventario individual por rollo
- Se mide en m²

### Otros productos (unidades):
- Sin modelo requerido
- Sin colores específicos
- Inventario por cantidad total
- Diversas unidades de medida

## Features especiales:
- **Upload de imágenes** - Con validación de formato y tamaño
- **Gestión de estados** - Cambio sin eliminación física
- **Búsqueda inteligente** - Por múltiples campos
- **Validación de unicidad** - Previene productos duplicados

Este módulo es fundamental porque define qué puede manejar el sistema. Todos los demás módulos dependen del catálogo de productos definido aquí.
