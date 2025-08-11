<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';
$TITULO = "Configuración del Sistema";

include_once $ROOT . 'db/conexion.php';
include_once $ROOT . 'includes/sesion.php';
include_once $ROOT . 'includes/config.php';

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
                $sql_update = "UPDATE parametros_sistema SET valor = '$valor', fecha_actualizacion = NOW() WHERE clave = '$clave'";
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

$parametros = [];
$sql = "SELECT id, clave, valor, descripcion, tipo, editable, fecha_actualizacion 
        FROM parametros_sistema 
        ORDER BY clave ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $partes = explode('_', $row['clave']);
    $categoria = count($partes) > 1 ? ucfirst($partes[0]) : 'General';

    if (!isset($parametros[$categoria])) {
        $parametros[$categoria] = [];
    }
    $parametros[$categoria][] = $row;
}

$tabuladores = [];
$sql_tabuladores = "SELECT * FROM tabuladores ORDER BY tipo, rango_min ASC";
$result_tabuladores = mysqli_query($conn, $sql_tabuladores);
while ($row = mysqli_fetch_assoc($result_tabuladores)) {
    $tabuladores[] = $row;
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
            <p class="descripcion-seccion">Administre los parámetros y tabuladores del sistema.</p>
        </div>

        <!-- Tabs principales -->
        <div class="tabs-principales">
            <div class="tab-principal active" data-tab="parametros">Parámetros</div>
            <div class="tab-principal" data-tab="tabuladores">Tabuladores</div>
        </div>

        <!-- Contenido de Parámetros -->
        <div class="tab-principal-content active" id="parametros">
            <div class="subtabs-container">
                <div class="subtabs">
                    <?php
                    $first = true;
                    foreach ($parametros as $categoria => $params): ?>
                        <div class="subtab <?= $first ? 'active' : '' ?>" data-subtab="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $categoria))) ?>">
                            <?= htmlspecialchars($categoria) ?>
                        </div>
                        <?php $first = false; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="subtabs-content">
                <?php
                $first = true;
                foreach ($parametros as $categoria => $params): ?>
                    <div class="subtab-content <?= $first ? 'active' : '' ?>" id="<?= htmlspecialchars(strtolower(str_replace(' ', '-', $categoria))) ?>">
                        <div class="parametros-grid">
                            <?php foreach ($params as $param): ?>
                                <div class="parametro-card <?= in_array($param['clave'], ['precio_instalacion_m2', 'garantia_default_anios']) ? 'parametro-important' : '' ?>">
                                    <form method="POST">
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
                                                <select name="valor" class="textfield" <?= !$param['editable'] ? 'disabled' : '' ?>>
                                                    <option value="1" <?= $param['valor'] == '1' ? 'selected' : '' ?>>Activo</option>
                                                    <option value="0" <?= $param['valor'] == '0' ? 'selected' : '' ?>>Inactivo</option>
                                                </select>
                                            <?php else: ?>
                                                <input name="valor"
                                                    type="<?= $param['tipo'] === 'decimal' || $param['tipo'] === 'entero' ? 'number' : 'text' ?>"
                                                    step="<?= $param['tipo'] === 'decimal' ? '0.01' : '1' ?>"
                                                    value="<?= htmlspecialchars($param['valor']) ?>"
                                                    class="textfield"
                                                    <?= $param['tipo'] === 'entero' ? 'min="0"' : '' ?>
                                                    <?= !$param['editable'] ? 'disabled' : '' ?>>
                                            <?php endif; ?>
                                        </div>

                                        <div class="parametro-actions">
                                            <input type="hidden" name="clave" value="<?= htmlspecialchars($param['clave']) ?>">
                                            <?php if ($param['editable']): ?>
                                                <button type="submit" class="btn-guardar">
                                                    <i class="fas fa-save"></i> Guardar
                                                </button>
                                            <?php endif; ?>
                                            <div class="parametro-info">
                                                Últ. actualización: <?= date('d/m/Y H:i', strtotime($param['fecha_actualizacion'])) ?>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Contenido de Tabuladores -->
        <div class="tab-principal-content" id="tabuladores">
            <div class="header-tabla">
                <div class="btn-nuevo" onclick="mostrarModalTabulador()">
                    <i class="fa-solid fa-plus"></i>
                </div>

                <div class="subtabs-container">
                    <div class="subtabs">
                        <div class="subtab active" data-subtab="precio_instalacion">Precio Instalación</div>
                        <div class="subtab" data-subtab="descuento_volumen">Descuento por Volumen</div>
                        <div class="subtab" data-subtab="mano_obra">Mano de Obra</div>
                        <div class="subtab" data-subtab="clavos">Clavos</div>
                        <div class="subtab" data-subtab="pegamento">Pegamento</div>
                        <div class="subtab" data-subtab="polvillo">Polvillo</div>
                    </div>
                </div>
            </div>

            <div class="tabla-container">
                <table class="tabla-lista">
                    <thead>
                        <tr>
                            <th>Rango Mínimo</th>
                            <th>Rango Máximo</th>
                            <th>Valor</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Actualizado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tabuladores)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No hay tabuladores configurados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tabuladores as $tabulador): ?>
                                <tr data-id="<?= $tabulador['id'] ?>" data-tipo="<?= $tabulador['tipo'] ?>" class="<?= $tabulador['tipo'] === 'precio_instalacion' ? '' : 'hidden' ?>">
                                    <td><?= number_format($tabulador['rango_min'], 2) ?></td>
                                    <td><?= number_format($tabulador['rango_max'], 2) ?></td>
                                    <td>$<?= number_format($tabulador['valor'], 2) ?></td>
                                    <td><?= htmlspecialchars($tabulador['descripcion'] ?? '') ?></td>
                                    <td><span class="estado <?= $tabulador['activo'] ? 'activo' : 'inactivo' ?>"><?= $tabulador['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
                                    <td><?= date('d/m/Y H:i', strtotime($tabulador['fecha_actualizacion'])) ?></td>
                                    <td>
                                        <button class="btn-editar" onclick="editarTabulador(<?= $tabulador['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-eliminar" onclick="eliminarTabulador(<?= $tabulador['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modalTabulador" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="tituloModalTabulador">Nuevo Rango de Precio</h2>
                <span class="close" onclick="cerrarModalTabulador()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="formTabulador">
                    <input type="hidden" id="tabulador_id" name="id">
                    <div class="form-group">
                        <label for="tipo">Tipo de Tabulador</label>
                        <select id="tipo" name="tipo" class="textfield">
                            <option value="precio_instalacion">Precio Instalación</option>
                            <option value="descuento_volumen">Descuento por Volumen</option>
                            <option value="mano_obra">Mano de Obra</option>
                            <option value="clavos">Clavos</option>
                            <option value="pegamento">Pegamento</option>
                            <option value="polvillo">Polvillo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="rango_min">Rango Mínimo</label>
                        <input type="number" step="0.01" id="rango_min" name="rango_min" class="textfield" required>
                    </div>
                    <div class="form-group">
                        <label for="rango_max">Rango Máximo</label>
                        <input type="number" step="0.01" id="rango_max" name="rango_max" class="textfield" required>
                    </div>
                    <div class="form-group">
                        <label for="valor">Valor</label>
                        <input type="number" step="0.01" id="valor" name="valor" class="textfield" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="textfield"></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="activo" name="activo" checked> Activo
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-guardar" onclick="guardarTabulador()">Guardar</button>
                        <button type="button" class="btn-cancelar" onclick="cerrarModalTabulador()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../scripts/configuracion/tabulador.js"></script>
    <script src="../../scripts/configuracion/parametros.js"></script>
    <?php include_once '../../includes/popup.php'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            displayPopUp();
            displayMensajeExitoso('<?= htmlspecialchars($_SESSION['mensaje_exito']) ?>');
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php elseif (isset($_SESSION['mensaje_error'])): ?>
            displayPopUp();
            displayMensajeError('<?= htmlspecialchars($_SESSION['mensaje_error']) ?>');
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>
    });
    </script>
</body>

</html>