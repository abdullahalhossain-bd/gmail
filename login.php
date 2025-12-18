<?php
session_start();
require 'db.php';

$message = "";

if (isset($_POST['login'])) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = "Invalid request. Please try again.";
    } else {
        $email    = clean($_POST['email']);
        $password = $_POST['password'];
        
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Store login session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email']   = $user['email'];
                
                // Redirect to home.php
                header("Location: home.php");
                exit;
            } else {
                $message = "Invalid password!";
            }
        } else {
            $message = "Email not found!";
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f4f4; 
            margin: 0;
            padding: 0;
        }
        .box {
            width: 350px; 
            margin: 80px auto; 
            padding: 20px;
            background: #fff; 
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        input {
            width: 100%; 
            padding: 10px; 
            margin: 8px 0;
            border: 1px solid #ccc; 
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            width: 100%; 
            padding: 10px;
            background: #28a745; 
            color: white;
            border: none; 
            border-radius: 5px; 
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { 
            background: #1e7e34; 
        }
        .msg { 
            color: red; 
            margin-bottom: 10px; 
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="box">
    <h2>Login</h2>
    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
    <p>No account? <a href="registration.php">Register</a></p>
</div>
</body>
</html>