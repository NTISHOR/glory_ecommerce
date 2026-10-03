<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } else {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "An account with this email already exists.";

        } else {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    phone,
                    password,
                    role,
                    status
                )
                VALUES (?, ?, ?, ?, 'admin', 'active')
            ");

            $stmt->execute([
                $full_name,
                $email,
                $phone,
                $hashed_password
            ]);

            logAdminActivity(
    $pdo,
    $_SESSION['user_id'],
    'CREATE_ADMIN',
    "Created administrator account for {$full_name} ({$email})."
);
            $success = "Admin account created successfully.";

            $_POST = [];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Admin | Glory E-commerce</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

</head>

<body>

<div class="admin-layout">

<aside class="sidebar">

    <div class="sidebar-brand">
        <h2>GloryMarket</h2>
        <small>Super Admin Panel</small>
    </div>

    <nav class="sidebar-nav">

        <a href="dashboard.php">
            <span>Dashboard</span>
        </a>

        <a href="admins.php" class="active">
            <span>Manage Admins</span>
        </a>

        <a href="permissions.php">
            <span>Permissions</span>
        </a>

        <a href="vendors.php">
            <span>Vendors</span>
        </a>

        <a href="customers.php">
            <span>Customers</span>
        </a>

        <a href="products.php">
            <span>Products</span>
        </a>

        <a href="categories.php">
            <span>Categories</span>
        </a>

        <a href="orders.php">
            <span>Orders</span>
        </a>

        <a href="payments.php">
            <span>Payments</span>
        </a>

        <a href="reports.php">
            <span>Reports</span>
        </a>

        <a href="activity-logs.php">
            <span>Activity Logs</span>
        </a>

        <a href="settings.php">
            <span>Settings</span>
        </a>

        <a href="../logout.php" class="logout-link">
            <span>Logout</span>
        </a>

    </nav>

</aside>

    <main class="main-content">

        <div class="topbar">

            <div>
                <h1>Add Administrator</h1>
                <p>Create a new administrator account.</p>
            </div>

        </div>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($success)): ?>

            <div class="success-message">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <div class="form-panel">

            <form method="POST">

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >

                    <small>
                        Password must be at least 8 characters.
                    </small>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                    >

                </div>


                <div class="form-actions">

                    <a
                        href="admins.php"
                        class="secondary-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        Create Admin
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>

</body>
</html>