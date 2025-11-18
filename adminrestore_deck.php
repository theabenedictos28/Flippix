<?php
session_start();
include 'db.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['deck_id'])) {
    $deck_id = (int)$_POST['deck_id'];
    $stmt = $conn->prepare("UPDATE decks SET status='approved', visibility='public' WHERE id=?");
    $stmt->bind_param("i", $deck_id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_approve.php?tab=deleted&restored=1");
    exit();
}
?>
