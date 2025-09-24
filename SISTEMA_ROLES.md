# Sistema de Roles - GreenRoads

## Roles Disponibles

### 1. Administrador (ID: 1)
- **Acceso completo** al sistema
- Puede gestionar:
  - Usuarios y roles
  - Productos e inventario
  - Cotizaciones (todas)
  - Instalaciones (todas)
  - Citas (todas)
  - Clientes
  - Configuración del sistema
  - Extras y parámetros

### 2. Vendedor (ID: 2)
- **Acceso limitado** orientado a ventas
- Puede gestionar:
  - Cotizaciones (solo las propias)
  - Citas (solo las propias)
  - Clientes
- **NO tiene acceso** a:
  - Usuarios
  - Productos
  - Inventario
  - Instalaciones
  - Configuración

### 3. Instalador (ID: 3)
- **Acceso muy limitado** para técnicos de campo
- Puede gestionar:
  - Instalaciones (solo las asignadas a él)
- **NO tiene acceso** a:
  - Usuarios
  - Productos
  - Inventario
  - Cotizaciones
  - Citas
  - Clientes
  - Configuración

## Funciones de Permisos

El archivo `includes/sesion.php` contiene todas las funciones de verificación de permisos:

### Funciones Básicas
- `esAdmin()` - Verifica si es administrador
- `esVendedor()` - Verifica si es vendedor
- `esInstalador()` - Verifica si es instalador

### Funciones de Validación Específica
- `puedeVerCotizacion($id_admin_cotizacion)` - Verifica si puede ver una cotización específica
- `puedeVerCita($id_admin_cita)` - Verifica si puede ver una cita específica
- `puedeVerInstalacion($id_tecnico_responsable)` - Verifica si puede ver una instalación específica

## Filtrado de Datos

### Vendedores
- Solo ven las cotizaciones que ellos crearon (`c.id_admin = usuario_actual`)
- Solo ven las citas que ellos agendaron (`c.id_admin = usuario_actual`)

### Instaladores  
- Solo ven las instalaciones asignadas a ellos (`i.tecnico_responsable = usuario_actual`)

### Administradores
- Ven todos los registros sin restricciones

### Validaciones de Front-end
- `modulos/dashboard/menu.php` - Menú con permisos por rol
- `modulos/cotizaciones/lista.php` - Lista filtrada por rol
- `modulos/citas/lista.php` - Lista filtrada por rol
- `modulos/instalacion/lista.php` - Lista filtrada por rol
- Todos los archivos de módulos tienen validaciones de acceso

### Archivos Core
- `includes/sesion.php` - Funciones de roles y permisos

