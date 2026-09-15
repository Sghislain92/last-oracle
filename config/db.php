<?php
// config/db.php
declare(strict_types=1);



function getPdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // À REMPLACER avec les valeurs de ton cPanel (Bases de données MySQL).
    // Le nom d'utilisateur et de base sont généralement préfixés par ton
    // identifiant cPanel, ex: "moncpanel_oracle_db".
    $host = 'localhost';
    $dbname = 'c1286229c_oracle';
    $user = 'c1286229c_oracle_user';
    $pass = 'Sghisl@!n2';


    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}