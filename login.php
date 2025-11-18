<?php

session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row["password"])) {
            $_SESSION["username"] = $row["username"];
            $_SESSION["user_id"] = $row["id"];
            $_SESSION["is_admin"] = (bool)$row["is_admin"];

            if ($_SESSION["is_admin"]) {
                header("Location: admin_approve.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            echo "<script>alert('Invalid password');</script>";
        }
    } else {
        echo "<script>alert('No account found with that email');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Flippix Dashboard</title>
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
        fontFamily: {
          display: ["Poppins", "sans-serif"],
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
</style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark transition-colors duration-300 min-h-screen flex flex-col">

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
      <a href="landingpage.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">Home</a>
      <a href="howto.html" class="text-gray-600 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.html" class="text-gray-600 dark:text-gray-300 hover:text-primary">About</a>
    </div>

    <!-- Desktop Theme Toggle -->
    <div class="hidden md:flex items-center space-x-4">
        <a href="signup.php" class="login-button">
        <button class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">Sign Up</button>
        </a>
      <button id="theme-toggle"
        class="p-2 rounded-full text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
        <span class="material-icons">dark_mode</span>
      </button>
    </div>
  </nav>

  <!-- Mobile Menu -->
  <div id="mobile-menu" class="md:hidden hidden bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
    <div class="flex flex-col space-y-3 px-6 py-4">
      <a href="landingpage.php" class="text-gray-700 dark:text-gray-300 hover:text-primary">Home</a>
      <a href="howto.html" class="text-gray-700 dark:text-gray-300 hover:text-primary">How to Use</a>
      <a href="about.html" class="text-gray-700 dark:text-gray-300 hover:text-primary">About</a>

      <!-- Theme Toggle in Mobile -->
      <button id="theme-toggle-mobile"
        class="flex items-center space-x-2 p-2 mt-2 bg-gray-200 dark:bg-gray-700 rounded-lg text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
        <span class="material-icons">dark_mode</span>
        <span>Dark Theme</span>
      </button>
      <a href="signup.php" class="login-button">
        <button class="flex items-center bg-primary text-white w-full px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors">Sign Up</button>
        </a>
    </div>
  </div>
</header>


<!-- Login Form -->
<main class="flex-grow flex justify-center items-center px-6 py-16">
  <form method="POST"
        class="w-full max-w-md bg-card-light dark:bg-card-dark shadow-lg rounded-2xl p-8 space-y-6 border border-gray-100 dark:border-gray-700 transition-all duration-300">
    <div class="text-center">
      <h2 class="text-3xl font-bold text-text-light dark:text-text-dark mb-2">Login to Flippix</h2>
      <p class="text-subtext-light dark:text-subtext-dark text-sm">Access your digital flashcards and study smarter</p>
    </div>

    <div class="space-y-4">
      <div>
        <label for="email" class="block text-sm font-medium text-subtext-light dark:text-subtext-dark mb-1">Email</label>
        <input id="email" name="email" type="email" required
               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary focus:border-primary transition-all py-3 px-4" placeholder="Enter your email">
      </div>
      <div>
        <label for="password" class="block text-sm font-medium text-subtext-light dark:text-subtext-dark mb-1">Password</label>
        <input id="password" name="password" type="password" required
               class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary focus:border-primary transition-all py-3 px-4" placeholder="Enter your password">
      </div>
      <div class="text-right">
  <a href="forgot_password.php" class="text-primary text-sm hover:underline">Forgot password?</a>
</div>
    </div>

    <button type="submit"
            class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-primary/90 transition-all shadow-md hover:shadow-lg">
      Login
    </button>

    <p class="text-subtext-light dark:text-subtext-dark text-center text-sm">
      Don’t have an account?
      <a href="signup.php" class="text-primary hover:underline">Sign Up</a>
    </p>
  </form>
</main>

<!-- Footer -->
<footer class="bg-card-light dark:bg-card-dark mt-auto">
  <div class="container mx-auto px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-center items-center">
    <img alt="Flippix small logo" class="h-6 w-6 mr-2" src="images/labers.png"/>
    <p class="text-sm text-subtext-light dark:text-subtext-dark">Flippix ©2025</p>
  </div>
</footer>

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

</body>
</html>
