<?php
session_start();

require_once '../config/db.php';

$pdo = getDbConnection();

// Restrict access to Super Admin
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Super Admin';

// Search and filter values
$search = trim($_GET['search'] ?? '');
$action_filter = trim($_GET['action'] ?? '');

// Build query conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        u.full_name LIKE ?
        OR u.email LIKE ?
        OR l.action LIKE ?
        OR l.description LIKE ?
        OR l.ip_address LIKE ?
    )";

    $search_term = "%{$search}%";

    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($action_filter !== '') {
    $where[] = "l.action = ?";
    $params[] = $action_filter;
}

$where_sql = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

// Fetch available action types
$action_stmt = $pdo->query("
    SELECT DISTINCT action
    FROM admin_activity_logs
    ORDER BY action ASC
");

$actions = $action_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch activity records
$sql = "
    SELECT
        l.id,
        l.admin_id,
        l.action,
        l.description,
        l.ip_address,
        l.created_at,
        u.full_name AS admin_name,
        u.email AS admin_email
    FROM admin_activity_logs l
    LEFT JOIN users u ON l.admin_id = u.id
    {$where_sql}
    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total number of matching records
$total_logs = count($activity_logs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs | GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>GloryMarket</h2>
            <p>Admin Panel</p>
        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a href="admins.php">
                <i class="fa-solid fa-user-shield"></i>
                Manage Admins
            </a>

            <a href="permissions.php">
                <i class="fa-solid fa-key"></i>
                Permissions
            </a>

            <a href="vendors.php">
                <i class="fa-solid fa-store"></i>
                Vendors
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i>
                Customers
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                Products
            </a>

            <a href="categories.php">
                <i class="fa-solid fa-list"></i>
                Categories
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                Orders
            </a>

            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i>
                Payments
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-line"></i>
                Reports
            </a>

            <a href="activity-logs.php" class="active">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Activity Logs
            </a>

            <a href="settings.php">
                <i class="fa-solid fa-gear"></i>
                Settings
            </a>

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Activity Logs</h1>
                <p>Monitor administrative actions and system activity.</p>
            </div>

            <div class="admin-details">
                <i class="fa-solid fa-user-circle"></i>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </header>


        <!-- ACTIVITY SUMMARY -->
        <section class="stats-grid">

            <div class="stat-card">
                <div>
                    <p>Total Activities</p>
                    <h2><?= number_format($total_logs) ?></h2>
                </div>

                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>

            <div class="stat-card">
                <div>
                    <p>Action Types</p>
                    <h2><?= number_format(count($actions)) ?></h2>
                </div>

                <i class="fa-solid fa-list-check"></i>
            </div>

        </section>


        <!-- ACTIVITY RECORDS -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Administrative Activity</h2>
                    <p>
                        <?= number_format($total_logs) ?>
                        matching record(s) found
                    </p>
                </div>
            </div>


            <!-- SEARCH AND FILTER -->
            <form method="GET" class="order-filter-form">

                <div class="filter-group">
                    <label for="search">Search Activities</label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Admin, action, description, IP..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>


                <div class="filter-group">
                    <label for="action">Action Type</label>

                    <select name="action" id="action">

                        <option value="">All Actions</option>

                        <?php foreach ($actions as $action): ?>

                            <option
                                value="<?= htmlspecialchars($action) ?>"
                                <?= $action_filter === $action ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    ucwords(strtolower(str_replace('_', ' ', $action)))
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <div class="filter-actions">

                    <button type="submit" class="primary-btn">
                        <i class="fa-solid fa-search"></i>
                        Search
                    </button>

                    <a href="activity-logs.php" class="secondary-btn">
                        Reset
                    </a>

                </div>

            </form>


            <!-- ACTIVITY TABLE -->
            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Administrator</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Date and Time</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($activity_logs)): ?>

                        <?php foreach ($activity_logs as $index => $log): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $log['admin_name'] ?? 'Unknown Admin'
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars(
                                            $log['admin_email'] ?? 'Account unavailable'
                                        ) ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="activity-action-badge">
                                        <?= htmlspecialchars(
                                            ucwords(
                                                strtolower(
                                                    str_replace('_', ' ', $log['action'])
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td class="activity-description">
                                    <?= htmlspecialchars(
                                        $log['description'] ?? 'No description provided'
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $log['ip_address'] ?? 'Unknown'
                                    ) ?>
                                </td>

                                <td>
                                    <?= !empty($log['created_at'])
                                        ? date(
                                            'd M Y, h:i A',
                                            strtotime($log['created_at'])
                                        )
                                        : 'Not available'
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="empty-state">
                                No activity records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>