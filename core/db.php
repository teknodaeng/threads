<?php
$host = 'localhost';
$database   = 'threads';
$username = 'root';
$password = 'CDP17s1850913#^_^';
$charset = 'utf8mb4';

$connect = "mysql:host=$host;dbname=$database;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($connect, $username, $password, $options);
} catch (\PDOException $e) {
    die("DB error: " . $e->getMessage());
}
