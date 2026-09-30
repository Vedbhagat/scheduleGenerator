<?php
    include_once 'dbConnect.php';
    $conn->execute_query("DROP DATABASE ". $database);
    echo "The database was deleted SUCCESSFULL";
?>