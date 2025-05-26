<?php 
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    //$ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    $ROOT = '../..';

    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/config.php';

    $usuario = $_POST['usuario'];
    $pwd = $_POST['pwd'];

    $fechaHoy = date("Y-m-d H:i:s");

    $estado = 'activo';

    mysqli_autocommit($conn, FALSE);

    try{
        $sql = "SELECT
        a.id
        FROM admins a
        WHERE BINARY a.usuario = ?
        AND BINARY a.clave = ?
        AND a.estado = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $usuario, $pwd, $estado);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows < 1) throw new Exception("El usuario o contraseña son incorrectos.");

        $stmt->bind_result($idUsuario);
        $stmt->fetch();

        $sql = "UPDATE admins SET ultima_conexion = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fechaHoy, $idUsuario);
        if(!$stmt->execute()) throw new Exception("Ha ocurrido un problema al iniciar la sesión, por favor inténtelo nuevamente.");

        session_start();
        
        $_SESSION['usuario'] = $idUsuario;

        if(!mysqli_commit($conn)) throw new Exception("Error de conexión");

        $data = array(
            "status" => 1
        );

        echo json_encode($data);

    } catch(Exception $e){
        mysqli_rollback($conn);
        $data = array(
            "status" => 0,
            "mensaje" => $e->getMessage()
        );
        echo json_encode($data);
    }

?>