<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db.php';
require_once 'PHPMailer/src/Exception.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Delete old tokens for this email
        $conn->query("DELETE FROM password_resets WHERE email='$email'");

        // Insert new token
        $insert = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $insert->bind_param("sss", $email, $token, $expires);
        $insert->execute();

        $reset_link = "https://flippix.site/reset_password.php?token=$token";

        // --- Send email ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'info@flippix.site';
            $mail->Password = 'Flippix@2025'; // use your Hostinger email password
            $mail->SMTPSecure = 'ssl';
            $mail->Port = 465;

            $mail->setFrom('info@flippix.site', 'Flippix Support');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Flippix Password Reset';
            $mail->Body = "
                <h2>Reset your password</h2>
                <p>Click the button below to reset your password. This link expires in 1 hour.</p>
                <p><a href='$reset_link' style='background:#4f46e5;color:white;padding:10px 15px;border-radius:5px;text-decoration:none;'>Reset Password</a></p>
                <p>If you didn't request this, just ignore this message.</p>
            ";

            $mail->send();
            $message = "<p class='text-green-600 text-center'>Password reset link sent! Check your email.</p>";
        } catch (Exception $e) {
            $message = "<p class='text-red-600 text-center'>Error sending email. Please try again later.</p>";
        }
    } else {
        $message = "<p class='text-red-600 text-center'>No account found with that email.</p>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Forgot Password - Flippix</title>
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
<!-- Centered Forgot Password Form -->
<main class="flex-grow flex items-center justify-center px-4 py-12">
  <form method="POST" 
        class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-semibold mb-4 text-center">Forgot Password</h2>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 text-center">
      Enter your registered email to receive a reset link.
    </p>

    <input type="email" name="email" required placeholder="Enter your email"
           class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-3 mb-4 focus:ring-2 focus:ring-indigo-500 focus:outline-none">

    <button type="submit"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-semibold transition">
      Send Reset Link
    </button>

    <?= $message ?>

    <p class="text-center mt-4 text-sm">
      <a href="login.php" class="text-indigo-600 hover:underline">Back to Login</a>
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



