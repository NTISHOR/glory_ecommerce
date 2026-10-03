<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

/* ==============================
   SUPER ADMIN ACCESS
============================== */

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

/* ==============================
   GET CUSTOMER ID
============================== */

$customer_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$customer_id) {
    header("Location: customers.php");
    exit;
}

/* ==============================
   FETCH CUSTOMER
============================== */

$stmt = $pdo->prepare("
    SELECT id, full_name, email
    FROM users
    WHERE id = ?
      AND role = 'customer'
    LIMIT 1
");

$stmt->execute([$customer_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers.php");
    exit;
}

/* ==============================
   HANDLE PASSWORD RESET
============================== */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirm_password === '') {

        $error = "Please fill in both password fields.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        try {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
                  AND role = 'customer'
            ");

            $stmt->execute([
                $hashed_password,
                $customer_id
            ]);

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'CHANGE_CUSTOMER_PASSWORD',
                "Reset password for customer {$customer['full_name']} ({$customer['email']})."
            );

            header(
                "Location: customer-view.php?id=" .
                $customer_id .
                "&success=password"
            );

            exit;

        } catch (PDOException $e) {

            $error = "Unable to reset customer password. Please try again.";

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

    <title>Change Customer Password - GloryMarket</title>

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

            <a href="vendors.php">
                <i class="fa-solid fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php" class="active">
                <i class="fa-solid fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php">
                <i class="fa-solid fa-list"></i>
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
                <i class="fa-solid fa-chart-line"></i>
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

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Change Customer Password</h1>

                <p>
                    Reset the customer's account password.
                </p>

            </div>

        </div>


        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="form-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-key"></i>
                        Password Reset
                    </h2>

                    <p>
                        Customer:
                        <strong>
                            <?= htmlspecialchars($customer['full_name']) ?>
                        </strong>
                    </p>

                    <p>
                        Email:
                        <?= htmlspecialchars($customer['email']) ?>
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

                    <a
                        href="customer-view.php?id=<?= (int) $customer_id ?>"
                        class="secondary-btn"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-key"></i>
                        Reset Password
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>

</body>

</html>