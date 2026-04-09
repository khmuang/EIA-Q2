<?php
// Centralized Database Configuration for EIA Dashboard

$host = 'riskaceap001.central.co.th'; 
$db = 'ORG1'; 
$user = 'R1'; 
$pass = 'KaceRep@min..'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try { 
    $pdo = new PDO($dsn, $user, $pass, $options); 
} catch (\PDOException $e) { 
    die("Database connection failed: " . $e->getMessage()); 
}
?>
