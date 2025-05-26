<?php
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    mysqli_autocommit($conn, FALSE);

    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];

    $estado = "activo";

    mysqli_autocommit($conn, FALSE);

    try {

        if(!tieneSesion()) throw new Exception("Tu sesión ha expirado. Favor de iniciar sesión nuevamente.");

        $usuarioMin = strtolower($usuario);
        $estadoVerify = "eliminado";
        
        $sql = "SELECT id FROM admins WHERE LOWER(usuario) = ? AND estado <> ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $usuarioMin, $estadoVerify);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows > 0) throw new Exception("Se encontró un registro con el mismo usuario.");
        
        $sql = "INSERT INTO admins(
            nombre,
            apellido,
            usuario,
            clave,
            estado
        )VALUES(?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            's'. // nombre
            's'. // apellido
            's'. // usuario
            's'. // clave
            's', // estado
            $nombre,
            $apellido,
            $usuario,
            $clave,
            $estado
        );
        if(!$stmt->execute()) throw new Exception("Ha ocurrido un problema al agregar la información, por favor inténtelo nuevamente.");

        if(!mysqli_commit($conn)) throw new Exception("Error de conexión");
        
        $data = array(
            "status"=> 1,
            "mensaje" => "La información se ha agregado exitosamente."
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