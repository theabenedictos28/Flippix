<?php
// save_play.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION["username"])) {
  http_response_code(401);
  echo json_encode(["ok"=>false, "error"=>"not_logged_in"]);
  exit();
}

require_once 'db.php';

/* Resolve user_id */
$username = $_SESSION["username"];
$ustmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$ustmt->bind_param("s", $username);
$ustmt->execute();
$urow = $ustmt->get_result()->fetch_assoc();
$user_id = (int)($urow['id'] ?? 0);

if (!$user_id) {
  http_response_code(403);
  echo json_encode(["ok"=>false, "error"=>"user_not_found"]);
  exit();
}

/* Read JSON from fetch(...) */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"invalid_json"]);
  exit();
}

$deck_id = isset($data['deck_id']) ? (int)$data['deck_id'] : 0;
$correct = isset($data['correct']) ? (int)$data['correct'] : 0;
$total   = isset($data['total']) ? (int)$data['total'] : 0;

if ($deck_id <= 0 || $total <= 0 || $correct < 0 || $correct > $total) {
  http_response_code(422);
  echo json_encode(["ok"=>false, "error"=>"invalid_values"]);
  exit();
}

/* Verify the deck exists */
$dst = $conn->prepare("SELECT id FROM decks WHERE id=? LIMIT 1");
$dst->bind_param("i", $deck_id);
$dst->execute();
if ($dst->get_result()->num_rows === 0) {
  http_response_code(404);
  echo json_encode(["ok"=>false, "error"=>"deck_not_found"]);
  exit();
}

/* Clamp total to number of cards in that deck (prevents wrong percentages) */
$cstmt = $conn->prepare("SELECT COUNT(*) AS c FROM flashcards WHERE deck_id=?");
$cstmt->bind_param("i", $deck_id);
$cstmt->execute();
$crow = $cstmt->get_result()->fetch_assoc();
$max_cards = (int)($crow['c'] ?? 0);

if ($max_cards > 0 && $total > $max_cards) {
  $total = $max_cards;
  if ($correct > $total) $correct = $total;
}

/* Insert a session row */
$ins = $conn->prepare("
  INSERT INTO play_sessions (user_id, deck_id, correct, total, created_at)
  VALUES (?, ?, ?, ?, NOW())
");
$ins->bind_param("iiii", $user_id, $deck_id, $correct, $total);

if ($ins->execute()) {
  echo json_encode(["ok"=>true]);
} else {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"db_insert_failed"]);
}
