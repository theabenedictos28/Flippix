<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Get user ID
$stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$stmt->bind_param("s", $_SESSION['username']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$user_id = (int)($user['id'] ?? 0);

$delete_old = $conn->prepare("
    DELETE FROM decks 
    WHERE user_id = ? AND archived = 1 AND archived_at <= NOW() - INTERVAL 30 DAY
");
$delete_old->bind_param("i", $user_id);
$delete_old->execute();

// Fetch archived decks
$sql = "SELECT id, title, topic, share_code, created_at 
        FROM decks 
        WHERE user_id = ? AND archived = 1 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$decks = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flippix Archived Decks</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
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
            fontFamily: { display: ["Poppins", "sans-serif"] },
            borderRadius: { DEFAULT: "0.5rem" },
        },
    },
};
</script>
<style>
body { font-family: 'Poppins', sans-serif; }
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300">
<div class="flex flex-col min-h-screen">

<!-- Navbar -->
<header class="bg-card-light dark:bg-card-dark shadow-md sticky top-0 z-50 transition-colors duration-300">
  <nav class="container mx-auto px-6 py-3 flex justify-between items-center">
    <div class="flex items-center">
      <a href="dashboard.php" class="flex items-center space-x-2">
        <img src="images/labers.png" alt="Flippix Logo" class="h-10 w-10">
        <span class="text-xl font-semibold">Flippix</span>
      </a>
    </div>

    <button id="menu-toggle" class="md:hidden text-gray-700 dark:text-gray-200 focus:outline-none p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition">
      <svg id="menu-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
           viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
           d="M4 6h16M4 12h16M4 18h16" /></svg>
      <svg id="close-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none"
           viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
           d="M6 18L18 6M6 6l12 12" /></svg>
    </button>

    <div class="hidden md:flex items-center space-x-8">
      <a href="index.php" class="hover:text-primary">Home</a>
      <a href="howto.php" class="hover:text-primary">How to Use</a>
      <a href="about.php" class="hover:text-primary">About</a>
      <a href="dashboard.php" class="hover:text-primary">Dashboard</a>
    </div>

    <div class="hidden md:flex items-center">
      <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition">
        <span class="material-icons">dark_mode</span>
      </button>
    </div>
  </nav>

  <div id="mobile-menu" class="md:hidden hidden bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
    <div class="flex flex-col space-y-3 px-6 py-4">
      <a href="index.php" class="hover:text-primary">Home</a>
      <a href="howto.php" class="hover:text-primary">How to Use</a>
      <a href="about.php" class="hover:text-primary">About</a>
      <a href="dashboard.php" class="hover:text-primary">Dashboard</a>

      <button id="theme-toggle-mobile" class="flex items-center space-x-2 p-2 mt-2 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
        <span class="material-icons">dark_mode</span><span>Dark Theme</span>
      </button>
    </div>
  </div>
</header>

<main class="flex-grow container mx-auto px-6 py-6">
<h1 class="text-2xl font-semibold mb-4">Archived Decks</h1>
 <div class="mb-4 px-4 py-2 bg-yellow-100 dark:bg-yellow-700 text-yellow-800 dark:text-yellow-100 rounded">
    ⚠️ Archived decks will be permanently deleted after 30 days.
  </div>
<?php if (empty($decks)): ?>
    <div class="text-gray-600 dark:text-gray-400">No archived decks.</div>
<?php else: ?>
    <div class="overflow-x-auto hidden md:block">
      <table class="w-full table-auto border border-gray-200 dark:border-gray-700 rounded-lg text-left">
        <thead class="bg-gray-200 dark:bg-gray-700">
          <tr>
            <th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Topic</th>
            <th class="px-4 py-2">Created</th>
            <th class="px-4 py-2">Code</th>
            <th class="px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($decks as $d): ?>
          <tr class="border-b border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
            <td class="px-4 py-2"><?= htmlspecialchars($d['title']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($d['topic'] ?: 'General') ?></td>
            <td class="px-4 py-2"><?= date("M j, Y", strtotime($d['created_at'])) ?></td>
            <td class="px-4 py-2 font-mono"><?= htmlspecialchars($d['share_code']) ?></td>
            <td class="px-4 py-2 flex flex-col md:flex-row gap-2">
              <form method="post" action="restore_deck.php" class="inline">
                <input type="hidden" name="deck_id" value="<?= $d['id'] ?>">
                <button type="submit" class="px-3 py-1 bg-green-500 text-white rounded hover:bg-green-600 transition w-full md:w-auto">Restore</button>
              </form>
              <form method="post" action="permanent_delete_deck.php" class="inline" onsubmit="return confirm('Permanently delete this deck?')">
                <input type="hidden" name="deck_id" value="<?= $d['id'] ?>">
                <button type="submit" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition w-full md:w-auto">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ✅ Mobile Cards Layout -->
    <div class="grid gap-4 md:hidden">
      <?php foreach($decks as $d): ?>
      <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow p-4">
        <h2 class="text-lg font-semibold mb-1"><?= htmlspecialchars($d['title']) ?></h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1"><strong>Topic:</strong> <?= htmlspecialchars($d['topic'] ?: 'General') ?></p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1"><strong>Created:</strong> <?= date("M j, Y", strtotime($d['created_at'])) ?></p>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3"><strong>Code:</strong> <span class="font-mono"><?= htmlspecialchars($d['share_code']) ?></span></p>

        <div class="flex flex-col sm:flex-row gap-2">
          <form method="post" action="restore_deck.php" class="flex-1">
            <input type="hidden" name="deck_id" value="<?= $d['id'] ?>">
            <button type="submit" class="w-full px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">Restore</button>
          </form>
          <form method="post" action="permanent_delete_deck.php" onsubmit="return confirm('Permanently delete this deck?')" class="flex-1">
            <input type="hidden" name="deck_id" value="<?= $d['id'] ?>">
            <button type="submit" class="w-full px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition">Delete</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
<?php endif; ?>

</main>

<footer class="bg-card-light dark:bg-card-dark mt-auto">
  <div class="container mx-auto px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center items-center">
    <img alt="Flippix small logo" class="h-6 w-6 mr-2" src="images/labers.png"/>
    <p class="text-sm text-subtext-light dark:text-subtext-dark">Flippix ©2025</p>
  </div>
</footer>

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

if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
  document.documentElement.classList.add('dark');
  themeToggles.forEach(btn => btn.querySelector('span').textContent = sunIcon);
} else {
  document.documentElement.classList.remove('dark');
  themeToggles.forEach(btn => btn.querySelector('span').textContent = moonIcon);
}
</script>

</body>
</html>
