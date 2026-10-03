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
$payment_status = $_GET['payment_status'] ?? '';

$allowed_statuses = [
    'pending',
    'paid',
    'failed',
    'refunded'
];

if (!in_array($payment_status, $allowed_statuses, true)) {
    $payment_status = '';
}

// Build query conditions
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        o.order_number LIKE ?
        OR CAST(o.id AS CHAR) LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
    )";

    $search_term = "%{$search}%";

    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($payment_status !== '') {
    $where[] = "o.payment_status = ?";
    $params[] = $payment_status;
}

$where_sql = !empty($where)
    ? "WHERE " . implode(" AND ", $where)
    : "";

// Fetch payment records from orders
$sql = "
    SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.full_name AS customer_name,
        u.email AS customer_email
    FROM orders o
    INNER JOIN users u ON o.customer_id = u.id
    {$where_sql}
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment summary statistics
$summary_sql = "
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(
            CASE WHEN payment_status = 'paid'
            THEN total_amount ELSE 0 END
        ), 0) AS total_paid,

        COALESCE(SUM(
            CASE WHEN payment_status = 'pending'
            THEN total_amount ELSE 0 END
        ), 0) AS total_pending,

        COALESCE(SUM(
            CASE WHEN payment_status = 'refunded'
            THEN total_amount ELSE 0 END
        ), 0) AS total_refunded,

        COALESCE(SUM(
            CASE WHEN payment_status = 'failed'
            THEN 1 ELSE 0 END
        ), 0) AS failed_count

    FROM orders
";

$summary_stmt = $pdo->query($summary_sql);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Format currency
function formatPaymentAmount($amount)
{
    return '₦' . number_format((float) $amount, 2);
}

// Format status labels
function formatPaymentStatus($status)
{
    return ucfirst(str_replace('_', ' ', $status ?? 'pending'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management | GloryMarket</title>

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

            <a href="payments.php" class="active">
                <i class="fa-solid fa-credit-card"></i>
                Payments
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-line"></i>
                Reports
            </a>

            <a href="activity-logs.php">
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
                <h1>Payments Management</h1>
                <p>Monitor customer payments and financial transactions.</p>
            </div>

            <div class="admin-details">
                <i class="fa-solid fa-user-circle"></i>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </header>


        <!-- PAYMENT SUMMARY -->
        <section class="stats-grid">

            <div class="stat-card">
                <div>
                    <p>Total Orders</p>
                    <h2>
                        <?= number_format((int) $summary['total_orders']) ?>
                    </h2>
                </div>
                <i class="fa-solid fa-receipt"></i>
            </div>

            <div class="stat-card">
                <div>
                    <p>Total Paid</p>
                    <h2>
                        <?= formatPaymentAmount($summary['total_paid']) ?>
                    </h2>
                </div>
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div class="stat-card">
                <div>
                    <p>Pending Payments</p>
                    <h2>
                        <?= formatPaymentAmount($summary['total_pending']) ?>
                    </h2>
                </div>
                <i class="fa-solid fa-clock"></i>
            </div>

            <div class="stat-card">
                <div>
                    <p>Total Refunded</p>
                    <h2>
                        <?= formatPaymentAmount($summary['total_refunded']) ?>
                    </h2>
                </div>
                <i class="fa-solid fa-rotate-left"></i>
            </div>

            <div class="stat-card">
                <div>
                    <p>Failed Payments</p>
                    <h2>
                        <?= number_format((int) $summary['failed_count']) ?>
                    </h2>
                </div>
                <i class="fa-solid fa-circle-xmark"></i>
            </div>

        </section>


        <!-- PAYMENT RECORDS -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Payment Records</h2>
                    <p>
                        <?= count($payments) ?>
                        record(s) found
                    </p>
                </div>
            </div>


            <!-- SEARCH AND FILTER -->
            <form method="GET" class="order-filter-form">

                <div class="filter-group">
                    <label for="search">Search Payments</label>

                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Order ID, order number, customer..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>


                <div class="filter-group">
                    <label for="payment_status">Payment Status</label>

                    <select name="payment_status" id="payment_status">

                        <option value="">All Payment Statuses</option>

                        <?php foreach ($allowed_statuses as $status): ?>

                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $payment_status === $status ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(formatPaymentStatus($status)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <div class="filter-actions">
                    <button type="submit" class="primary-btn">
                        <i class="fa-solid fa-search"></i>
                        Search
                    </button>

                    <a href="payments.php" class="secondary-btn">
                        Reset
                    </a>
                </div>

            </form>


            <!-- PAYMENT TABLE -->
            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Order Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!empty($payments)): ?>

                        <?php foreach ($payments as $payment): ?>

                            <tr>

                                <td>
                                    #<?= (int) $payment['id'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($payment['order_number']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($payment['customer_name']) ?>
                                    </strong>
                                    <br>
                                    <small>
                                        <?= htmlspecialchars($payment['customer_email']) ?>
                                    </small>
                                </td>

                                <td>
                                    <?= formatPaymentAmount($payment['total_amount']) ?>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($payment['payment_status'] ?? 'pending') ?>">
                                        <?= htmlspecialchars(formatPaymentStatus($payment['payment_status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($payment['order_status'] ?? 'pending') ?>">
                                        <?= htmlspecialchars(formatPaymentStatus($payment['order_status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= date('d M Y, h:i A', strtotime($payment['created_at'])) ?>
                                </td>

                                <td>
                                    <a
                                        href="order-view.php?id=<?= (int) $payment['id'] ?>"
                                        class="view-btn"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        View Order
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                No payment records found.
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