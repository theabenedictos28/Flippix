<?php
session_start();
require 'db.php';

if (!isset($_SESSION["username"])) {
  header("Location: login.php");
  exit();
}

$username = $_SESSION["username"];

// Delete user and their data (if you have related tables, add them here)
$stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
$stmt->bind_param("s", $username);

if ($stmt->execute()) {
  session_destroy();
  echo "<script>alert('Account deleted successfully.'); window.location.href='login.php';</script>";
} else {
  echo "<script>alert('Failed to delete account. Please try again.'); history.back();</script>";
}

$stmt->close();
$conn->close();
?>
