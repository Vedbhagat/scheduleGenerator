<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "atgs_devlopment";

global $conn;
try{
    $conn = new mysqli($servername, $username, $password);   
    $conn->execute_query("CREATE DATABASE if NOT EXISTS " . $database);
    $conn->select_db($database);
}catch(mysqli_sql_exception $e){
    echo "Database connection Error" . $e->getMessage();
}
if ($conn->connect_error){
    die("connection failed: " . $conn->connect_error);
}

//echo "connected successfully<br>";

?>
