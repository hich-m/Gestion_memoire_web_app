<?php

/**
 * Configuration de la base de données et initialisation des sessions
 */

// Initialisation de la session
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'gestionmemoires';
$username = 'root';
$password = '';

try {
    // Connexion PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
