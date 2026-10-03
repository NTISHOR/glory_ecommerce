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

// Date filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Validate date format
function isValidReportDate($date)
{
    $date_object = DateTime::createFromFormat('Y-m-d', $date);

    return $date_object && $date_object->format('Y-m-d') === $date;
}

if ($date_from !== '' && !isValidReportDate($date_from)) {
    $date_from = '';
}

if ($date_to !== '' && !isValidReportDate($date_to)) {
    $date_to = '';
}

// Prevent an invalid date range
if (
    $date_from !== '' &&
    $date_to !== '' &&
    $date_from > $date_to
) {
    $date_to = '';
}

// Build date conditions for orders
$order_conditions = [];
$order_params = [];

if ($date_from !== '') {
    $order_conditions[] = "DATE(created_at) >= ?";
    $order_params[] = $date_from;
}

if ($date_to !== '') {
    $order_conditions[] = "DATE(created_at) <= ?";
    $order_params[] = $date_to;
}

$order_where = !empty($order_conditions)
    ? "WHERE " . implode(" AND ", $order_conditions)
    : "";

// Sales and order summary
$summary_sql = "
    SELECT
        COUNT(*) AS total_orders,

        COALESCE(SUM(
            CASE
                WHEN payment_status = 'paid'
                THEN total_amount
                ELSE 0
            END
        ), 0) AS total_revenue,

        COALESCE(SUM(
            CASE
                WHEN payment_status = 'pending'
                THEN total_amount
                ELSE 0
            END
        ), 0) AS pending_amount,

        COALESCE(SUM(
            CASE
                WHEN payment_status = 'refunded'
                THEN total_amount
                ELSE 0
            END
        ), 0) AS refunded_amount,

        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END)
            AS pending_orders,

        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END)
            AS confirmed_orders,

        SUM(CASE WHEN order_status = 'processing' THEN 1 ELSE 0 END)
            AS processing_orders,

        SUM(CASE WHEN order_status = 'shipped' THEN 1 ELSE 0 END)
            AS shipped_orders,

        SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END)
            AS delivered_orders,

        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END)
            AS cancelled_orders

    FROM orders
    {$order_where}
";

$stmt = $pdo->prepare($summary_sql);
$stmt->execute($order_params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Payment status report
$payment_sql = "
    SELECT
        payment_status,
        COUNT(*) AS payment_count,
        COALESCE(SUM(total_amount), 0) AS payment_amount
    FROM orders
    {$order_where}
    GROUP BY payment_status
    ORDER BY payment_status
";

$stmt = $pdo->prepare($payment_sql);
$stmt->execute($order_params);
$payment_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Customer report
$customer_conditions = ["role = 'customer'"];
$customer_params = [];

if ($date_from !== '') {
    $customer_conditions[] = "DATE(created_at) >= ?";
    $customer_params[] = $date_from;
}

if ($date_to !== '') {
    $customer_conditions[] = "DATE(created_at) <= ?";
    $customer_params[] = $date_to;
}

$customer_where = "WHERE " . implode(" AND ", $customer_conditions);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_customers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)
            AS active_customers,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END)
            AS inactive_customers,
        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END)
            AS suspended_customers
    FROM users
    {$customer_where}
");

$stmt->execute($customer_params);
$customer_report = $stmt->fetch(PDO::FETCH_ASSOC);

// Vendor report
$vendor_conditions = ["u.role = 'vendor'"];
$vendor_params = [];

if ($date_from !== '') {
    $vendor_conditions[] = "DATE(u.created_at) >= ?";
    $vendor_params[] = $date_from;
}

if ($date_to !== '') {
    $vendor_conditions[] = "DATE(u.created_at) <= ?";
    $vendor_params[] = $date_to;
}

$vendor_where = "WHERE " . implode(" AND ", $vendor_conditions);

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_vendors,
        SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END)
            AS active_vendors,
        SUM(CASE WHEN vp.verification_status = 'verified' THEN 1 ELSE 0 END)
            AS verified_vendors,
        SUM(CASE WHEN vp.verification_status = 'pending' THEN 1 ELSE 0 END)
            AS pending_vendors,
        SUM(CASE WHEN vp.verification_status = 'rejected' THEN 1 ELSE 0 END)
            AS rejected_vendors
    FROM users u
    LEFT JOIN vendor_profiles vp ON vp.user_id = u.id
    {$vendor_where}
");

$stmt->execute($vendor_params);
$vendor_report = $stmt->fetch(PDO::FETCH_ASSOC);

// Product report
$product_conditions = [];
$product_params = [];

if ($date_from !== '') {
    $product_conditions[] = "DATE(created_at) >= ?";
    $product_params[] = $date_from;
}

if ($date_to !== '') {
    $product_conditions[] = "DATE(created_at) <= ?";
    $product_params[] = $date_to;
}

$product_where = !empty($product_conditions)
    ? "WHERE " . implode(" AND ", $product_conditions)
    : "";

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)
            AS active_products,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END)
            AS inactive_products
    FROM products
    {$product_where}
");

$stmt->execute($product_params);
$product_report = $stmt->fetch(PDO::FETCH_ASSOC);

// Currency formatter
function formatReportAmount($amount)
{
    return '₦' . number_format((float) $amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | GloryMarket</title>

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

            <a href="reports.php" class="active">
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
                <h1>Reports</h1>
                <p>Monitor sales, orders, customers, vendors, and products.</p>
            </div>

            <div class="admin-details">
                <i class="fa-solid fa-user-circle"></i>
                <span><?= htmlspecialchars($full_name) ?></span>
            </div>
        </header>


        <!-- DATE FILTER -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Report Period</h2>
                    <p>Select a date range to filter the reports.</p>
                </div>
            </div>

            <form method="GET" class="report-filter-form">

                <div class="filter-group">
                    <label for="date_from">From Date</label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= htmlspecialchars($date_from) ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date</label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= htmlspecialchars($date_to) ?>"
                    >
                </div>

                <div class="filter-actions">
                    <button type="submit" class="primary-btn">
                        <i class="fa-solid fa-filter"></i>
                        Apply Filter
                    </button>

                    <a href="reports.php" class="secondary-btn">
                        Reset
                    </a>
                </div>

            </form>

        </section>


        <!-- SALES SUMMARY -->
        <section class="report-section">

            <div class="report-section-heading">
                <h2>Sales Overview</h2>
                <p>Summary of orders and payment activity.</p>
            </div>

            <div class="stats-grid">

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
                        <p>Total Revenue</p>
                        <h2>
                            <?= formatReportAmount($summary['total_revenue']) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Pending Amount</p>
                        <h2>
                            <?= formatReportAmount($summary['pending_amount']) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Refunded Amount</p>
                        <h2>
                            <?= formatReportAmount($summary['refunded_amount']) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-rotate-left"></i>
                </div>

            </div>

        </section>


        <!-- ORDER REPORT -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Order Status Report</h2>
                    <p>Distribution of orders by fulfilment status.</p>
                </div>
            </div>

            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Order Status</th>
                            <th>Number of Orders</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php
                        $order_statuses = [
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'processing' => 'Processing',
                            'shipped' => 'Shipped',
                            'delivered' => 'Delivered',
                            'cancelled' => 'Cancelled'
                        ];
                        ?>

                        <?php foreach ($order_statuses as $key => $label): ?>

                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>

                                <td>
                                    <?= number_format(
                                        (int) ($summary[$key . '_orders'] ?? 0)
                                    ) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- PAYMENT REPORT -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Payment Report</h2>
                    <p>Payment totals grouped by payment status.</p>
                </div>
            </div>

            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Payment Status</th>
                            <th>Number of Orders</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($payment_report)): ?>

                            <?php foreach ($payment_report as $payment): ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            ucfirst($payment['payment_status'] ?? 'Pending')
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $payment['payment_count']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= formatReportAmount(
                                            $payment['payment_amount']
                                        ) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="3" class="empty-state">
                                    No payment records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- CUSTOMER REPORT -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Customer Report</h2>
                    <p>Customer registrations and account statuses.</p>
                </div>
            </div>

            <div class="stats-grid">

                <div class="stat-card">
                    <div>
                        <p>Total Customers</p>
                        <h2>
                            <?= number_format(
                                (int) $customer_report['total_customers']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-users"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Active Customers</p>
                        <h2>
                            <?= number_format(
                                (int) $customer_report['active_customers']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-user-check"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Inactive Customers</p>
                        <h2>
                            <?= number_format(
                                (int) $customer_report['inactive_customers']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-user-clock"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Suspended Customers</p>
                        <h2>
                            <?= number_format(
                                (int) $customer_report['suspended_customers']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-user-slash"></i>
                </div>

            </div>

        </section>


        <!-- VENDOR REPORT -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Vendor Report</h2>
                    <p>Vendor accounts and verification overview.</p>
                </div>
            </div>

            <div class="stats-grid">

                <div class="stat-card">
                    <div>
                        <p>Total Vendors</p>
                        <h2>
                            <?= number_format(
                                (int) $vendor_report['total_vendors']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-store"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Active Vendors</p>
                        <h2>
                            <?= number_format(
                                (int) $vendor_report['active_vendors']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-store-circle-check"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Verified Vendors</p>
                        <h2>
                            <?= number_format(
                                (int) $vendor_report['verified_vendors']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-circle-check"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Pending Verification</p>
                        <h2>
                            <?= number_format(
                                (int) $vendor_report['pending_vendors']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Rejected Vendors</p>
                        <h2>
                            <?= number_format(
                                (int) $vendor_report['rejected_vendors']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>

            </div>

        </section>


        <!-- PRODUCT REPORT -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Product Report</h2>
                    <p>Product registration and availability overview.</p>
                </div>
            </div>

            <div class="stats-grid">

                <div class="stat-card">
                    <div>
                        <p>Total Products</p>
                        <h2>
                            <?= number_format(
                                (int) $product_report['total_products']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Active Products</p>
                        <h2>
                            <?= number_format(
                                (int) $product_report['active_products']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div class="stat-card">
                    <div>
                        <p>Inactive Products</p>
                        <h2>
                            <?= number_format(
                                (int) $product_report['inactive_products']
                            ) ?>
                        </h2>
                    </div>
                    <i class="fa-solid fa-box"></i>
                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>