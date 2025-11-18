<?php
$host = "localhost";   // XAMPP = localhost
$user = "u917647618_flippix_db";        // default MySQL user
$pass = "Flippix@2025";            // default MySQL password (empty in XAMPP)
$db   = "u917647618_flippix_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
