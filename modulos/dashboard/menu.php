<?php 
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    //$ROOT = $_SERVER['DOCUMENT_ROOT'];

    $ROOT = '../../';

    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    if(!tieneSesion()) {
        header("Location: $URL_ROOT/login");
        exit();
    }

    $sql = "SELECT nombre FROM admins WHERE id = ".$_SESSION['usuario']."";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    $nombreUsuario = $row['nombre'];

    $TITULO = "Bienvenido, $nombreUsuario";
?>

<!DOCTYPE html>
<html>

    <head>
        <?php include_once $ROOT.'/includes/head.php'; ?>
    </head>

    <body>
        <?php 
            // HEADER
            $headerParams = [
                "titulo" => $TITULO,
                "btn_logout" => true
            ];
            include_once $ROOT.'/../includes/header.php';
        ?>

        <div class="ajustes">
            <div class="digital-clock-container">
                <div class="digital-clock">
                    <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
                </div>
            </div>
            <ul id="list">
                <li class="btn-ajustes" onclick="location.href='<?php $ROOT; ?>/modulos/dashboard'">
                    <div class="icono-btn-ajustes"><i class="fa-light fa-chart-column"></i></div>
                    <div class="texto-btn-ajustes">Dashboard</div>
                </li>

                <li class="btn-ajustes" onclick="location.href='<?php $ROOT; ?>/modulos/productos/lista'">
                    <div class="icono-btn-ajustes"><i class="fa-light fa-boxes-stacked"></i></div>
                    <div class="texto-btn-ajustes">Productos</div>
                </li>

                <li class="btn-ajustes" onclick="location.href='<?php $ROOT; ?>/modulos/inventario/lista'">
                    <div class="icono-btn-ajustes"><i class="fa-light fa-truck"></i></div>
                    <div class="texto-btn-ajustes">Inventario</div>
                </li>

                <li class="btn-ajustes" onclick="location.href='<?php $ROOT; ?>/modulos/cotizaciones/lista'">
                    <div class="icono-btn-ajustes"><i class="fa-light fa-list"></i></div>
                    <div class="texto-btn-ajustes">Cotizaciones</div>
                </li>

                <li class="btn-ajustes" onclick="location.href='<?php $ROOT; ?>/modulos/usuarios/lista'">
                    <div class="icono-btn-ajustes"><i class="fa-light fa-users-gear"></i></div>
                    <div class="texto-btn-ajustes">Usuarios</div>
                </li>
            </ul>
        </div>
    </body>

    <script>    
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const period = hours >= 12 ? 'PM' : 'AM';

            // Convertir a formato de 12 horas
            hours = hours % 12 || 12; // Si es 0, mostrar 12

            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = minutes;
            document.getElementById('seconds').textContent = seconds;

            // Agregar el indicador AM/PM
            const periodElement = document.getElementById('period');
            if (!periodElement) {
                const clock = document.querySelector('.digital-clock');
                const periodSpan = document.createElement('span');
                periodSpan.id = 'period';
                periodSpan.textContent = ` ${period}`;
                periodSpan.style.fontSize = '1rem';
                periodSpan.style.marginLeft = '5px';
                clock.appendChild(periodSpan);
            } else {
                periodElement.textContent = ` ${period}`;
            }
        }

        // Actualizar el reloj cada segundo
        setInterval(updateClock, 1000);

        // Inicializar el reloj
        updateClock();
    </script>
</html>