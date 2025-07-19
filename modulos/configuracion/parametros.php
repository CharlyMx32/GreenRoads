<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';
$TITULO = "Parámetros del Sistema";

include_once $ROOT . 'db/conexion.php';
include_once $ROOT . 'includes/sesion.php';
include_once $ROOT . 'includes/config.php';

// if (!tieneSesion() || !tienePermiso('admin_parametros')) {
//     header("Location: $URL_ROOT/login");
//     exit();
// }

function sanitizar($data, $conn)
{
    return htmlspecialchars(mysqli_real_escape_string($conn, trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clave'], $_POST['valor'])) {
        $clave = sanitizar($_POST['clave'], $conn);
        $valor = sanitizar($_POST['valor'], $conn);

        $sql_check = "SELECT editable FROM parametros_sistema WHERE clave = '$clave'";
        $result = mysqli_query($conn, $sql_check);

        if (mysqli_num_rows($result)) {
            $row = mysqli_fetch_assoc($result);
            if ($row['editable']) {
                $sql_update = "UPDATE parametros_sistema SET valor = '$valor' WHERE clave = '$clave'";
                if (mysqli_query($conn, $sql_update)) {
                    $_SESSION['mensaje_exito'] = "Parámetro actualizado correctamente";
                } else {
                    $_SESSION['mensaje_error'] = "Error al actualizar el parámetro";
                }
            } else {
                $_SESSION['mensaje_error'] = "Este parámetro no es editable";
            }
        } else {
            $_SESSION['mensaje_error'] = "Parámetro no encontrado";
        }
    }
    header("Location: parametros.php");
    exit();
}

// Obtener parámetros y agrupar por prefijo de clave (ej: "email_", "general_")
$parametros = [];
$sql = "SELECT id, clave, valor, descripcion, tipo, editable, fecha_actualizacion 
        FROM parametros_sistema 
        ORDER BY clave ASC";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    // Determinar categoría basada en prefijo de clave
    $partes = explode('_', $row['clave']);
    $categoria = count($partes) > 1 ? ucfirst($partes[0]) : 'General';

    if (!isset($parametros[$categoria])) {
        $parametros[$categoria] = [];
    }
    $parametros[$categoria][] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . 'includes/head.php'; ?>
    <link rel="stylesheet" href="<?= $URL_ROOT ?>css/parametros.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="main-container">
        <div class="header-parametros">
            <h1 class="titulo-seccion">Configuración del Sistema</h1>
            <p class="descripcion-seccion">Administre los parámetros globales del sistema de pastos sintéticos.</p>

            <div class="search-container">
                <input type="text" class="search-input" placeholder="Buscar parámetro..." id="searchParam">
            </div>
        </div>

        <div class="categorias-parametros">
            <div class="tabs">
                <?php
                $first = true;
                foreach ($parametros as $categoria => $params): ?>
                    <div class="tab <?= $first ? 'active' : '' ?>" data-tab="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $categoria))) ?>">
                        <?= htmlspecialchars($categoria) ?>
                        <span class="badge"><?= count($params) ?></span>
                    </div>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>

            <?php
            $first = true;
            foreach ($parametros as $categoria => $params): ?>
                <div class="tab-content <?= $first ? 'active' : '' ?>" id="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $categoria))) ?>">
                    <div class="parametros-grid">
                        <?php foreach ($params as $param): ?>
                            <form method="POST" class="parametro-card <?= in_array($param['clave'], ['precio_instalacion_m2', 'garantia_default_anios']) ? 'parametro-important' : '' ?>">
                                <div class="header-parametro">
                                    <span class="clave"><?= htmlspecialchars($param['clave']) ?></span>
                                    <?php if (!$param['editable']): ?>
                                        <span class="badge no-editable">Solo lectura</span>
                                    <?php endif; ?>
                                </div>

                                <div class="descripcion" title="<?= htmlspecialchars($param['descripcion']) ?>">
                                    <?= htmlspecialchars($param['descripcion']) ?>
                                </div>

                                <div class="campo-valor">
                                    <?php if ($param['tipo'] === 'boolean'): ?>
                                        <select name="valor" class="textfield">
                                            <option value="1" <?= $param['valor'] == '1' ? 'selected' : '' ?>>Activo</option>
                                            <option value="0" <?= $param['valor'] == '0' ? 'selected' : '' ?>>Inactivo</option>
                                        </select>
                                    <?php else: ?>
                                        <input name="valor"
                                            type="<?= $param['tipo'] === 'decimal' || $param['tipo'] === 'entero' ? 'number' : 'text' ?>"
                                            step="<?= $param['tipo'] === 'decimal' ? '0.01' : '1' ?>"
                                            value="<?= htmlspecialchars($param['valor']) ?>"
                                            class="textfield"
                                            <?= $param['tipo'] === 'entero' ? 'min="0"' : '' ?>>
                                    <?php endif; ?>
                                </div>

                                <div class="parametro-actions">
                                    <input type="hidden" name="clave" value="<?= htmlspecialchars($param['clave']) ?>">
                                    <button type="submit" class="btn-guardar">
                                        <i class="fas fa-save"></i> Guardar
                                    </button>
                                    <div class="parametro-info">
                                        Últ. actualización: <?= date('d/m/Y', strtotime($param['fecha_actualizacion'])) ?>
                                    </div>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php $first = false; ?>
            <?php endforeach; ?>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                    tab.classList.add('active');
                    const tabId = tab.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            // Búsqueda
            const searchInput = document.getElementById('searchParam');
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const parametros = document.querySelectorAll('.parametro-card');

                parametros.forEach(param => {
                    const clave = param.querySelector('.clave').textContent.toLowerCase();
                    const desc = param.querySelector('.descripcion').textContent.toLowerCase();

                    if (clave.includes(searchTerm) || desc.includes(searchTerm)) {
                        param.style.display = 'flex';
                    } else {
                        param.style.display = 'none';
                    }
                });
            });

            // Manejar envío de formularios
            document.querySelectorAll('.parametro-card form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const btnSubmit = this.querySelector('button[type="submit"]');

                    // Deshabilitar botón durante la solicitud
                    btnSubmit.disabled = true;
                    btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

                    // Mostrar mensaje de carga
                    displayPopUp("Actualizando parámetro...");

                    fetch('../../php/configuracion/parametros.php?t=' + Date.now(), {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) throw new Error('Error en la respuesta del servidor');
                            return response.json();
                        })
                        .then(data => {
                            if (data.status == 0) {
                                throw new Error(data.mensaje || "Error al actualizar el parámetro");
                            }
                            displayMensajeExitoso(data.mensaje);

                            // Actualizar la fecha de modificación en la tarjeta
                            const fechaElement = this.querySelector('.parametro-info');
                            if (fechaElement) {
                                fechaElement.textContent = 'Últ. actualización: ' + new Date().toLocaleDateString('es-MX');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            displayMensajeError(error.message);
                        })
                        .finally(() => {
                            btnSubmit.disabled = false;
                            btnSubmit.innerHTML = '<i class="fas fa-save"></i> Guardar';
                        });
                });
            });
        });
    </script>

    <?php include_once '../../includes/popup.php'; ?>
</body>

</html>