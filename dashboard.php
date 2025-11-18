<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include 'db.php';



/* --- Resolve user_id --- */
$username = $_SESSION["username"];
$ustmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$ustmt->bind_param("s", $username);
$ustmt->execute();
$urow = $ustmt->get_result()->fetch_assoc();
$user_id = (int)($urow['id'] ?? 0);



/* ---------- Lightweight API endpoints (AJAX) ---------- */
if (isset($_GET['api'])) {
  header('Content-Type: application/json; charset=utf-8');

  if ($_GET['api'] === 'distinct_decks') {
    $cnt = 0;
    if ($user_id) {
      $q = $conn->prepare("SELECT COUNT(DISTINCT deck_id) AS c FROM play_sessions WHERE user_id=?");
      $q->bind_param("i", $user_id);
      if ($q->execute()) {
        $r = $q->get_result()->fetch_assoc();
        $cnt = (int)($r['c'] ?? 0);
      }
    }
    echo json_encode(["ok"=>true, "count"=>$cnt]);
    exit();
  }



 if ($_GET['api'] === 'community_played') {
  if (!$user_id) {
    echo json_encode(['ok' => false]);
    exit();
  }

  $stmt = $conn->prepare("
    SELECT COUNT(DISTINCT ps.deck_id) AS count
    FROM play_sessions ps
    JOIN decks d ON ps.deck_id = d.id
    WHERE ps.user_id = ?
  ");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  echo json_encode(['ok' => true, 'count' => (int)$res['count']]);
  exit();
}



  if ($_GET['api'] === 'mastery') {
    $percent = 0.0;
    if ($user_id) {
      $q = $conn->prepare("SELECT SUM(correct) AS c, SUM(total) AS t FROM play_sessions WHERE user_id=?");
      $q->bind_param("i", $user_id);
      if ($q->execute()) {
        $r = $q->get_result()->fetch_assoc();
        $c = (int)($r['c'] ?? 0);
        $t = (int)($r['t'] ?? 0);
        if ($t > 0) $percent = round(($c / $t) * 100, 1);
      }
    }
    echo json_encode(["ok"=>true, "percent"=>$percent]);
    exit();
  }


if ($_GET['api'] === 'search_code') {
    header('Content-Type: application/json; charset=utf-8');

    $code = trim($_GET['code'] ?? '');
    if ($code === '') {
        echo json_encode(['ok' => true, 'deck' => null]);
        exit();
    }

    $user_id = $_SESSION['user_id'] ?? 0;

    $sql = "
        SELECT 
            d.id, d.title, d.topic, d.share_code, d.thumbnail, 
            d.visibility, d.status, d.created_at,
            u.username AS owner,
            COUNT(DISTINCT s.id) AS total_views,
            (SELECT COUNT(*) FROM flashcards WHERE deck_id = d.id) AS card_count,
            CASE WHEN f.user_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorited,
            (SELECT COUNT(*) FROM likes l WHERE l.deck_id = d.id) AS like_count,
            (SELECT COUNT(*) FROM likes l WHERE l.deck_id = d.id AND l.user_id = ?) AS is_liked
        FROM decks d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN favorites f ON f.deck_id = d.id AND f.user_id = ?
        LEFT JOIN play_sessions s ON s.deck_id = d.id
        WHERE d.share_code = ?
          AND d.status NOT IN ('pending', 'declined')
          AND d.archived = 0
        GROUP BY d.id, d.title, d.topic, d.share_code, d.thumbnail, 
                 u.username, d.created_at, f.user_id, d.visibility, d.status
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['ok' => false, 'error' => $conn->error]);
        exit();
    }

    // Bind user_id twice: for favorites and likes
    $stmt->bind_param('iis', $user_id, $user_id, $code);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $thumb = !empty($row['thumbnail']) ? $row['thumbnail'] : 'images/labers.png';
        $created = date('M j, Y', strtotime($row['created_at'] ?? ''));

        echo json_encode([
            'ok' => true,
            'deck' => [
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'topic' => $row['topic'] ?? 'General',
                'share_code' => $row['share_code'],
                'thumb' => $thumb,
                'owner' => $row['owner'],
                'created_at' => $created,
                'visibility' => $row['visibility'],
                'status' => $row['status'],
                'views' => (int)$row['total_views'],
                'card_count' => (int)$row['card_count'],
                'is_favorited' => (bool)$row['is_favorited'],
                'like_count' => (int)$row['like_count'],
                'is_liked' => (bool)$row['is_liked']
            ]
        ]);
        exit();
    }

    echo json_encode(['ok' => true, 'deck' => null]);
    exit();
}

if ($_GET['api'] === 'favorites') {
    if (!$user_id) {
        echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
        exit();
    }

    $sql = "
        SELECT 
            d.id, d.title, d.topic, d.share_code, d.thumbnail, 
            u.username AS owner, d.created_at,
            COUNT(DISTINCT s.id) AS total_views,
            (SELECT COUNT(*) FROM flashcards WHERE deck_id = d.id) AS card_count
        FROM favorites f
        JOIN decks d ON f.deck_id = d.id
        JOIN users u ON d.user_id = u.id
        LEFT JOIN play_sessions s ON s.deck_id = d.id
        WHERE f.user_id = ?
          AND d.status = 'approved'
          AND d.visibility = 'public'
          AND d.archived = 0 
        GROUP BY d.id, d.title, d.topic, d.share_code, d.thumbnail, u.username, d.created_at
        ORDER BY f.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $favorites = [];
    while ($row = $res->fetch_assoc()) {
        $thumb = deckThumbSrc($row['thumbnail']);

        // Check if user already liked this deck
        $likeCheck = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND deck_id=? LIMIT 1");
        $likeCheck->bind_param("ii", $user_id, $row['id']);
        $likeCheck->execute();
        $isLiked = $likeCheck->get_result()->num_rows > 0;

        // Get total likes for this deck
        $likeCountRes = $conn->query("SELECT COUNT(*) AS cnt FROM likes WHERE deck_id=" . (int)$row['id']);
        $likeCountRow = $likeCountRes->fetch_assoc();
        $likeCount = (int)$likeCountRow['cnt'];

        $favorites[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'topic' => $row['topic'] ?? 'General',
            'share_code' => $row['share_code'],
            'owner' => $row['owner'],
            'created_at' => $row['created_at'],
            'thumb' => $thumb,
            'views' => (int)$row['total_views'],
            'card_count' => (int)$row['card_count'],
            'is_fav' => true,           // Always true on favorites page
            'is_liked' => $isLiked,     // User's like status
            'like_count' => $likeCount  // Total likes
        ];
    }

    echo json_encode(['ok' => true, 'favorites' => $favorites]);
    exit();
}


  if ($_GET['api'] === 'unfavorite' && isset($_GET['deck_id'])) {
  if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit();
  }

  $deck_id = (int)$_GET['deck_id'];
  $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND deck_id = ?");
  $stmt->bind_param("ii", $user_id, $deck_id);
  $stmt->execute();

  echo json_encode(['ok' => true]);
  exit();
}


  
  if ($_GET['api'] === 'toggle_favorite') {
    if (!$user_id) {
      echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
      exit();
    }

    $deck_id = (int)($_GET['deck_id'] ?? 0);
    if ($deck_id <= 0) {
      echo json_encode(['ok' => false, 'error' => 'invalid_id']);
      exit();
    }

    // Check if it's already a favorite
    $check = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND deck_id=? LIMIT 1");
    $check->bind_param("ii", $user_id, $deck_id);
    $check->execute();
    $isFav = $check->get_result()->num_rows > 0;

    if ($isFav) {
      // Remove from favorites
      $del = $conn->prepare("DELETE FROM favorites WHERE user_id=? AND deck_id=?");
      $del->bind_param("ii", $user_id, $deck_id);
      $del->execute();
      echo json_encode(['ok' => true, 'favorited' => false]);
    } else {
      // Add to favorites
      $ins = $conn->prepare("INSERT IGNORE INTO favorites (user_id, deck_id) VALUES (?, ?)");
      $ins->bind_param("ii", $user_id, $deck_id);
      $ins->execute();
      echo json_encode(['ok' => true, 'favorited' => true]);
    }
    exit();
  }
if ($_GET['api'] === 'toggle_like') {
    if (!$user_id) { echo json_encode(['ok'=>false,'error'=>'not_logged_in']); exit; }

    $deck_id = (int)($_GET['deck_id'] ?? 0);
    if ($deck_id <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid_id']); exit; }

    // Check if already liked
    $check = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND deck_id=? LIMIT 1");
    $check->bind_param("ii", $user_id, $deck_id);
    $check->execute();
    $isLiked = $check->get_result()->num_rows > 0;

    if ($isLiked) {
        $del = $conn->prepare("DELETE FROM likes WHERE user_id=? AND deck_id=?");
        $del->bind_param("ii", $user_id, $deck_id);
        $del->execute();
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO likes (user_id, deck_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $deck_id);
        $ins->execute();
    }

    // Get updated like count
    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM likes WHERE deck_id=$deck_id");
    $countRow = $countRes->fetch_assoc();

    echo json_encode([
        'ok'=>true,
        'liked'=>!$isLiked,
        'like_count'=>(int)$countRow['cnt']
    ]);
    exit();
}



if ($_GET['api'] === 'history') {
  if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit();
  }

  $sql = "
    SELECT 
      d.id, d.title, d.topic, d.share_code, d.thumbnail,
      d.visibility, d.created_at, u.username AS owner,
      MAX(ps.created_at) AS last_played,
      (
        SELECT CONCAT(correct, '/', total)
        FROM play_sessions 
        WHERE user_id = ps.user_id AND deck_id = ps.deck_id
        ORDER BY created_at DESC 
        LIMIT 1
      ) AS last_score,
      IF(f.deck_id IS NULL, 0, 1) AS is_fav,
      COUNT(DISTINCT ps.id) AS total_views,
      (SELECT COUNT(*) FROM flashcards WHERE deck_id = d.id) AS card_count
    FROM play_sessions ps
    JOIN decks d ON ps.deck_id = d.id
    JOIN users u ON d.user_id = u.id
    LEFT JOIN favorites f ON f.deck_id = d.id AND f.user_id = ?
    WHERE ps.user_id = ?
    GROUP BY d.id, d.title, d.topic, d.share_code, d.thumbnail, d.visibility, d.created_at, u.username, f.deck_id
    ORDER BY last_played DESC
    LIMIT 10
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $user_id, $user_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $history = [];
  while ($row = $res->fetch_assoc()) {
    $thumb = deckThumbSrc($row['thumbnail']);
    $history[] = [
      'id' => (int)$row['id'],
      'title' => $row['title'],
      'topic' => $row['topic'] ?? 'General',
      'share_code' => $row['share_code'],
      'owner' => $row['owner'],
      'created_at' => $row['created_at'],
      'last_played' => $row['last_played'],
      'last_score' => $row['last_score'] ?? '—',
      'visibility' => $row['visibility'] ?? 'private',
      'thumb' => $thumb,
      'is_fav' => (bool)$row['is_fav'],
      'views' => (int)$row['total_views'],
      'card_count' => (int)$row['card_count']
    ];
  }

  echo json_encode(['ok' => true, 'history' => $history]);
  exit();
}



/* ---- API: Notifications ---- */
if (isset($_GET['api']) && $_GET['api'] === 'notifications') {
    header('Content-Type: application/json');
    $username = $_SESSION["username"];

    // Get user_id
    $ustmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $ustmt->bind_param("s", $username);
    $ustmt->execute();
    $ustmt->bind_result($user_id);
    $ustmt->fetch();
    $ustmt->close();

    $stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["ok" => true, "notifications" => $rows]);
    exit;
}


// ✅ Mark notifications as read
if (isset($_GET['api']) && $_GET['api'] === 'mark_notifications_read') {
    header('Content-Type: application/json');
    $username = $_SESSION["username"];

    // Get user_id
    $ustmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $ustmt->bind_param("s", $username);
    $ustmt->execute();
    $ustmt->bind_result($user_id);
    $ustmt->fetch();
    $ustmt->close();

    // Update notifications
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    echo json_encode(["ok" => true]);
    exit;
}



  echo json_encode(["ok"=>false, "error"=>"Unknown API"]);
  exit();
}

/* --- Handle delete (POST back to same page) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_deck_id'])) {
    $deck_id = (int)$_POST['delete_deck_id'];

    // verify ownership
    $chk = $conn->prepare("SELECT id FROM decks WHERE id=? AND user_id=? LIMIT 1");
    $chk->bind_param("ii", $deck_id, $user_id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $conn->begin_transaction();
        try {
            $delF = $conn->prepare("DELETE FROM flashcards WHERE deck_id=?");
            $delF->bind_param("i", $deck_id);
            $delF->execute();

            $delD = $conn->prepare("DELETE FROM decks WHERE id=?");
            $delD->bind_param("i", $deck_id);
            $delD->execute();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
    header("Location: dashboard.php?tab=myflashcards");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_visibility'])) {
  $deck_id = (int)$_POST['deck_id'];
  $visibility = $_POST['visibility'] === 'public' ? 'public' : 'private';

  // Verify ownership
  $chk = $conn->prepare("SELECT id, status FROM decks WHERE id=? AND user_id=? LIMIT 1");
  $chk->bind_param("ii", $deck_id, $user_id);
  $chk->execute();
  $deck = $chk->get_result()->fetch_assoc();

  if (!$deck) {
    echo json_encode(["ok"=>false, "error"=>"Deck not found"]);
    exit();
  }

  if ($visibility === 'public') {
    // User requests to make it public → mark pending
    $upd = $conn->prepare("UPDATE decks SET status='pending', visibility='private' WHERE id=?");
    $upd->bind_param("i", $deck_id);
    $upd->execute();

    echo json_encode(["ok"=>true, "message"=>"Your deck is now pending admin approval."]);
  } else {
    // Making private again → instantly private
    $upd = $conn->prepare("UPDATE decks SET visibility='private', status='approved' WHERE id=?");
    $upd->bind_param("i", $deck_id);
    $upd->execute();

    echo json_encode(["ok"=>true, "message"=>"Your deck is now private."]);
  }

  exit();
}

/* --- Fetch decks for this user with card counts, topic, and total views --- */
$decks = [];
if ($user_id) {
  $sql = "
    SELECT 
      d.id, d.title, d.topic, d.thumbnail, d.share_code, d.created_at, 
      d.status, d.visibility,
      COALESCE(fc.cnt, 0) AS card_count,
      COUNT(DISTINCT ps.id) AS total_views
    FROM decks d
    LEFT JOIN (
      SELECT deck_id, COUNT(*) AS cnt 
      FROM flashcards 
      GROUP BY deck_id
    ) fc ON fc.deck_id = d.id
    LEFT JOIN play_sessions ps ON ps.deck_id = d.id
  WHERE d.user_id = ? AND d.archived = 0
    GROUP BY 
      d.id, d.title, d.topic, d.thumbnail, d.share_code, 
      d.created_at, d.status, d.visibility, fc.cnt
    ORDER BY d.created_at DESC
  ";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $decks[] = $r;
}

function deckThumbSrc($thumb) {
  if (!$thumb) return 'images/labers.png'; // fallback

  // If it's already a URL or file path, just return it
  if (str_starts_with($thumb, 'uploads/') || str_starts_with($thumb, 'http') || str_starts_with($thumb, '/')) {
    return $thumb;
  }

  // If it’s binary blob (rare case)
  return 'data:image/png;base64,' . base64_encode($thumb);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link rel="stylesheet" href="styles/dashboard.css">

  <title>Flippix - Dashboard</title>
    <!-- Tailwind + Google Fonts -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#4F46E5",
            "background-light": "#F9FAFB",
            "background-dark": "#111827",
            "card-light": "#FFFFFF",
            "card-dark": "#1F2937",
            "text-light": "#1F2937",
            "text-dark": "#F9FAFB",
            "subtext-light": "#6B7280",
            "subtext-dark": "#9CA3AF",
          },
          fontFamily: {
            display: ["Poppins", "sans-serif"],
          },
          borderRadius: {
            DEFAULT: "0.75rem",
          },
        },
      },
    };
  </script>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 flex flex-col h-screen overflow-hidden">

  <!-- Fixed Header -->
  <header class="bg-card-light dark:bg-card-dark shadow-sm fixed top-0 left-0 w-full z-40">
    <nav class="container mx-auto px-4 sm:px-6 py-3 flex justify-between items-center">
      <!-- Left: Logo + Title -->
      <div class="flex items-center gap-3">
        <!-- Hamburger for Mobile -->
        <button id="hamburgerBtn" class="md:hidden text-gray-700 dark:text-gray-200 focus:outline-none">
          <span class="material-icons text-3xl">menu</span>
        </button>
        <img src="images/labers.png" alt="Flippix Logo" class="h-9 w-9">
        <span class="text-xl font-semibold text-text-light dark:text-text-dark">Flippix</span>
      </div>

      <!-- Center: Nav (hidden on mobile) -->
      <div class="hidden md:flex items-center space-x-8">
        <a href="index.php" class="text-text-light dark:text-text-dark hover:text-primary">Home</a>
        <a href="howto.php" class="text-text-light dark:text-text-dark hover:text-primary">How to Use</a>
        <a href="about.php" class="text-text-light dark:text-text-dark hover:text-primary">About</a>
        <a href="dashboard.php" class="text-primary font-semibold">Dashboard</a>
      </div>

      <!-- Right: Theme Toggle -->
      <div class="flex items-center space-x-4">
        <button id="theme-toggle"
          class="p-2 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
          <span class="material-icons">dark_mode</span>
        </button>
      </div>
    </nav>
  </header>

  <!-- Fixed Username + Notification Bar -->
  <div
    class="fixed top-[72px] left-0 w-full z-30 px-4 sm:px-8 md:px-16 py-3 flex flex-wrap md:flex-nowrap justify-between items-center bg-card-light dark:bg-card-dark border-b border-t border-indigo-200">
    
    <p class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-300 mb-2 md:mb-0">
      Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>
    </p>
    
    <div class="flex items-center space-x-4">
      <!-- Notification Bell -->
      <button id="notifBell" class="relative text-gray-800 dark:text-gray-300 hover:text-blue-600 transition">
        <i class="fa-solid fa-bell text-2xl"></i>
        <span id="notifBadge"
          class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
      </button>

      <!-- Archive Button -->
      <a href="archived_decks.php" title="Archived Decks"
         class="relative text-gray-800 dark:text-gray-300 hover:text-blue-600 transition">
<i class="fa-solid fa-folder-open text-2xl"></i>
      </a>
    </div>
</div>


<!-- Notification Modal -->
<div id="notifModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="bg-white dark:bg-gray-900 w-11/12 max-w-md rounded-2xl shadow-2xl p-5 animate-fadeIn transition-colors duration-300">
    <!-- Header -->
    <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-3">
      <h3 class="text-xl font-bold text-text-light dark:text-text-dark flex items-center gap-2">
        <i class="fa-solid fa-bell text-blue-600 dark:text-blue-400"></i> Notifications
      </h3>
      <button id="closeNotif" class="text-text-dark dark:text-white hover:text-red-500 text-2xl font-bold transition-colors duration-200">
        &times;
      </button>
    </div>

    <!-- Notification List -->
    <div id="notifList" class="max-h-80 overflow-y-auto mt-4 space-y-2">
      <p class="text-text-dark dark:text-white text-center py-6">Loading notifications...</p>
    </div>
  </div>
</div>

  <!-- Dashboard Layout (Main Scroll Area) -->
  <div class="flex flex-1 pt-[120px] pb-[64px] overflow-hidden">

    <!-- Sidebar -->
    <aside id="sidebar"
      class="fixed md:static top-0 left-0 z-50 md:z-10 h-full md:h-screen w-64 bg-card-light dark:bg-card-dark border-r border-indigo-200 dark:border-gray-700 shadow-md transform -translate-x-full md:translate-x-0 transition-transform duration-300 flex flex-col">
      <div class="flex md:hidden items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <img src="images/labers.png" alt="Flippix Logo" class="h-12 w-12 rounded-full">
        <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">Flippix</span>
      </div>

      <div class="menu bg-card-light dark:bg-card-dark flex flex-col px-4 py-6 space-y-2">
        <button class="tab-btn active" data-tab="dashboard">🏠 Dashboard</button>
        <button class="tab-btn" data-tab="flashcards">📖 My Flashcards</button>
        <button class="tab-btn" data-tab="search">🔎 Search Code</button>
        <button class="tab-btn" data-tab="create">➕ Create Flashcard</button>
        <a href="community.php"><button class="tab-btn" data-tab="community">👥 Community</button></a>
        <button class="tab-btn" data-tab="settings">⚙️ Settings</button>

              <!-- Mobile-Only Navigation Links -->
    <div class="border-t border-gray-300 dark:border-gray-700 pt-4 px-4 md:hidden">
      <a href="index.php" class="block text-gray-600 dark:text-gray-300 hover:text-primary py-2 px-2 rounded-md transition">🏠 Home</a>
      <a href="howto.php" class="block text-gray-600 dark:text-gray-300 hover:text-primary py-2 px-2 rounded-md transition">📘 How to Use</a>
      <a href="about.php" class="block text-gray-600 dark:text-gray-300 hover:text-primary py-2 px-2 rounded-md transition">ℹ️ About</a>
    </div>

        <a href="logout.php" class="text-red-500 dark:text-red-400 logout-link mt-3">↩ Logout</a>
      </div>
    </aside>

    <!-- Scrollable Main Content -->
    <main class="flex-1 pb-20 md:pb-0 bg-gray-50 bg-background-light dark:bg-background-dark p-4 md:p-8 overflow-y-auto">

<!-- Dashboard Tab -->
<div id="dashboard" class="tab-content active">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200 mb-6">Dashboard Overview</h1>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mt-5 max-w-2xl mx-auto px-4">
    

  <!-- Total Decks Played -->
  <button id="totalDecksBtn" type="button"
    class="flex flex-col items-center justify-center bg-card-light dark:bg-card-dark shadow-md border border-gray-200 rounded-2xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
    <span class="material-icons text-5xl text-blue-500 mb-3">library_books</span>
    <span class="text-lg font-semibold text-gray-800  dark:text-gray-300 text-center">Total Decks Played</span>
    <small id="totalDecksCount" class="text-lg font-semibold text-indigo-400 dark:text-indigo-200 mt-1">—</small>
  </button>

  <!-- Mastery Percentage -->
  <button id="masteryBtn" type="button"
    class="flex flex-col items-center justify-center bg-card-light dark:bg-card-dark shadow-md border border-gray-200 rounded-2xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
    <span class="material-icons text-5xl text-green-500 mb-3">emoji_events</span>
    <span class="text-lg font-semibold text-gray-800  dark:text-gray-300  text-center">Mastery Percentage</span>
    <small id="masteryLive" class="text-lg font-semibold text-indigo-400 dark:text-indigo-200 mt-1">Current: —</small>
  </button>

    <!-- Favorites -->
    <button type="button" onclick="showFavorites()"
      class="flex flex-col items-center justify-center bg-card-light dark:bg-card-dark shadow-md border border-gray-200 rounded-2xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
       <span class="material-icons text-5xl text-black-500 mb-3">bookmark</span>
      <span class="text-lg font-semibold text-gray-800  dark:text-gray-300  text-center mb-3">
        Favorites
      </span>
    </button>

    <!-- History -->
    <button type="button" onclick="showHistory()"
      class="flex flex-col items-center justify-center bg-card-light dark:bg-card-dark shadow-md border border-gray-200 rounded-2xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
       <span class="material-icons text-5xl text-yellow-500 mb-3">history</span>
      <span class="text-lg font-semibold text-gray-800 dark:text-gray-300 text-center mb-3">
        History
      </span>
    </button>
  </div>
</div>

<!-- Favorites Section -->
<div id="favoritesSection" class="tab-content hidden">
  <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-300 mb-5 text-center">🔖 My Favorite Decks</h2>
  <div id="favoritesList" class="deck-list text-gray-600 dark:text-gray-300 text-center">
    <p>Loading favorites...</p>
  </div>
</div>



     <!-- HISTORY SECTION -->
<div id="historySection" class="tab-content">
  <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-300 mb-5 text-center">🕒 Recently Played Decks</h2>
  <div id="historyList" class="deck-grid">
    <p class="loading-text text-gray-600 dark:text-gray-300">Loading your history...</p>
  </div>
</div>


<!-- Flashcards Tab -->
<div id="flashcards" class="tab-content">
<div class="absolute bottom-20 right-6 z-20">
  <button 
    class="btn-new bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg shadow-md transition-all duration-300"
    onclick="window.location.href='create_flashcard.php'">
    New Deck
  </button>
</div>

<div class="flashcards-grid" id="myDecksGrid">
  <?php if (empty($decks)): ?>
    <div class="text-gray-600 dark:text-gray-400 mt-8">No decks yet!</div>
  <?php else: ?>
    <?php foreach ($decks as $d): ?>
      <?php
              if (($d['status'] ?? '') === 'deleted') continue;

        $id = (int)$d['id'];
        $username = $_SESSION["username"];
        $thumb = deckThumbSrc($d['thumbnail']);
        $cardCount = (int)$d['card_count'];
        $created = htmlspecialchars(date('M j, Y', strtotime($d['created_at'])));
        $title = htmlspecialchars($d['title']);
        $code  = htmlspecialchars($d['share_code'] ?? '');
        $status = $d['status'] ?? '';
        $visibility = $d['visibility'] ?? '';
      ?>
      <div class="deck-card-modern bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col hover:scale-105">
        <!-- Thumbnail + Topic -->
        <div class="relative">
          <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= $title ?>" class="w-full h-40 object-cover" onerror="this.src='images/labers.png'">
          <span class="absolute top-3 right-3 bg-white/90 dark:bg-gray-700/90 text-primary text-xs font-semibold px-3 py-1 rounded-lg shadow-sm">
            <?= htmlspecialchars($d['topic'] ?: 'General') ?>
          </span>
        </div>

        <!-- Card Body -->
        <div class="p-4 flex flex-col flex-grow text-left">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white line-clamp-2 mb-2"><?= $title ?></h3>
          <div class="flex justify-between items-center text-sm text-gray-600 dark:text-gray-300 mb-3">
  <span>📝 <?= $cardCount ?> card<?= $cardCount === 1 ? '' : 's' ?></span>
  <span class="text-gray-500 dark:text-gray-400 text-xs">🕒 <?= $created ?></span>
</div>


          <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-3">
            <span>👁️ <?= (int)$d['total_views'] ?> views</span>
            <span class="capitalize"><?= htmlspecialchars($status ?: 'private') ?></span>
          </div>

          <!-- Status & Visibility Section -->
          <?php if ($status === 'approved'): ?>
            <div class="deck-visibility mb-3">
              <select onchange="updateVisibility(<?= $id ?>, this.value)" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>🌐 Public</option>
                <option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>🔒 Private</option>
              </select>
            </div>

          <?php elseif ($status === 'pending'): ?>
            <div class="deck-visibility mb-3">
              <select onchange="updateVisibility(<?= $id ?>, this.value)" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                <option value="pending" selected disabled>⏳ Pending (Under Review)</option>
                <option value="private">Cancel Request (Make Private)</option>
              </select>
            </div>

          <?php elseif ($status === 'declined'): ?>
            <div class="deck-visibility mb-3">
              <select onchange="updateVisibility(<?= $id ?>, this.value)" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                <option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>🔒 Private</option>
                <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>🌐 Public (Re-submit)</option>
              </select>
            </div>
          <?php endif; ?>

          <!-- Deck Code -->
          <?php if ($code): ?>
          <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-sm flex justify-between items-center">
            <span class="font-mono">Code: <?= $code ?></span>
            <button class="btn-copy bg-primary text-white text-xs px-3 py-1 rounded-md hover:bg-primary/90 transition" onclick="copyCode('<?= $code ?>', this)">Copy</button>
          </div>
          <?php endif; ?>

          <!-- Action Buttons -->
          <div class="deck-actions flex justify-between mt-auto gap-2">
            <a href="deck_view.php?deck=<?= $id ?>" class="btn bg-primary text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition flex-1 text-center">Open</a>

            <?php if ($visibility !== 'public' || $status === 'declined'): ?>
              <a href="edit_deck.php?deck=<?= $id ?>" class="btn edit bg-yellow-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-yellow-600 transition flex-1 text-center">Edit</a>
            <?php else: ?>
              <button class="btn secondary bg-gray-400 text-white px-3 py-2 rounded-lg text-sm font-medium flex-1 text-center cursor-not-allowed" title="Public decks can't be edited" disabled>Edit</button>
            <?php endif; ?>

            <button type="button" class="btn delete bg-red-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-red-600 transition flex-1 text-center" 
        onclick="confirmArchive(<?= $id ?>)">
  
</button>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<form id="archiveForm" method="post" action="archive_deck.php" style="display:none;">
  <input type="hidden" name="deck_id" id="archiveDeckId">
</form>



  <!-- Hidden delete form -->
  <form id="deleteForm" method="POST" style="display:none;">
    <input type="hidden" name="delete_deck_id" id="deleteDeckId">
  </form>
</div>


<!-- Search Code Tab -->
<div id="search" class="tab-content p-6 bg-white dark:bg-gray-900 rounded-2xl shadow-lg transition-all duration-300">
  <h2 class="text-2xl font-bold mb-5 text-gray-800 dark:text-gray-100 flex items-center gap-2">
    🔎 Search by Deck Code
  </h2>
  <div class="flex items-center gap-3">
    <input 
      type="text" 
      id="codeQuery" 
      class="w-full px-4 py-3 text-gray-800 dark:text-gray-100 bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300" 
      placeholder="Enter deck code (e.g. ABC123)" 
    />
    <button 
      type="button" 
      class="px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-md transition-all duration-300"
      onclick="doCodeSearch()">
      Search
    </button>
  </div>

  <div id="searchResult" class="search-result mt-5 text-gray-700 dark:text-gray-200"></div>
</div>

<!-- Create Flashcard Tab -->
<div id="create" class="tab-content hidden flex flex-col items-center justify-center p-8 bg-white dark:bg-gray-300 rounded-2xl shadow-md transition-all duration-300">
  <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-900 flex items-center gap-2">
    ➕ Create a New Flashcard Deck
  </h2>

  <a 
    href="create_flashcard.php" 
    class="px-16 py-4 bg-blue-600 hover:bg-blue-700 text-white text-lg font-semibold rounded-xl shadow-md hover:shadow-lg transition-all duration-300"
  > Create Deck
  </a>
</div>



<!-- Settings Tab -->
<div id="settings" class="tab-content hidden p-3">

  <div class="space-y-8">
    <!-- Change Username Form -->
    <form action="change_username.php" method="POST" class="space-y-4">
      <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Change Username</h3>
      <div class="flex flex-col sm:flex-row gap-3">
        <input 
          type="text" 
          name="new_username" 
          value="<?php echo htmlspecialchars($_SESSION['username']); ?>" 
          required
          class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        >
        <button 
          type="submit" 
          class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-all duration-300"
        >
          Update
        </button>
      </div>
    </form>

    <!-- Change Password Form -->
    <form action="change_password.php" method="POST" class="space-y-4">
      <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">Change Password</h3>
      <div class="grid gap-3">
        <input 
          type="password" 
          name="current_password" 
          placeholder="Current password" 
          required
          class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        >
        <input 
          type="password" 
          name="new_password" 
          placeholder="New password" 
          required
          class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        >
        <input 
          type="password" 
          name="confirm_password" 
          placeholder="Confirm new password" 
          required
          class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"
        >
      </div>
      <button 
        type="submit" 
        class="w-full sm:w-auto px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-all duration-300"
      >
        Update Password
      </button>
    </form>

    <!-- Delete Account -->
    <form action="delete_account.php" method="POST" onsubmit="return confirm('Are you sure you want to delete your account?');">
      <button 
        type="submit" 
        class="w-full sm:w-auto px-6 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg shadow-sm transition-all duration-300"
      >
        🗑️ Delete Account
      </button>
    </form>
  </div>
</div>


  </main>
</div>
   <!-- Fixed Footer -->
  <footer
    class="fixed bottom-0 left-0 w-full bg-card-light dark:bg-card-dark border-t border-indigo-200 dark:border-gray-700 z-40">
    <div
      class="container mx-auto px-6 py-3 flex justify-center items-center text-sm text-subtext-light dark:text-subtext-dark">
      <img src="images/labers.png" alt="Flippix Logo" class="h-5 w-5 mr-2">
      Flippix ©2025
    </div>
  </footer>




  <div id="overlay" class="fixed inset-0 bg-black bg-opacity-30 hidden z-40 md:hidden"></div>
  <script>
// Tab switching by click
    const buttons = document.querySelectorAll(".tab-btn");
    const tabs = document.querySelectorAll(".tab-content");

    function activateTab(tabId, updateUrl = true) {
      buttons.forEach(b => b.classList.remove("active"));
      tabs.forEach(t => t.classList.remove("active"));
      const btn = document.querySelector(`.tab-btn[data-tab="${tabId}"]`);
      const tab = document.getElementById(tabId);
      if (btn && tab) {
        btn.classList.add("active");
        tab.classList.add("active");
        
        // Update URL without page reload
        if (updateUrl) {
          const newUrl = new URL(window.location);
          newUrl.searchParams.set('tab', tabId);
          window.history.pushState({}, '', newUrl);
        }
      }
    }

    buttons.forEach(btn => {
      btn.addEventListener("click", () => activateTab(btn.dataset.tab, true));
    });

    // Auto-activate tab from URL (?tab=...)
    document.addEventListener("DOMContentLoaded", async () => {
      const params = new URLSearchParams(window.location.search);
      let tabParam = (params.get("tab") || "").toLowerCase();
      if (tabParam === "myflashcards") tabParam = "flashcards";
      const valid = ["dashboard","flashcards","search","create","settings"];
      if (valid.includes(tabParam)) {
        activateTab(tabParam);
      }

      // Load live mastery label
      try {
        const res = await fetch('dashboard.php?api=mastery', {cache:'no-store'});
        const data = await res.json();
        if (data.ok) {
          const lbl = document.getElementById('masteryLive');
          if (lbl) lbl.textContent = `Current: ${data.percent}%`;
        }
      } catch (e) { /* ignore */ }
    });

   function confirmArchive(id){
  if(confirm("Are you sure you want to delete this deck? You can restore it later from the archive.")) {
    document.getElementById('archiveDeckId').value = id;
    document.getElementById('archiveForm').submit();
  }
}



  // ----- Search Code tab -----
  async function doCodeSearch() {
  const q = document.getElementById('codeQuery').value.trim();
  const container = document.getElementById('searchResult');

  if (!q) {
    container.innerHTML = '<div class="no-result">Enter a code to search.</div>';
    return;
  }

  container.innerHTML = '<div class="no-result">Searching…</div>';

  try {
    const res = await fetch('dashboard.php?api=search_code&code=' + encodeURIComponent(q), { cache: 'no-store' });
    const data = await res.json();

    if (!data.ok) {
      container.innerHTML = '<div class="no-result" style="color:#c00;">Error searching.</div>';
      return;
    }

    if (!data.deck) {
      container.innerHTML = '<div class="no-result">No deck found for that code.</div>';
      return;
    }

   const d = data.deck;

// 🛑 Skip deleted decks
if (d.status === 'deleted') {
  container.innerHTML = '<div class="no-result">This deck has been deleted and is no longer available.</div>';
  return;
}

const thumb = d.thumb ? d.thumb : 'images/labers.png';
const isPrivate = d.visibility === 'private';
const isFav = d.is_favorited || false;


    const favBtn = isPrivate
      ? ''
      : `
        <button type="button" 
                class="btn-fav ${isFav ? 'active' : ''}" 
                data-id="${d.id}"
                onclick="toggleSearchFavorite(${d.id}, this)"
                title="${isFav ? 'Remove from favorites' : 'Add to favorites'}">
          ${isFav ? '❤️' : '🤍'}
        </button>
      `;

    // ✅ Copy button like in favorites
    const copyBtn = `
  <button class="btn-copy" onclick="copySearchCode('${d.share_code}', this)">
    Copy
  </button>
`;


    container.innerHTML = `
<div class="deck-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
    <img src="${thumb}" alt="Thumbnail" class="deck-thumb w-full h-40 object-cover">

    <div class=" p-4 flex flex-col justify-between">
      <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2">
        ${escapeHtml(d.title || 'Untitled')}
      </h3>

      <div class=" text-sm text-gray-600 dark:text-gray-400 mb-3 grid grid-cols-2 gap-y-1">
        <p>👤 ${escapeHtml(d.owner || 'Unknown')}</p>
        <p>🃏 ${d.card_count} card${d.card_count == 1 ? '' : 's'}</p>
        <p>👁️ ${d.views || 0} views</p>
        <p>📅 ${escapeHtml(d.created_at || '')}</p>
      </div>

        <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-xs flex justify-between items-center">
          <span class="text-gray-800 dark:text-gray-200">
            <strong>Code:</strong> ${escapeHtml(d.share_code || '')}
          </span>
          <button class="btn-copy text-primary hover:underline" onclick="copySearchCode('${d.share_code}', this)">Copy</button>
        </div>


      <div class="deck-actions flex flex-wrap justify-between items-center gap-3 mt-2">
        <a href="deck_view.php?code=${encodeURIComponent(d.share_code)}" 
           class="btn bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition">
          Open & Play
        </a>

        <!-- Like button -->
<button type="button" 
    class="btn-like text-2xl ml-2 flex items-center justify-center"
    data-id="${d.id}"
    title="${d.is_liked ? 'Unlike' : 'Like'}"
    onclick="toggleSearchLike(${d.id}, this)">
    <span class="like-icon">${d.is_liked ? '❤️' : '🤍'}</span>
    <span class="like-count ml-1 text-sm">${d.like_count || 0}</span>
</button>

<!-- Bookmark button -->
<button type="button"
    class="btn-save text-2xl ml-2 flex items-center justify-center"
    data-id="${d.id}"
    title="${isFav ? 'Remove from bookmarks' : 'Add to bookmarks'}"
    onclick="toggleSearchFavorite(${d.id}, this)">
    <span class="save-icon material-icons">
        ${isFav ? 'bookmark' : 'bookmark_border'}
    </span>
</button>

      </div>
    </div>
  </div>
</div>

    `;

  } catch (e) {
    container.innerHTML = '<div class="no-result" style="color:#c00;">Network error.</div>';
  }
}

/* --- Copy button (no alert, smooth feedback) --- */
/* --- Copy Code (Search Result) --- */
function copySearchCode(code, btnElement) {
  navigator.clipboard.writeText(code).then(() => {
    const originalText = btnElement.textContent;
    btnElement.textContent = '✓';
    btnElement.style.background = '#48bb78'; // green success

    setTimeout(() => {
      btnElement.textContent = originalText;
      btnElement.style.background = '#edf2f7'; // original color
    }, 2000);
  }).catch(err => {
    console.error('Copy failed:', err);
    alert('Failed to copy code');
  });
}



async function toggleSearchLike(deckId, btnElement) {
  btnElement.disabled = true;
  
  try {
    const res = await fetch(`dashboard.php?api=toggle_like&deck_id=${deckId}`, { cache: 'no-store' });
    const data = await res.json();
    
    if (data.ok) {
      const icon = btnElement.querySelector('.like-icon');
      const count = btnElement.querySelector('.like-count');
      
      icon.textContent = data.liked ? '❤️' : '🤍';
      count.textContent = data.like_count;
      btnElement.title = data.liked ? 'Unlike' : 'Like';
    } else if (data.error === 'not_logged_in') {
      alert('Please log in to like a deck!');
    } else {
      alert('Failed to update like.');
    }
    
  } catch (err) {
    console.error(err);
    alert('Error updating like. Try again.');
  } finally {
    btnElement.disabled = false;
  }
}


async function toggleSearchFavorite(deckId, btnElement) {
  btnElement.disabled = true;
  
  try {
    const res = await fetch(`dashboard.php?api=toggle_favorite&deck_id=${deckId}`, { cache: 'no-store' });
    const data = await res.json();
    
    if (data.ok) {
      const icon = btnElement.querySelector('.save-icon');
      if (data.favorited) {
        btnElement.classList.add('active');
        icon.textContent = 'bookmark';
        btnElement.title = 'Remove from bookmarks';
      } else {
        btnElement.classList.remove('active');
        icon.textContent = 'bookmark_border';
        btnElement.title = 'Add to bookmarks';
      }
    } else if (data.error === 'not_logged_in') {
      alert('Please log in to bookmark a deck!');
    } else {
      alert('Failed to update bookmark.');
    }
  } catch (err) {
    console.error(err);
    alert('Error updating bookmark. Try again.');
  } finally {
    btnElement.disabled = false;
  }
}


function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  })[m]);
}





   

async function updateVisibility(deckId, value) {
  const formData = new FormData();
  formData.append('update_visibility', '1');
  formData.append('deck_id', deckId);
  formData.append('visibility', value);

  try {
    const res = await fetch('dashboard.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.ok) {
      // Reload page while staying on current tab
      window.location.href = 'dashboard.php?tab=flashcards';
    }
  } catch (err) {
    console.error('Network error:', err);
  }
}

async function showCommunityPlayed() {
  const countEl = document.getElementById('totalDecksCount');
  countEl.textContent = 'Loading…';

  try {
    const res = await fetch('dashboard.php?api=community_played', { cache: 'no-store' });
    const data = await res.json();

    if (data.ok) {
      countEl.textContent = data.count;
    } else {
      countEl.textContent = '—';
    }
  } catch (e) {
    countEl.textContent = '—';
    console.error('Error fetching community decks played:', e);
  }
}

// Automatically load the count on page load
document.addEventListener('DOMContentLoaded', showCommunityPlayed);



// ========== FAVORITES FUNCTIONALITY ==========

function showFavorites() {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
  document.getElementById('favoritesSection').classList.add('active');
  
  // Update tab button active state
  const favBtn = document.querySelector('.tab-btn[onclick="showFavorites()"]');
  if (favBtn) favBtn.classList.add('active');
  
  loadFavorites();
}

async function loadFavorites() {
  const list = document.getElementById('favoritesList');
  list.innerHTML = '<div class="loading">Loading your favorites...</div>';
  
  try {
    const res = await fetch('dashboard.php?api=favorites', { cache: 'no-store' });
    const data = await res.json();
    
    if (!data.ok) {
      list.innerHTML = '<div class="no-favorites">⚠️ Error loading favorites.<br><small>Please try again later.</small></div>';
      return;
    }
    
    const favs = data.favorites;
    
    if (favs.length === 0) {
      list.innerHTML = `
        <div class="no-favorites">
          You haven't saved any favorites yet.<br>
          <small style="font-size: 0.9rem; color: #718096; margin-top: 10px; display: block;">
            Start exploring the community and save your favorite decks!
          </small>
        </div>
      `;
      return;
    }
    
    // Render favorite decks
list.innerHTML = favs.map(deck => {
  // Handle thumbnail path
  let thumbPath = deck.thumb || 'images/labers.png';
  if (thumbPath && !thumbPath.startsWith('uploads/') && !thumbPath.startsWith('images/')) {
    thumbPath = 'uploads/' + thumbPath;
  }
  
  // Format date
  const createdDate = new Date(deck.created_at);
  const formattedDate = createdDate.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric', 
    year: 'numeric' 
  });
  
  return `
<div class="deck-card-modern bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col hover:scale-105">
  <!-- Thumbnail -->
  <div class="relative">
    <img src="${escapeHtml(thumbPath)}"
         alt="${escapeHtml(deck.title)}"
         class=" w-full h-48 object-cover"
         loading="lazy"
         onerror="this.src='images/labers.png'">

    <!-- Topic Badge -->
    <span class="topic-badge absolute top-3 right-3 bg-blue-600 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-md">
      ${escapeHtml(deck.topic || 'General')}
    </span>
  </div>

  <!-- Content -->
  <div class=" bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark p-5">
    <h3 class="text-lg sm:text-xl font-bold text-left text-gray-800 dark:text-text-dark">${escapeHtml(deck.title)}</h3>

    <!-- Info Grid -->
    <div class="deck-info grid grid-cols-2 sm:grid-cols-4 gap-2 dark:text-gray-300 text-gray-800 text-sm mb-2">
      <div class="info-cell flex items-center gap-1"><span>👤</span>${escapeHtml(deck.owner)}</div>
      <div class="info-cell flex items-center gap-1"><span>📝</span>${deck.card_count || 0} cards</div>
      <div class="info-cell flex items-center gap-1"><span>👁️</span>${deck.views || 0} views</div>
      <div class="info-cell flex items-center gap-1"><span>🕒</span>${formattedDate}</div>
    </div>

    <!-- Share Code -->
    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-sm flex justify-between items-center">
        <strong>Code:</strong> <span class="code-text font-mono">${escapeHtml(deck.share_code)}</span>
      <button 
        class="btn-copy bg-primary text-white text-xs px-3 py-1 rounded-md hover:bg-primary/90 transition"
        onclick="copyFavCode('${escapeHtml(deck.share_code)}', this)">
        Copy
      </button>
    </div>

    <!-- Actions -->
    <div class="deck-actions flex justify-between items-center">
      <a href="deck_view.php?deck=${deck.id}" 
         class="btn-view flex-1 text-white font-medium px-8 py-2 rounded-md text-center transition">
        Open & Play
      </a>

      <!-- Like Button -->
     <!-- Like button -->
<button type="button" 
    class="btn-like text-xl ml-2 flex items-center justify-center"
    data-id="${deck.id}"
    title="${deck.is_liked ? 'Unlike' : 'Like'}"
    onclick="toggleLike(${deck.id}, this)">
    <span class="like-icon">${deck.is_liked ? '❤️' : '🤍'}</span>
    <span class="like-count ml-1 text-sm">${deck.like_count}</span>
</button>

<!-- Bookmark button -->
<button type="button"
    class="btn-save text-xl ml-2 flex items-center justify-center"
    data-id="${deck.id}"
    title="${deck.is_fav ? 'Remove from bookmarks' : 'Add to bookmarks'}"
    onclick="toggleFavorite(${deck.id}, this)">
    <span class="save-icon material-icons">
        ${deck.is_fav ? 'bookmark' : 'bookmark_border'}
    </span>
</button>

    </div>
  </div>
</div>
  `;
}).join('');

    
  } catch (err) {
    console.error('Error loading favorites:', err);
    list.innerHTML = `
      <div class="no-favorites">
        ⚠️ Failed to load favorites.<br>
        <small style="font-size: 0.9rem; color: #718096; margin-top: 10px; display: block;">
          Please check your connection and try again.
        </small>
      </div>
    `;
  }
}

// Copy share code for favorites
function copyFavCode(code, btnElement) {
  navigator.clipboard.writeText(code).then(() => {
    const originalText = btnElement.textContent;
    btnElement.textContent = '✓';
    btnElement.style.background = '#48bb78';
    
    // Show toast notification if available
    if (typeof showToast === 'function') {
      showToast(`Code "${code}" copied!`, 'success');
    }
    
    setTimeout(() => {
      btnElement.textContent = originalText;
      btnElement.style.background = '#4A90E2';
    }, 2000);
  }).catch(err => {
    console.error('Copy failed:', err);
    if (typeof showToast === 'function') {
      showToast('Failed to copy code', 'error');
    } else {
      alert('Failed to copy code');
    }
  });
}

// Unfavorite a deck
async function unfavoriteDeck(deckId, btnElement) {
  if (!confirm("Remove this deck from your favorites?")) {
    return;
  }
  
  btnElement.disabled = true;
  const originalContent = btnElement.innerHTML;
  btnElement.innerHTML = '⏳';
  
  try {
    const res = await fetch(`dashboard.php?api=unfavorite&deck_id=${deckId}`, { 
      cache: 'no-store' 
    });
    const data = await res.json();
    
    if (data.ok) {
      // Show success message
      if (typeof showToast === 'function') {
        showToast('Removed from favorites!', 'success');
      }
      
      // Reload favorites list with smooth transition
      setTimeout(() => {
        loadFavorites();
      }, 300);
      
    } else {
      throw new Error(data.error || 'Failed to remove favorite');
    }
    
  } catch (err) {
    console.error('Unfavorite error:', err);
    
    if (typeof showToast === 'function') {
      showToast('Failed to remove favorite. Please try again.', 'error');
    } else {
      alert('Failed to remove favorite. Please try again.');
    }
    
    btnElement.disabled = false;
    btnElement.innerHTML = originalContent;
  }
}

// Helper function to escape HTML
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Optional: Auto-refresh favorites when returning to the tab
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    const favSection = document.getElementById('favoritesSection');
    if (favSection && favSection.classList.contains('active')) {
      loadFavorites();
    }
  }
});

async function toggleLike(deckId, btn) {
    try {
        const res = await fetch(`dashboard.php?api=toggle_like&deck_id=${deckId}`, { cache: 'no-store' });
        const data = await res.json();
        if (data.ok) {
            btn.querySelector('.like-icon').textContent = data.liked ? '❤️' : '🤍';
            btn.querySelector('.like-count').textContent = data.like_count;
        }
    } catch (err) {
        console.error('Error toggling like', err);
    }
}

async function toggleFavorite(deckId, btn) {
    try {
        const res = await fetch(`dashboard.php?api=toggle_favorite&deck_id=${deckId}`, { cache: 'no-store' });
        const data = await res.json();
        if (data.ok) {
            btn.querySelector('.save-icon').textContent = data.favorited ? 'bookmark' : 'bookmark_border';
            // Optional: remove card from favorites list if unbookmarked
            if (!data.favorited) {
                btn.closest('.deck-card-modern').remove();
            }
        }
    } catch (err) {
        console.error('Error toggling favorite', err);
    }
}


// ========== HISTORY FUNCTIONALITY ==========

async function showHistory() {
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
  document.getElementById('historySection').classList.add('active');
  
  // Update tab button active state
  const historyBtn = document.querySelector('.tab-btn[onclick*="showHistory"]');
  if (historyBtn) historyBtn.classList.add('active');
  
  loadHistory();
}

async function loadHistory() {
  const list = document.getElementById('historyList');
  list.innerHTML = '<p class="loading-text">Loading your history...</p>';
  
  try {
    const res = await fetch('dashboard.php?api=history', { cache: 'no-store' });
    const data = await res.json();
    
    if (!data.ok) {
      list.innerHTML = '<p class="loading-text" style="color:#c00;">⚠️ Error loading history.<br><small>Please try again later.</small></p>';
      return;
    }
    
    const decks = data.history;
    
    if (decks.length === 0) {
      list.innerHTML = `
        <div class="no-history">
          No recently played decks yet.<br>
          <small style="font-size: 0.9rem; color: #718096; margin-top: 10px; display: block;">
            Start playing flashcards to build your history!
          </small>
        </div>
      `;
      return;
    }
    
    // Render history decks
    list.innerHTML = decks.map(deck => {
      const isPrivate = deck.visibility === 'private';
      
      // Handle thumbnail path
      let thumbPath = deck.thumb || 'images/labers.png';
      if (thumbPath && !thumbPath.startsWith('uploads/') && !thumbPath.startsWith('images/')) {
        thumbPath = 'uploads/' + thumbPath;
      }
      
      const isFav = deck.is_fav || false;
      
      // Format last played date
      const lastPlayed = new Date(deck.last_played);
      const now = new Date();
      const diffTime = Math.abs(now - lastPlayed);
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
      
      let timeAgo;
      if (diffDays === 0) {
        timeAgo = 'Today';
      } else if (diffDays === 1) {
        timeAgo = 'Yesterday';
      } else if (diffDays < 7) {
        timeAgo = `${diffDays} days ago`;
      } else if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        timeAgo = `${weeks} ${weeks === 1 ? 'week' : 'weeks'} ago`;
      } else {
        timeAgo = lastPlayed.toLocaleDateString('en-US', { 
          month: 'short', 
          day: 'numeric', 
          year: 'numeric' 
        });
      }
      
      // Open button (disabled if private)
   /**   const openBtn = isPrivate
        ? `<button class="btn secondary" disabled title="Private deck — cannot access">
             <span>🔒</span>
             <span>Private</span>
           </button>`
        : `<a class="btn" href="deck_view.php?deck=${deck.id}">
             <span>Open & Play</span>
           </a>`;
      
      // Favorite button (hidden if private)
      const favBtn = isPrivate ? '' : `
        <button type="button" 
                class="btn-fav ${isFav ? 'active' : ''}" 
                data-id="${deck.id}"
                onclick="toggleHistoryFavorite(${deck.id}, this)"
                title="${isFav ? 'Remove from favorites' : 'Add to favorites'}"
                aria-label="${isFav ? 'Remove from favorites' : 'Add to favorites'}">
          ${isFav ? '❤️' : '🤍'}
        </button>
      `;  **/
      
      // Privacy badge
      const privacyBadge = isPrivate 
        ? '<span class="privacy-badge"><span>🔒</span><span>Private</span></span>' 
        : '';
      
      return `
<div class="deck-card-modern bg-white dark:bg-gray-800 rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col hover:scale-105">
  <!-- Thumbnail -->
  <div class="relative">
    <img src="${escapeHtml(thumbPath)}"
         alt="${escapeHtml(deck.title)}"
         class=" w-full h-48 object-cover"
         loading="lazy"
         onerror="this.src='images/labers.png'">

    <!-- Privacy Badge (if exists) -->
    ${privacyBadge}

    <!-- Topic Badge -->
    <span class="topic-badge absolute top-3 right-3 text-xs font-semibold px-3 py-1 rounded-full shadow-md">
      ${escapeHtml(deck.topic || 'General')}
    </span>
  </div>

  <!-- Content -->
  <div class=" bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark p-5">
    <h3 class="text-lg sm:text-xl text-text-light dark:text-text-dark font-bold mb-2">
      ${escapeHtml(deck.title)}
    </h3>

    <!-- Deck Info -->
    <div class="deck-info grid grid-cols-2 sm:grid-cols-4 gap-2 text-text-light dark:text-gray-300 text-sm mb-2">
      <div class="info-cell flex items-center gap-1"><span>👤</span>${escapeHtml(deck.owner)}</div>
      <div class="info-cell flex items-center gap-1"><span>📝</span>${deck.card_count || 0} cards</div>
      <div class="info-cell flex items-center gap-1"><span>👁️</span>${deck.views || 0} views</div>
      <div class="info-cell flex items-center gap-1"><span>🕒</span>${new Date(deck.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })}</div>
    </div>

    <!-- Share Code -->
    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-sm flex justify-between items-center">
        <strong>Code:</strong> 
        <span class="code-text font-mono">${escapeHtml(deck.share_code || 'N/A')}</span>
      </span>
      ${deck.share_code ? `
      <button 
        class="btn-copy bg-primary text-white text-xs px-3 py-1 rounded-md hover:bg-primary/90 transition"
        onclick="copyFavCode('${escapeHtml(deck.share_code)}', this)">
        Copy
      </button>` : ''}
    </div>

    <div class="border border-dashed border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 flex flex-col sm:flex-row sm:items-center gap-1 bg-gray-100 text-gray-700 text-sm px-3 py-2 rounded-md mb-2">
      <div class="flex items-center gap-2">
        <span class="icon text-blue-600">🕒</span>
        <span class="text-gray-800 dark:text-white text-xs">Last played: ${timeAgo}</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="icon text-green-600">🏆</span>
        <span class="text-gray-800 dark:text-white text-xs">Score: ${deck.last_score || '—'}</span>
      </div>
    </div>

    <!-- Actions 
    <div class="deck-actions flex justify-between items-center">

    </div> -->
  </div>
</div>
      `;
    }).join('');
    
  } catch (err) {
    console.error('Error loading history:', err);
    list.innerHTML = `
      <p class="loading-text" style="color:#c00;">
        ⚠️ Network error. Please try again.<br>
        <small style="font-size: 0.9rem; color: #718096; margin-top: 10px; display: block;">
          Check your connection and refresh the page.
        </small>
      </p>
    `;
  }
}

// Toggle favorite for history items
async function toggleHistoryFavorite(deckId, btnElement) {
  btnElement.disabled = true;
  const originalContent = btnElement.innerHTML;
  btnElement.innerHTML = '⏳';
  
  try {
    const res = await fetch(`dashboard.php?api=toggle_favorite&deck_id=${deckId}`, { 
      cache: 'no-store' 
    });
    const data = await res.json();
    
    if (data.ok) {
      if (data.favorited) {
        btnElement.classList.add('active');
        btnElement.innerHTML = '❤️';
        btnElement.title = 'Remove from favorites';
        
        // Show success toast if available
        if (typeof showToast === 'function') {
          showToast('Added to favorites!', 'success');
        }
      } else {
        btnElement.classList.remove('active');
        btnElement.innerHTML = '🤍';
        btnElement.title = 'Add to favorites';
        
        if (typeof showToast === 'function') {
          showToast('Removed from favorites', 'success');
        }
      }
    } else if (data.error === 'not_logged_in') {
      if (typeof showToast === 'function') {
        showToast('Please log in to save favorites!', 'error');
      } else {
        alert('Please log in to save favorites!');
      }
      btnElement.innerHTML = originalContent;
    } else {
      throw new Error(data.error || 'Failed to update favorite');
    }
    
  } catch (err) {
    console.error('Favorite toggle error:', err);
    
    if (typeof showToast === 'function') {
      showToast('Error saving favorite. Please try again.', 'error');
    } else {
      alert('Error saving favorite. Please try again.');
    }
    
    btnElement.innerHTML = originalContent;
  } finally {
    btnElement.disabled = false;
  }
}

// Helper function to escape HTML
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Optional: Auto-refresh history when returning to the tab
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    const historySection = document.getElementById('historySection');
    if (historySection && historySection.classList.contains('active')) {
      loadHistory();
    }
  }
});

function escapeHtml(str) {
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

  </script>

<script>
// 🔁 Load notifications & badge count
async function updateNotifications(showModal = false) {
  const notifList = document.getElementById('notifList');
  const badge = document.getElementById('notifBadge');

  if (showModal) notifList.innerHTML = '<p class="notif-empty">Loading notifications...</p>';

  try {
    const res = await fetch('dashboard.php?api=notifications', { cache: 'no-store' });
    const data = await res.json();

    if (!data.ok) throw new Error("Failed to fetch");

    // Count unread notifications
    const unreadCount = data.notifications.filter(n => n.is_read == 0).length;

    // 🔴 Show or hide badge
    badge.style.display = unreadCount > 0 ? 'inline' : 'none';
    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;

    // 🧾 Render notifications
    if (showModal) {
      if (data.notifications.length === 0) {
        notifList.innerHTML = '<p class="notif-empty text-text-light dark:text-text-dark">No new notifications.</p>';
      } else {
        notifList.innerHTML = data.notifications.map(n => {
          const msg = n.message.toLowerCase();
          const type = msg.includes("approved") ? "approved"
                      : msg.includes("declined") ? "declined"
                      : msg.includes("deleted") ? "deleted"
                      : "info";

          const icons = {
            approved: "✅",
            declined: "❌",
            deleted: "🗑",
            info: "🔔"
          };

          const colors = {
            approved: "#16a34a",
            declined: "#dc2626",
            deleted: "#f97316",
            info: "#4f46e5"
          };

          return `
            <div class="notif-item ${n.is_read ? '' : 'unread'}">
              <div class="notif-icon" style="color:${colors[type]}">${icons[type]}</div>
              <div class="notif-text text-text-light  dark:text-text-dark">
                <p>${n.message}</p>
                <small>${new Date(n.created_at).toLocaleString()}</small>
              </div>
            </div>
          `;
        }).join('');
      }

      // ✅ Mark notifications as read when viewed
      await fetch('dashboard.php?api=mark_notifications_read', { method: 'POST' });
      badge.style.display = 'none';
    }
  } catch (err) {
    console.error(err);
    notifList.innerHTML = '<p class="notif-empty" style="color:red;">⚠️ Failed to load notifications.</p>';
  }
}

// 🛎️ Open modal
document.getElementById('notifBell').addEventListener('click', function() {
  document.getElementById('notifModal').style.display = 'flex';
  updateNotifications(true);
});

// ❌ Close modal
document.getElementById('closeNotif').addEventListener('click', function() {
  document.getElementById('notifModal').style.display = 'none';
});

// 🖱️ Close when clicking outside
window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('notifModal')) {
    document.getElementById('notifModal').style.display = 'none';
  }
});

// 🔁 Load badge on page load & every 30 seconds
updateNotifications();
setInterval(updateNotifications, 30000);

</script>


<script>
  function copyCode(code, btnElement) {
  navigator.clipboard.writeText(code).then(() => {
    const originalText = btnElement.textContent;
    btnElement.textContent = '✓';
    btnElement.style.background = '#48bb78';
    setTimeout(() => {
      btnElement.textContent = originalText;
      btnElement.style.background = '#667eea';
    }, 2000);
  }).catch(err => {
    console.error('Copy failed:', err);
    alert('Failed to copy code');
  });
}

// MOBILE HAMBURGER
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    hamburgerBtn.addEventListener('click', () => {
      sidebar.classList.toggle('-translate-x-full');
      overlay.classList.toggle('hidden');
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
    });


// Dark Mode
    const themeToggle = document.getElementById('theme-toggle');
    const sunIcon = 'light_mode';
    const moonIcon = 'dark_mode';

    themeToggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      const isDark = document.documentElement.classList.contains('dark');
      themeToggle.querySelector('span').textContent = isDark ? sunIcon : moonIcon;
      localStorage.theme = isDark ? 'dark' : 'light';
    });

    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
      themeToggle.querySelector('span').textContent = sunIcon;
    } else {
      document.documentElement.classList.remove('dark');
      themeToggle.querySelector('span').textContent = moonIcon;
    }
</script>
</body>
</html>