<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_POST['deck_id'])) exit;

$deck_id = (int)$_POST['deck_id'];

// Restore only if user owns it
$stmt = $conn->prepare("UPDATE decks SET archived = 0 WHERE id = ? AND user_id = (SELECT id FROM users WHERE username=?)");
$stmt->bind_param("is", $deck_id, $_SESSION['username']);
$stmt->execute();

header("Location: archived_decks.php");
exit;
