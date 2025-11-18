<?php
session_start();
include 'db.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $deleted_at = date('Y-m-d H:i:s'); // 🕒 current date & time

    // ✅ Update status and save deletion timestamp
    $stmt = $conn->prepare("UPDATE decks SET status='deleted', visibility='private', deleted_at=? WHERE id=?");
    $stmt->bind_param("si", $deleted_at, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_approve.php?tab=deleted");
    exit();
}
?>
