<?php
require '../database/database.php';
session_start();

$db = new Database();

if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    header('Location: dashboard.php');
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $admin_user = $db->getAdminByUsername($username);

    if ($admin_user && password_verify($password, $admin_user['password'])) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $admin_user['username'];
        $_SESSION['admin_id'] = $admin_user['UA_ID'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials or not an admin account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap" rel="stylesheet">
    <style>
        body {
            background: #121212;
            font-family: 'Orbitron', sans-serif;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .login-box {
            background: #1f1f1f;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
            width: 100%;
            max-width: 400px;
        }
        .login-box h3 {
            text-align: center;
            margin-bottom: 25px;
            color: #00ffff;
        }
        .password-input-wrap {
            position: relative;
        }
        .password-eye {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #ccc;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #00ffff;
        }
        .btn-dark {
            background-color: #00ffff;
            color: #000;
            font-weight: bold;
            border: none;
        }
        .btn-dark:hover {
            background-color: #00cccc;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h3>👑 Admin Login</h3>
        <form method="post" action="login.php">
            <input name="username" class="form-control mb-3" placeholder="Username" required>
            <div class="password-input-wrap mb-3">
                <input name="password" type="password" id="passwordInput" class="form-control" placeholder="Password" required>
                <span class="password-eye" onclick="togglePassword()" title="Show/Hide Password">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4.5c-3.038 0-5.5-2.5-5.5-4.5S4.962 3.5 8 3.5 13.5 6 13.5 8 11.038 12.5 8 12.5z"/>
                        <path d="M8 5.5A2.5 2.5 0 1 0 8 10a2.5 2.5 0 0 0 0-4.5zM8 9A1 1 0 1 1 8 7a1 1 0 0 1 0 2z"/>
                    </svg>
                </span>
            </div>
            <button type="submit" class="btn btn-dark w-100">LOGIN</button>
            <?php if ($error): ?>
                <div class="alert alert-danger text-center mt-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById("passwordInput");
            const eyeIcon = document.getElementById("eyeIcon");

            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.innerHTML = `
                    <path d="M13.359 11.238a8.708 8.708 0 0 0 2.03-3.238s-3-5.5-8-5.5a7.91 7.91 0 0 0-3.159.668M2.13 2.13 1 3.25l2.196 2.196C1.778 7.086 0 8 0 8s3 5.5 8 5.5c1.636 0 3.106-.495 4.36-1.237L14.75 15 16 13.75l-3.641-3.641zM8 10.5a2.5 2.5 0 0 1-2.5-2.5c0-.397.094-.772.26-1.106l3.847 3.847A2.49 2.49 0 0 1 8 10.5zm-4.98-4.72 1.478 1.478A2.5 2.5 0 0 1 8 5.5c.59 0 1.13.2 1.562.532l1.53 1.53a5.62 5.62 0 0 1 1.064 1.016c-.97 1.426-2.528 2.422-4.156 2.422-.937 0-1.814-.317-2.51-.846z"/>`;
            } else {
                passwordInput.type = "password";
                eyeIcon.innerHTML = `
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zm-8 4.5c-3.038 0-5.5-2.5-5.5-4.5S4.962 3.5 8 3.5 13.5 6 13.5 8 11.038 12.5 8 12.5z"/>
                    <path d="M8 5.5A2.5 2.5 0 1 0 8 10a2.5 2.5 0 0 0 0-4.5zM8 9A1 1 0 1 1 8 7a1 1 0 0 1 0 2z"/>`;
            }
        }
    </script>
</body>
</html>
