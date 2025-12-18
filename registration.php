<?php
require 'db.php';

$message = "";

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $email, $password);

    if ($stmt->execute()) {
        $message = "Registration successful!";
    } else {
        $message = "Error: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
        body { font-family: Arial; background:#f4f4f4; }
        .box {
            width: 350px; margin: 80px auto; padding: 20px;
            background: #fff; border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        input {
            width: 100%; padding: 10px; margin: 8px 0;
            border: 1px solid #ccc; border-radius: 5px;
        }
        button {
            width: 100%; padding: 10px;
            background:#007bff; color:white;
            border:none; border-radius:5px;
            cursor:pointer;
        }
        button:hover { background:#0056b3; }
        .msg { color:green; margin-bottom:10px; }
    </style>
</head>
<body>

<div class="box">
    <h2>Register</h2>
    <p class="msg"><?= $message ?></p>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>

        <button type="submit" name="register">Register</button>
    </form>

    <p>Already have an account? <a href="login.php">Login</a></p>
</div>

</body>
</html>
