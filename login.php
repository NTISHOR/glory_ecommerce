<?php
session_start();

require_once __DIR__ . '/config/db.php';

$pdo = getDbConnection();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id, full_name, email, password, role, status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {

            $error = "Invalid email or password.";

        } elseif ($user['status'] !== 'active') {

            $error = "Your account is not active. Please contact support.";

        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            switch ($user['role']) {

                case 'super_admin':
                    header("Location: admin/dashboard.php");
                    exit;

                case 'admin':
                    header("Location: admin/dashboard.php");
                    exit;

                case 'vendor':
                    header("Location: vendor/dashboard.php");
                    exit;

                case 'customer':
                    header("Location: customer/dashboard.php");
                    exit;

                default:
                    session_unset();
                    session_destroy();
                    $error = "Invalid account role.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Glory E-commerce</title>

    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

<div class="login-container">

    <h2>Welcome Back</h2>
    <p>Sign in to your Glory E-commerce account.</p>

    <?php if (!empty($error)): ?>
        <div class="error-message">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="form-group">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                required
                autocomplete="email"
            >
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>

        <button type="submit">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

</div>
</body>
</html>
