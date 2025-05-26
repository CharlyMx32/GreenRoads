<?php
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    mysqli_autocommit($conn, FALSE);

    $id = $_POST['id'];
    $estado = $_POST['status'];

    mysqli_autocommit($conn, FALSE);

    try {

        if(!tieneSesion()) throw new Exception("Tu sesión ha expirado. Favor de iniciar sesión nuevamente.");
        
        $sql = "UPDATE admins SET estado = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $estado, $id);
        if(!$stmt->execute()) throw new Exception("Ha ocurrido un problema al modificar el estado del usuario, por favor inténtelo nuevamente.");

        if(!mysqli_commit($conn)) throw new Exception("Error de conexión");
        
        $data = array(
            "status"=> 1,
        );

        echo json_encode($data);

    } catch(Exception $e) {
        mysqli_rollback($conn);
        $data = array(
            "status"=> 0, 
            "mensaje"=> $e->getMessage()
        );

        echo json_encode($data);
    }

?>