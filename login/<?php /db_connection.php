<?php
// Database configuration
$dbHost = "localhost";
$dbName = "your_database";
$dbUser = "your_username";
$dbPass = "your_password";

function getDbConnection() {
    global $dbHost, $dbName, $dbUser, $dbPass;
    
    try {
        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
