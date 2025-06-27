<?php
// Configuración de rutas y título de la página
$ROOT = '../..';
$TITULO = "Editar inventario";

include_once $ROOT . '/db/conexion.php';      
include_once $ROOT . '/includes/sesion.php';  
include_once $ROOT . '/includes/config.php';  

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Validar y obtener el ID del producto desde la URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: lista.php?error=id_invalido");
    exit();
}

// Consulta SQL para obtener los datos del producto del inventario
$sql = "SELECT i.id, i.cantidad, i.largo_metros, i.ancho_metros, 
            p.id AS id_producto, p.nombre, p.descripcion, 
            u.simbolo, u.nombre AS unidad_nombre
        FROM inventario i 
        JOIN productos p ON p.id = i.id_producto 
        JOIN unidades u ON u.id = p.id_unidad 
        WHERE i.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();  
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar y filtrar la cantidad recibida
    $nueva_cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_FLOAT);

    // Validar que la cantidad sea un número válido y positivo
    if ($nueva_cantidad === false || $nueva_cantidad < 0) {
        header("Location: editar.php?id=$id&error=cantidad_invalida");
        exit();
    }

    // Calcular la diferencia entre la cantidad nueva y la anterior
    $cantidad_anterior = $producto['cantidad'];
    $diferencia = $nueva_cantidad - $cantidad_anterior;

    // Actualizar la cantidad en la base de datos
    $sql_update = "UPDATE inventario SET cantidad = ?, actualizado_en = NOW() WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("di", $nueva_cantidad, $id);

    if ($stmt_update->execute()) {
        // Determinar el tipo de movimiento (entrada o salida) según la diferencia
        $tipo_movimiento = ($diferencia >= 0) ? 'entrada' : 'salida';
        $cantidad_movimiento = abs($diferencia);  

        // Registrar el movimiento en el historial de inventario
        $sql_movimiento = "INSERT INTO movimientos_inventario 
                        (id_producto, cantidad, tipo_movimiento, motivo, id_admin) 
                        VALUES (?, ?, ?, 'Ajuste manual', ?)";
        $stmt_mov = $conn->prepare($sql_movimiento);
        $id_admin = $_SESSION['usuario_id'] ?? null;
        $stmt_mov->bind_param("idsi", $producto['id_producto'], $cantidad_movimiento, $tipo_movimiento, $id_admin);

        if ($stmt_mov->execute()) {
            header("Location: lista.php?success=actualizado");
        } else {
            header("Location: editar.php?id=$id&error=bd_movimiento");
        }
        $stmt_mov->close();
    } else {
        header("Location: editar.php?id=$id&error=bd_inventario");
    }
    $stmt_update->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"  
    ];
    include_once '../../includes/header.php';
    ?>

    <main class="content">
        <div class="formulario active">
            <div class="seccion-formulario">
                <h1 class="subtitulo-formulario">
                    Editar: <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                </h1>

                <?php if (isset($_GET['error'])): ?>
                    <div class="mensaje-error">
                        <?php
                        switch ($_GET['error']) {
                            case 'cantidad_invalida':
                                echo "La cantidad ingresada no es válida";
                                break;
                            case 'bd':
                                echo "Error al actualizar en la base de datos";
                                break;
                            case 'bd_movimiento':
                                echo "Error al registrar el movimiento";
                                break;
                            case 'bd_inventario':
                                echo "Error al actualizar el inventario";
                                break;
                            default:
                                echo "Ocurrió un error desconocido";
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario para editar la cantidad -->
                <form method="POST">
                    <div class="campo-formulario">
                        <label for="cantidad">
                            Cantidad (<?= htmlspecialchars($producto['simbolo']) ?>)
                            <?php if ($producto['largo_metros'] || $producto['ancho_metros']): ?>
                                <!-- Mostrar dimensiones si es un producto con medidas -->
                                <small class="block">
                                    Rollos de <?= $producto['largo_metros'] ?>m × <?= $producto['ancho_metros'] ?>m
                                </small>
                            <?php endif; ?>
                        </label>
                        <input
                            type="number"
                            id="cantidad"
                            name="cantidad"
                            step="<?= ($producto['largo_metros'] || $producto['ancho_metros']) ? '1' : '0.01' ?>"
                            min="0"
                            class="textfield"
                            value="<?= htmlspecialchars($producto['cantidad']) ?>"
                            required>
                    </div>

                    <!-- Botones de acción -->
                    <div class="acciones-formulario">
                        <button type="submit" class="btnadd">Guardar</button>
                        <button type="button" class="btnadd-filtro" onclick="window.history.back()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Incluir popup para mensajes emergentes -->
    <?php include_once '../../includes/popup.php'; ?>
</body>

</html>