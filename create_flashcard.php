<?php
session_start();
if (!isset($_SESSION["username"])) {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Flippix — Create Flashcard</title>
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
  body{margin:0;background:#fff;color:#000;min-height:100vh;display:flex;flex-direction:column;}


  /* Page title band */
  .band{border-bottom:2px solid #4a6cff;padding:14px 24px;font-size:26px;font-weight:700}

  /* Layout */
  .left, .center, .right{display:flex;flex-direction:column;gap:16px}

  /* Inputs */
  .input, .select, .hint, .btn-outline{
    font-size:16px;padding:12px 14px;border:2px solid #000;border-radius:6px;outline:none;background:#fff
  }
  .input:focus, .select:focus, .hint:focus{border-color:#4a6cff;box-shadow:0 0 4px rgba(74,108,255,.45)}
  .hint[disabled]{background:#f0f0f0;color:#888;border-color:#bbb}
  .hint::placeholder{color:#9a9a9a}
  .muted{font-size:12px;color:#666;margin-top:-10px}

  /* Card preview */
  .counter{font-weight:700;text-align:right;margin:0 auto 6px;width:360px}
  .card-preview{
    width:360px;height:260px;border:2px solid #000;border-radius:18px;margin:0 auto 8px;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    position:relative;padding:12px;overflow:hidden;text-align:center
  }
  .preview-image{margin-bottom:70px;border-radius:6px;object-fit:contain;display:none}
  .preview-text{
    flex:1;display:flex;align-items:center;justify-content:center;
    padding:10px;font-weight:700;line-height:1.2;word-break:break-word;overflow:hidden
  }
  .preview-text.empty{color:#9a9a9a;font-weight:500}

  /* Delete button (top-right of card) */
  .delete-btn{
    position:absolute;top:8px;right:8px;
    width:36px;height:36px;border:2px solid #000;border-radius:50%;
    background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:18px;line-height:1;transition:transform .15s ease, background .15s ease
  }
  .delete-btn:hover{background:#ffecec;transform:scale(1.05)}
  .delete-btn.hidden{display:none}

  /* Under-card controls */
  .under-controls{display:flex;gap:18px;justify-content:center;align-items:center;margin-top:4px}
  .circle-btn{ 
    border-radius:50%;
    background:#fff;cursor:pointer;display:flex;
    transition:transform .15s ease, background .15s ease, opacity .15s ease
  }
  .circle-btn:hover{background:#f5f5f5;transform:scale(1.05)}
  .circle-btn.disabled{opacity:.45;cursor:not-allowed;background:#f9f9f9}

  .save-wrap{display:flex;justify-content:center;margin-top:8px}
  .save{border:none;background:#4a6cff;color:#fff;font-weight:700;border-radius:8px;padding:10px 28px;font-size:18px;cursor:pointer}

  /* Image controls (left column) */
  .image-controls{display:flex;gap:8px}
  .btn-outline{cursor:pointer;text-align:center}
  .remove-btn{
    display:none;background:#ff4d4d;color:#fff;font-weight:600;padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-size:14px
  }
  .remove-btn:hover{background:#d93636}

  /* Deck modal */
  .deck-modal{
    position:fixed; inset:0; background:rgba(0,0,0,.35);
    display:none; align-items:center; justify-content:center; z-index:9999;
  }
  .deck-modal.show{ display:flex; }
  .deck-dialog{
    width:520px; background:#fff; border:2px solid #000; border-radius:14px;
    padding:18px; box-shadow:0 12px 30px rgba(0,0,0,.2); position:relative;
  }
  .deck-header{ display:flex; align-items:center; justify-content:space-between; margin-bottom:8px }
  .deck-header h3{ margin:0; font-size:22px }
  .xbtn{
    width:34px;height:34px;border:2px solid #000;border-radius:8px;background:#fff;cursor:pointer
  }
  .deck-label{ font-weight:700; display:block; margin-bottom:4px }
  .deck-input{
    width:100%; padding:10px 12px; border:2px solid #000; border-radius:8px; font-size:16px;
  }
  .thumb-box{
    border:2px solid #aaa; border-radius:14px; padding:12px; display:flex; gap:12px; align-items:center; justify-content:center; min-height:140px;
  }
  .thumb-box img{ max-width:120px; max-height:120px; border-radius:10px; border:1px solid #ddd }
  .deck-actions{ display:flex; justify-content:flex-end; margin-top:12px }
  .confirm{
    background:#4a6cff; color:#fff; font-weight:700; border:none; border-radius:8px; padding:10px 18px; cursor:pointer
  }

  /* Congrats overlay */
  .congrats-overlay{
    position:fixed; inset:0; background:rgba(255,255,255,.85);
    display:none; align-items:center; justify-content:center; z-index:10000;
  }
  .congrats-overlay.show{ display:flex; }
  .congrats-card{
    width:520px; max-width:90vw;
    background:#fff; border:2px solid #000; border-radius:14px;
    padding:28px; text-align:center; box-shadow:0 12px 30px rgba(0,0,0,.2);
    font-size:22px; line-height:1.35;
  }
  .congrats-card h2{ margin:0 0 12px 0; font-size:32px }
  .congrats-code{ font-weight:800; font-size:28px; margin:10px 0 18px }
  .congrats-actions button{
    background:#4a6cff; color:#fff; font-weight:700; border:none; border-radius:8px;
    padding:10px 18px; cursor:pointer; font-size:18px;
  }
  /*nav*/

.top-nav {
  display: flex;
  align-items: center;
  background: #4c5bd4;
  padding: 12px 40px;
  color: #fff;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1000;
  box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}

/* push links to the right */
.top-nav .nav-links {
  display: flex;
  gap: 25px;
  margin-left: auto; /* this moves them to the right */
}

.top-nav a {
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  padding: 6px 12px;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.top-nav a:hover,
.top-nav a.active {
  background: #3a4ab0;
}

.feedback-question p { margin:0 0 6px 0; font-size:16px; }
.feedback-scale {
  display:flex; justify-content:space-between; gap:8px;
  margin:6px 0;
}
.feedback-scale label {
  display:flex; flex-direction:column; align-items:center;
  font-size:15px; font-weight:600;
}
.feedback-scale input[type="radio"] {
  width:22px; height:22px; accent-color:#4a6cff;
  margin-bottom:4px;
  cursor:pointer;
}
.scale-labels {
  display:flex; justify-content:space-between; font-size:13px; color:#666;
}

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
<header class="bg-card-light dark:bg-card-dark shadow-md sticky top-0 z-50 transition-colors duration-300">
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

<!-- Main Content -->
<div class="main-content container mx-auto text-gray-800 dark:text-gray-200">


  <!-- Flashcard Form -->
  <form class="create-wrap grid grid-cols-1 lg:grid-cols-3 gap-8 py-8 px-2 " 
        method="POST" 
        action="save_flashcard.php" 
        enctype="multipart/form-data"
        onsubmit="return openDeckModal(event)">
    
    <input type="hidden" name="all_cards" id="allCardsInput">
    <input type="hidden" name="deck_title" id="deckTitleInput">
    <input type="hidden" name="deck_thumbnail" id="deckThumbInput">

    <!-- ✅ Left Section -->
    <div class="left bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl shadow-md">
      <label for="topic" class="block mb-2 font-semibold">Select Topic</label>
      <select id="topic" name="topic" required
              class="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200 mb-4">
        <option value="" disabled selected>Select a topic</option>
        <option>Science</option>
        <option>Math</option>
        <option>History</option>
        <option>English</option>
        <option>Technology</option>
        <option>General</option>
      </select>

      <input id="questionInput" name="question" placeholder="Type your Question" required
             class="w-full p-2 mb-3 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"/>

      <input id="answerInput" name="answer" placeholder="Type your Answer" required
             class="w-full p-2 mb-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200"/>

      <span class="text-sm text-gray-500 dark:text-gray-400 block">*optional</span>

      <div class="flex flex-wrap gap-3">
        <label class="flex-1 text-center bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-2 rounded-lg cursor-pointer transition font-medium">
          <input type="file" id="imageInput" name="image" accept="image/*" class="hidden">
          Add Image
        </label>
        <button type="button" id="removeImage"
                class="flex-1 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-900 dark:text-gray-100 px-3 py-2 rounded-lg transition font-medium">
          Remove
        </button>
      </div>
    </div>

    <!-- ✅ Center Section -->
    <div class="center text-center">
      <div id="cardCounter" class="mb-4 text-sm font-semibold text-gray-600 dark:text-gray-400">1/10</div>

<div id="cardBox"
     class="relative w-full aspect-[5/4] bg-gray-100 dark:bg-gray-700 rounded-xl shadow-lg flex items-center justify-center overflow-hidden mb-4">

  <!-- Delete Button -->
  <button type="button" id="deleteBtn" title="Delete this flashcard"
          class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-2 shadow hidden">🗑️</button>

  <!-- Image -->
  <img id="previewImage" class="preview-image max-h-48 max-w-full object-contain" alt="Preview">

  <!-- Text -->
  <div id="previewText"
       class="absolute text-center text-lg font-medium text-gray-800 dark:text-gray-100 transition-all duration-300">
    Type your Question
  </div>
</div>


      <div class="flex justify-center gap-4 mb-6">
        <button type="button" id="prevBtn" class="circle-btn text-2xl shadow-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-full w-12 h-12 flex items-center justify-center">↩</button>
        <button type="button" id="addCardBtn" class="circle-btn text-3xl shadow-lg bg-blue-500 hover:bg-blue-600 text-white rounded-full w-12 h-12 flex items-center justify-center">+</button>
        <button type="button" id="nextBtn" class="circle-btn text-2xl shadow-lg bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 rounded-full w-12 h-12 flex items-center justify-center">↪</button>
      </div>

      <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold">
        SAVE
      </button>
    </div>

    <!-- ✅ Right Section -->
    <div class="right bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl shadow-md">
      <label for="difficulty" class="block mb-2 font-semibold">Choose Difficulty</label>
      <select id="difficulty" name="difficulty" required
              class="w-full p-2 mb-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-200">
        <option value="" disabled selected>Choose Difficulty</option>
        <option value="easy">Easy</option>
        <option value="medium">Medium</option>
        <option value="hard">Hard</option>
      </select>

      <textarea id="hint_gibberish" name="hint_gibberish" rows="2" placeholder="Type your Gibberish Hint" disabled required
                class="w-full p-2 mb-3 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-200"></textarea>

      <textarea id="hint_description" name="hint_description" rows="2" placeholder="Type your Description Hint" disabled required
                class="w-full p-2 mb-3 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-200"></textarea>

      <textarea id="hint_obvious" name="hint_obvious" rows="2" placeholder="Type your Most Obvious Hint" disabled required
                class="w-full p-2 border border-gray-300 dark:border-gray-700 rounded-lg text-gray-900 dark:text-gray-200"></textarea>
    </div>
  </form>
</div>

<!-- Deck Modal -->
<div id="deckModal"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden">
  <div class="w-11/12 max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 relative">
    <!-- Header -->
    <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
      <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Set Deck Details</h3>
      <button type="button" id="deckClose"
              class="text-gray-500 hover:text-red-500 transition text-lg font-bold">✕</button>
    </div>

    <!-- Form Content -->
    <label class="block text-sm font-medium mb-1 text-gray-700 dark:text-gray-300">Name Title:</label>
    <input type="text" id="deckTitle"
           class="w-full p-2 mb-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
           placeholder="Enter deck name..." maxlength="80"/>

    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Upload your thumbnail:</label>
    <div class="flex flex-col items-center gap-3 border-2 border-dashed border-gray-300 dark:border-gray-600 p-4 rounded-xl">
      <img id="thumbPreview" alt="Thumbnail" class="hidden max-h-32 rounded-lg shadow-md"/>
      <input type="file" id="thumbFile" accept="image/*"
             class="text-sm text-gray-600 dark:text-gray-300 file:mr-2 file:py-1 file:px-3 file:border file:rounded-md file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary-dark"/>
    </div>

    <div class="mt-6 text-right">
      <button id="deckConfirm"
              class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg transition">
        Confirm
      </button>
    </div>
  </div>
</div>

<!-- Congrats Overlay -->
<div id="congrats"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden">
  <div class="w-11/12 max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-2xl text-center p-6 relative">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2">🎉 Congratulations!</h2>
    <p class="text-gray-700 dark:text-gray-300 mb-2">You’ve made your flashcard</p>
    <p class="text-gray-700 dark:text-gray-300 mb-4">Here’s your code:</p>

    <div class="flex justify-center items-center mb-6 relative">
  <div id="congratsCode"
       class="text-2xl font-mono font-semibold bg-gray-100 dark:bg-gray-700 text-primary px-4 py-2 rounded-lg inline-block">
  </div>
  <button id="copyCodeBtn"
          class="ml-2 text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white transition"
          title="Copy to clipboard">
    📋
  </button>
</div>
    <div id="copyTooltip"
         class="absolute top-2 right-4 bg-black text-white text-sm px-2 py-1 rounded opacity-0 transition-opacity">
      Copied!
    </div>

    <button id="goMyFlashcards"
            class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg transition">
      My Flashcards
    </button>
  </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 dark:bg-black/70 backdrop-blur-sm hidden transition-all duration-300">
  <div id="feedbackContent"
       class="w-11/12 max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 transform scale-95 opacity-0 transition-all duration-300">
    
    <!-- Header -->
    <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
      <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">We'd love your feedback</h3>
      <button id="closeFeedback" class="text-gray-500 hover:text-red-500 transition text-lg font-bold">✕</button>
    </div>

    <form id="feedbackForm" class="space-y-6">
      <!-- Question 1 -->
      <div>
        <p class="font-medium text-gray-800 dark:text-gray-200 mb-2">Flippix is easy to use</p>
        <div class="flex justify-between items-center">
          <span class="text-xs text-gray-500 dark:text-gray-400">Strongly disagree</span>
          <div class="flex space-x-2">
            <label><input type="radio" name="easy" value="1" class="accent-primary"> 1</label>
            <label><input type="radio" name="easy" value="2" class="accent-primary"> 2</label>
            <label><input type="radio" name="easy" value="3" class="accent-primary"> 3</label>
            <label><input type="radio" name="easy" value="4" class="accent-primary"> 4</label>
            <label><input type="radio" name="easy" value="5" class="accent-primary"> 5</label>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400">Strongly agree</span>
        </div>
      </div>

      <!-- Question 2 -->
      <div>
        <p class="font-medium text-gray-800 dark:text-gray-200 mb-2">Flippix does what I need it to do</p>
        <div class="flex justify-between items-center">
          <span class="text-xs text-gray-500 dark:text-gray-400">Strongly disagree</span>
          <div class="flex space-x-2">
            <label><input type="radio" name="useful" value="1" class="accent-primary"> 1</label>
            <label><input type="radio" name="useful" value="2" class="accent-primary"> 2</label>
            <label><input type="radio" name="useful" value="3" class="accent-primary"> 3</label>
            <label><input type="radio" name="useful" value="4" class="accent-primary"> 4</label>
            <label><input type="radio" name="useful" value="5" class="accent-primary"> 5</label>
          </div>
          <span class="text-xs text-gray-500 dark:text-gray-400">Strongly agree</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <button type="button" id="skipFeedback"
                class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
          Skip
        </button>
        <button type="submit"
                class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg transition">
          Submit
        </button>
      </div>
    </form>
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
 /* ---------- State ---------- */
const maxCards = 10;
let currentIndex = 0;        // 0-based cursor
let createdCount = 0;        // how many cards created via +
const cards = [];            // array of saved cards
let deckTopic = ''; // ✅ Single topic for entire deck

/* ---------- Elements ---------- */
const counterEl = document.getElementById('cardCounter');
const qInput = document.getElementById('questionInput');
const aInput = document.getElementById('answerInput');
const imageInput = document.getElementById('imageInput');
const previewImage = document.getElementById('previewImage');
const removeBtn = document.getElementById('removeImage');
const deleteBtn = document.getElementById('deleteBtn');
const select = document.getElementById('difficulty');
const topicSelect = document.getElementById('topic');
const gibberish = document.getElementById('hint_gibberish');
const description = document.getElementById('hint_description');
const obvious = document.getElementById('hint_obvious');
const cardBox = document.getElementById('cardBox');
const preview = document.getElementById('previewText');
const addCardBtn = document.getElementById('addCardBtn');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');

// Deck modal elements
const formEl = document.querySelector('form.create-wrap');
const deckModal = document.getElementById('deckModal');
const deckTitle = document.getElementById('deckTitle');
const deckConfirm = document.getElementById('deckConfirm');
const deckClose = document.getElementById('deckClose');
const thumbFile = document.getElementById('thumbFile');
const thumbPreview = document.getElementById('thumbPreview');
const hiddenAllCards = document.getElementById('allCardsInput');
const hiddenDeckTitle = document.getElementById('deckTitleInput');
const hiddenDeckThumb = document.getElementById('deckThumbInput');


/* ---------- Validation Functions ---------- */
function isCurrentCardValid() {
  const q = qInput.value.trim();
  const a = aInput.value.trim();
  const diff = select.value;
  
  // ✅ Only validate question, answer, and difficulty (NOT topic per card)
  if (!q || !a || !diff) return false;
  
  if (diff === 'easy') {
    return gibberish.value.trim() && description.value.trim() && obvious.value.trim();
  } else if (diff === 'medium') {
    return gibberish.value.trim() && description.value.trim();
  } else if (diff === 'hard') {
    return description.value.trim();
  }
  
  return true;
}

function validateCurrentCard() {
  if (!isCurrentCardValid()) {
    let msg = 'Please complete all required fields:\n\n';
    if (!qInput.value.trim()) msg += '✗ Question\n';
    if (!aInput.value.trim()) msg += '✗ Answer\n';
    if (!select.value) msg += '✗ Difficulty\n';
    
    const diff = select.value;
    if (diff === 'easy') {
      if (!gibberish.value.trim()) msg += '✗ Gibberish Hint\n';
      if (!description.value.trim()) msg += '✗ Description Hint\n';
      if (!obvious.value.trim()) msg += '✗ Obvious Hint\n';
    } else if (diff === 'medium') {
      if (!gibberish.value.trim()) msg += '✗ Gibberish Hint\n';
      if (!description.value.trim()) msg += '✗ Description Hint\n';
    } else if (diff === 'hard') {
      if (!description.value.trim()) msg += '✗ Description Hint\n';
    }
    
    alert(msg);
    return false;
  }
  return true;
}



/* ---------- Helpers ---------- */
function updateCounter(){ 
  counterEl.textContent = `${currentIndex+1}/${maxCards}`; 
}

function setDisabled(el, isDisabled){ 
  el.disabled = isDisabled; 
  if(isDisabled) el.value=''; 
}

function applyDifficulty(value){
  setDisabled(gibberish, true); 
  setDisabled(description, true); 
  setDisabled(obvious, true);
  
  if(value === 'easy'){ 
    setDisabled(gibberish, false); 
    setDisabled(description, false); 
    setDisabled(obvious, false); 
  }
  else if(value === 'medium'){ 
    setDisabled(gibberish, false); 
    setDisabled(description, false); 
  }
  else if(value === 'hard'){ 
    setDisabled(description, false); 
  }
}

function onDifficultyChange(){ 
  applyDifficulty(select.value || "");
  refreshUI();
}
select.addEventListener("change", onDifficultyChange);

// ✅ Topic change handler - locks after first card
function onTopicChange() {
  if (createdCount === 0) {
    deckTopic = topicSelect.value;
  }
  refreshUI();
}
topicSelect.addEventListener("change", onTopicChange);

function fitPreviewText(){
  const maxSize = 28, minSize = 12;
  let size = maxSize;
  preview.style.fontSize = maxSize + 'px';
  while((preview.scrollHeight > cardBox.clientHeight || preview.scrollWidth > cardBox.clientWidth) && size > minSize){
    size--; 
    preview.style.fontSize = size + 'px';
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
    previewTextToBottom();
    
    previewImage.onload = function () {
      this.style.display = 'block';
      previewTextToBottom();
    };
  } else {
    previewImage.src = '';
    previewImage.style.display = 'none';
    removeBtn.style.display = 'none';
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

function clearForm(){
  qInput.value = ''; 
  aInput.value = '';
  imageInput.value = '';
  // ✅ DON'T clear topic - it stays the same for all cards
  updatePreview();
  showImage('');
  select.value = ''; 
  applyDifficulty('');
  refreshUI();
}

function collectForm(){
  let currentImageData = '';
  if (previewImage.style.display === 'block' && previewImage.src) {
    currentImageData = previewImage.src;
  }
  
  return {
    question: qInput.value.trim(),
    answer: aInput.value.trim(),
    // ✅ DON'T store topic per card
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
  clearForm();
  if(!card) { 
    return; 
  }
  
  qInput.value = card.question || '';
  aInput.value = card.answer || '';
  // ✅ Topic stays locked to deckTopic
  select.value = card.difficulty || '';
  applyDifficulty(select.value || '');
  gibberish.value = card.hints?.gibberish || '';
  description.value = card.hints?.description || '';
  obvious.value = card.hints?.obvious || '';
  
  const imgData = card.imageData || '';
  showImage(imgData);
  
  updatePreview();
  refreshUI();
}

function toggleDisabled(btn, isDisabled){
  if(isDisabled){ 
    btn.classList.add('disabled');
    btn.style.cursor = 'not-allowed';
    btn.style.opacity = '0.45';
  }
  else { 
    btn.classList.remove('disabled');
    btn.style.cursor = 'pointer';
    btn.style.opacity = '1';
  }
}

function refreshUI(){
  updateCounter();
  
  if(currentIndex < createdCount){ 
    deleteBtn.classList.remove('hidden'); 
  }
  else { 
    deleteBtn.classList.add('hidden'); 
  }
  
  toggleDisabled(prevBtn, currentIndex === 0);
  toggleDisabled(nextBtn, currentIndex + 1 >= createdCount);
  
  // ✅ Add button requires topic to be selected AND card to be valid
  const canAdd = createdCount < maxCards && topicSelect.value && isCurrentCardValid();
  toggleDisabled(addCardBtn, !canAdd);
  
  // ✅ Lock topic after first card is created
  if (createdCount > 0) {
    topicSelect.disabled = true;
  }
}


/* ---------- Events ---------- */
qInput.addEventListener('input', () => {
  updatePreview();
  refreshUI();
});

aInput.addEventListener('input', refreshUI);
gibberish.addEventListener('input', refreshUI);
description.addEventListener('input', refreshUI);
obvious.addEventListener('input', refreshUI);
window.addEventListener('resize', fitPreviewText);

imageInput.addEventListener('change', function(){
  const f = this.files[0];
  if(f){
    const r = new FileReader();
    r.onload = function(e) {
      const base64Data = e.target.result;
      showImage(base64Data);
      if (currentIndex < createdCount && cards[currentIndex]) {
        cards[currentIndex].imageData = base64Data;
      }
    };
    r.readAsDataURL(f);
  }
});

removeBtn.addEventListener('click', ()=> { 
  imageInput.value = ''; 
  showImage('');
  if (currentIndex < createdCount && cards[currentIndex]) {
    cards[currentIndex].imageData = '';
  }
});

// ✅ Add card button - locks topic on first card
addCardBtn.addEventListener('click', ()=>{
  if (addCardBtn.classList.contains('disabled')) return;
  
  // ✅ Lock topic on first card
  if (createdCount === 0) {
    if (!topicSelect.value) {
      alert('Please select a topic first.');
      return;
    }
    deckTopic = topicSelect.value;
    topicSelect.disabled = true;
  }
  
  if (!validateCurrentCard()) return;
  
  const data = collectForm();
  
  if(currentIndex < createdCount){
    cards[currentIndex] = data;
  } else {
    cards[createdCount] = data;
    createdCount++;
  }

  if(createdCount < maxCards){
    currentIndex = createdCount;
    populateForm(null);
  } else {
    currentIndex = createdCount - 1;
    alert(`You've reached the maximum of ${maxCards} cards.`);
  }
  refreshUI();
});

prevBtn.addEventListener('click', ()=>{
  if (prevBtn.classList.contains('disabled')) return;

  if (currentIndex < createdCount && isCurrentCardValid()) {
    cards[currentIndex] = collectForm();
  }

  currentIndex--;
  populateForm(currentIndex < createdCount ? cards[currentIndex] : null);
});

nextBtn.addEventListener('click', ()=>{
  if (nextBtn.classList.contains('disabled')) return;

  if (currentIndex < createdCount && isCurrentCardValid()) {
    cards[currentIndex] = collectForm();
  }

  currentIndex++;
  populateForm(cards[currentIndex]);
});

// ✅ Delete button - unlocks topic if all cards deleted
deleteBtn.addEventListener('click', ()=>{
  if(currentIndex >= createdCount) return;
  if(!confirm('Delete this flashcard?')) return;
  
  cards.splice(currentIndex, 1);
  createdCount--;
  
  // ✅ Unlock topic if all cards deleted
  if (createdCount === 0) {
    topicSelect.disabled = false;
    topicSelect.value = '';
    deckTopic = '';
  }
  
  if(currentIndex >= createdCount && currentIndex > 0) {
    currentIndex = createdCount - 1;
  }
  
  populateForm(currentIndex < createdCount ? cards[currentIndex] : null);
});

/* ---------- SAVE flow ---------- */
function openDeckModal(e) {
  e.preventDefault();

  // ✅ Check if topic selected
  if (!deckTopic && !topicSelect.value) {
    alert('Please select a topic first.');
    return false;
  }

  if (currentIndex < createdCount) {
    if (isCurrentCardValid()) {
      cards[currentIndex] = collectForm();
    }
  } else if (isCurrentCardValid()) {
    if (createdCount === 0 && topicSelect.value) {
      deckTopic = topicSelect.value;
    }
    cards[createdCount] = collectForm();
    createdCount++;
  }

  if (!cards.length || createdCount === 0) {
    alert('Please create at least one valid flashcard before saving.');
    return false;
  }

  deckTitle.value = '';
  thumbFile.value = '';
  thumbPreview.src = 'images/labers.png';
  thumbPreview.classList.remove('hidden');
  thumbPreview.style.display = 'block';

  deckModal.classList.remove('hidden');
  return false;
}
window.openDeckModal = openDeckModal;

thumbFile.addEventListener('change', function() {
  const f = this.files[0];
  if (!f) {
    thumbPreview.src = 'images/labers.png';
    thumbPreview.classList.remove('hidden');
    return;
  }
  const r = new FileReader();
  r.onload = e => {
    thumbPreview.src = e.target.result;
    thumbPreview.classList.remove('hidden');
  };
  r.readAsDataURL(f);
});

deckClose.addEventListener('click', () => {
  deckModal.classList.add('hidden');
});

// ✅ Confirm - adds topic to ALL cards before saving
deckConfirm.addEventListener('click', function() {
  const title = deckTitle.value.trim();
  if (!title) {
    alert('Please enter a deck title.');
    return;
  }

  const validCards = cards.filter(c =>
    c.question && c.answer && c.difficulty  // ✅ Don't check c.topic
  );

  if (validCards.length === 0) {
    alert('No valid flashcards to save.');
    return;
  }

  // ✅ Add the same topic to ALL cards
  const cardsWithTopic = validCards.map(card => ({
    ...card,
    topic: deckTopic
  }));

  hiddenAllCards.value = JSON.stringify(cardsWithTopic);
  hiddenDeckTitle.value = title;
  hiddenDeckThumb.value = thumbPreview.src;

  deckModal.classList.add('hidden');
  formEl.submit();
});

refreshUI();
    
/* ---------- Show Congrats overlay when redirected with code ---------- */
(function() {
  const url = new URL(window.location.href);
  const code = url.searchParams.get('deckcode');
  if (!code) return;

  const congratsModal = document.getElementById('congrats');
  const codeDisplay = document.getElementById('congratsCode');
  const goBtn = document.getElementById('goMyFlashcards');

  // ✅ Set the actual generated code (not static "xyz123")
  codeDisplay.textContent = code;

  // Show Congrats modal
  congratsModal.classList.remove('hidden');
  congratsModal.classList.add('flex');

  // When "My Flashcards" is clicked → hide Congrats, show Feedback modal
  goBtn.addEventListener('click', (e) => {
    e.preventDefault();
    congratsModal.classList.add('hidden');
    congratsModal.classList.remove('flex');

    // Show feedback modal
    showFeedbackModal();
  });

  // Clean URL
  window.history.replaceState({}, document.title, window.location.pathname);
})();


  // ---------- Congrats Modal Copy ----------
  const copyBtn = document.getElementById('copyCodeBtn');
  const codeEl = document.getElementById('congratsCode');
  const tooltip = document.getElementById('copyTooltip');

  copyBtn.addEventListener('click', () => {
    const code = codeEl.textContent;
    navigator.clipboard.writeText(code).then(() => {
      // Show tooltip
      tooltip.style.opacity = '1';
      setTimeout(() => {
        tooltip.style.opacity = '0';
      }, 1500);
    }).catch(err => {
      console.error('Failed to copy code:', err);
    });
  });
</script>

<style>
/* Smooth scaling for modal appearance */
#congrats.show #congratsContent {
  transform: scale(1);
  opacity: 1;
}
</style>

<script>
  const feedbackModal = document.getElementById('feedbackModal');
const feedbackContent = document.getElementById('feedbackContent');
const closeFeedback = document.getElementById('closeFeedback');
const skipFeedback = document.getElementById('skipFeedback');
const feedbackForm = document.getElementById('feedbackForm');

function showFeedbackModal() {
  feedbackModal.classList.remove('hidden');
  setTimeout(() => {
    feedbackContent.classList.remove('scale-95', 'opacity-0');
    feedbackContent.classList.add('scale-100', 'opacity-100');
  }, 10);
}

function hideFeedbackModal() {
  feedbackContent.classList.add('scale-95', 'opacity-0');
  setTimeout(() => feedbackModal.classList.add('hidden'), 300);
}

closeFeedback.addEventListener('click', hideFeedbackModal);
skipFeedback.addEventListener('click', () => {
  hideFeedbackModal();
  window.location.href = 'dashboard.php?tab=myflashcards';
});

feedbackForm.addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = new FormData(feedbackForm);

  fetch('send_feedback.php', { method: 'POST', body: formData })
    .then(r => { if (!r.ok) throw new Error('Error submitting feedback'); return r.text(); })
    .then(() => {
      alert('Thank you for your feedback!');
      hideFeedbackModal();
      window.location.href = 'dashboard.php?tab=myflashcards';
    })
    .catch(err => {
      console.error(err);
      alert('Failed to send feedback. Please try again later.');
    });
});

</script>
</body>
</html>
