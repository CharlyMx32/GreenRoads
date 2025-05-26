<?php
    date_default_timezone_set('America/Mexico_City');

    $sql = "SET lc_time_names = 'es_ES';";
    mysqli_query($conn, $sql);

    $URL_ROOT = "https://admin.greenroads.com.mx";
?>