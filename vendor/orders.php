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

$store_name = $vendor['store_name'] ?? $vendor['full_name'];
$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';

/* =========================================
   FILTERS
========================================= */

$search = trim($_GET['search'] ?? '');
$payment_status = trim($_GET['payment_status'] ?? '');
$order_status = trim($_GET['order_status'] ?? '');

$allowed_payment_statuses = [
    'pending',
    'paid',
    'failed',
    'refunded'
];

$allowed_order_statuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

if (!in_array($payment_status, $allowed_payment_statuses, true)) {
    $payment_status = '';
}

if (!in_array($order_status, $allowed_order_statuses, true)) {
    $order_status = '';
}

/* =========================================
   BUILD QUERY
========================================= */

$where = [];
$params = [];

$where[] = "oi.vendor_id = ?";
$params[] = $vendor_id;

if ($search !== '') {
    $where[] = "
        (
            o.order_number LIKE ?
            OR u.full_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $search_value = '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
}

if ($payment_status !== '') {
    $where[] = "o.payment_status = ?";
    $params[] = $payment_status;
}

if ($order_status !== '') {
    $where[] = "o.order_status = ?";
    $params[] = $order_status;
}

$where_clause = implode(' AND ', $where);

/* =========================================
   FETCH VENDOR ORDERS
========================================= */

$sql = "
    SELECT
        o.id,
        o.order_number,
        o.customer_id,
        o.payment_status,
        o.order_status,
        o.delivery_address,
        o.created_at,

        u.full_name AS customer_name,
        u.email AS customer_email,

        SUM(oi.subtotal) AS vendor_subtotal,
        SUM(oi.quantity) AS vendor_quantity,
        COUNT(DISTINCT oi.id) AS vendor_item_count

    FROM orders o

INNER JOIN order_items oi
    ON oi.order_id = o.id

    LEFT JOIN users u
        ON u.id = o.customer_id

    WHERE $where_clause

    GROUP BY
        o.id,
        o.order_number,
        o.customer_id,
        o.payment_status,
        o.order_status,
        o.delivery_address,
        o.created_at,
        u.full_name,
        u.email

    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   TOTAL VENDOR ORDERS
========================================= */

$total_orders = count($orders);

/* =========================================
   STATUS HELPERS
========================================= */

function orderStatusClass(string $status): string
{
    return match ($status) {
        'pending' => 'status-pending',
        'confirmed' => 'status-confirmed',
        'processing' => 'status-processing',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled',
        default => 'status-default'
    };
}

function paymentStatusClass(string $status): string
{
    return match ($status) {
        'pending' => 'payment-pending',
        'paid' => 'payment-paid',
        'failed' => 'payment-failed',
        'refunded' => 'payment-refunded',
        default => 'payment-default'
    };
}

function formatStatus(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Orders - Vendor Dashboard</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/vendor-dashboard.css">
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

            <a href="orders.php" class="active">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
            </a>

            <a href="sales.php">
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

        <div class="vendor-topbar">

            <div>
                <h1>Orders</h1>
                <p>Manage orders containing your products.</p>
            </div>

        </div>


        <!-- =====================================
             FILTERS
        ====================================== -->

        <div class="vendor-panel orders-filter-panel">

            <form method="GET" class="orders-filter-form">

                <div class="filter-group search-filter">

                    <label for="search">Search</label>

                    <div class="search-input-wrapper">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            placeholder="Order number, customer name or email"
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    </div>

                </div>


                <div class="filter-group">

                    <label for="payment_status">Payment Status</label>

                    <select name="payment_status" id="payment_status">

                        <option value="">All Payments</option>

                        <?php foreach ($allowed_payment_statuses as $status): ?>

                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $payment_status === $status ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(formatStatus($status)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="order_status">Order Status</label>

                    <select name="order_status" id="order_status">

                        <option value="">All Orders</option>

                        <?php foreach ($allowed_order_statuses as $status): ?>

                            <option
                                value="<?= htmlspecialchars($status) ?>"
                                <?= $order_status === $status ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(formatStatus($status)) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-actions">

                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <a href="orders.php" class="btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>

                </div>

            </form>

        </div>


        <!-- =====================================
             ORDER SUMMARY
        ====================================== -->

        <div class="orders-summary">

            <div class="summary-info">

                <i class="fa-solid fa-cart-shopping"></i>

                <div>
                    <strong><?= $total_orders ?></strong>
                    <span>
                        <?= $total_orders === 1 ? 'Order' : 'Orders' ?>
                    </span>
                </div>

            </div>

        </div>


        <!-- =====================================
             ORDERS TABLE
        ====================================== -->

        <div class="vendor-panel orders-panel">

            <div class="panel-header">

                <div>
                    <h2>Customer Orders</h2>
                    <p>
                        Orders containing products from your store.
                    </p>
                </div>

            </div>


            <?php if (empty($orders)): ?>

                <div class="vendor-empty-state">

                    <i class="fa-solid fa-cart-shopping"></i>

                    <h3>No Orders Found</h3>

                    <p>
                        There are no orders matching your current filters.
                    </p>

                    <?php if ($search !== '' || $payment_status !== '' || $order_status !== ''): ?>

                        <a href="orders.php" class="btn-secondary">
                            Clear Filters
                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="vendor-table-wrapper">

                    <table class="vendor-table orders-table">

                        <thead>

                            <tr>

                                <th>Order</th>

                                <th>Customer</th>

                                <th>Items</th>

                                <th>Your Subtotal</th>

                                <th>Payment</th>

                                <th>Order Status</th>

                                <th>Date</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($orders as $order): ?>

                            <tr>

                                <td>

                                    <strong>
                                        #<?= htmlspecialchars($order['order_number']) ?>
                                    </strong>

                                </td>


                                <td>

                                    <div class="customer-order-info">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $order['customer_name'] ?? 'Customer'
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                $order['customer_email'] ?? ''
                                            ) ?>
                                        </small>

                                    </div>

                                </td>


                                <td>

                                    <div class="order-items-count">

                                        <strong>
                                            <?= (int) $order['vendor_quantity'] ?>
                                        </strong>

                                        <span>
                                            <?= (int) $order['vendor_quantity'] === 1
                                                ? 'item'
                                                : 'items' ?>
                                        </span>

                                    </div>

                                </td>


                                <td>

                                    <strong class="vendor-order-total">
                                        ₦<?= number_format(
                                            (float) $order['vendor_subtotal'],
                                            2
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    <span class="order-status-badge
                                        <?= htmlspecialchars(
                                            paymentStatusClass(
                                                $order['payment_status']
                                            )
                                        ) ?>">

                                        <?= htmlspecialchars(
                                            formatStatus(
                                                $order['payment_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="order-status-badge
                                        <?= htmlspecialchars(
                                            orderStatusClass(
                                                $order['order_status']
                                            )
                                        ) ?>">

                                        <?= htmlspecialchars(
                                            formatStatus(
                                                $order['order_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <div class="order-date">

                                        <strong>
                                            <?= date(
                                                'M d, Y',
                                                strtotime($order['created_at'])
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= date(
                                                'h:i A',
                                                strtotime($order['created_at'])
                                            ) ?>
                                        </small>

                                    </div>

                                </td>


                                <td>

                                    <a
                                        href="order-view.php?id=<?= (int) $order['id'] ?>"
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