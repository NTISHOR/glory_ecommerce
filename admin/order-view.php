
<?php
session_start();
require_once '../config/db.php';
require_once 'admin_activity.php';

$pdo = getDbConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure only Super Admin can access this page
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Super Admin';

// Validate order ID
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id || $order_id <= 0) {
    header("Location: orders.php?error=invalid_order");
    exit;
}


// Allowed order statuses
$allowed_order_statuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

// Status update messages
$success_message = '';
$error_message = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF token before processing any changes
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        die('Invalid security token. Please refresh the page and try again.');
    }

    $new_status = $_POST['order_status'] ?? '';

    if (!in_array($new_status, $allowed_order_statuses, true)) {

        $error_message = "Invalid order status selected.";

    } else {

        try {

            // Fetch current status
            $stmt = $pdo->prepare("
                SELECT order_status
                FROM orders
                WHERE id = ?
            ");

            $stmt->execute([$order_id]);

            $current_status = $stmt->fetchColumn();

            if ($current_status === false) {

                $error_message = "Order not found.";

            } elseif ($current_status === $new_status) {

                $error_message = "The order already has this status.";

            } else {

                // Update order status only
                $stmt = $pdo->prepare("
                    UPDATE orders
                    SET order_status = ?
                    WHERE id = ?
                ");

                $stmt->execute([$new_status, $order_id]);

                
                // Record activity
                logAdminActivity(
                    $pdo,
                    (int) $_SESSION['user_id'],
                    'UPDATE_ORDER_STATUS',
                    "Order #{$order_id} status changed from {$current_status} to {$new_status}."
                );

                $success_message = "Order status updated successfully.";
            }

        } catch (PDOException $e) {

            $error_message = "Unable to update order status. Please try again.";

        }
    }
}

// Fetch order and customer details
$stmt = $pdo->prepare("
SELECT
    o.*,
    u.full_name AS customer_name,
    u.email AS customer_email,
    u.phone AS customer_phone,

    verifier.full_name AS payment_verifier_name,

    refunder.full_name AS refund_by_name

FROM orders o

INNER JOIN users u
    ON o.customer_id = u.id

LEFT JOIN users verifier
    ON verifier.id = o.payment_verified_by

LEFT JOIN users refunder
    ON refunder.id = o.refunded_by

WHERE o.id = ?

LIMIT 1
");

$stmt->execute([$order_id]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php?error=order_not_found");
    exit;
}

// Fetch order items and vendor information
$stmt = $pdo->prepare("
    SELECT
        oi.id,
        oi.product_id,
        oi.vendor_id,
        oi.product_name,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        u.full_name AS vendor_name,
        vp.store_name
    FROM order_items oi
    LEFT JOIN users u ON oi.vendor_id = u.id
    LEFT JOIN vendor_profiles vp ON vp.user_id = oi.vendor_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$stmt->execute([$order_id]);

$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   FETCH VENDOR CONFIRMATIONS
========================================= */

$stmt = $pdo->prepare("
    SELECT
        oi.vendor_id,
        COALESCE(
            vp.store_name,
            u.full_name,
            'Unknown Vendor'
        ) AS vendor_name,
        voc.confirmed_at

    FROM order_items oi

    LEFT JOIN users u
        ON u.id = oi.vendor_id

    LEFT JOIN vendor_profiles vp
        ON vp.user_id = oi.vendor_id

    LEFT JOIN vendor_order_confirmations voc
        ON voc.order_id = oi.order_id
        AND voc.vendor_id = oi.vendor_id

    WHERE oi.order_id = ?

    GROUP BY
        oi.vendor_id,
        vp.store_name,
        u.full_name,
        voc.confirmed_at

    ORDER BY vendor_name ASC
");

$stmt->execute([$order_id]);

$vendor_confirmations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate item quantity
$total_items = 0;

foreach ($order_items as $item) {
    $total_items += (int) $item['quantity'];
}

// Format currency
function formatNaira($amount): string
{
    return '₦' . number_format((float) $amount, 2);
}

// Format status for display
function formatStatus($status): string
{
    return ucfirst(str_replace('_', ' ', $status ?? 'pending'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Order Details | Glory E-commerce</title>

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
                <h1>Order Details</h1>
                <p>View complete customer order information.</p>
            </div>

            <div class="admin-profile">
                <i class="fas fa-user-circle"></i>

                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Super Admin</small>
                </div>
            </div>

        </header>

        <!-- Back Button -->
        <div style="margin-bottom: 20px;">
            <a href="orders.php" class="secondary-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Orders
            </a>
        </div>

        <!-- Order Summary -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>
                        Order #<?= (int) $order['id'] ?>
                    </h2>

                    <p>
                        Order Number:
                        <strong>
                            <?= htmlspecialchars($order['order_number']) ?>
                        </strong>
                    </p>
                </div>

                <span class="status-badge order-<?= htmlspecialchars($order['order_status'] ?? 'pending') ?>">
                    <?= htmlspecialchars(formatStatus($order['order_status'])) ?>
                </span>
            </div>

            <div class="order-details-grid">

                <div class="order-detail-card">
                    <i class="fas fa-calendar"></i>
                    <div>
                        <small>Order Date</small>
                        <strong>
                            <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                        </strong>
                    </div>
                </div>

                <div class="order-detail-card">
                    <i class="fas fa-box"></i>
                    <div>
                        <small>Total Items</small>
                        <strong><?= $total_items ?></strong>
                    </div>
                </div>

                <div class="order-detail-card">
                    <i class="fas fa-credit-card"></i>
                    <div>
                        <small>Payment Status</small>
                        <strong>
                            <?= htmlspecialchars(formatStatus($order['payment_status'])) ?>
                        </strong>
                    </div>
                </div>

            </div>

        </section>


<!-- Vendor Confirmation Tracking -->
<section class="table-panel">

    <div class="panel-header">
        <div>
            <h2>
                <i class="fas fa-store"></i>
                Vendor Confirmations
            </h2>

            <p>
                Track which vendors have acknowledged this order.
            </p>
        </div>
    </div>

    <div class="table-wrapper">

        <table class="orders-table">

            <thead>
                <tr>
                    <th>Vendor</th>
                    <th>Confirmation Status</th>
                    <th>Confirmation Date</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($vendor_confirmations)): ?>

                    <tr>
                        <td colspan="3" class="empty-state">
                            No vendor confirmations found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $vendor_confirmations as $confirmation
                    ): ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $confirmation['vendor_name']
                                    ) ?>
                                </strong>
                            </td>

                            <td>

                                <?php if (
                                    !empty($confirmation['confirmed_at'])
                                ): ?>

                                    <span class="status-badge order-confirmed">
                                        <i class="fas fa-check-circle"></i>
                                        Confirmed
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge order-pending">
                                        <i class="fas fa-clock"></i>
                                        Awaiting Confirmation
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if (
                                    !empty($confirmation['confirmed_at'])
                                ): ?>

                                    <?= date(
                                        'M d, Y h:i A',
                                        strtotime(
                                            $confirmation['confirmed_at']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span>Not confirmed</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<!-- Order Status Management -->
<section class="table-panel">

    <div class="panel-header">
        <div>
            <h2>
                <i class="fas fa-edit"></i>
                Update Order Status
            </h2>
            <p>Change the fulfilment status of this order.</p>
        </div>
    </div>

    <div style="padding: 20px;">

        <?php if ($success_message !== ''): ?>
            <div class="success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="order-status-form" >
<input
    type="hidden"
    name="csrf_token"
    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
>
            <div class="form-group">
                <label for="order_status">Order Status</label>

                <select name="order_status" id="order_status" required>

                    <?php foreach ($allowed_order_statuses as $status): ?>

                        <option
                            value="<?= htmlspecialchars($status) ?>"
                            <?= $order['order_status'] === $status ? 'selected' : '' ?>
                        >
                            <?= ucfirst($status) ?>
                        </option>

                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-btn">
                    <i class="fas fa-save"></i>
                    Update Status
                </button>
            </div>

        </form>

    </div>

</section>

        <!-- Customer Information -->
        <section class="table-panel">

            <div class="panel-header">
                <h2>
                    <i class="fas fa-user"></i>
                    Customer Information
                </h2>
            </div>

            <div class="order-info-grid">

                <div>
                    <small>Full Name</small>
                    <strong>
                        <?= htmlspecialchars($order['customer_name']) ?>
                    </strong>
                </div>

                <div>
                    <small>Email Address</small>
                    <strong>
                        <?= htmlspecialchars($order['customer_email']) ?>
                    </strong>
                </div>

                <div>
                    <small>Phone Number</small>
                    <strong>
                        <?= htmlspecialchars($order['customer_phone'] ?? 'Not provided') ?>
                    </strong>
                </div>

                <div>
                    <small>Delivery Address</small>
                    <strong>
                        <?= nl2br(htmlspecialchars($order['delivery_address'])) ?>
                    </strong>
                </div>

            </div>

            <?php if (!empty($order['customer_note'])): ?>
                <div class="customer-note">
                    <strong>Customer Note:</strong>
                    <p><?= nl2br(htmlspecialchars($order['customer_note'])) ?></p>
                </div>
            <?php endif; ?>

        </section>

        <!-- Ordered Products -->
        <section class="table-panel">

            <div class="panel-header">
                <div>
                    <h2>Ordered Products</h2>
                    <p>Products included in this order.</p>
                </div>
            </div>

            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($order_items)): ?>

                        <tr>
                            <td colspan="5" class="empty-state">
                                No products found for this order.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($order_items as $item): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </strong>
                                    <small>
                                        Product ID: #<?= (int) $item['product_id'] ?>
                                    </small>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $item['store_name']
                                        ?: ($item['vendor_name'] ?? 'Unknown Vendor')
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $item['quantity'] ?>
                                </td>

                                <td>
                                    <?= formatNaira($item['unit_price']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= formatNaira($item['subtotal']) ?>
                                    </strong>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- Payment Summary -->
        <section class="table-panel">

            <div class="panel-header">
                <h2>Payment Summary</h2>
            </div>

            <div class="payment-summary">

                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong>
                        <?= formatNaira($order['subtotal']) ?>
                    </strong>
                </div>

                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <strong>
                        <?= formatNaira($order['delivery_fee']) ?>
                    </strong>
                </div>

                <div class="summary-row">
                    <span>Discount</span>
                    <strong>
                        -<?= formatNaira($order['discount']) ?>
                    </strong>
                </div>

                <div class="summary-row total-row">
                    <span>Total Amount</span>
                    <strong>
                        <?= formatNaira($order['total_amount']) ?>
                    </strong>
                </div>

                <div class="summary-row">
                    <span>Payment Status</span>
                    <span class="status-badge payment-<?= htmlspecialchars($order['payment_status'] ?? 'pending') ?>">
                        <?= htmlspecialchars(formatStatus($order['payment_status'])) ?>
                    </span>
                </div>

                <div class="summary-row">
    <span>Payment Method</span>
    <strong>
        <?= htmlspecialchars(formatStatus($order['payment_method'])) ?>
    </strong>
</div>

<?php if (!empty($order['payment_verified_at'])): ?>

    <div class="summary-row">
        <span>Verified By</span>
        <strong>
            <?= htmlspecialchars(
                $order['payment_verifier_name'] ?? 'Admin'
            ) ?>
        </strong>
    </div>

    <div class="summary-row">
        <span>Verified At</span>
        <strong>
            <?= date(
                'M d, Y h:i A',
                strtotime($order['payment_verified_at'])
            ) ?>
        </strong>
    </div>

<?php endif; ?>

            </div>

            <?php if (!empty($order['refunded_at'])): ?>

    <div class="summary-row">
        <span>Refunded By</span>
        <strong>
            <?= htmlspecialchars($order['refund_by_name'] ?? 'Admin') ?>
        </strong>
    </div>

    <div class="summary-row">
        <span>Refunded At</span>
        <strong>
            <?= date(
                'M d, Y h:i A',
                strtotime($order['refunded_at'])
            ) ?>
        </strong>
    </div>

    <div class="summary-row">
        <span>Refund Reason</span>
        <strong>
            <?= htmlspecialchars($order['refund_reason'] ?? '—') ?>
        </strong>
    </div>

<?php endif; ?>

            <?php if (!empty($order['refunded_at'])): ?>

    <div class="summary-row">
        <span>Refunded By</span>
        <strong>
            <?= htmlspecialchars($order['refund_by_name'] ?? 'Admin') ?>
        </strong>
    </div>

    <div class="summary-row">
        <span>Refunded At</span>
        <strong>
            <?= date(
                'M d, Y h:i A',
                strtotime($order['refunded_at'])
            ) ?>
        </strong>
    </div>

    <div class="summary-row">
        <span>Refund Reason</span>
        <strong>
            <?= htmlspecialchars($order['refund_reason'] ?? '—') ?>
        </strong>
    </div>

<?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>