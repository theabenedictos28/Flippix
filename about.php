<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Flippix Dashboard</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
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
      .container {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
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
      <a href="howto.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.php" class="text-primary font-semibold hover:text-blue-600">About</a>
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
      <a href="howto.php" class="text-gray-600 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.php" class="text-primary font-semibold hover:text-blue-600">About</a>
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
  <section class="container mx-auto px-6 py-16 mt-8 flex flex-col md:flex-row items-center">
    
    <div class="md:w-1/2 lg:w-2/5 text-center md:text-justify order-1 md:order-1 mb-8 md:mb-0">
      <img alt="Flashcards illustration" class="w-96 h-auto rounded-lg mx-auto md:mx-0" src="images/labers.png" />
    </div>

    <div class="md:w-1/2 lg:w-3/5 mt-8 md:mt-0 md:pl-12 order-2 md:order-2">
      <h1 class="text-5xl md:text-6xl font-bold text-text-light dark:text-text-dark leading-tight mb-4">
        About Us
      </h1>
      <p class="text-lg text-subtext-light dark:text-subtext-dark mb-2">
        At Flippix, our goal is to make studying smarter, not harder. The system helps learners absorb, review, and retain knowledge through digital flashcards.
      </p>
      <p class="text-lg text-subtext-light dark:text-subtext-dark mb-2">
        Flippix adapts to your pace, helping you revisit tough topics and boost memory through active recall. Whether you’re preparing for exams or exploring new lessons, Flippix supports consistent progress and better understanding.
      </p>
      <p class="text-lg text-subtext-light dark:text-subtext-dark mb-2">
        Learn, review, and master all in one place with Flippix — your ultimate digital learning companion.
      </p>
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


</body></html>
