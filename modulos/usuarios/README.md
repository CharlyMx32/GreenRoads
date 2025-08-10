# 👥 Módulo de Usuarios

## ¿Qué hace este módulo?
Gestiona las cuentas de los usuarios que pueden acceder al sistema GreenRoads. Permite crear, editar y administrar perfiles de empleados con diferentes niveles de acceso.

## Archivos del módulo:
- **`lista.php`** - Lista todos los usuarios registrados en el sistema
- **`agregar.php`** - Formulario para crear nuevos usuarios
- **`informacion.php`** - Vista y edición del perfil de usuario

## ¿Qué datos maneja?

### Información básica:
- **Nombre** (obligatorio) - Nombre del usuario
- **Apellido** (obligatorio) - Apellido del usuario  
- **Usuario** (obligatorio) - Username para login (único)
- **Contraseña** (obligatorio) - Password encriptada
- **Rol** - Tipo de usuario (admin, empleado, etc.)
- **Estado** - Activo/inactivo/eliminado
- **Última conexión** - Registro de último acceso

## Tipos de roles:
- **Admin** - Acceso completo al sistema
- **Empleado** - Acceso limitado a funciones operativas
- **Otros roles** - Según configuración del sistema

## ¿Con qué se conecta?

### Base de datos:
- `admins` - Tabla principal de usuarios
- `roles` - Tipos de usuario disponibles
- `sesiones` - Control de sesiones activas

### Módulos relacionados:
- **Login** - Validación de credenciales
- **Dashboard** - Menú según permisos del rol
- **Todos los módulos** - Validación de acceso por rol

### PHP Backend:
- `php/usuarios/` - Scripts para CRUD de usuarios
- `php/sesion/` - Manejo de autenticación

## ¿Cómo funciona?

### Lista de usuarios:
1. **Vista completa** - Todos los usuarios excepto eliminados
2. **Estados visuales** - Activos normal, inactivos en gris
3. **Información de acceso** - Última conexión registrada
4. **Búsqueda en tiempo real** - Por nombre o usuario

### Agregar usuario:
1. **Datos básicos** - Nombre, apellido, usuario
2. **Credenciales** - Usuario único y contraseña segura
3. **Asignación de rol** - Según permisos necesarios
4. **Estado inicial** - Activo por defecto

### Información de usuario:
- **Ver perfil** - Datos completos del usuario
- **Editar información** - Modificar datos básicos
- **Cambiar contraseña** - Actualización de credenciales
- **Historial** - Últimas conexiones y actividad

## Validaciones importantes:
- **Usuario único** - No duplicados en el sistema
- **Contraseña segura** - Mínimo de caracteres y complejidad
- **Campos obligatorios** - Nombre, apellido, usuario, contraseña
- **Formato de email** - Si se incluye email debe ser válido

## Estados de usuarios:
- **Activo** - Puede acceder al sistema normalmente
- **Inactivo** - Cuenta suspendida, no puede loguearse
- **Eliminado** - Borrado lógico, no aparece en listas

## Control de acceso por roles:

### Admin:
- Acceso a todos los módulos
- Gestión de usuarios
- Configuración del sistema
- Reportes y estadísticas

### Empleado:
- Módulos operativos (productos, inventario, cotizaciones)
- Sin acceso a configuración
- Sin gestión de usuarios

## Seguridad implementada:
- **Contraseñas encriptadas** - Hash seguro en base de datos
- **Validación de sesiones** - Verificación en cada página
- **Control de permisos** - Acceso según rol asignado
- **Registro de actividad** - Log de conexiones

## Features especiales:
- **Búsqueda inteligente** - Filtro por múltiples campos
- **Estados visuales** - Colores según estado del usuario
- **Gestión de roles** - Asignación dinámica de permisos
- **Último acceso** - Información de actividad reciente

## Permisos del módulo:
- **Solo admins** pueden acceder a gestión de usuarios
- Se valida rol antes de mostrar cualquier contenido
- Empleados no pueden ver ni modificar usuarios

Este módulo es crítico para la seguridad del sistema, ya que controla quién puede acceder y qué pueden hacer dentro de GreenRoads.
