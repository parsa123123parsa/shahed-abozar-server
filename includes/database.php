<?php
/**
 * MySQL connection settings for InfinityFree.
 * Replace the placeholders with the database credentials shown in your InfinityFree
 * control panel. Do not commit real credentials to a public repository.
 */
const DB_HOST = 'YOUR_INFINITYFREE_MYSQL_HOST';
const DB_NAME = 'YOUR_DATABASE_NAME';
const DB_USER = 'YOUR_DATABASE_USERNAME';
const DB_PASSWORD = 'YOUR_DATABASE_PASSWORD';

function getDatabaseConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
