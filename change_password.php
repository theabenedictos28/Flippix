<?php
session_start();
require 'db.php';

if (!isset($_SESSION["username"])) {
  header("Location: login.php");
  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: dashboard.php");
  exit();
}

$username         = $_SESSION["username"];
$current_password = $_POST["current_password"] ?? "";
$new_password     = $_POST["new_password"] ?? "";
$confirm_password = $_POST["confirm_password"] ?? "";

// Basic validations
if ($new_password !== $confirm_password) {
  echo "<script>alert('New passwords do not match.'); history.back();</script>";
  exit();
}
if (strlen($new_password) < 8) {
  echo "<script>alert('New password must be at least 8 characters.'); history.back();</script>";
  exit();
}

// Fetch stored password (could be hash or legacy plaintext)
$stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
  echo "<script>alert('User not found.'); history.back();</script>";
  exit();
}

$row = $result->fetch_assoc();
$userId = (int)$row['id'];
$stored = $row['password'];

// Determine if stored value is a password_hash or legacy plaintext
$isHashed = password_get_info($stored)['algo'] !== 0;

// Verify current password
$validCurrent = $isHashed ? password_verify($current_password, $stored)
                          : hash_equals($stored, $current_password);

if (!$validCurrent) {
  echo "<script>alert('Incorrect current password.'); history.back();</script>";
  exit();
}

// Hash the new password and update
$newHash = password_hash($new_password, PASSWORD_DEFAULT);

$upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->bind_param("si", $newHash, $userId);

if ($upd->execute()) {
  echo "<script>alert('Password updated successfully.'); window.location.href='dashboard.php';</script>";
} else {
  echo "<script>alert('Error updating password.'); history.back();</script>";
}

$upd->close();
$stmt->close();
$conn->close();
