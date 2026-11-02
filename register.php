<?php
session_start();
require_once "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($username === "" || $password === "") {
        $message = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ?"
        );
        mysqli_stmt_bind_param($check, "s", $username);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $message = "Username already exists.";
        } else {
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (username, password) VALUES (?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ss",
                $username,
                $hashed_password
                
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                mysqli_stmt_close($check);
                mysqli_close($conn);

                header("Location: index.php?registered=1");
                exit;
            } else {
                $message = "Registration failed. Please try again.";
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($check);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Create an Account</h2>

        <?php if ($message !== ""): ?>
            <p class="message">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <input type="text" name="username"
                   placeholder="Username"
                   maxlength="50" required>

            <input type="password" name="password"
                   placeholder="Password (at least 8 characters)"
                   required>

            <input type="password" name="confirm_password"
                   placeholder="Confirm Password"
                   required>

            <button type="submit">Register</button>
        </form>

        <p>Already have an account?
            <a href="index.php">Login here</a>
        </p>
    </div>
</body>
</html>
