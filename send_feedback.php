<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
  http_response_code(401);
  exit('Not logged in');
}

$easy = intval($_POST['easy'] ?? 0);
$useful = intval($_POST['useful'] ?? 0);
$user = $_SESSION['username'];

// save to feedback table
$stmt = $conn->prepare("INSERT INTO feedback (username, easy, useful, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sii", $user, $easy, $useful);
$stmt->execute();
$stmt->close();
$conn->close();
?>
