<?php
session_start();

require_once '../config/db.php';

$pdo = getDbConnection();

/* =========================================
   VENDOR AUTHENTICATION
========================================= */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

/* =========================================
   VENDOR INFORMATION
========================================= */

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        vp.store_name,
        vp.logo,
        vp.verification_status
    FROM users u
    LEFT JOIN vendor_profiles vp
        ON vp.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$vendor_id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: ../login.php");
    exit();
}

$store_name = $vendor['store_name'] ?? $vendor['full_name'];

$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';


/* =========================================
   DATE FILTERS
========================================= */

$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

/*
 * Validate date format.
 */
if (
    $date_from !== '' &&
    !DateTime::createFromFormat('Y-m-d', $date_from)
) {
    $date_from = '';
}

if (
    $date_to !== '' &&
    !DateTime::createFromFormat('Y-m-d', $date_to)
) {
    $date_to = '';
}


/* =========================================
   BUILD DATE CONDITIONS
========================================= */

$date_where = [];
$date_params = [$vendor_id];

/*
 * Only PAID orders count as sales.
 * Cancelled orders are excluded.
 */

$date_where[] = "o.payment_status = 'paid'";
$date_where[] = "o.order_status != 'cancelled'";

$date_where[] = "oi.vendor_id = ?";

if ($date_from !== '') {
    $date_where[] = "DATE(o.created_at) >= ?";
    $date_params[] = $date_from;
}

if ($date_to !== '') {
    $date_where[] = "DATE(o.created_at) <= ?";
    $date_params[] = $date_to;
}

$date_where_clause = implode(' AND ', $date_where);


/* =========================================
   SALES SUMMARY
========================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(oi.subtotal), 0) AS total_sales,
        COALESCE(SUM(oi.quantity), 0) AS total_items,
        COUNT(DISTINCT o.id) AS total_orders

    FROM orders o

    INNER JOIN order_items oi
        ON oi.order_id = o.id

    WHERE $date_where_clause
");

$stmt->execute($date_params);

$sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

$total_sales = (float) ($sales_summary['total_sales'] ?? 0);
$total_items = (int) ($sales_summary['total_items'] ?? 0);
$total_orders = (int) ($sales_summary['total_orders'] ?? 0);


/* =========================================
   ALL-TIME PAID SALES
========================================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(oi.subtotal), 0)

    FROM order_items oi

    INNER JOIN orders o
        ON o.id = oi.order_id

    WHERE oi.vendor_id = ?
      AND o.payment_status = 'paid'
      AND o.order_status != 'cancelled'
");

$stmt->execute([$vendor_id]);

$all_time_sales = (float) $stmt->fetchColumn();


/* =========================================
   SALES BY ORDER
========================================= */

$order_where = [];
$order_params = [$vendor_id];

$order_where[] = "oi.vendor_id = ?";
$order_where[] = "o.payment_status = 'paid'";
$order_where[] = "o.order_status != 'cancelled'";

if ($date_from !== '') {
    $order_where[] = "DATE(o.created_at) >= ?";
    $order_params[] = $date_from;
}

if ($date_to !== '') {
    $order_where[] = "DATE(o.created_at) <= ?";
    $order_params[] = $date_to;
}

$order_where_clause = implode(' AND ', $order_where);

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.created_at,
        o.payment_status,
        o.order_status,

        u.full_name AS customer_name,

        SUM(oi.subtotal) AS vendor_total,
        SUM(oi.quantity) AS vendor_quantity,
        COUNT(DISTINCT oi.id) AS vendor_item_count

    FROM orders o

    INNER JOIN order_items oi
        ON oi.order_id = o.id

    LEFT JOIN users u
        ON u.id = o.customer_id

    WHERE $order_where_clause

    GROUP BY
        o.id,
        o.order_number,
        o.created_at,
        o.payment_status,
        o.order_status,
        u.full_name

    ORDER BY o.created_at DESC
");

$stmt->execute($order_params);

$sales_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================
   STATUS HELPERS
========================================= */

function formatSalesStatus(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
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

    <title>Sales - Vendor Dashboard</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/vendor-dashboard.css"
    >

</head>

<body>

<div class="vendor-dashboard">

    <!-- =========================================
         SIDEBAR
    ========================================== -->

    <aside class="vendor-sidebar">

        <div class="vendor-logo-area">

            <img
                src="<?= htmlspecialchars($vendor_logo) ?>"
                alt="Vendor Logo"
                class="vendor-logo"
            >

            <div class="vendor-store-name">
                <?= htmlspecialchars($store_name) ?>
            </div>

            <small>Vendor Account</small>

        </div>


        <nav class="vendor-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                <span>My Products</span>
            </a>

            <a href="add-product.php">
                <i class="fa-solid fa-plus"></i>
                <span>Add Product</span>
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
            </a>

            <a href="sales.php" class="active">
                <i class="fa-solid fa-chart-column"></i>
                <span>Sales</span>
            </a>

            <a href="store.php">
                <i class="fa-solid fa-store"></i>
                <span>My Store</span>
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                <span>Profile</span>
            </a>

            <a href="security.php">
                <i class="fa-solid fa-lock"></i>
                <span>Security</span>
            </a>

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- =========================================
         MAIN CONTENT
    ========================================== -->

    <main class="vendor-main">


        <!-- =====================================
             TOPBAR
        ====================================== -->

        <div class="vendor-topbar">

            <div>

                <h1>Sales</h1>

                <p>
                    Track sales generated from your products.
                </p>

            </div>

        </div>


        <!-- =====================================
             DATE FILTER
        ====================================== -->

        <div class="vendor-panel sales-filter-panel">

            <form
                method="GET"
                class="sales-filter-form"
            >

                <div class="filter-group">

                    <label for="date_from">
                        From Date
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="<?= htmlspecialchars($date_from) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="date_to">
                        To Date
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="<?= htmlspecialchars($date_to) ?>"
                    >

                </div>


                <div class="sales-filter-actions">

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        <i class="fa-solid fa-filter"></i>
                        Apply Filter
                    </button>

                    <a
                        href="sales.php"
                        class="btn-secondary"
                    >
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- =====================================
             SALES STATISTICS
        ====================================== -->

        <div class="vendor-stats-grid sales-stats-grid">

            <div class="vendor-stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-naira-sign"></i>
                </div>

                <div class="stat-content">

                    <span>
                        Sales in Selected Period
                    </span>

                    <strong>
                        ₦<?= number_format(
                            $total_sales,
                            2
                        ) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-money-bill-trend-up"></i>
                </div>

                <div class="stat-content">

                    <span>
                        All-Time Paid Sales
                    </span>

                    <strong>
                        ₦<?= number_format(
                            $all_time_sales,
                            2
                        ) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-box-open"></i>
                </div>

                <div class="stat-content">

                    <span>
                        Items Sold
                    </span>

                    <strong>
                        <?= number_format($total_items) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <div class="stat-content">

                    <span>
                        Orders
                    </span>

                    <strong>
                        <?= number_format($total_orders) ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- =====================================
             SALES TABLE
        ====================================== -->

        <div class="vendor-panel sales-table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Sales Transactions
                    </h2>

                    <p>
                        Paid sales generated from your products.
                    </p>

                </div>

            </div>


            <?php if (empty($sales_orders)): ?>

                <div class="vendor-empty-state">

                    <i class="fa-solid fa-chart-column"></i>

                    <h3>
                        No Sales Found
                    </h3>

                    <p>
                        There are no paid sales for the selected period.
                    </p>

                    <?php if ($date_from !== '' || $date_to !== ''): ?>

                        <a
                            href="sales.php"
                            class="btn-secondary"
                        >
                            View All Sales
                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="vendor-table-wrapper">

                    <table class="vendor-table sales-table">

                        <thead>

                            <tr>

                                <th>Order</th>

                                <th>Customer</th>

                                <th>Items</th>

                                <th>Vendor Sales</th>

                                <th>Payment</th>

                                <th>Order Status</th>

                                <th>Date</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($sales_orders as $sale): ?>

                            <tr>

                                <td>

                                    <strong>
                                        #<?= htmlspecialchars(
                                            $sale['order_number']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $sale['customer_name'] ?? 'Customer'
                                    ) ?>

                                </td>


                                <td>

                                    <?= (int) $sale['vendor_quantity'] ?>

                                </td>


                                <td>

                                    <strong class="sales-amount">
                                        ₦<?= number_format(
                                            (float) $sale['vendor_total'],
                                            2
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <span class="order-status-badge payment-paid">
                                        <?= htmlspecialchars(
                                            formatSalesStatus(
                                                $sale['payment_status']
                                            )
                                        ) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="order-status-badge">

                                        <?= htmlspecialchars(
                                            formatSalesStatus(
                                                $sale['order_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= date(
                                        'M d, Y',
                                        strtotime($sale['created_at'])
                                    ) ?>

                                </td>


                                <td>

                                    <a
                                        href="order-view.php?id=<?= (int) $sale['id'] ?>"
                                        class="table-action view-action"
                                    >
                                        <i class="fa-solid fa-eye"></i>
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>