<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$vendor_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$vendor_id) {
    header("Location: vendors.php");
    exit;
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Fetch Vendor
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        status
    FROM users
    WHERE id = ?
      AND role = 'vendor'
    LIMIT 1
");

$stmt->execute([$vendor_id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: vendors.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Change Password
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password === '') {

        $error = "New password is required.";

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
              AND role = 'vendor'
        ");

        $stmt->execute([
            $hashed_password,
            $vendor_id
        ]);

        logAdminActivity(
            $pdo,
            $_SESSION['user_id'],
            'CHANGE_VENDOR_PASSWORD',
            "Changed the password for vendor {$vendor['full_name']} ({$vendor['email']})."
        );

        $success =
            "Vendor password changed successfully.";

        $_POST = [];
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

    <title>Change Vendor Password - GloryMarket</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>GloryMarket</h2>

            <small>Super Admin Panel</small>

        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fa-solid fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fa-solid fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php" class="active">
                <i class="fa-solid fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php">
                <i class="fa-solid fa-tags"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Change Vendor Password</h1>

                <p>
                    Set a new password for the vendor account.
                </p>

            </div>

        </div>


        <!-- MESSAGES -->

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


        <!-- VENDOR INFORMATION -->

        <div class="table-panel">

            <div class="panel-header">

                <div class="panel-header">

    <div>

        <h2>
            <i class="fa-solid fa-user"></i>
            Vendor Account
        </h2>

        <p>
            Vendor account information and management.
        </p>

    </div>

    <div>

        <a
            href="vendor-edit.php?id=<?= $vendor_id ?>"
            class="primary-btn"
        >
            <i class="fa-solid fa-pen"></i>
            Edit Vendor
        </a>

        <a
            href="vendor-password.php?id=<?= $vendor_id ?>"
            class="secondary-btn"
        >
            <i class="fa-solid fa-key"></i>
            Change Password
        </a>

    </div>

</div>

            </div>


            <div class="admin-details">

                <div class="detail-item">

                    <strong>Full Name</strong>

                    <span>
                        <?= htmlspecialchars($vendor['full_name']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Email</strong>

                    <span>
                        <?= htmlspecialchars($vendor['email']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Account Status</strong>

                    <span>

                        <span class="status-badge status-<?= htmlspecialchars($vendor['status']) ?>">

                            <?= htmlspecialchars(
                                ucfirst($vendor['status'])
                            ) ?>

                        </span>

                    </span>

                </div>

            </div>

        </div>


        <!-- PASSWORD FORM -->

        <div class="form-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-key"></i>
                        Set New Password
                    </h2>

                    <p>
                        The password must contain at least 8 characters.
                    </p>

                </div>

            </div>


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
                        autocomplete="new-password"
                        required
                    >

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
                        autocomplete="new-password"
                        required
                    >

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-key"></i>
                        Change Password
                    </button>

                    <a
                        href="vendor-view.php?id=<?= $vendor_id ?>"
                        class="secondary-btn"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>

                </div>

            </form>

        </div>

    </main>

</div>

</body>

</html>