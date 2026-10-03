<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../login.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, full_name, email, phone, status, created_at
    FROM users
    WHERE role = 'admin'
    ORDER BY created_at DESC
");

$stmt->execute();

$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admins | Glory E-commerce</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
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
            <h1>Manage Admins</h1>
            <p>Manage administrator accounts and access.</p>
        </div>

        <a href="admin-create.php" class="primary-btn">
            + Add Admin
        </a>

    </div>

    <div class="table-panel">

        <div class="panel-header">
            <h2>Administrator Accounts</h2>
        </div>

        <div class="table-wrapper">

            <table class="orders-table">

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Date Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($admins)): ?>

                    <?php foreach ($admins as $admin): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($admin['full_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($admin['email']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($admin['phone'] ?? '-') ?>
                            </td>

                            <td>
    <span class="status-badge status-<?= htmlspecialchars($admin['status']) ?>">
        <?= htmlspecialchars(ucfirst($admin['status'])) ?>
    </span>
</td>

                            <td>
                                <?= htmlspecialchars($admin['created_at']) ?>
                            </td>

                            <td>
                                <a
                                    href="admin-view.php?id=<?= $admin['id'] ?>"
                                    class="view-btn"
                                >
                                    View
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-state">
                            No administrator accounts found.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

</div>

</body>
</html>