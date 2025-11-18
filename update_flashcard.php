<?php
// update_flashcard.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["username"])) {
  http_response_code(401);
  echo json_encode(['ok'=>false, 'error'=>'Not authenticated.']);
  exit();
}

include 'db.php';

/* ---------- Helpers ---------- */
function get_user_id(mysqli $conn, string $username): int {
  $q = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $q->bind_param("s", $username);
  $q->execute();
  $res = $q->get_result()->fetch_assoc();
  return (int)($res['id'] ?? 0);
}

function save_base64_image(string $dataUrl, string $prefix, int $id): ?string {
  if (strpos($dataUrl, 'base64,') === false) return null;

  $parts = explode('base64,', $dataUrl, 2);
  $decoded = base64_decode($parts[1]);

  // Ensure uploads folder exists
  $uploadDir = __DIR__ . '/uploads/';
  if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

  $filename = $uploadDir . "{$prefix}_{$id}_" . time() . ".png";
  if (file_put_contents($filename, $decoded)) {
    return 'uploads/' . basename($filename); // relative path for DB
  }
  return null;
}

/* ---------- Parse JSON body ---------- */
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload) && isset($_POST['payload'])) {
  $payload = json_decode($_POST['payload'], true);
}
if (!is_array($payload)) {
  echo json_encode(['ok'=>false,'error'=>'Invalid JSON body.']);
  exit();
}

/* ---------- Validate minimal fields ---------- */
$deck_id   = (int)($payload['deck_id'] ?? 0);
$title     = trim($payload['title'] ?? '');
$topic     = trim($payload['topic'] ?? '');
$thumbnail = $payload['thumbnail'] ?? '';
$cards     = $payload['cards'] ?? [];
$deleteIds = $payload['delete_ids'] ?? [];
$visibility = $payload['visibility'] ?? null;

if (!$deck_id || $title === '' || !is_array($cards)) {
  echo json_encode(['ok'=>false,'error'=>'Missing fields (deck_id, title, cards).']);
  exit();
}

/* ---------- Ownership check ---------- */
$user_id = get_user_id($conn, $_SESSION["username"]);
if (!$user_id) {
  echo json_encode(['ok'=>false,'error'=>'User not found.']);
  exit();
}

$d = $conn->prepare("SELECT user_id, status, visibility FROM decks WHERE id=? LIMIT 1");
$d->bind_param("i", $deck_id);
$d->execute();
$deck_info = $d->get_result()->fetch_assoc();

if (!$deck_info || (int)$deck_info['user_id'] !== $user_id) {
  echo json_encode(['ok'=>false,'error'=>'You do not own this deck.']);
  exit();
}

$current_status = $deck_info['status'];
$current_visibility = $deck_info['visibility'];

/* ---------- Begin transaction ---------- */
$conn->begin_transaction();

try {
  // Handle thumbnail
$thumb_path = null;

if (!empty($thumbnail)) {
  if (str_starts_with($thumbnail, 'data:image')) {
    $thumb_path = save_base64_image($thumbnail, 'deck', $deck_id);
  } elseif (str_contains($thumbnail, '/uploads/')) {
    $thumb_path = preg_replace('~^https?://[^/]+/[^/]+/~', '', $thumbnail);
    if (!str_starts_with($thumb_path, 'uploads/')) {
      $thumb_path = 'uploads/' . basename($thumb_path);
    }
  } elseif (str_starts_with($thumbnail, 'uploads/')) {
    $thumb_path = $thumbnail;
  } else {
  // ✅ if thumbnail is the default placeholder, keep it as-is
  if (str_contains($thumbnail, 'images/labers.png')) {
    $thumb_path = 'images/labers.png';
  } else {
    $thumb_path = 'uploads/' . basename($thumbnail);
  }
}

} else {
  // ✅ preserve existing thumbnail if not replaced
  $q = $conn->prepare("SELECT thumbnail FROM decks WHERE id=? LIMIT 1");
  $q->bind_param("i", $deck_id);
  $q->execute();
  $res = $q->get_result()->fetch_assoc();
  $thumb_path = $res['thumbnail'] ?? null;
}



  // --- Decide new status ---
  $new_status = $current_status;
  if ($visibility === 'public' && $current_visibility !== 'public') {
    $new_status = 'pending'; // Needs approval
  }

  // Update deck info
  if ($thumb_path && $visibility !== null) {
    $upd = $conn->prepare("UPDATE decks SET title=?, topic=?, thumbnail=?, visibility=?, status=? WHERE id=?");
    $upd->bind_param("sssssi", $title, $topic, $thumb_path, $visibility, $new_status, $deck_id);
  } elseif ($thumb_path) {
    $upd = $conn->prepare("UPDATE decks SET title=?, topic=?, thumbnail=?, status=? WHERE id=?");
    $upd->bind_param("ssssi", $title, $topic, $thumb_path, $new_status, $deck_id);
  } elseif ($visibility !== null) {
    $upd = $conn->prepare("UPDATE decks SET title=?, topic=?, visibility=?, status=? WHERE id=?");
    $upd->bind_param("ssssi", $title, $topic, $visibility, $new_status, $deck_id);
  } else {
    $upd = $conn->prepare("UPDATE decks SET title=?, topic=?, status=? WHERE id=?");
    $upd->bind_param("sssi", $title, $topic, $new_status, $deck_id);
  }

  if (!$upd->execute()) throw new Exception("Failed updating deck: " . $conn->error);

  /* ---------- Delete selected cards ---------- */
  if (is_array($deleteIds) && count($deleteIds) > 0) {
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
    $types = 'i' . str_repeat('i', count($deleteIds));
    $sql = "DELETE FROM flashcards WHERE deck_id=? AND id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $bind = [$types, $deck_id];
    foreach ($deleteIds as $v) $bind[] = (int)$v;
    $refs = [];
    foreach ($bind as $k => $v) { $refs[$k] = &$bind[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    if (!$stmt->execute()) throw new Exception("Failed deleting cards: " . $conn->error);
  }

/* ---------- Insert / Update Cards ---------- */
  $inserted = 0; $updated = 0;

  foreach ($cards as $c) {
    $cid = isset($c['id']) ? (int)$c['id'] : 0;
    $question = trim($c['question'] ?? '');
    $answer   = trim($c['answer'] ?? '');
    $difficulty = $c['difficulty'] ?? null;
    if ($difficulty === '') $difficulty = null;
    $h_g = trim($c['hints']['gibberish']   ?? '');
    $h_d = trim($c['hints']['description'] ?? '');
    $h_o = trim($c['hints']['obvious']     ?? '');
    $imgData = $c['imageData'] ?? '';
    $imgPath = null;

    if (!empty($imgData)) {
      // Case 1: New base64 image
      if (str_starts_with($imgData, 'data:image')) {
        $imgPath = save_base64_image($imgData, 'card', ($cid > 0 ? $cid : $deck_id));
      } 
      // Case 2: Full URL from browser (e.g., http://localhost/labu/uploads/card_123.png)
      elseif (str_contains($imgData, '/uploads/')) {
        $imgPath = preg_replace('~^https?://[^/]+/[^/]+/~', '', $imgData);
        if (!str_starts_with($imgPath, 'uploads/')) {
          $imgPath = 'uploads/' . basename($imgPath);
        }
      }
      // Case 3: Already correct format (uploads/card_123.png)
      elseif (str_starts_with($imgData, 'uploads/')) {
        $imgPath = $imgData;
      }
      // Case 4: Just filename
      else {
        $clean = ltrim($imgData, '/');
        $imgPath = 'uploads/' . basename($clean);
      }
    }
    // If imgData is explicitly empty, set to NULL to remove image
    else {
      $imgPath = null;
    }

    // Skip completely empty cards
    if ($question==='' && $answer==='' && $h_g==='' && $h_d==='' && $h_o==='') continue;

    if ($cid > 0) {
      // Update existing card
      $q = $conn->prepare("UPDATE flashcards SET question=?, answer=?, difficulty=?, hint_gibberish=?, hint_description=?, hint_obvious=?, image=? WHERE id=? AND deck_id=?");
      $q->bind_param("sssssssii", $question, $answer, $difficulty, $h_g, $h_d, $h_o, $imgPath, $cid, $deck_id);
      if (!$q->execute()) throw new Exception("Update card failed: " . $conn->error);
      $updated++;
    } else {
      // Insert new card
      $q = $conn->prepare("INSERT INTO flashcards (deck_id, user_id, question, answer, difficulty, hint_gibberish, hint_description, hint_obvious, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $q->bind_param("iisssssss", $deck_id, $user_id, $question, $answer, $difficulty, $h_g, $h_d, $h_o, $imgPath);
      if (!$q->execute()) throw new Exception("Insert card failed: " . $conn->error);
      $inserted++;
    }
  }

  $conn->commit();
  echo json_encode(['ok'=>true,'inserted'=>$inserted,'updated'=>$updated,'deleted'=>count($deleteIds),'status'=>$new_status]);
} catch (Throwable $e) {
  $conn->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
?>
