<?php
session_start();
require_once "db.php";

if (isset($_SESSION["user_id"])) {
    if ($_SESSION["role"] === "Admin") {
        header("Location: dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit;
}

$message = "";

if (isset($_GET["registered"])) {
    $message = "Registration successful! You can now log in.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, username, password, role
         FROM users WHERE username = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_result(
        $stmt,
        $user_id,
        $db_username,
        $db_password,
        $db_role
    );

    if (
        mysqli_stmt_fetch($stmt) &&
        password_verify($password, $db_password)
    ) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user_id;
        $_SESSION["username"] = $db_username;
        $_SESSION["role"] = $db_role;

        if ($db_role === "Admin") {
    header("Location: dashboard.php");
} else {
    header("Location: user_dashboard.php");
}
exit;

        header("Location: dashboard.php");
        exit;

    } else {
        $message = "Invalid username or password.";
    }

    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Construction Materials System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">

    <h2>Login</h2>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="index.php">

        <input
            type="text"
            name="username"
            placeholder="Username"
            maxlength="50"
            required
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            required
        >

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>

</body>
</html>
