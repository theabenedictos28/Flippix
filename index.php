<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

/* ---------- FAVORITE TOGGLE API ---------- */
if (isset($_GET['api']) && $_GET['api'] === 'toggle_favorite') {
    header('Content-Type: application/json; charset=utf-8');

    $deck_id = (int)($_GET['deck_id'] ?? 0);
    if ($deck_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_id']);
        exit;
    }

    // Find user ID
    $uStmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $uStmt->bind_param("s", $_SESSION['username']);
    $uStmt->execute();
    $user_id = (int)($uStmt->get_result()->fetch_assoc()['id'] ?? 0);

    if (!$user_id) {
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit;
    }

    // Check if already a favorite
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

/* ---------- FETCH USER ID FOR FAVORITES ---------- */
$user_id = 0;
if (isset($_SESSION['username'])) {
    $uStmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $uStmt->bind_param("s", $_SESSION['username']);
    $uStmt->execute();
    $user_id = (int)($uStmt->get_result()->fetch_assoc()['id'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Flippix - Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
    .btn-fav {
  background: #fff;
  color: #e53e3e;
  border: 2px solid #e53e3e;
  padding: 10px;
  border-radius: 8px;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.btn-fav:hover {
  transform: scale(1.1) rotate(5deg);
  box-shadow: 0 6px 18px rgba(229,62,62,0.3);
}

.btn-fav.active {
  background: #e53e3e;
  color: #fff;
  border-color: #e53e3e;
  animation: heartbeat 0.3s ease;
}
.deck-actions {
  display: flex;
  gap: 6px;
  margin-top: auto;
  padding-top: 10px;
}
.btn-view {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%);
  color: #fff;
  padding: 10px 12px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  text-align: center;
  transition: all 0.3s ease;
  font-size: 12px;
  border: none;
  cursor: pointer;
}


.btn-view:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(102,126,234,0.4);
}

.btn-fav {
  background: #fff;
  color: #e53e3e;
  border: 2px solid #e53e3e;
  padding: 10px;
  border-radius: 8px;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.btn-fav:hover {
  transform: scale(1.1) rotate(5deg);
  box-shadow: 0 6px 18px rgba(229,62,62,0.3);
}

.btn-fav.active {
  background: #e53e3e;
  color: #fff;
  border-color: #e53e3e;
  animation: heartbeat 0.3s ease;
}
  </style>
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
      <a href="index.php" class="text-primary font-semibold hover:text-blue-600">Home</a>
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

  <!-- Main Content -->
  <main class="flex-grow container mx-auto px-6 py-4 text-center">
    <h3 class="text-2xl md:text-3xl font-bold mb-2">Welcome, <?= htmlspecialchars($_SESSION["username"]); ?> 👋</h3
>
    <p class="text-gray-600 text-sm sm:text-base mb-1">
      Explore community flashcard decks or create your own to study smarter with Flippix — your digital learning companion.
    </p>
        <div class="flex justify-center gap-8">
      <a href="dashboard.php" class="flex flex-col items-center text-primary hover:scale-105 transition">
        <span class="material-icons text-2xl mb-1">dashboard</span>
        <span>Dashboard</span>
      </a>
      <a href="community.php" class="flex flex-col items-center text-primary hover:scale-105 transition">
        <span class="material-icons text-2xl mb-1">groups</span>
        <span>Community</span>
      </a>
    </div>
    <?php
     $stmt = $conn->prepare("
        SELECT d.id, d.title, d.topic, d.thumbnail, d.created_at, d.share_code, u.username,
               (SELECT COUNT(*) FROM flashcards f WHERE f.deck_id = d.id) AS card_count,
               (SELECT COUNT(DISTINCT s.id) FROM play_sessions s WHERE s.deck_id = d.id) AS total_views,
               IF(fav.deck_id IS NULL, 0, 1) AS is_fav,
               (SELECT COUNT(*) FROM likes l WHERE l.deck_id = d.id) AS like_count,
               (SELECT COUNT(*) FROM likes l WHERE l.deck_id = d.id AND l.user_id = ?) AS is_liked
        FROM decks d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN favorites fav ON fav.deck_id = d.id AND fav.user_id = ?
        WHERE d.visibility='public' AND d.status='approved'
        AND d.archived = 0 
        ORDER BY d.created_at DESC
        LIMIT 3
      ");
      $stmt->bind_param("ii", $user_id, $user_id);

      $stmt->execute();
      $result = $stmt->get_result();
    ?>

    <?php if ($result->num_rows > 0): ?>
      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 mt-2">
        <?php while ($row = $result->fetch_assoc()): 
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
<div class="deck-card bg-white dark:bg-card-dark rounded-2xl shadow-md hover:shadow-xl hover:scale-105 transition-all duration-300 overflow-hidden max-w-sm sm:max-w-md md:max-w-lg w-full mx-auto">
  
  <!-- Thumbnail -->
  <div class="deck-thumb-wrapper relative">
    <img src="<?= htmlspecialchars($thumbPath) ?>" 
         alt="<?= htmlspecialchars($row['title']) ?>" 
         class="deck-thumb w-full h-40 object-cover" 
         loading="lazy"
         onerror="this.src='images/labers.png'">

    <span class="topic-badge absolute top-3 left-3 bg-indigo-600 text-white text-xs font-semibold px-3 py-1 rounded-full shadow-md">
      <?= htmlspecialchars($row['topic']) ?>
    </span>
  </div>

  <!-- Content -->
  <div class="deck-content px-4 py-3">
    <h3 class="text-lg sm:text-xl font-bold text-gray-800 dark:text-text-dark mb-3 text-left">
      <?= htmlspecialchars($row['title']) ?>
    </h3>

    <!-- Info section -->
    <div class="deck-info flex grid grid-cols-2 gap-2 text-gray-500 dark:text-gray-400 text-sm mb-2">
      <div class="info-row flex items-center gap-1">
        <span class="info-icon">👤</span>
        <span><?= htmlspecialchars($row['username']) ?></span>
      </div>
      <div class="info-row flex items-center gap-1">
        <span class="info-icon">📝</span>
        <span><?= (int)$row['card_count'] ?> cards</span>
      </div>
      <div class="info-row flex items-center gap-1">
        <span class="info-icon">👁️</span>
        <span><?= (int)$row['total_views'] ?> views</span>
      </div>
      <div class="info-row flex items-center gap-1">
        <span class="info-icon">🕒</span>
        <span><?= date("M j, Y", strtotime($row['created_at'])) ?></span>
      </div>
    </div>

    <!-- Share code -->
    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg px-4 py-2 mb-2 mt-2 border border-dashed border-gray-300 dark:border-gray-600 text-sm flex justify-between items-center">
      <span class="text-m text-gray-700 dark:text-white">
        <strong>Code:</strong>
        <span class="code-text text-text-light dark:text-text-dark  font-mono"><?= htmlspecialchars($row['share_code']) ?></span>
      </span>
      <button class="btn-copy bg-primary text-white text-xs px-3 py-1 rounded-md hover:bg-primary/90 transition"
              onclick="copyCode('<?= htmlspecialchars($row['share_code']) ?>', this)">
        Copy
      </button>
    </div>

    <!-- Actions -->
            <div class="deck-actions justify-between mt-auto">
              <a href="deck_view.php?deck=<?= $row['id'] ?>" class="btn-view">
                <button class="text-xs md:text-xs lg:text-sm hover:bg-blue-600 text-text-dark rounded-lg hover:scale-105 transition-all duration-300">
                <span>Open & Play</span>
                </button>
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

        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <p class="text-gray-600 dark:text-gray-400 mt-8">No public decks available yet.</p>
    <?php endif; $stmt->close(); ?>


  </main>

  <!-- Footer -->
  <footer class="bg-card-light dark:bg-card-dark border-t border-blue-200 dark:border-gray-200-700 mt-auto">
    <div class="container mx-auto px-6 py-4 flex justify-center items-center text-sm text-subtext-light dark:text-subtext-dark">
      <img src="images/labers.png" alt="Flippix Logo" class="h-6 w-6 mr-2">
      Flippix ©2025
    </div>
  </footer>

  <!-- Toast -->
  <div id="toast" class="hidden fixed bottom-6 right-6 bg-card-light dark:bg-card-dark border-l-4 border-primary text-text-light dark:text-text-dark px-5 py-3 rounded-lg shadow-lg text-sm"></div>

  <!-- Script -->
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

    // Copy share code
    function copyCode(code, btn) {
      navigator.clipboard.writeText(code).then(() => {
        showToast(`Copied "${code}" to clipboard!`);
        btn.textContent = '✓';
        setTimeout(() => btn.textContent = 'Copy', 1500);
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-save').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const icon = btn.querySelector('.save-icon'); // <-- select the icon
      btn.disabled = true;

      try {
        const res = await fetch(`community.php?api=toggle_favorite&deck_id=${id}`, {cache:'no-store'});
        const data = await res.json();
        
        if (data.ok) {
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
          showToast('Please log in to save favorites!', 'error');
        } else {
          showToast('Something went wrong. Please try again.', 'error');
        }
      } catch (err) {
        showToast('Error saving favorite. Please try again.', 'error');
        console.error(err);
      } finally {
        btn.disabled = false;
      }
    });
  });
});

  </script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-like').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      btn.disabled = true;

      try {
        const res = await fetch(`community.php?api=toggle_like&deck_id=${id}`, {cache:'no-store'});
        const data = await res.json();
        console.log(data); // <- debug response

        if (data.ok) {
          const countSpan = btn.querySelector('.like-count');
          const iconSpan = btn.querySelector('.like-icon');
          let count = parseInt(countSpan.textContent) || 0;

          if (data.liked) {
            btn.classList.add('active');
            btn.title = 'Unlike';
            iconSpan.textContent = '❤️';
            count++;
          } else {
            btn.classList.remove('active');
            btn.title = 'Like';
            iconSpan.textContent = '🤍';
            count = Math.max(0, count - 1);
          }
          countSpan.textContent = count;
        } else if (data.error === 'not_logged_in') {
          alert('Please log in!');
        } else {
          alert('Something went wrong.');
        }
      } catch (err) {
        console.error(err);
      } finally {
        btn.disabled = false;
      }
    });
  });
});

</script>
</body>
</html>
