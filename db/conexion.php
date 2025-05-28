<?php
    $servername = "localhost";
    $username = "root";
    $password = "3223";
    $dbname = "pruebas_greenroadsv2";
    $port = 3306;

    $conn = mysqli_connect($servername, $username, $password, $dbname, $port);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    $conn->set_charset("utf8");
?>