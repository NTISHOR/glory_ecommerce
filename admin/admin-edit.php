<?php

session_start();

require_once __DIR__ . '/../config/db.php';

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
    SELECT
        id,
        full_name,
        email,
        phone,
        role,
        status
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
   UPDATE ADMIN
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? '';

    if (empty($full_name) || empty($email)) {

        $error = "Full name and email are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (!in_array($status, ['active', 'inactive', 'suspended'], true)) {

        $error = "Invalid account status.";

    } else {

        /* Check if another user already has this email */

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([$email, $admin_id]);

        if ($stmt->fetch()) {

            $error = "Another account is already using this email address.";

        } else {

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    status = ?
                WHERE id = ?
                  AND role = 'admin'
            ");

            $stmt->execute([
                $full_name,
                $email,
                $phone,
                $status,
                $admin_id
            ]);

            $success = "Administrator account updated successfully.";

            /* Refresh displayed information */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    full_name,
                    email,
                    phone,
                    role,
                    status
                FROM users
                WHERE id = ?
                  AND role = 'admin'
                LIMIT 1
            ");

            $stmt->execute([$admin_id]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
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

    <title>Edit Admin | Glory E-commerce</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
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


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Edit Administrator</h1>

                <p>
                    Update administrator account information.
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

                <h2>Administrator Information</h2>

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


                <!-- FULL NAME -->

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($admin['full_name']) ?>"
                        required
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars($admin['email']) ?>"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= htmlspecialchars($admin['phone'] ?? '') ?>"
                    >

                </div>


                <!-- ROLE -->

                <div class="form-group">

                    <label>
                        Role
                    </label>

                    <input
                        type="text"
                        value="Administrator"
                        disabled
                    >

                    <small>
                        The administrator role cannot be changed from this page.
                    </small>

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Account Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="active"
                            <?= $admin['status'] === 'active' ? 'selected' : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $admin['status'] === 'inactive' ? 'selected' : '' ?>
                        >
                            Inactive
                        </option>

                        <option
                            value="suspended"
                            <?= $admin['status'] === 'suspended' ? 'selected' : '' ?>
                        >
                            Suspended
                        </option>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="form-actions">

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        Save Changes
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