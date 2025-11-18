<?php
session_start();
include 'db.php';

/* ---------- LIKE TOGGLE API ---------- */
if (isset($_GET['api']) && $_GET['api'] === 'toggle_like') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['username'])) {
        echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
        exit;
    }

    $deck_id = (int)($_GET['deck_id'] ?? 0);
    if ($deck_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_id']);
        exit;
    }

    $uStmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $uStmt->bind_param("s", $_SESSION['username']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $user_id = (int)($uRow['id'] ?? 0);

    if (!$user_id) {
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit;
    }

    $check = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND deck_id=? LIMIT 1");
    $check->bind_param("ii", $user_id, $deck_id);
    $check->execute();
    $isLiked = $check->get_result()->num_rows > 0;

    if ($isLiked) {
        $del = $conn->prepare("DELETE FROM likes WHERE user_id=? AND deck_id=?");
        $del->bind_param("ii", $user_id, $deck_id);
        $del->execute();
        echo json_encode(['ok' => true, 'liked' => false]);
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO likes (user_id, deck_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $deck_id);
        $ins->execute();
        echo json_encode(['ok' => true, 'liked' => true]);
    }
    exit;
}


/* ---------- FAVORITE TOGGLE API ---------- */
if (isset($_GET['api']) && $_GET['api'] === 'toggle_favorite') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['username'])) {
        echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
        exit;
    }

    $deck_id = (int)($_GET['deck_id'] ?? 0);
    if ($deck_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_id']);
        exit;
    }

    // Find user ID
    $uStmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $uStmt->bind_param("s", $_SESSION['username']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $user_id = (int)($uRow['id'] ?? 0);

    if (!$user_id) {
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit;
    }

    // Check if it's already a favorite
    $check = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND deck_id=? LIMIT 1");
    $check->bind_param("ii", $user_id, $deck_id);
    $check->execute();
    $isFav = $check->get_result()->num_rows > 0;

    if ($isFav) {
        $del = $conn->prepare("DELETE FROM favorites WHERE user_id=? AND deck_id=?");
        $del->bind_param("ii", $user_id, $deck_id);
        $del->execute();
        echo json_encode(['ok' => true, 'favorited' => false]);
    } else {
        $ins = $conn->prepare("INSERT IGNORE INTO favorites (user_id, deck_id) VALUES (?, ?)");
        $ins->bind_param("ii", $user_id, $deck_id);
        $ins->execute();
        echo json_encode(['ok' => true, 'favorited' => true]);
    }
    exit;
}

/* ---------- FETCH DECKS (with favorite info) ---------- */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$topic  = isset($_GET['topic']) ? trim($_GET['topic']) : '';

$user_id = 0;
if (isset($_SESSION['username'])) {
    $uStmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $uStmt->bind_param("s", $_SESSION['username']);
    $uStmt->execute();
    $uRow = $uStmt->get_result()->fetch_assoc();
    $user_id = (int)($uRow['id'] ?? 0);
}

$sql = "SELECT d.id, d.title, d.topic, d.share_code, d.thumbnail, 
               u.username, d.created_at, 
               IF(f.deck_id IS NULL, 0, 1) AS is_fav,
               IF(l.deck_id IS NULL, 0, 1) AS is_liked,
               (SELECT COUNT(*) FROM likes WHERE deck_id = d.id) AS like_count,
               COUNT(DISTINCT s.id) AS total_views,
               (SELECT COUNT(*) FROM flashcards WHERE deck_id = d.id) AS card_count
        FROM decks d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN favorites f ON f.deck_id = d.id AND f.user_id = ?
        LEFT JOIN likes l ON l.deck_id = d.id AND l.user_id = ?
        LEFT JOIN play_sessions s ON s.deck_id = d.id
        WHERE d.status = 'approved' AND d.visibility = 'public'
        AND d.archived = 0";


$params = [];
$types  = 'i';
$params[] = &$user_id;

if (!empty($search)) {
    $sql .= " AND (d.title LIKE ? OR u.username LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = &$searchParam;
    $params[] = &$searchParam;
    $types .= 'ss';
}

if (!empty($topic)) {
    $sql .= " AND d.topic = ?";
    $params[] = &$topic;
    $types .= 's';
}

$sql .= " GROUP BY d.id";

$sort = $_GET['sort'] ?? 'latest';
if ($sort === 'views') {
    $sql .= " ORDER BY total_views DESC, d.created_at DESC";
} elseif ($sort === 'likes') {
    $sql .= " ORDER BY like_count DESC, d.created_at DESC";
} else {
    $sql .= " ORDER BY d.created_at DESC";
}


$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
}

if (!empty($search) && !empty($topic)) {
    $searchParam = "%{$search}%";
    $stmt->bind_param("iisss", $user_id, $user_id, $searchParam, $searchParam, $topic);
} elseif (!empty($search)) {
    $searchParam = "%{$search}%";
    $stmt->bind_param("iiss", $user_id, $user_id, $searchParam, $searchParam);
} elseif (!empty($topic)) {
    $stmt->bind_param("iis", $user_id, $user_id, $topic);
} else {
    $stmt->bind_param("ii", $user_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$decks = $result->fetch_all(MYSQLI_ASSOC);
$totalDecks = count($decks);

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Decks — Flippix</title>
  <!-- Tailwind + Google Fonts -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="styles/community.css"> 
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
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 flex flex-col min-h-screen">

<!-- Navbar -->
<header class="bg-card-light dark:bg-card-dark shadow-md sticky top-0 z-50 transition-colors duration-300">
  <nav class="container mx-auto px-6 py-3 flex justify-between items-center">
    
    <!-- Logo -->
    <div class="flex items-center">
      <a href="dashboard.php" class="flex items-center space-x-2">
        <img src="images/labers.png" alt="Flippix Logo" class="h-10 w-10">
        <span class="text-xl font-semibold text-text-light dark:text-text-dark">Flippix</span>
      </a>
    </div>

    <!-- Hamburger Button (Mobile) -->
    <button id="menu-toggle"
      class="md:hidden text-gray-700 dark:text-gray-200 focus:outline-none p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition">
      <svg id="menu-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
        viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <svg id="close-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none"
        viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <!-- Desktop Links -->
    <div class="hidden md:flex items-center space-x-8">
      <a href="index.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">Home</a>
      <a href="howto.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">About</a>
      <a href="dashboard.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">Dashboard</a>
    </div>

    <!-- Desktop Theme Toggle -->
    <div class="hidden md:flex items-center">
      <button id="theme-toggle"
        class="p-2 rounded-full text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
        <span class="material-icons">dark_mode</span>
      </button>
    </div>
  </nav>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="md:hidden hidden bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
    <div class="flex flex-col space-y-3 px-6 py-4">
      <a href="index.php" class="text-primary font-semibold hover:text-blue-600">Home</a>
      <a href="howto.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">About</a>
      <a href="dashboard.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">Dashboard</a>

      <!-- Theme Toggle in Mobile -->
      <button id="theme-toggle-mobile"
        class="flex items-center space-x-2 p-2 mt-2 bg-gray-200 dark:bg-gray-700 rounded-lg text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
        <span class="material-icons">dark_mode</span>
        <span>Dark Theme</span>
      </button>
    </div>
  </div>
</header>

<div class="relative w-full mt-5">
  <div class="mx-auto px-6 text-center">
    <h1 class="text-2xl sm:text-3xl font-semibold text-gray-800 dark:text-gray-100">
      Explore Community Flashcards
    </h1>
  </div>
</div>



<div class="container">
  <div class="filters-wrapper">
    <form method="get" class="filters">
      <div class="filter-group ">
        <label class="filter-label text-text-light dark:text-text-dark">🔍 Search</label>
        <input class="bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark border-gray-800 dark:border-gray-300" type="text" name="search" placeholder="Deck title or creator..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
      </div>
      
      <div class="filter-group">
        <label class="filter-label text-text-light dark:text-text-dark">📚 Topic</label>
        <select name="topic" class="bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark border-gray-300 shadow-md rounded-lg">
          <option value="">All Topics</option>
          <option value="Science" <?= $topic === 'Science' ? 'selected' : '' ?>>Science</option>
          <option value="Math" <?= $topic === 'Math' ? 'selected' : '' ?>>Math</option>
          <option value="History" <?= $topic === 'History' ? 'selected' : '' ?>>History</option>
          <option value="English" <?= $topic === 'English' ? 'selected' : '' ?>>English</option>
          <option value="Technology" <?= $topic === 'Technology' ? 'selected' : '' ?>>Technology</option>
          <option value="General" <?= $topic === 'General' ? 'selected' : '' ?>>General</option>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label text-text-light dark:text-text-dark">⚡ Sort By</label>
        <select name="sort" class="bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark border-gray-300 shadow-md rounded-lg">
          <option value="latest" <?= ($_GET['sort'] ?? '') === 'latest' ? 'selected' : '' ?>>Latest</option>
          <option value="views" <?= ($_GET['sort'] ?? '') === 'views' ? 'selected' : '' ?>>Most Viewed</option>
              <option value="likes" <?= ($_GET['sort'] ?? '') === 'likes' ? 'selected' : '' ?>>Most Likes</option>
        </select>
      </div>

      <button type="submit" class="btn-search text-center bg-indigo-400 text-text-dark border-none shadow-lg">🔍 Search</button>
      <a href="community.php" class="btn-clear text-center bg-indigo-400 text-text-dark border-none shadow-lg ">✖️ Clear</a>
    </form>
  </div>

  <div class="deck-list px-10">
    <?php if (empty($decks)): ?>
      <div class="no-results bg-gray-200 dark:bg-gray-800">
        <div class="no-results-icon">🔍</div>
        <div class="no-results-text text-text-light dark:text-text-dark border-gray-800 ">No decks found</div>
        <div class="no-results-subtext text-text-light dark:text-text-dark border-gray-800 ">Try adjusting your filters or search terms</div>
      </div>
    <?php else: ?>
      <?php foreach ($decks as $row): 
        $thumb = trim($row['thumbnail']);
        if (empty($thumb)) {
          $thumbPath = 'images/labers.png';
        } elseif (!str_starts_with($thumb, 'uploads/')) {
          $thumbPath = 'uploads/' . $thumb;
        } else {
          $thumbPath = $thumb;
        }

        if (!file_exists($thumbPath)) {
          $thumbPath = 'images/labers.png';
        }
      ?>
        <div class="deck-card bg-white dark:bg-gray-800 text-text-light dark:text-text-dark rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col hover:scale-105">
          <div class="deck-thumb-wrapper">
            <img src="<?= htmlspecialchars($thumbPath) ?>" alt="<?= htmlspecialchars($row['title']) ?>" class="deck-thumb" loading="lazy">
            <span class="topic-badge absolute top-3 right-3 text-xs font-semibold px-3 py-1 rounded-full shadow-md"><?= htmlspecialchars($row['topic']) ?></span>
          </div>
          
          <div class="deck-content bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark">
            <h3><?= htmlspecialchars($row['title']) ?></h3>
            
            <div class="deck-info bg-card-light dark:bg-card-dark text-text-light dark:text-text-dark">
              <div class="info-row">
                <span class="info-icon">👤</span>
                <span class="text-text-light dark:text-text-dark"><?= htmlspecialchars($row['username']) ?></span>
              </div>
              <div class="info-row">
                <span class="info-icon">📝</span>
                <span class="text-text-light dark:text-text-dark"><?= (int)$row['card_count'] ?> cards</span>
              </div>
              <div class="info-row">
                <span class="info-icon">👁️</span>
                <span class="text-text-light dark:text-text-dark"><?= (int)$row['total_views'] ?> views</span>
              </div>
              <div class="info-row">
                <span class="info-icon">🕒</span>
                <span class="text-text-light dark:text-text-dark"><?= date("M j, Y", strtotime($row['created_at'])) ?></span>
              </div>
            </div>


  <div class=" bg-gray-100 dark:bg-gray-700 rounded-lg p-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-xs flex justify-between items-center">
    <span><strong>Code:</strong> <span class="code-text"><?= htmlspecialchars($row['share_code']) ?></span></span>
    <button class="btn-copy" onclick="copyCode('<?= htmlspecialchars($row['share_code']) ?>', this)">Copy</button>
  </div>


            
            <div class="deck-actions flex items-center space-x-3">
    <a href="deck_view.php?deck=<?= $row['id'] ?>" class="btn-view">
        <span>Open & Play</span>
    </a>

    <button type="button" 
        class="btn-like flex items-center justify-center <?= $row['is_liked'] ? 'active' : '' ?>" 
        data-id="<?= $row['id'] ?>"
        title="<?= $row['is_liked'] ? 'Unlike' : 'Like' ?>">
        <span class="like-icon text-lg"><?= $row['is_liked'] ? '❤️' : '🤍' ?></span>
        <span class="like-count ml-1"><?= $row['like_count'] ?></span>
    </button>

    <button type="button" 
        class="btn-save flex items-center justify-center <?= $row['is_fav'] ? 'active' : '' ?>" 
        data-id="<?= $row['id'] ?>"
        title="<?= $row['is_fav'] ? 'Remove from bookmarks' : 'Add to bookmarks' ?>">
        <span class="save-icon material-icons text-2xl">
            <?= $row['is_fav'] ? 'bookmark' : 'bookmark_border' ?>
        </span>
    </button>
</div>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="toast" id="toast">
  <span id="toast-message"></span>
</div>

  <script>
  const menuToggle = document.getElementById('menu-toggle');
  const menu = document.getElementById('mobile-menu');
  const menuIcon = document.getElementById('menu-icon');
  const closeIcon = document.getElementById('close-icon');

  menuToggle.addEventListener('click', () => {
    menu.classList.toggle('hidden');
    menuIcon.classList.toggle('hidden');
    closeIcon.classList.toggle('hidden');
  });

  const themeToggles = document.querySelectorAll('#theme-toggle, #theme-toggle-mobile');
  const sunIcon = 'light_mode';
  const moonIcon = 'dark_mode';

  themeToggles.forEach(toggle => {
    toggle.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      const isDark = document.documentElement.classList.contains('dark');
      themeToggles.forEach(btn => btn.querySelector('span').textContent = isDark ? sunIcon : moonIcon);
      localStorage.theme = isDark ? 'dark' : 'light';
    });
  });

  // Apply saved or system theme
  if (localStorage.theme === 'dark' ||
      (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    themeToggles.forEach(btn => btn.querySelector('span').textContent = sunIcon);
  } else {
    document.documentElement.classList.remove('dark');
    themeToggles.forEach(btn => btn.querySelector('span').textContent = moonIcon);
  }

// Toast notification system
function showToast(message, type = 'success') {
  const toast = document.getElementById('toast');
  const toastMessage = document.getElementById('toast-message');
  
  toastMessage.textContent = message;
  toast.className = `toast ${type} show`;
  
  setTimeout(() => {
    toast.classList.remove('show');
  }, 3000);
}

// Copy share code function
function copyCode(code, btn) {
  navigator.clipboard.writeText(code).then(() => {
    const originalText = btn.textContent;
    btn.textContent = '✓';
    btn.style.background = '#48bb78';
    showToast(`Code "${code}" copied to clipboard!`, 'success');
    
    setTimeout(() => {
      btn.textContent = originalText;
      btn.style.background = '#667eea';
    }, 2000);
  }).catch(() => {
    showToast('Failed to copy code', 'error');
  });
}

document.querySelectorAll('.btn-save').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        btn.disabled = true;

        try {
            const res = await fetch(`community.php?api=toggle_favorite&deck_id=${id}`, {cache:'no-store'});
            const data = await res.json();

            if (data.ok) {
                const icon = btn.querySelector('.save-icon');
                if (data.favorited) {
                    btn.classList.add('active');
                    icon.textContent = 'bookmark';
                    btn.title = 'Remove from bookmarks';
                    showToast('Added to bookmarks!', 'success');
                } else {
                    btn.classList.remove('active');
                    icon.textContent = 'bookmark_border';
                    btn.title = 'Add to bookmarks';
                    showToast('Removed from bookmarks', 'success');
                }
            } else if (data.error === 'not_logged_in') {
                showToast('Please log in to save bookmarks!', 'error');
            } else {
                showToast('Something went wrong. Try again.', 'error');
            }
        } catch (err) {
            showToast('Error saving bookmark. Try again.', 'error');
            console.error(err);
        } finally {
            btn.disabled = false;
        }
    });
});

</script>

<script>
 document.querySelectorAll('.btn-like').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    btn.disabled = true;

    try {
      const res = await fetch(`community.php?api=toggle_like&deck_id=${id}`, {cache:'no-store'});
      const data = await res.json();

      if (data.ok) {
        const countSpan = btn.querySelector('.like-count');
        const iconSpan = btn.querySelector('.like-icon');
        let count = parseInt(countSpan.textContent) || 0;

        if (data.liked) {
          btn.classList.add('active');
          btn.title = 'Unlike';
          iconSpan.textContent = '❤️';
          count++;
          showToast('Liked the deck!', 'success');
        } else {
          btn.classList.remove('active');
          btn.title = 'Like';
          iconSpan.textContent = '🤍';
          count = Math.max(0, count - 1);
          showToast('Removed like', 'success');
        }
        countSpan.textContent = count;
      } else if (data.error === 'not_logged_in') {
        showToast('Please log in to like decks!', 'error');
      } else {
        showToast('Something went wrong. Please try again.', 'error');
      }
    } catch (err) {
      showToast('Error liking deck. Please try again.', 'error');
      console.error(err);
    } finally {
      btn.disabled = false;
    }
  });
});

</script>

</body>
</html>