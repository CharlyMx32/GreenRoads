<?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "pruebas_greenroadsv2";
    $port = 3307;

    $conn = mysqli_connect($servername, $username, $password, $dbname, $port);
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    $conn->set_charset("utf8");
?>