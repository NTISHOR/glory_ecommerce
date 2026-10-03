<?php

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

$admin_id = $_GET['id'] ?? '';

if (!is_numeric($admin_id)) {
    header("Location: admins.php");
    exit;
}

/* =========================================
   GET ADMIN
========================================= */

$stmt = $pdo->prepare("
    SELECT id, full_name, email
    FROM users
    WHERE id = ?
      AND role = 'admin'
    LIMIT 1
");

$stmt->execute([$admin_id]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header("Location: admins.php");
    exit;
}

$error = '';
$success = '';

/* =========================================
   CHANGE PASSWORD
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {

        $error = "Please enter and confirm the new password.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
              AND role = 'admin'
        ");

      $stmt->execute([
    $hashed_password,
    $admin_id
]);

logAdminActivity(
    $pdo,
    $_SESSION['user_id'],
    'CHANGE_ADMIN_PASSWORD',
    "Changed password for administrator {$admin['full_name']} ({$admin['email']})."
);

$success = "Administrator password changed successfully.";
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

    <title>Change Admin Password | Glory E-commerce</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

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

                <h1>Change Administrator Password</h1>

                <p>
                    Set a new password for this administrator account.
                </p>

            </div>

            <a
                href="admin-view.php?id=<?= $admin['id'] ?>"
                class="secondary-btn"
            >
                ← Back
            </a>

        </div>


        <div class="form-panel">

            <div class="panel-header">

                <h2>Password Management</h2>

            </div>


            <div class="admin-password-info">

                <p>
                    <strong>Administrator:</strong>
                    <?= htmlspecialchars($admin['full_name']) ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($admin['email']) ?>
                </p>

            </div>


            <?php if (!empty($success)): ?>

                <div class="success-message">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        required
                    >

                    <small>
                        Password must contain at least 8 characters.
                    </small>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        required
                    >

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        Change Password
                    </button>

                    <a
                        href="admin-view.php?id=<?= $admin['id'] ?>"
                        class="secondary-btn"
                    >
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </main>

</div>

</body>

</html>