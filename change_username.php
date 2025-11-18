<?php
session_start();
include 'db.php';

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST["new_username"]);
    $current_user = $_SESSION["username"];

    if (!empty($new_username)) {
        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $new_username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            echo "<script>alert('Username already taken!'); window.history.back();</script>";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE username = ?");
            $stmt->bind_param("ss", $new_username, $current_user);
            if ($stmt->execute()) {
                $_SESSION["username"] = $new_username;
                echo "<script>alert('Username updated successfully!'); window.location.href='dashboard.php';</script>";
            } else {
                echo "<script>alert('Error updating username.'); window.history.back();</script>";
            }
            $stmt->close();
        }
        $check->close();
    } else {
        echo "<script>alert('Please enter a new username.'); window.history.back();</script>";
    }
}

$conn->close();
?>
