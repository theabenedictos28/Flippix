<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Flippix Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                    fontFamily: {
                        display: ["Poppins", "sans-serif"],
                    },
                    borderRadius: {
                        DEFAULT: "0.5rem",
                    },
                },
            },
        };
    </script>
<style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .login-button {
          text-decoration: none;
        }
      .carousel-section {
      flex: 0 0 580px;
      max-width: 580px;
      background: #f7fafc;
      border: 2px solid #e2e8f0;
      border-radius: 16px;
      padding: 35px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 420px;
      position: relative;
      margin-left: 20px;
    }

    .carousel-container {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .carousel-slide {
      display: none;
      text-align: center;
      animation: fadeIn 0.5s ease;
    }

    .carousel-slide.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .carousel-slide img {
      max-width: 100%;
      max-height: 260px;
      border-radius: 12px;
      margin-bottom: 15px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .carousel-slide h3 {
      font-size: 18px;
      color: #1a202c;
      margin-bottom: 8px;
      font-weight: 600;
    }

    .carousel-slide p {
      font-size: 13px;
      color: #4a5568;
      line-height: 1.6;
    }

    .carousel-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      gap: 10px;
    }

    .carousel-nav.prev {
      left: 10px;
    }

    .carousel-nav.next {
      right: 10px;
    }

    .nav-btn {
      background: #4a6cff;
      color: #fff;
      border: none;
      width: 45px;
      height: 45px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(74, 108, 255, 0.3);
    }

    .nav-btn:hover {
      background: #3a56d4;
      transform: scale(1.1);
    }

    .carousel-indicators {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .indicator {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #cbd5e0;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .indicator.active {
      background: #4a6cff;
      width: 30px;
      border-radius: 5px;
    }

    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300">
<div class="flex flex-col min-h-screen">
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
      <a href="howto.php" class="text-primary font-semibold hover:text-blue-600">How to Use</a>
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
      <a href="index.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">Home</a>
      <a href="howto.php" class="text-primary font-semibold hover:text-blue-600">How to Use</a>
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
<main class="flex-grow">
<section class="container mx-auto px-6 py-16 flex flex-col md:flex-row items-center">
<div class="md:w-1/2 lg:w-2/5 text-center md:text-left">
<h1 class="text-5xl md:text-6xl font-bold text-text-light dark:text-text-dark leading-tight mb-4">Flippix</h1>
<h2 class="text-3xl md:text-4xl font-semibold text-primary mb-6">How to Use the Flashcard Creation Feature</h2>
<p class="text-lg text-subtext-light dark:text-subtext-dark mb-2">
                        In this section, you will learn how to create your own digital flashcards using the platform. </p>
        <p class="text-lg text-subtext-light dark:text-subtext-dark mb-2">
        The process is simple and user-friendly just choose a topic, type your question and 
        answer, and save it to your collection. You can also add hints or tags to make studying more 
        organized. This feature helps you build personalized study materials that make 
        learning easier, more interactive, and more effective.
                      </p>
</div>
<div class="md:w-1/2 lg:w-3/5 mt-2 mx-2 md:mt-0 md:pl-12">
    <div class="carousel-section bg-card-light dark:bg-card-dark">
      <button class="nav-btn carousel-nav prev" onclick="changeSlide(-1)">
        <i class="fa-solid fa-chevron-left"></i>
      </button>

      <div class="carousel-container">
        <div class="carousel-slide active">
          <img src="images/step1.png" class="mx-auto" alt="Step 1" onerror="this.src='images/labers.png'">
          <h3 class="text-text-light dark:text-text-dark" >Step 1: Choose a Topic</h3>
          <p class="text-subtext-light dark:text-subtext-dark">Select or create a topic for your flashcard deck to keep your studies organized.</p>
        </div>

        <div class="carousel-slide">
          <img src="images/step2.png" class="mx-auto" alt="Step 2" onerror="this.src='images/labers.png'">
          <h3 class="text-text-light dark:text-text-dark">Step 2: Add Question & Answer</h3>
          <p class="text-subtext-light dark:text-subtext-dark">Type in your question on the front and the answer on the back of the flashcard.</p>
        </div>

         <div class="carousel-slide">
          <img src="images/step3.png" class="mx-auto" alt="Step 3" onerror="this.src='images/labers.png'">
          <h3 class="text-text-light dark:text-text-dark">Step 3: Add Image</h3>
          <p class="text-subtext-light dark:text-subtext-dark">A sample flashcard with an uploaded image to help visual learning.</p>
        </div>

        <div class="carousel-slide">
          <img src="images/step3.png" class="mx-auto" alt="Step 4" onerror="this.src='images/labers.png'">
          <h3 class="text-text-light dark:text-text-dark">Step 4: Add Difficulty/Hints</h3>
          <p class="text-subtext-light dark:text-subtext-dark">Add hints to make reviewing easier.</p>
        </div>

        <div class="carousel-slide">
          <img src="images/step4.png" class="mx-auto" alt="Step 5" onerror="this.src='images/labers.png'">
          <h3 class="text-text-light dark:text-text-dark">Step 5: Save Your Flashcard</h3>
          <p class="text-subtext-light dark:text-subtext-dark">Save your flashcard to your collection and start studying anytime!</p>
        </div>
      </div>

      <button class="nav-btn carousel-nav next" onclick="changeSlide(1)">
        <i class="fa-solid fa-chevron-right"></i>
      </button>

      <div class="carousel-indicators">
        <span class="indicator active" onclick="goToSlide(0)"></span>
        <span class="indicator" onclick="goToSlide(1)"></span>
        <span class="indicator" onclick="goToSlide(2)"></span>
        <span class="indicator" onclick="goToSlide(3)"></span>
<span class="indicator" onclick="goToSlide(4)"></span>
                <span class="indicator" onclick="goToSlide(5)"></span>
      </div>
    </div>
  </div>
</section>
</main>
<footer class="bg-card-light dark:bg-card-dark mt-auto">
<div class="container mx-auto px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center items-center">
<img alt="Flippix small logo" class="h-6 w-6 mr-2" src="images/labers.png"/>
<p class="text-sm text-subtext-light dark:text-subtext-dark">Flippix ©2025</p>
</div>
</footer>
</div>
<!-- Dark Mode Script -->
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
</script>


    <script>
let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');
const indicators = document.querySelectorAll('.indicator');

function showSlide(n) {
  if (n >= slides.length) {
    currentSlide = 0;
  } else if (n < 0) {
    currentSlide = slides.length - 1;
  } else {
    currentSlide = n;
  }

  slides.forEach((slide, index) => {
    slide.classList.remove('active');
    indicators[index].classList.remove('active');
  });

  slides[currentSlide].classList.add('active');
  indicators[currentSlide].classList.add('active');
}

function changeSlide(direction) {
  showSlide(currentSlide + direction);
}

function goToSlide(n) {
  showSlide(n);
}

// Optional: Auto-advance slides every 5 seconds
// setInterval(() => changeSlide(1), 5000);
</script>

</body></html>