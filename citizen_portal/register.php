<?php
require_once '../db/user_db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check if email exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $message = "Email already exists!";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
        if ($stmt->execute([$full_name, $email, $password_hash])) {
            $message = "Registration successful! <a href='login.php'>Login here</a>";
        } else {
            $message = "Registration failed!";
        }
    }   
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>
    <h2>Register</h2>
    <p style="color:red;"><?= $message ?></p>
    <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required><br><br>
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login</a></p>
</body>
</html>
