# 📋 Módulo de Clientes

## ¿Qué hace este módulo?
Maneja todo lo relacionado con los clientes de la empresa. Básicamente puedes agregar, ver, editar y eliminar clientes del sistema.

## Archivos del módulo:
- **`agregar.php`** - Formulario para dar de alta nuevos clientes
- **`editar.php`** - Formulario para modificar datos de clientes existentes  
- **`lista.php`** - Tabla que muestra todos los clientes registrados

## ¿Qué datos maneja?
Cuando agregas o editas un cliente, el sistema pide:
- **Nombre** (obligatorio) - Nombre del cliente
- **Teléfono** (opcional) - Número de contacto
- **Email** (opcional) - Correo electrónico (valida que tenga formato correcto)
- **Dirección** (opcional) - Dirección física del cliente

## ¿Con qué se conecta?
- **Base de datos**: Tabla `clientes` 
- **PHP backend**: Scripts en `php/clientes/` (agregar.php, editar.php, eliminar.php)
- **Includes**: Usa header.php, head.php, popup.php para la interfaz
- **Sesiones**: Verifica que el usuario esté logueado

## ¿Cómo funciona?
1. **Lista**: Muestra todos los clientes activos en una tabla con opciones para editar o eliminar
2. **Agregar**: Formulario simple que valida que el nombre no esté vacío y el email tenga formato correcto
3. **Editar**: Pre-llena el formulario con los datos actuales del cliente para modificarlos
4. **Eliminar**: Cambia el estado del cliente a "inactivo" en lugar de borrarlo completamente

## Validaciones que hace:
- Nombre obligatorio
- Email con formato válido (debe tener @)
- Confirmación antes de eliminar
- Previene envíos dobles deshabilitando botones

El módulo es bastante directo: CRUD básico para manejar la info de los clientes.
