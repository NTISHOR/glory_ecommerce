<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Available Permissions
|--------------------------------------------------------------------------
*/

$available_permissions = [
    'manage_vendors' => 'Manage Vendors',
    'manage_customers' => 'Manage Customers',
    'manage_products' => 'Manage Products',
    'manage_categories' => 'Manage Categories',
    'manage_orders' => 'Manage Orders',
    'manage_payments' => 'Manage Payments',
    'view_reports' => 'View Reports',
    'view_activity_logs' => 'View Activity Logs',
    'manage_settings' => 'Manage Settings'
];

/*
|--------------------------------------------------------------------------
| Get Administrators
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, full_name, email, status
    FROM users
    WHERE role = 'admin'
    ORDER BY full_name ASC
");

$stmt->execute();

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Selected Administrator
|--------------------------------------------------------------------------
*/

$selected_admin_id = filter_input(
    INPUT_GET,
    'admin_id',
    FILTER_VALIDATE_INT
);

$selected_admin = null;
$admin_permissions = [];

if ($selected_admin_id) {

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, status
        FROM users
        WHERE id = ?
          AND role = 'admin'
        LIMIT 1
    ");

    $stmt->execute([$selected_admin_id]);

    $selected_admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected_admin) {

        $stmt = $pdo->prepare("
            SELECT permission
            FROM admin_permissions
            WHERE admin_id = ?
        ");

        $stmt->execute([$selected_admin_id]);

        $admin_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
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

    <title>Admin Permissions | Glory E-commerce</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>
                Glory<span>Market</span>
            </h2>

            <small>
                Super Admin Panel
            </small>

        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fas fa-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php" class="active">
                <i class="fas fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php">
                <i class="fas fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php">
                <i class="fas fa-list"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <main class="main-content">

    <?php if (isset($_GET['success'])): ?>

    <div class="success-message">
        Administrator permissions updated successfully.
    </div>

<?php endif; ?>


<?php if (isset($_GET['error'])): ?>

    <div class="error-message">
        Unable to update administrator permissions.
        Please try again.
    </div>

<?php endif; ?>

        <div class="topbar">

            <div>

                <h1>Admin Permissions</h1>

                <p>
                    Control what each administrator can access.
                </p>

            </div>

        </div>


        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>Administrator Access</h2>

                    <p>
                        Select an administrator to manage their permissions.
                    </p>

                </div>

            </div>


            <?php if (empty($admins)): ?>

                <div class="empty-state">

                    <i class="fas fa-user-shield"></i>

                    <p>
                        No administrator accounts found.
                    </p>

                </div>

            <?php else: ?>

                <div class="admin-permission-list">

                    <?php foreach ($admins as $admin): ?>

                        <a
                            href="permissions.php?admin_id=<?= $admin['id'] ?>"
                            class="permission-admin-card"
                        >

                            <div>

                                <strong>
                                    <?= htmlspecialchars($admin['full_name']) ?>
                                </strong>

                                <small>
                                    <?= htmlspecialchars($admin['email']) ?>
                                </small>

                            </div>

                            <span class="status-badge status-<?= htmlspecialchars($admin['status']) ?>">
                                <?= htmlspecialchars(ucfirst($admin['status'])) ?>
                            </span>

                        </a>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


        <?php if ($selected_admin): ?>

            <div class="table-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Permissions for
                            <?= htmlspecialchars($selected_admin['full_name']) ?>
                        </h2>

                        <p>
                            Select the permissions this administrator should have.
                        </p>

                    </div>

                </div>


                <form method="POST" action="permissions-save.php">

                    <input
                        type="hidden"
                        name="admin_id"
                        value="<?= $selected_admin['id'] ?>"
                    >


                    <div class="permission-grid">

                        <?php foreach ($available_permissions as $permission_key => $permission_name): ?>

                            <label class="permission-item">

                                <input
                                    type="checkbox"
                                    name="permissions[]"
                                    value="<?= htmlspecialchars($permission_key) ?>"
                                    <?= in_array($permission_key, $admin_permissions, true) ? 'checked' : '' ?>
                                >

                                <span>
                                    <?= htmlspecialchars($permission_name) ?>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="primary-btn"
                        >
                            Save Permissions
                        </button>

                    </div>

                </form>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>

</html>