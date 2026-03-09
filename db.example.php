<?php
declare(strict_types=1);

/**
 * Database Configuration Example
 * 
 * Copy this file to db.php and update with your actual credentials.
 * Never commit db.php to version control!
 */

$DB_HOST = '127.0.0.1';       // Database host
$DB_PORT = '5432';            // PostgreSQL port
$DB_NAME = 'dialerdb';        // Database name
$DB_USER = 'your_username';   // Database user
$DB_PASS = 'your_password';   // Database password

$dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Unable to connect to database. Please check your configuration.");
}
