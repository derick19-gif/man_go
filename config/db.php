<?php
// config/db.php

$host = 'localhost';
$dbname = 'man_go_db'; // Nom de votre base de donnes
$username = 'root';
$password = ''; // Par dfaut vide sur XAMPP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion  la base de donnes : " . $e->getMessage());
}