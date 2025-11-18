<?php
session_start();
if (!isset($_SESSION["username"])) { header("Location: login.php"); exit(); }
include 'db.php';

/* ---- Resolve current user ---- */
$username = $_SESSION["username"];
$ustmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$ustmt->bind_param("s", $username);
$ustmt->execute();
$urow = $ustmt->get_result()->fetch_assoc();
$user_id = (int)($urow['id'] ?? 0);
if (!$user_id) { die("User not found."); }

/* ---- Load deck ---- */
$deck_id = isset($_GET['deck']) ? (int)$_GET['deck'] : 0;
if (!$deck_id) { die("Deck id missing."); }

$dstmt = $conn->prepare("SELECT id, user_id, title, topic, thumbnail, status, visibility FROM decks WHERE id=? LIMIT 1");
$dstmt->bind_param("i", $deck_id);
$dstmt->execute();
$deck = $dstmt->get_result()->fetch_assoc();
if (!$deck) { die("Deck not found."); }
if ((int)$deck['user_id'] !== $user_id) { die("You do not own this deck."); }

/* ---- Load cards ---- */
$cstmt = $conn->prepare("SELECT id, question, answer, difficulty, hint_gibberish, hint_description, hint_obvious, image
                         FROM flashcards WHERE deck_id=? ORDER BY id ASC");
$cstmt->bind_param("i", $deck_id);
$cstmt->execute();
$rs = $cstmt->get_result();

$cards = [];
while ($row = $rs->fetch_assoc()) {
  $imgPath = '';
  if (!empty($row['image'])) {
  $clean = ltrim($row['image'], '/');

  // If already correct
  if (str_starts_with($clean, 'uploads/')) {
    $imgPath = $clean;
  }
  // If only filename saved (e.g., "card_12.png")
  elseif (file_exists(__DIR__ . '/uploads/' . $clean)) {
    $imgPath = 'uploads/' . basename($clean);
  }
  // If absolute or other weird paths, normalize it
  elseif (file_exists($clean)) {
    $imgPath = str_replace(__DIR__ . '/', '', $clean);
  }
}


  $cards[] = [
    'id' => (int)$row['id'],
    'question' => $row['question'],
    'answer'   => $row['answer'],
    'difficulty' => $row['difficulty'],
    'hints' => [
      'gibberish'   => $row['hint_gibberish'] ?? '',
      'description' => $row['hint_description'] ?? '',
      'obvious'     => $row['hint_obvious'] ?? '',
    ],
    'imageData' => $imgPath
  ];
}

/* ---- Helper for deck thumbnail ---- */
function deckThumbSrc($thumb) {
  if (!empty($thumb)) {
    // Case 1: Base64 blob (when editing before saving)
    if (str_starts_with($thumb, 'data:image')) {
      return $thumb;
    }

    // Case 2: Full relative path like 'uploads/deck_12.png'
    if (str_starts_with($thumb, 'uploads/')) {
      return $thumb;
    }

    // Case 3: Only filename saved (e.g. 'deck_12.png')
    if (file_exists(__DIR__ . '/uploads/' . $thumb)) {
      return 'uploads/' . $thumb;
    }

    // Case 4: Full absolute path accidentally stored (e.g. '/var/www/html/uploads/deck_12.png')
    if (file_exists($thumb)) {
      return str_replace(__DIR__ . '/', '', $thumb);
    }
  }

  // Default fallback
  return 'images/labers.png';
}


$deck_thumb = deckThumbSrc($deck['thumbnail']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Deck — <?= htmlspecialchars($deck['title']) ?> | Flippix</title>
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
  *{box-sizing:border-box;font-family:Poppins, sans-serif}

  .wrap{display:grid;grid-template-columns:320px 1fr 320px;gap:28px;padding:24px 28px}
  .left,.center,.right{display:flex;flex-direction:column;gap:16px}

  .section{border:2px solid #000;border-radius:10px;padding:14px}
  .section h3{margin:0 0 10px;font-size:18px}

  .input, .select, .hint, .btn-outline{
    font-size:16px;padding:12px 14px;border:2px solid #000;border-radius:6px;outline:none;background:#fff
  }
  .input:focus, .select:focus, .hint:focus{border-color:#4a6cff;box-shadow:0 0 4px rgba(74,108,255,.45)}
  .hint[disabled]{background:#f0f0f0;color:#888;border-color:#bbb}
  .muted{font-size:12px;color:#666;margin-top:-10px}

  .thumb{width:180px;height:120px;border:2px solid #000;border-radius:10px;object-fit:cover;background:#f5f5f5}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

  .counter{font-weight:700;text-align:right;margin:0 auto 6px;width:360px}
  .card-preview{
    width:360px;height:260px;border:2px solid #000;border-radius:18px;margin:0 auto 8px;
    display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;padding:12px;overflow:hidden;text-align:center
  }
  .preview-image{margin-bottom:70px;border-radius:6px;object-fit:contain;display:none}
  .preview-text{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:10px;line-height:1.2;word-break:break-word;overflow:hidden
  }
  .preview-text.empty{color:#9a9a9a;font-weight:500}
  .delete-btn{position:absolute;top:8px;right:8px;width:36px;height:36px;border:2px solid #000;border-radius:50%;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px}
  .delete-btn.hidden{display:none}

  .under-controls{display:flex;gap:18px;justify-content:center;align-items:center;margin-top:4px}
  .circle-btn{ 
    border-radius:50%;
    background:#fff;cursor:pointer;display:flex;
    transition:transform .15s ease, background .15s ease, opacity .15s ease
  }
  .circle-btn:hover{background:#f5f5f5;transform:scale(1.05)}
  .circle-btn.disabled{opacity:.45;cursor:not-allowed;background:#f9f9f9}

  .savebar{display:flex;justify-content:center;margin:12px 0}
  .save{border:none;background:#4a6cff;color:#fff;font-weight:700;border-radius:8px;padding:12px 26px;font-size:18px;cursor:pointer}

/* 🔹 Default enabled style */
textarea:enabled,
select:enabled {
  background-color: #ffffff;
  border-color: #d1d5db; /* gray-300 */
  color: #111827; /* gray-900 */
}

/* 🔹 Disabled style (light mode) */
textarea:disabled,
select:disabled {
  background-color: #f3f4f6; /* gray-100 */
  border-color: #e5e7eb; /* gray-200 */
  color: #9ca3af; /* gray-400 */
  cursor: not-allowed;
  opacity: 0.8;
}

/* 🔹 Dark mode */
.dark textarea:enabled,
.dark select:enabled {
  background-color: #374151; /* gray-700 */
  border-color: #4b5563; /* gray-600 */
  color: #e5e7eb; /* gray-200 */
}

.dark textarea:disabled,
.dark select:disabled {
  background-color: #1f2937; /* gray-800 */
  border-color: #374151; /* gray-700 */
  color: #6b7280; /* gray-500 */
  cursor: not-allowed;
  opacity: 0.7;
}

</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 flex flex-col min-h-screen">


<!-- Navbar -->
<header class="bg-card-light dark:bg-card-dark border-b border-indigo-200 dark:border-gray-700 sticky top-0 z-50 transition-colors duration-300">
  <nav class="container mx-auto px-4 sm:px-6 py-3 flex justify-between items-center">
    
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
      <a href="index.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">Home</a>
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
      <a href="index.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">Home</a>
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
<!--
<div class="band text-3xl font-semibold py-3 px-8 bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark border-b border-indigo-200 dark:border-gray-700">Edit Flashcard</div> -->
<form id="editDeckForm" class="flex flex-col lg:flex-row gap-8 py-2 px-6 mx-5" novalidate>

  <!-- Left: Deck Details -->
  <div class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Deck Details</h3>

    <label for="deckTopic" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Select Topic</label>
    <select id="deckTopic" name="topic" required
            class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
      <option value="" disabled>Select a topic</option>
      <option value="Science" <?= ($deck['topic'] ?? '')==='Science' ? 'selected' : '' ?>>Science</option>
      <option value="Math" <?= ($deck['topic'] ?? '')==='Math' ? 'selected' : '' ?>>Math</option>
      <option value="History" <?= ($deck['topic'] ?? '')==='History' ? 'selected' : '' ?>>History</option>
      <option value="English" <?= ($deck['topic'] ?? '')==='English' ? 'selected' : '' ?>>English</option>
      <option value="Technology" <?= ($deck['topic'] ?? '')==='Technology' ? 'selected' : '' ?>>Technology</option>
      <option value="General" <?= ($deck['topic'] ?? '')==='General' ? 'selected' : '' ?>>General</option>
    </select>

    <label for="deckTitle" class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Title</label>
    <input id="deckTitle" class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none" 
           type="text" maxlength="80" required value="<?= htmlspecialchars($deck['title']) ?>">

    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Thumbnail</label>
    <img id="thumbPreview" class="w-full h-40 object-cover rounded-lg border border-gray-300 dark:border-gray-700 mb-4" src="<?= htmlspecialchars($deck_thumb) ?>" alt="Thumbnail">

    <div class="flex gap-3 mb-2">
      <label class="flex-1 text-center bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded-lg cursor-pointer transition font-medium">
        <input type="file" id="thumbFile" accept="image/*" class="hidden">
        Change
      </label>
      <button type="button" id="thumbRemove" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 px-3 py-2 rounded-lg transition font-medium">
        Remove
      </button>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400 italic">If removed, a default placeholder will be used.</p>
  </div>

  <!-- Center: Card Preview -->
  <div class="flex-1 flex flex-col items-center justify-between bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
    <div id="cardCounter" class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">1/1</div>

    <div id="cardBox" class="relative w-full aspect-[5/4] bg-gray-100 dark:bg-gray-700 rounded-xl shadow-lg flex items-center justify-center overflow-hidden mb-4">
      <button type="button" id="deleteBtn" class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-2 shadow transition">🗑️</button>
      <img id="previewImage" class="preview-image max-h-48 max-w-full object-contain" alt="Preview">
      <div id="previewText" class="absolute text-center text-lg  font-medium text-gray-800 dark:text-gray-100 transition-all duration-300">Type your Question</div>
    </div>

    <div class="flex items-center justify-center gap-4 mb-4">
      <button type="button" id="prevBtn" class="circle-btn text-2xl shadow-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-full w-12 h-12 flex items-center justify-center" title="Previous">&#8617;</button>
      <button type="button" id="addCardBtn" class="circle-btn text-3xl shadow-lg bg-blue-500 hover:bg-blue-600 text-white rounded-full w-12 h-12 flex items-center justify-center" title="Add Card">+</button>
      <button type="button" id="nextBtn" class="circle-btn text-2xl shadow-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-full w-12 h-12 flex items-center justify-center" title="Next">&#8618;</button>
    </div>

    <button type="submit" id="saveDeck" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg text-lg font-semibold shadow transition">
      Save Changes
    </button>
  </div>

  <!-- Right: Card Fields -->
  <div class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
    <h3 class="text-xl font-semibold mb-4 text-gray-900 dark:text-gray-100">Card Fields</h3>

    <input class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" 
           type="text" id="questionInput" placeholder="Type your Question" required>

    <input class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" 
           type="text" id="answerInput" placeholder="Type your Answer" required>

    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Image</label>
    <div class="flex gap-3 mb-4">
      <label class="flex-1 text-center bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded-lg cursor-pointer transition font-medium">
        <input type="file" id="imageInput" accept="image/*" class="hidden">
        Add/Change Image
      </label>
      <button type="button" id="removeImage" class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 px-3 py-2 rounded-lg transition font-medium">
        Remove
      </button>
    </div>

    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Difficulty</label>
    <select id="difficulty" class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
      <option value="" disabled>Choose Difficulty</option>
      <option value="easy">Easy</option>
      <option value="medium">Medium</option>
      <option value="hard">Hard</option>
    </select>

    <textarea id="hint_gibberish" class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" rows="1" placeholder="Gibberish Hint (easy/medium)" required></textarea>
    <textarea id="hint_description" class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" rows="1" placeholder="Description Hint (easy/medium/hard)" required></textarea>
    <textarea id="hint_obvious" class="w-full p-2.5 rounded-lg border-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 mb-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none" rows="1" placeholder="Most Obvious Hint (easy only)" required></textarea>

    <p class="text-sm text-gray-500 dark:text-gray-400 italic">Hint inputs enable/disable automatically based on difficulty.</p>
  </div>
</form>

   <!-- Fixed Footer 
  <footer
    class=" bottom-0 left-0 w-full bg-card-light dark:bg-card-dark border-t border-indigo-200 dark:border-gray-700 z-40">
    <div
      class="container mx-auto px-6 py-3 flex justify-center items-center text-sm text-subtext-light dark:text-subtext-dark">
      <img src="images/labers.png" alt="Flippix Logo" class="h-5 w-5 mr-2">
      Flippix ©2025
    </div>
  </footer> -->
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
 // ====== Boot data from PHP ======
const DECK_ID = <?= (int)$deck_id ?>;
const BOOT_CARDS = <?= json_encode($cards, JSON_UNESCAPED_UNICODE) ?>;

// ====== State ======
const maxCards = 100;
let cards = BOOT_CARDS.length ? BOOT_CARDS : [{
  id: null, question:'', answer:'', difficulty:'', hints:{gibberish:'',description:'',obvious:''}, imageData:''
}];
let deletedIds = [];
let currentIndex = 0;
let createdCount = cards.length;

// ====== Elements ======
const counterEl = document.getElementById('cardCounter');
const qInput = document.getElementById('questionInput');
const aInput = document.getElementById('answerInput');
const imageInput = document.getElementById('imageInput');
const previewImage = document.getElementById('previewImage');
const removeBtn = document.getElementById('removeImage');
const deleteBtn = document.getElementById('deleteBtn');
const select = document.getElementById('difficulty');
const gibberish = document.getElementById('hint_gibberish');
const description = document.getElementById('hint_description');
const obvious = document.getElementById('hint_obvious');
const cardBox = document.getElementById('cardBox');
const preview = document.getElementById('previewText');
const addCardBtn = document.getElementById('addCardBtn');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
const deckTitle = document.getElementById('deckTitle');
const thumbFile = document.getElementById('thumbFile');
const thumbRemove = document.getElementById('thumbRemove');
const thumbPreview = document.getElementById('thumbPreview');

// ====== Helpers ======
function updateCounter(){ 
  counterEl.textContent = `${currentIndex+1}/${createdCount}`; 
}

function setDisabled(el, isDisabled){ 
  el.disabled = isDisabled; 
  if(isDisabled) el.value=''; 
}

function applyDifficulty(value){
  setDisabled(gibberish,true); 
  setDisabled(description,true); 
  setDisabled(obvious,true);
  if(value==='easy'){ 
    setDisabled(gibberish,false); 
    setDisabled(description,false); 
    setDisabled(obvious,false); 
  }
  else if(value==='medium'){ 
    setDisabled(gibberish,false); 
    setDisabled(description,false); 
  }
  else if(value==='hard'){ 
    setDisabled(description,false); 
  }
}

select.addEventListener("change", ()=>applyDifficulty(select.value || ""));

function fitPreviewText(){
  const maxSize=28, minSize=12;
  let size=maxSize;
  preview.style.fontSize=maxSize+'px';
  while((preview.scrollHeight>cardBox.clientHeight || preview.scrollWidth>cardBox.clientWidth) && size>minSize){
    size--; 
    preview.style.fontSize=size+'px';
  }
}

function updatePreview() {
  const val = qInput.value.trim();
  if (!val) {
    preview.textContent = preview.dataset.placeholder || 'Type your Question';
    preview.classList.add('empty');
  } else {
    preview.textContent = val;
    preview.classList.remove('empty');
  }
  fitPreviewText();

  // ✅ Adjust text position depending on image
  if (previewImage.src && previewImage.style.display === 'block') {
    previewTextToBottom();
  } else {
    previewTextToCenter();
  }
}

function showImage(dataUrl) {
  if (dataUrl && dataUrl.trim() !== '') {
    previewImage.src = dataUrl;
    previewImage.style.display = 'block';
    removeBtn.style.display = 'inline-block';

    // ✅ Move preview text to bottom when image is present
    previewTextToBottom();

    // Force reload to ensure layout updates
    previewImage.onload = function () {
      this.style.display = 'block';
      previewTextToBottom();
    };
  } else {
    previewImage.src = '';
    previewImage.style.display = 'none';
    removeBtn.style.display = 'none';

    // ✅ Re-center text when no image
    previewTextToCenter();
  }
}

function previewTextToBottom() {
  preview.style.position = 'absolute';
  preview.style.bottom = '12px';
  preview.style.top = '73%';
  preview.style.left = '50%';
  preview.style.transform = 'translateX(-50%)';
  preview.style.textAlign = 'center';
}

function previewTextToCenter() {
  preview.style.position = 'absolute';
  preview.style.top = '50%';
  preview.style.left = '50%';
  preview.style.transform = 'translate(-50%, -50%)';
  preview.style.bottom = 'auto';
  preview.style.textAlign = 'center';
}


function collectForm(){
  // Get current image data - either base64 or path
  let currentImageData = '';
  if (previewImage.style.display === 'block' && previewImage.src) {
    currentImageData = previewImage.src;
  }
  
  return {
    id: cards[currentIndex]?.id ?? null,
    question: qInput.value.trim(),
    answer: aInput.value.trim(),
    difficulty: select.value || '',
    hints: {
      gibberish: gibberish.value.trim(),
      description: description.value.trim(),
      obvious: obvious.value.trim()
    },
    imageData: currentImageData
  };
}

function populateForm(card){
  qInput.value = card.question || '';
  aInput.value = card.answer || '';
  select.value = card.difficulty || '';
  applyDifficulty(select.value || '');
  gibberish.value = card.hints?.gibberish || '';
  description.value = card.hints?.description || '';
  obvious.value = card.hints?.obvious || '';
  
  // Clear file input
  imageInput.value = '';
  
  // Show image if exists
  const imgData = card.imageData || '';
  showImage(imgData);
  
  updatePreview();
}

function refreshUI(){
  updateCounter();
  deleteBtn.classList.remove('hidden');
  prevBtn.classList.toggle('disabled', currentIndex === 0);
  nextBtn.classList.toggle('disabled', !(currentIndex + 1 < createdCount));
  addCardBtn.classList.toggle('disabled', createdCount >= maxCards);
}

// ====== Wire inputs ======
qInput.addEventListener('input', updatePreview);
window.addEventListener('resize', fitPreviewText);

imageInput.addEventListener('change', function(){
  const f = this.files[0];
  if(f){
    const r = new FileReader();
    r.onload = function(e) {
      const base64Data = e.target.result;
      showImage(base64Data);
      // Update current card's imageData immediately
      cards[currentIndex].imageData = base64Data;
    };
    r.readAsDataURL(f);
  }
});

removeBtn.addEventListener('click', ()=> { 
  imageInput.value = ''; 
  showImage('');
  // Update current card to remove image
  cards[currentIndex].imageData = '';
});

// Add new card
addCardBtn.addEventListener('click', ()=>{
  cards[currentIndex] = collectForm();
  
  if(createdCount >= maxCards){ 
    alert('Reached maximum cards.'); 
    return; 
  }
  
  cards.splice(currentIndex+1, 0, {
    id:null, question:'', answer:'', difficulty:'', hints:{gibberish:'',description:'',obvious:''}, imageData:''
  });
  createdCount++;
  currentIndex++;
  populateForm(cards[currentIndex]);
  refreshUI();
});

// Prev/Next
prevBtn.addEventListener('click', ()=>{
  if(prevBtn.classList.contains('disabled')) return;
  cards[currentIndex] = collectForm();
  currentIndex--;
  populateForm(cards[currentIndex]);
  refreshUI();
});

nextBtn.addEventListener('click', ()=>{
  if(nextBtn.classList.contains('disabled')) return;
  cards[currentIndex] = collectForm();
  currentIndex++;
  populateForm(cards[currentIndex]);
  refreshUI();
});

// Delete current card
deleteBtn.addEventListener('click', ()=>{
  if(!confirm('Delete this card?')) return;
  const removed = cards.splice(currentIndex, 1)[0];
  createdCount--;
  if (removed?.id) deletedIds.push(removed.id);
  if (createdCount === 0) {
    cards = [{id:null,question:'',answer:'',difficulty:'',hints:{gibberish:'',description:'',obvious:''},imageData:''}];
    createdCount = 1; 
    currentIndex = 0;
  } else if (currentIndex >= createdCount) {
    currentIndex = createdCount - 1;
  }
  populateForm(cards[currentIndex]);
  refreshUI();
});

// Deck thumbnail change/remove
thumbFile.addEventListener('change', function(){
  const f = this.files[0];
  if(!f) return;
  const r = new FileReader();
  r.onload = e => { thumbPreview.src = e.target.result; };
  r.readAsDataURL(f);
});

thumbRemove.addEventListener('click', ()=>{
  thumbPreview.src = 'images/labers.png';
  thumbFile.value = '';
});

// Save deck
// Save deck (use native form validation)
document.getElementById('editDeckForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;

  // Run built-in validation
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }
  // Persist current edits
  cards[currentIndex] = collectForm();
  
  // Remove totally empty cards
  const cleaned = cards.filter(c => (
    c.question || c.answer || c.hints?.gibberish || c.hints?.description || c.hints?.obvious
  ));
  
  if (cleaned.length === 0) {
    alert('No cards to save.');
    return;
  }
  
  const payload = {
    deck_id: DECK_ID,
    title: deckTitle.value.trim(),
    topic: document.getElementById('deckTopic').value.trim(),
    thumbnail: thumbPreview.src || '',
    cards: cleaned,
    delete_ids: deletedIds
  };
  
  try{
    const res = await fetch('update_flashcard.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = 'dashboard.php?tab=myflashcards';
    } else {
      alert('Save failed: ' + (data.error || 'Unknown error'));
    }
  }catch(err){
    console.error('Save error:', err);
    alert('Network/Server error while saving.');
  }
});

// ====== Init ======
populateForm(cards[currentIndex]);
refreshUI();
updatePreview();
</script>
</body>
</html>
