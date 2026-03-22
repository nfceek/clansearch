<?php
$host = "localhost";
// DEV
/*  */
$user = "clans";
$pass = "clans";              
$dbname = "clans";


/*
//PROD
$user = "adminbat_clans";
$pass = "G7v!pR2#xT9bQwL";      
$dbname = "adminbat_clans";
*/

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>