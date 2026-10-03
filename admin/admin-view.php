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

$stmt = $pdo->prepare("
    SELECT
        id,
        full_name,
        email,
        phone,
        role,
        status,
        created_at,
        updated_at
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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_status = $_POST['status'] ?? '';

    if (!in_array($new_status, ['active', 'inactive', 'suspended'], true)) {

        $error = "Invalid account status.";

    } elseif ($new_status === $admin['status']) {

        $error = "The administrator already has this status.";

    } else {

        $stmt = $pdo->prepare("
            UPDATE users
            SET status = ?
            WHERE id = ?
              AND role = 'admin'
        ");

        $stmt->execute([
            $new_status,
            $admin_id
        ]);

        logAdminActivity(
            $pdo,
            $_SESSION['user_id'],
            'CHANGE_ADMIN_STATUS',
            "Changed {$admin['full_name']}'s status from {$admin['status']} to {$new_status}."
        );

        $success = "Administrator status updated successfully.";

        /* Refresh administrator data */

        $stmt = $pdo->prepare("
            SELECT
                id,
                full_name,
                email,
                phone,
                role,
                status,
                created_at,
                updated_at
            FROM users
            WHERE id = ?
              AND role = 'admin'
            LIMIT 1
        ");

        $stmt->execute([$admin_id]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Details | Glory E-commerce</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

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

            <div>
                <h1>Administrator Details</h1>
                <p>View administrator account information.</p>
            </div>

            <a href="admins.php" class="secondary-btn">
                ← Back to Admins
            </a>

        </div>


        <!-- ADMIN INFORMATION -->

        <div class="form-panel">

            <div class="panel-header">
                <h2>Account Information</h2>
            </div>


            <div class="admin-details">

                <div class="detail-item">
                    <strong>Full Name</strong>
                    <span>
                        <?= htmlspecialchars($admin['full_name']) ?>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Email Address</strong>
                    <span>
                        <?= htmlspecialchars($admin['email']) ?>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Phone Number</strong>
                    <span>
                        <?= htmlspecialchars($admin['phone'] ?? '-') ?>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Role</strong>
                    <span>
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $admin['role']))) ?>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Status</strong>
                    <span>
                        <span class="status-badge status-<?= htmlspecialchars($admin['status']) ?>">
                            <?= htmlspecialchars(ucfirst($admin['status'])) ?>
                        </span>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Date Created</strong>
                    <span>
                        <?= htmlspecialchars($admin['created_at']) ?>
                    </span>
                </div>


                <div class="detail-item">
                    <strong>Last Updated</strong>
                    <span>
                        <?= htmlspecialchars($admin['updated_at']) ?>
                    </span>
                </div>

            </div>


           <div class="form-actions">

    <a
        href="admin-edit.php?id=<?= $admin['id'] ?>"
        class="primary-btn"
    >
        Edit Admin
    </a>

    <a
        href="admin-password.php?id=<?= $admin['id'] ?>"
        class="secondary-btn"
    >
        Change Password
    </a>

    <form method="POST" class="status-form">

    <?php if ($admin['status'] === 'active'): ?>

        <button
            type="submit"
            name="status"
            value="inactive"
            class="status-action inactive-action"
            onclick="return confirm('Are you sure you want to deactivate this administrator?');"
        >
            Deactivate
        </button>

        <button
            type="submit"
            name="status"
            value="suspended"
            class="status-action suspend-action"
            onclick="return confirm('Are you sure you want to suspend this administrator?');"
        >
            Suspend
        </button>

    <?php elseif ($admin['status'] === 'inactive'): ?>

        <button
            type="submit"
            name="status"
            value="active"
            class="status-action activate-action"
            onclick="return confirm('Activate this administrator account?');"
        >
            Activate
        </button>

        <button
            type="submit"
            name="status"
            value="suspended"
            class="status-action suspend-action"
            onclick="return confirm('Are you sure you want to suspend this administrator?');"
        >
            Suspend
        </button>

    <?php elseif ($admin['status'] === 'suspended'): ?>

        <button
            type="submit"
            name="status"
            value="active"
            class="status-action activate-action"
            onclick="return confirm('Activate this administrator account?');"
        >
            Activate
        </button>

        <button
            type="submit"
            name="status"
            value="inactive"
            class="status-action inactive-action"
            onclick="return confirm('Deactivate this administrator account?');"
        >
            Deactivate
        </button>

    <?php endif; ?>

</form>

    <a
        href="admins.php"
        class="secondary-btn"
    >
        Back
    </a>

</div>
        </div>

    </main>

</div>

</body>
</html>