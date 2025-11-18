<?php
// deck_view.php
session_start();
if (!isset($_SESSION["username"])) {
  header("Location: login.php");
  exit();
}
include 'db.php';

/* Resolve current user id */
$username = $_SESSION["username"];
$ustmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
$ustmt->bind_param("s", $username);
$ustmt->execute();
$urow = $ustmt->get_result()->fetch_assoc();
$user_id = (int)($urow['id'] ?? 0);

/* Load deck by id or code */
$deck_id = 0;
$share_code = null;

if (isset($_GET['deck'])) {
  $deck_id = (int)$_GET['deck'];
  $dstmt = $conn->prepare("SELECT id, user_id, title, thumbnail FROM decks WHERE id=? LIMIT 1");
  $dstmt->bind_param("i", $deck_id);
  $dstmt->execute();
  $deck = $dstmt->get_result()->fetch_assoc();
} elseif (isset($_GET['code'])) {
  $share_code = trim($_GET['code']);
  $dstmt = $conn->prepare("SELECT id, user_id, title, thumbnail FROM decks WHERE share_code=? LIMIT 1");
  $dstmt->bind_param("s", $share_code);
  $dstmt->execute();
  $deck = $dstmt->get_result()->fetch_assoc();
  $deck_id = (int)($deck['id'] ?? 0);
} else {
  die("Deck not specified.");
}

if (!$deck_id) {
  die("Deck not found.");
}

/* Load flashcards for this deck */
$cstmt = $conn->prepare("SELECT id, question, answer, difficulty, hint_gibberish, hint_description, hint_obvious, image
                         FROM flashcards
                         WHERE deck_id=? ORDER BY id ASC");
$cstmt->bind_param("i", $deck_id);
$cstmt->execute();
$rs = $cstmt->get_result();

$cards = [];
while ($row = $rs->fetch_assoc()) {
  $imgPath = trim($row['image'] ?? '');

  // determine image source
  if ($imgPath && file_exists(__DIR__ . '/' . $imgPath)) {
    $imgSrc = $imgPath; // relative uploads path
  } else {
    $imgSrc = ''; // no image or missing file
  }

  $cards[] = [
    'id' => (int)$row['id'],
    'q'  => $row['question'],
    'a'  => $row['answer'],
    'd'  => $row['difficulty'],
    'hg' => $row['hint_gibberish'] ?? '',
    'hd' => $row['hint_description'] ?? '',
    'ho' => $row['hint_obvious'] ?? '',
    'img'=> $imgSrc
  ];
}

if (empty($cards)) {
  die("This deck has no cards yet.");
}

function deckThumbSrc($blob) {
  if (!is_null($blob) && $blob !== '') {
    return 'data:image/png;base64,' . base64_encode($blob);
  }
  return 'images/labers.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($deck['title']) ?> — Flippix</title>
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
  body{margin:0;background:#fff;color:#000;min-height:100vh;display:flex;flex-direction:column}


  /* Layout */
  .wrap{padding:10px 30px;flex:1;display:grid;grid-template-columns: 240px 1fr 120px;gap:16px;align-items:center}
  .left{display:flex;flex-direction:column;gap:18px;align-items:flex-start}
  .right{display:flex;justify-content:flex-end;align-items:flex-start;font-size:22px;font-weight:700}
  .center{display:flex;flex-direction:column;align-items:center;gap:14px}

  /* Hint rows: icon + text side-by-side */
  .hint-item{ display:flex; align-items:center; gap:12px; max-width: 240px; }
  .hint-btn{ background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center; }
  .hint-btn img{ width:54px; height:54px; opacity:.9; transition:transform .12s ease, filter .12s ease; }
  .hint-btn:hover img{ transform:scale(1.05); }
  .hint-btn[disabled]{ cursor:not-allowed; }
  .hint-btn[disabled] img{ opacity:.35; }

  /* “Lit” glow when active (per hint for nicer colors) */
  #btnGib.lit img  { filter: drop-shadow(0 0 6px #ff7a00) brightness(1.2); }   /* matchstick orange */
  #btnDesc.lit img { filter: drop-shadow(0 0 6px #ffa500) brightness(1.2); }   /* candle amber */
  #btnObv.lit img  { filter: drop-shadow(0 0 8px #ffeb3b) brightness(1.25); }  /* bulb yellow */

  .hint-text{
    font-size:13px; color:#444; text-align:left; word-break:break-word; min-height:18px; flex:1 1 auto;
  }

  /* Card box */
  .card{
    width:380px;height:280px;border:3px solid #000;border-radius:16px;display:flex;align-items:center;justify-content:center;
    perspective:1000px; position:relative; overflow:hidden; background:#fff; transition:border-color .18s ease;
  }
  .card-inner{
    position:absolute;inset:0;transform-style:preserve-3d;transition:transform .45s ease;
    display:flex;align-items:center;justify-content:center;padding:16px;text-align:center;
  }
  .face{position:absolute;inset:0;backface-visibility:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;}
  .back{transform:rotateY(180deg);}
  .card.flip .card-inner{transform:rotateY(180deg);}

  .card.correct{border-color:#22c55e;}
  @keyframes shake {10%,90%{transform:translateX(-2px);}20%,80%{transform:translateX(4px);}30%,50%,70%{transform:translateX(-8px);}40%,60%{transform:translateX(8px);} }
  .card.wrong{border-color:#ef4444; animation: shake .35s; }

  .card-image{max-width:90%;max-height:55%;margin-bottom:8px;border-radius:10px}
  .question, .answer{font-weight:700;line-height:1.2;word-break:break-word;max-width:95%;}
  .question{font-size:22px;color:#111}
  .answer{font-size:22px;color:#0a0}

  /* Input */
  .answer-input{width:420px;max-width:92vw;border:none;border-bottom:3px solid #000;padding:8px 6px;font-size:22px;text-align:center;outline:none}
  .answer-input:focus{border-color:#4a6cff}
  .check-btn{margin-top:6px;background:#4a6cff;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer}

  /* Footer row */
  .footerline{border-top:2px solid #4a6cff;margin-top:10px;padding:8px 0;text-align:center}
  .difficulty{font-size:20px;margin-left:16px}

  /* Overlay end screen */
  .overlay{position:fixed;inset:0;background:rgba(255,255,255,.92);display:none;align-items:center;justify-content:center;z-index:9999}
  .overlay.show{display:flex}
  .overlay-card{border-radius:14px;padding:26px 24px;width:520px;max-width:92vw;text-align:center}
  .overlay-card h2{margin:0 0 10px}
  .overlay-card .score{font-size:26px;font-weight:800;margin:10px 0 18px}
  .overlay-card .btn{background:#4a6cff;color:#fff;border:none;border-radius:8px;padding:10px 18px;font-weight:700;cursor:pointer}

  /* 🔄 Flip animation */
  .card-inner {
    transition: transform 0.6s ease;
    transform-style: preserve-3d;
  }

  /* ✅ Flip when correct */
  .flip .card-inner {
    transform: rotateY(180deg);
  }

  /* 🟩 Optional effect when correct */
  .correct .front {
    border-color: #16a34a; /* green */
    box-shadow: 0 0 20px rgba(34,197,94,0.5);
  }

  /* 🟥 Optional effect when wrong */
  .wrong .front {
    border-color: #dc2626; /* red */
    box-shadow: 0 0 20px rgba(239,68,68,0.5);
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


<div class="flex flex-col lg:flex-row justify-center items-stretch gap-8 w-full max-w-6xl mx-auto p-6 relative">

  <!-- ✅ Left: Hint Buttons with Text (Visible on Desktop) -->
  <div class="hidden lg:flex flex-col items-start gap-6 w-1/4 mt-28">
    <div class="flex items-center gap-3">
      <button id="btnGib" title="Gibberish Hint"
              class="hint-btn-desktop w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-600 hover:scale-105 transition-all shadow-md flex items-center justify-center">
        <img src="images/posporoo.png"
             data-off="images/posporoo.png"
             data-on="images/posporo2.png" alt="Gibberish" class="w-9 h-9 object-contain">
      </button>
      <div id="txtGib" class="text-gray-800 w-72  dark:text-gray-200 font-medium text-sm sm:text-base"></div>
    </div>

    <div class="flex items-center gap-3">
      <button id="btnDesc" title="Description Hint"
              class="hint-btn-desktop w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-600 shadow-md hover:scale-105 transition-all flex items-center justify-center">
        <img src="images/kandila.png"
             data-off="images/kandila.png"
             data-on="images/kandila2.png" alt="Description" class="w-14 h-12 object-contain">
      </button>
      <div id="txtDesc" class="text-gray-800 dark:text-gray-200 w-72 font-medium text-sm sm:text-base"></div>
    </div>

    <div class="flex items-center gap-3">
      <button id="btnObv" title="Most Obvious Hint"
              class="hint-btn-desktop w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-600 hover:scale-105 transition-all shadow-md flex items-center justify-center">
        <img src="images/ilaw.png" 
             data-off="images/ilaw.png"
             data-on="images/ilaw2.png"alt="Obvious" class="w-9 h-9 object-contain">
      </button>
      <div id="txtObv" class="text-gray-800 w-72  dark:text-gray-200 font-medium text-sm sm:text-base"></div>
    </div>
  </div>

  <!-- ✅ Center: Flashcard + Input -->
  <div class="relative flex flex-col items-center justify-between flex-1 w-full">
<!-- ✅ Mobile Floating Hint Buttons (with bubbles) -->
<div class="flex flex-col gap-4 absolute left-2 top-1/2 -translate-y-1/2 lg:hidden z-20">
  <!-- Gibberish Hint -->
  <div class="relative flex items-center group">
    <button id="btnGibMobile" title="Gibberish Hint"
            class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 hover:scale-105 transition-all shadow-md flex items-center justify-center relative z-10">
      <img src="images/posporoo.png"
           data-off="images/posporoo.png"
           data-on="images/posporo2.png"
           alt="Gibberish"
           class="w-8 h-8 object-contain transition-all duration-300">
    </button>
    <div id="txtGibMobile"
         class="absolute left-16 bg-white w-48 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-xs font-medium px-3 py-2 rounded-xl shadow-lg opacity-0 scale-95 pointer-events-none transition-all duration-300 origin-left before:content-[''] before:absolute before:left-[-6px] before:top-1/2 before:-translate-y-1/2 before:border-8 before:border-transparent before:border-r-white dark:before:border-r-gray-800">
      Gibberish Hint
    </div>
  </div>

  <!-- Description Hint -->
  <div class="relative flex items-center group">
    <button id="btnDescMobile" title="Description Hint"
            class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 hover:scale-105 transition-all shadow-md flex items-center justify-center relative z-10">
      <img src="images/kandila.png"
           data-off="images/kandila.png"
           data-on="images/kandila2.png"
           alt="Description"
           class="w-8 h-8 object-contain transition-all duration-300">
    </button>
    <div id="txtDescMobile"
         class="absolute left-16 bg-white w-48 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-xs font-medium px-3 py-2 rounded-xl shadow-lg opacity-0 scale-95 pointer-events-none transition-all duration-300 origin-left before:content-[''] before:absolute before:left-[-6px] before:top-1/2 before:-translate-y-1/2 before:border-8 before:border-transparent before:border-r-white dark:before:border-r-gray-800">
      Description Hint
    </div>
  </div>

  <!-- Obvious Hint -->
  <div class="relative flex items-center group">
    <button id="btnObvMobile" title="Obvious Hint"
            class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-600 hover:scale-105 transition-all shadow-md flex items-center justify-center relative z-10">
      <img src="images/ilaw.png"
           data-off="images/ilaw.png"
           data-on="images/ilaw2.png"
           alt="Obvious"
           class="w-8 h-8 object-contain transition-all duration-300">
    </button>
    <div id="txtObvMobile"
         class="absolute left-16 bg-white w-48 dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-xs font-medium px-3 py-2 rounded-xl shadow-lg opacity-0 scale-95 pointer-events-none transition-all duration-300 origin-left before:content-[''] before:absolute before:left-[-6px] before:top-1/2 before:-translate-y-1/2 before:border-8 before:border-transparent before:border-r-white dark:before:border-r-gray-800">
      Obvious Hint
    </div>
  </div>
</div>

<!-- ✅ Difficulty label -->
<div class="text-center text-base font-medium text-gray-800 dark:text-gray-200" id="diffLine">
  Difficulty Level: —
</div>

    <!-- Progress -->
    <div id="progress" class="self-end text-lg font-bold text-gray-800 dark:text-gray-200 mb-2">/<?= count($cards) ?></div>

    <!-- Card -->
    <div id="card" class="relative w-full max-w-sm aspect-[5/4] perspective z-10 shadow-2xl">
      <div class="card-inner relative w-full h-full transition-transform duration-500 [transform-style:preserve-3d]">
        <!-- Front -->
        <div class="face front absolute w-full h-full rounded-2xl shadow-xl bg-white dark:bg-gray-700 flex flex-col items-center justify-center text-center border-gray-200 dark:border-gray-700 [backface-visibility:hidden]">
          <img id="cardImg" class="max-h-48 mb-3 hidden rounded-lg" alt="">
          <div id="qText" class="text-base sm:text-2xl font-semibold text-gray-800 dark:text-gray-100 px-4"></div>
        </div>
        <!-- Back -->
        <div class="face back absolute w-full h-full rounded-2xl shadow-xl bg-indigo-50 dark:bg-gray-900 flex items-center justify-center text-center  border-indigo-200 dark:border-gray-700 [transform:rotateY(180deg)] [backface-visibility:hidden]">
          <div id="aText" class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 px-6"></div>
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="w-full max-w-md mt-6 flex flex-col sm:flex-row items-center gap-3">
      <input type="text" id="answerInput" placeholder="Type your answer..."
             class="flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100  focus:ring-indigo-500 focus:outline-none text-base">

    </div>
          <button id="checkBtn"
              class="px-5 py-2.5 mt-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-base transition-all shadow-md">
        Check
      </button>
  </div>

  <!-- ✅ Right (placeholder) -->
  <div class="hidden lg:flex flex-col justify-center items-center w-1/4 text-gray-600 dark:text-gray-400 italic">
  </div>
</div>




<!-- End screen -->
<div id="endOverlay" class="overlay" aria-hidden="true">
  <div class="overlay-card bg-white dark:bg-gray-700">
    <h2>Finished!</h2>
    <div class="score text-text-light dark:text-text-dark" id="scoreText"></div>
    <button class="btn" onclick="window.location.href='dashboard.php?tab=myflashcards'">Back to My Flashcards</button>
  </div>
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
  // Cards from PHP
  const CARDS = <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE); ?>;
  const TOTAL = CARDS.length;

  // Elements
  const cardEl = document.getElementById('card');
  const qText  = document.getElementById('qText');
  const aText  = document.getElementById('aText');
  const imgEl  = document.getElementById('cardImg');
  const ansIn  = document.getElementById('answerInput');
  const checkBtn = document.getElementById('checkBtn');
  const progress = document.getElementById('progress');
  const diffLine = document.getElementById('diffLine');
  const endOverlay = document.getElementById('endOverlay');
  const scoreText  = document.getElementById('scoreText');

  // Hint buttons & per-button text
  const btnGib  = document.getElementById('btnGib');
  const btnDesc = document.getElementById('btnDesc');
  const btnObv  = document.getElementById('btnObv');
  const txtGib  = document.getElementById('txtGib');
  const txtDesc = document.getElementById('txtDesc');
  const txtObv  = document.getElementById('txtObv');

  // The <img> elements inside the buttons (for optional icon swap)
  const imgGib  = btnGib.querySelector('img');
  const imgDesc = btnDesc.querySelector('img');
  const imgObv  = btnObv.querySelector('img');

  // === Progress Saving Setup ===
const progressKey = `deck_${<?= (int)$deck_id ?>}_progress`;
const saved = JSON.parse(localStorage.getItem(progressKey) || '{}');

let idx = saved.idx || 0;
let correct = saved.correct || 0;

function saveProgress() {
  localStorage.setItem(progressKey, JSON.stringify({ idx, correct }));
}


  function cap(s){ return (s||'').charAt(0).toUpperCase() + (s||'').slice(1); }

function resetHintsUI() {
  // Clear desktop hint text
  txtGib.textContent  = '';
  txtDesc.textContent = '';
  txtObv.textContent  = '';

  // Remove 'lit' and restore default desktop icons
  [ [btnGib, imgGib], [btnDesc, imgDesc], [btnObv, imgObv] ].forEach(([btn, img]) => {
    btn.classList.remove('lit');
    if (img && img.dataset.off) img.src = img.dataset.off;
  });
}



/* ✅ Reset mobile hint UI */
function resetMobileHintsUI() {
  // Restore all mobile hint icons and hide text bubbles
  const mobileHints = [
    { btn: 'btnGibMobile', text: 'txtGibMobile' },
    { btn: 'btnDescMobile', text: 'txtDescMobile' },
    { btn: 'btnObvMobile', text: 'txtObvMobile' }
  ];

  mobileHints.forEach(({ btn, text }) => {
    const button = document.getElementById(btn);
    const img = button.querySelector('img');
    const textBubble = document.getElementById(text);

    if (img && img.dataset.off) img.src = img.dataset.off;
    textBubble.classList.remove('opacity-100', 'scale-100');
    textBubble.classList.add('opacity-0', 'scale-95', 'pointer-events-none');
  });
}

/* ✅ Toggle mobile hint bubble (show/hide text + light icon) */
function toggleMobileHint(btnId, textId, hintText) {
  const button = document.getElementById(btnId);
  if (button.disabled) return;                          // ✅ ADD THESE 2 LINES HERE
  const img = button.querySelector('img');
  const textBubble = document.getElementById(textId);

  const isActive = textBubble.classList.contains('opacity-100');

  // Reset all first
  resetMobileHintsUI();

  if (!isActive) {
    // Show this one
    img.src = img.dataset.on;
    textBubble.textContent = hintText || '';
    textBubble.classList.remove('opacity-0', 'scale-95', 'pointer-events-none');
    textBubble.classList.add('opacity-100', 'scale-100');
  } else {
    // Hide this one (reset handled already)
    img.src = img.dataset.off;
  }
}

  function showCard(i){
    const c = CARDS[i];
    // reset states
    cardEl.classList.remove('flip','correct','wrong');
    ansIn.value = '';
    ansIn.focus();

    resetHintsUI();
        resetMobileHintsUI();


    // content
    qText.textContent = c.q || '(no question)';
    aText.textContent = c.a || '(no answer)';
    if (c.img) { imgEl.src = c.img; imgEl.style.display = 'block'; } else { imgEl.src=''; imgEl.style.display='none'; }

    progress.textContent = `${i+1}/${TOTAL}`;
    diffLine.textContent = `Difficulty Level: ${c.d ? cap(c.d) : '—'}`;

    // enable/disable hint buttons depending on data
    btnGib.disabled  = !c.hg;  btnGib.style.opacity  = c.hg ? 1 : .35;
    btnDesc.disabled = !c.hd;  btnDesc.style.opacity = c.hd ? 1 : .35;
    btnObv.disabled  = !c.ho;  btnObv.style.opacity  = c.ho ? 1 : .35;

     // enable/disable mobile hint buttons depending on data
    const btnGibMobile = document.getElementById('btnGibMobile');
    const btnDescMobile = document.getElementById('btnDescMobile');
    const btnObvMobile = document.getElementById('btnObvMobile');
    
    btnGibMobile.disabled  = !c.hg;  btnGibMobile.style.opacity  = c.hg ? 1 : .35;
    btnDescMobile.disabled = !c.hd;  btnDescMobile.style.opacity = c.hd ? 1 : .35;
    btnObvMobile.disabled  = !c.ho;  btnObvMobile.style.opacity  = c.ho ? 1 : .35;
  }

  function normalize(s){
    return (s || '').trim().toLowerCase()
      .replace(/\s+/g,' ')
      .replace(/[^\p{L}\p{N}\s]/gu,'');
  }

 function checkAnswer(){
    const c = CARDS[idx];
    const user = normalize(ansIn.value);
    const gold = normalize(c.a);

    if (!user) { ansIn.focus(); return; }

    // Disable button and input to prevent multiple submissions
    checkBtn.disabled = true;
    ansIn.disabled = true;

    if (user === gold) {
      correct++;
      cardEl.classList.add('correct');
      cardEl.classList.remove('wrong');
      cardEl.classList.add('flip'); // reveal answer
    } else {
      cardEl.classList.add('wrong');
      cardEl.classList.remove('correct');
    }

     // Wait for animation to finish (showing correct/wrong)
  setTimeout(() => {
    // Always unflip before showing the next question
    cardEl.classList.remove('flip');
    cardEl.classList.remove('correct', 'wrong');

    // proceed after a short delay
    setTimeout(()=>{
      idx++;
      saveProgress(); // ✅ add this line here
      if (idx < TOTAL) {
        showCard(idx);
        // Re-enable button and input
        checkBtn.disabled = false;
        ansIn.disabled = false;
      } else {
            endSession();
      }
    }, 400); // short delay to let flip-back finish
  }, 1200);
}

  function endSession(){
      localStorage.removeItem(progressKey);
    fetch('save_play.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        deck_id: <?= (int)$deck_id ?>,
        correct: correct,
        total: TOTAL
      }),
      cache: 'no-store',
      credentials: 'same-origin'
    })
    .then(r => r.json().catch(()=>({ok:false,error:'bad_json'})))
    .then(res => {
      if (!res.ok) {
        console.warn('save_play failed:', res);
      }
      // show overlay regardless so user flow isn’t blocked
      scoreText.textContent = `Your score: ${correct} / ${TOTAL}`;
      endOverlay.classList.add('show');
    })
    .catch(err => {
      console.warn('save_play network error:', err);
      scoreText.textContent = `Your score: ${correct} / ${TOTAL}`;
      endOverlay.classList.add('show');
    });
    
  }

  // Helper to toggle a hint “lit” state, swap icon if available, and set/clear text
function toggleHint(btn, imgEl, textEl, hintText){
  if (btn.disabled) return;
  const nowLit = !btn.classList.contains('lit');

  if (nowLit) {
    btn.classList.add('lit');
    if (imgEl && imgEl.dataset.on) imgEl.src = imgEl.dataset.on;   // 🔥 switch to lit image
    textEl.textContent = hintText || '';
  } else {
    btn.classList.remove('lit');
    if (imgEl && imgEl.dataset.off) imgEl.src = imgEl.dataset.off; // 💡 back to normal
    textEl.textContent = '';
  }
}

btnGib.addEventListener('click',  ()=> toggleHint(btnGib,  btnGib.querySelector('img'),  txtGib,  CARDS[idx].hg));
btnDesc.addEventListener('click', ()=> toggleHint(btnDesc, btnDesc.querySelector('img'), txtDesc, CARDS[idx].hd));
btnObv.addEventListener('click',  ()=> toggleHint(btnObv,  btnObv.querySelector('img'),  txtObv,  CARDS[idx].ho));

// Mobile hint buttons (floating bubbles)
document.getElementById('btnGibMobile').addEventListener('click', () =>
  toggleMobileHint('btnGibMobile', 'txtGibMobile', CARDS[idx].hg)
);
document.getElementById('btnDescMobile').addEventListener('click', () =>
  toggleMobileHint('btnDescMobile', 'txtDescMobile', CARDS[idx].hd)
);
document.getElementById('btnObvMobile').addEventListener('click', () =>
  toggleMobileHint('btnObvMobile', 'txtObvMobile', CARDS[idx].ho)
);



  // Check
  document.getElementById('checkBtn').addEventListener('click', checkAnswer);
  document.getElementById('answerInput').addEventListener('keydown', (e)=>{ if (e.key === 'Enter') { e.preventDefault(); checkAnswer(); } });

  // Init
  showCard(idx);
</script>

</body>
</html>
