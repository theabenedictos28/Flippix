<?php
session_start();
include 'db.php';

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

// --- Get logged-in user id ---
$username = $_SESSION["username"];
$ustmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$ustmt->bind_param("s", $username);
$ustmt->execute();
$ures = $ustmt->get_result();
if ($ures->num_rows === 0) { die("User not found."); }
$user_id = (int)$ures->fetch_assoc()['id'];

// --- Collect POST payload ---
$deck_title     = trim($_POST['deck_title'] ?? '');
$deck_topic     = trim($_POST['topic'] ?? '');
$cards_json     = $_POST['all_cards'] ?? '[]';
$cards          = json_decode($cards_json, true);
$deck_thumb_b64 = trim($_POST['deck_thumbnail'] ?? '');

// --- Validate basic inputs ---
if ($deck_title === '') {
    echo "<script>alert('Please provide a deck title.'); window.location='create_flashcard.php';</script>";
    exit();
}
if (!is_array($cards) || count($cards) === 0) {
    echo "<script>alert('No flashcards to save.'); window.location='create_flashcard.php';</script>";
    exit();
}

// --- Ensure uploads directory exists ---
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// --- Helper: save base64 image to file ---
function saveBase64Image($b64, $prefix = 'img') {
    global $uploadDir;
    if (!$b64 || strpos($b64, 'base64,') === false) return null;

    $ext = 'png';
    if (strpos($b64, 'image/jpeg') !== false) $ext = 'jpg';
    elseif (strpos($b64, 'image/webp') !== false) $ext = 'webp';

    $data = base64_decode(explode('base64,', $b64)[1]);
    if ($data === false) return null;

    $fileName = $prefix . '_' . uniqid() . '.' . $ext;
    if (file_put_contents($uploadDir . $fileName, $data) === false) return null;

    return 'uploads/' . $fileName;
}

// --- Save deck thumbnail ---
$deck_thumb_path = null;
if (!empty($deck_thumb_b64) && strpos($deck_thumb_b64, 'base64,') !== false) {
    $deck_thumb_path = saveBase64Image($deck_thumb_b64, 'thumb');
}
if (empty($deck_thumb_path)) {
    $deck_thumb_path = 'images/labers.png'; // default fallback
}

// --- Generate unique share code ---
function make_code($len = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return strtolower($out);
}

$share_code = make_code(6);

// Ensure unique
$check = $conn->prepare("SELECT id FROM decks WHERE share_code=? LIMIT 1");
do {
    $check->bind_param("s", $share_code);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    if ($exists) $share_code = make_code(6);
} while ($exists);

// --- Create deck (approved by default, private visibility) ---
$dstmt = $conn->prepare("INSERT INTO decks (user_id, title, topic, thumbnail, share_code, status, visibility)
                         VALUES (?, ?, ?, ?, ?, 'approved', 'private')");
$dstmt->bind_param("issss", $user_id, $deck_title, $deck_topic, $deck_thumb_path, $share_code);

if (!$dstmt->execute()) {
    die("Failed to create deck: " . $conn->error);
}
$deck_id = $conn->insert_id;

// --- Insert flashcards ---
$cstmt = $conn->prepare("INSERT INTO flashcards 
(deck_id, user_id, question, answer, difficulty, hint_gibberish, hint_description, hint_obvious, image)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($cards as $card) {
    $question    = trim($card['question'] ?? '');
    $answer      = trim($card['answer'] ?? '');
    $difficulty  = $card['difficulty'] ?? null;
    $gibberish   = trim($card['hints']['gibberish'] ?? '');
    $description = trim($card['hints']['description'] ?? '');
    $obvious     = trim($card['hints']['obvious'] ?? '');
    $imagePath   = null;

    if (!empty($card['imageData'])) {
        $imagePath = saveBase64Image($card['imageData'], 'card');
    }

    if ($question || $answer) {
        $cstmt->bind_param(
            "iisssssss",
            $deck_id, $user_id,
            $question, $answer, $difficulty,
            $gibberish, $description, $obvious, $imagePath
        );
        $cstmt->execute();
    }
}

// ✅ Redirect to success page
header("Location: create_flashcard.php?deckcode=" . urlencode($share_code));
exit;
?>
