<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || !isset($_POST['deck_id'])) {
    die("Invalid request");
}

$deck_id = (int)$_POST['deck_id'];

// Archive deck and set archived_at timestamp
$stmt = $conn->prepare("
    UPDATE decks 
    SET archived = 1, archived_at = NOW() 
    WHERE id = ? AND user_id = (SELECT id FROM users WHERE username = ?)
");
$stmt->bind_param("is", $deck_id, $_SESSION['username']);
$stmt->execute();

header("Location: dashboard.php?tab=flashcards&archived=1");
exit;
?>
