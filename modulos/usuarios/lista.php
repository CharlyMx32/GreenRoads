<?php 
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    $TITULO = "Usuarios";
    
    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    if(!tieneSesion()) {
        header("Location: $URL_ROOT/login");
        exit();
    }

    $usuarios = [];
    $sql = "SELECT
    a.*,
    DATE_FORMAT(a.ultima_conexion, '%d/%m/%Y %h:%i %p') AS ultima_conexion
    FROM admins a
    WHERE a.estado <> 'eliminado'
    ORDER BY a.estado ASC, a.nombre ASC";
    $result = mysqli_query($conn, $sql);
    while($row = mysqli_fetch_assoc($result)) {
        $usuarios[] = $row;
    }
?>

<!DOCTYPE html>
<html>

    <head>
        <?php include_once $ROOT.'/includes/head.php'; ?>
        
        <!-- LINKS JS INTERNO -->
        <script src="<?php $ROOT ?>/js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
    </head>

    <body>
        <?php 
            // HEADER
            $headerParams = [
                "buscador" => true,
                "btn_atras" => 'window.history.back()'
            ];
            include_once $ROOT.'/includes/header.php';
        ?>

        <div class="content">
            <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
                <table class="tabla-lista">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Última conexión</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach($usuarios as $usuario) { ?>
                            <tr>
                                <td><?php echo $usuario['nombre'].' '.$usuario['apellido']; ?></td>
                                <td><?php echo $usuario['usuario']; ?></td>
                                <td><?php echo ($usuario['ultima_conexion'] == '' ? 'Sin registro' : $usuario['ultima_conexion']); ?></td>
                                <td><?php echo $usuario['estado']; ?></td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <div class="opcion-tabla-lista editar" onclick="location.href='<?php $ROOT ?>/modulos/usuarios/informacion?u=<?php echo $usuario['id'] ?>'"><i class="fa-solid fa-pen-to-square"></i></div>
                                        <div class="opcion-tabla-lista power" onclick="changeStatus(<?php echo $usuario['id'] ?>, '<?php echo ($usuario['estado'] == 'activo' ? 'inactivo' : 'activo') ?>')" <?php if($usuario['estado'] == 'activo') echo 'style="color: #00dd0b;"'; ?>><i class="fa-solid fa-power-off"></i></div>
                                        <div class="opcion-tabla-lista eliminar" onclick="changeStatus(<?php echo $usuario['id'] ?>, 'eliminado')"><i class="fa-solid fa-trash"></i></div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="btn-nuevo" onclick="location.href='agregar'"><i class="fa-solid fa-plus"></i></div>

        <!-- POPUP -->
        <?php include_once $ROOT.'/includes/popup.php'; ?>
    </body>

    <script>
        setSearcher({
            input: ".textfield-buscador-navegador",
            search_element: "table tbody tr",
            display_type: "table-row"
        });
        
        function changeStatus(id, status) {
            let alertMsg;

            if(status == 'inactivo') alertMsg = "¿Está seguro que desea deshabilitar al usuario?";
            else if(status == 'activo') alertMsg = "¿Está seguro que desea habilitar al usuario?";
            else if(status == 'eliminado') alertMsg = "¿Está seguro que desea eliminar al usuario?";

            if(!confirm(alertMsg)) return false;

            displayPopUp();

            $.post('<?php $ROOT ?>/php/usuarios/cambiar_estado', {
                id: id,
                status: status
            })
            .done(function(data) {
                let respuesta = JSON.parse(data);

                if (respuesta.status == 0) displayMensajeError(respuesta.mensaje);
                else window.location.reload();
            })
            .fail(function() {
                displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
            });
        }
    </script>
</html>