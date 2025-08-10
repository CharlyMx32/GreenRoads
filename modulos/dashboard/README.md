# 🏠 Módulo Dashboard

## ¿Qué hace este módulo?
Es la pantalla principal del sistema, el menú de navegación donde el usuario ve todas las opciones disponibles después de loguearse. Es como el "escritorio" de GreenRoads.

## Archivos del módulo:
- **`menu.php`** - Menú principal con todas las opciones del sistema

## ¿Qué muestra?

### Información del usuario:
- **Saludo personalizado** - "Bienvenido, [Nombre del usuario]"  
- **Reloj digital** - Hora actual en tiempo real con formato AM/PM

### Opciones de navegación:
- **Dashboard** - Vuelve al menú principal
- **Productos** - Gestión del catálogo de productos
- **Inventario** - Control de stock y rollos
- **Cotizaciones** - Crear y gestionar cotizaciones
- **Clientes** - Base de datos de clientes
- **Usuarios** - Gestión de usuarios (solo admins)
- **Extras** - Servicios adicionales (solo admins)
- **Parámetros** - Configuración del sistema (solo admins)

## ¿Con qué se conecta?
- **Base de datos**: Tabla `admins` para obtener nombre del usuario
- **Sesiones**: Valida que el usuario esté logueado y obtiene su rol
- **Includes**: Usa header.php, head.php para estructura
- **Módulos**: Enlaces a todos los demás módulos del sistema

## ¿Cómo funciona?

### Control de acceso:
1. **Valida sesión** - Si no está logueado, redirige al login
2. **Obtiene datos** - Nombre del usuario desde la base de datos
3. **Verifica permisos** - Muestra opciones según el rol (admin o no)

### Permisos por rol:
- **Todos los usuarios**: Dashboard, Productos, Inventario, Cotizaciones, Clientes
- **Solo admins**: Usuarios, Extras, Parámetros

### Features:
- **Reloj en tiempo real** - Se actualiza cada segundo
- **Navegación intuitiva** - Iconos claros para cada sección
- **Responsive** - Se adapta a diferentes tamaños de pantalla
- **Control de roles** - Opciones dinámicas según permisos

## Datos que maneja:
- **ID del usuario** - Desde la sesión
- **Nombre del usuario** - Consulta a base de datos
- **Rol del usuario** - Para mostrar opciones específicas
- **Hora actual** - Para el reloj digital

## Validaciones:
- Sesión activa obligatoria
- Verificación de rol para opciones administrativas
- Redirección automática si no hay sesión

Este módulo es súper simple pero esencial - es la primera impresión que tiene el usuario del sistema y desde donde accede a todo lo demás. Es como el lobby de un hotel, te orienta hacia donde necesitas ir.
