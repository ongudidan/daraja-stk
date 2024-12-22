<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_NAME', 'mpesa');

// Establish a database connection
try {
    $db = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if (!$db) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    die("Connection error occurred. Please check the error log for details.");
}
