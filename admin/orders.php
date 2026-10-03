
<?php
session_start();

require_once '../config/db.php';

$pdo = getDbConnection();

// Ensure only Super Admin can access this page
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

// Administrator information
$full_name = $_SESSION['full_name'] ?? 'Super Admin';

// Search and filter inputs
$search = trim($_GET['search'] ?? '');
$payment_filter = $_GET['payment_status'] ?? '';
$order_filter = $_GET['order_status'] ?? '';

// Valid filter options
$valid_payment_statuses = [
    'pending',
    'paid',
    'failed',
    'refunded'
];

$valid_order_statuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

// Validate filters
if (!in_array($payment_filter, $valid_payment_statuses, true)) {
    $payment_filter = '';
}

if (!in_array($order_filter, $valid_order_statuses, true)) {
    $order_filter = '';
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

if ($payment_filter !== '') {
    $where[] = "o.payment_status = ?";
    $params[] = $payment_filter;
}

if ($order_filter !== '') {
    $where[] = "o.order_status = ?";
    $params[] = $order_filter;
}

// Construct SQL query
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
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY o.created_at DESC";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total orders matching current filters
$total_orders = count($orders);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Orders | Glory E-commerce</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>Glory<span>Market</span></h2>
            <small>Super Admin Panel</small>
        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fas fa-lock"></i>
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
                <i class="fas fa-tags"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php" class="active">
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

    <!-- Main Content -->
    <main class="main-content">

        <header class="topbar">

            <div>
                <h1>Orders</h1>
                <p>Manage customer orders and order status.</p>
            </div>

            <div class="admin-profile">
                <i class="fas fa-user-circle"></i>

                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Super Admin</small>
                </div>
            </div>

        </header>

        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>All Orders</h2>
                    <p>
                        View and manage customer orders.
                        <strong>
                            (<?= $total_orders ?> orders found)
                        </strong>
                    </p>
                </div>
            </div>

            <!-- Search and Filters -->
            <form method="GET" action="orders.php" class="order-filter-form">

                <div class="filter-group">

                    <input
                        type="text"
                        name="search"
                        placeholder="Search order ID, order number, customer..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                    <select name="payment_status">
                        <option value="">All Payment Statuses</option>

                        <?php foreach ($valid_payment_statuses as $status): ?>
                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $payment_filter === $status ? 'selected' : '' ?>
                            >
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <select name="order_status">
                        <option value="">All Order Statuses</option>

                        <?php foreach ($valid_order_statuses as $status): ?>
                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $order_filter === $status ? 'selected' : '' ?>
                            >
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <button type="submit" class="primary-btn">
                        <i class="fas fa-search"></i>
                        Search
                    </button>

                    <a href="orders.php" class="secondary-btn">
                        <i class="fas fa-undo"></i>
                        Reset
                    </a>

                </div>

            </form>

            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Order Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($orders)): ?>

                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <p>No orders found.</p>
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($orders as $order): ?>

                            <tr>

                                <!-- Internal Order ID -->
                                <td>
                                    <strong>
                                        #<?= (int) $order['id'] ?>
                                    </strong>
                                </td>

                                <!-- Customer-facing Order Number -->
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </strong>
                                </td>

                                <!-- Customer -->
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($order['customer_name']) ?>
                                    </strong>
                                    <small>
                                        <?= htmlspecialchars($order['customer_email']) ?>
                                    </small>
                                </td>

                                <!-- Total -->
                                <td>
                                    ₦<?= number_format((float) $order['total_amount'], 2) ?>
                                </td>

                                <!-- Payment Status -->
                                <td>
                                    <span class="status-badge payment-<?= htmlspecialchars($order['payment_status'] ?? 'pending') ?>">
                                        <?= ucfirst(htmlspecialchars($order['payment_status'] ?? 'pending')) ?>
                                    </span>
                                </td>

                                <!-- Order Status -->
                                <td>
                                    <span class="status-badge order-<?= htmlspecialchars($order['order_status'] ?? 'pending') ?>">
                                        <?= ucfirst(htmlspecialchars($order['order_status'] ?? 'pending')) ?>
                                    </span>
                                </td>

                                <!-- Date -->
                                <td>
                                    <?= date('M d, Y', strtotime($order['created_at'])) ?>
                                </td>

                                <!-- Action -->
                                <td>
                                    <a
                                        href="order-view.php?id=<?= (int) $order['id'] ?>"
                                        class="view-btn"
                                    >
                                        <i class="fas fa-eye"></i>
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>