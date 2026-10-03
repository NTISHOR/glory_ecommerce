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
   CSRF TOKEN
========================================= */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/* =========================================
   ORDER ID
========================================= */

$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$order_id) {
    header("Location: orders.php");
    exit();
}


/* =========================================
   UPDATE VENDOR ITEM FULFILMENT STATUS
========================================= */

$success_message = '';
$error_message = '';

$allowed_fulfillment_statuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)
    ) {
        $error_message = 'Invalid request. Please refresh the page and try again.';
    } else {

        $item_id = filter_input(
            INPUT_POST,
            'item_id',
            FILTER_VALIDATE_INT
        );

        $new_status = $_POST['fulfillment_status'] ?? '';

        if (
            !$item_id ||
            !in_array($new_status, $allowed_fulfillment_statuses, true)
        ) {
            $error_message = 'Invalid product or fulfilment status.';
        } else {

            /*
             * Confirm that this item belongs to:
             * 1. The current order
             * 2. The logged-in vendor
             */

            $stmt = $pdo->prepare("
                SELECT id, fulfillment_status
                FROM order_items
                WHERE id = ?
                  AND order_id = ?
                  AND vendor_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $item_id,
                $order_id,
                $vendor_id
            ]);

            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {

                $error_message = 'You are not authorized to update this product.';

            } elseif ($item['fulfillment_status'] === 'cancelled') {

                $error_message = 'A cancelled product cannot be updated.';

            } elseif ($item['fulfillment_status'] === 'delivered') {

                $error_message = 'A delivered product cannot be updated.';

            } else {

                $stmt = $pdo->prepare("
                    UPDATE order_items
                    SET fulfillment_status = ?
                    WHERE id = ?
                      AND order_id = ?
                      AND vendor_id = ?
                ");

                $stmt->execute([
                    $new_status,
                    $item_id,
                    $order_id,
                    $vendor_id
                ]);

                header(
                    "Location: order-view.php?id=" .
                    $order_id .
                    "&updated=1"
                );
                exit();
            }
        }
    }
}

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
    header("Location: orders.php");
    exit();
}

$store_name = $vendor['store_name'] ?? $vendor['full_name'];

$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';

/* =========================================
   FETCH ORDER
   Only if this vendor has products in it
========================================= */

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.customer_id,
        o.subtotal,
        o.delivery_fee,
        o.discount,
        o.total_amount,
o.payment_method,
o.payment_status,
o.order_status,
        o.delivery_address,
        o.customer_note,
        o.created_at,
        o.updated_at,

        u.full_name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone

    FROM orders o

    INNER JOIN order_items oi
        ON oi.order_id = o.id
        AND oi.vendor_id = ?

    LEFT JOIN users u
        ON u.id = o.customer_id

    WHERE o.id = ?

    LIMIT 1
");

$stmt->execute([
    $vendor_id,
    $order_id
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}


/* =========================================
   VENDOR ORDER CONFIRMATION
========================================= */

$success_message = '';
$error_message = '';

/* Check whether this vendor has confirmed */

$stmt = $pdo->prepare("
    SELECT confirmed_at
    FROM vendor_order_confirmations
    WHERE order_id = ?
      AND vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $order_id,
    $vendor_id
]);

$confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

$vendor_confirmed = (
    $confirmation &&
    !empty($confirmation['confirmed_at'])
);

/* Process confirmation */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['confirm_order'])
) {

    $submitted_token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submitted_token) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submitted_token)
    ) {

        $error_message = 'Invalid request. Please refresh the page and try again.';

    } elseif ($vendor_confirmed) {

        $error_message = 'You have already confirmed this order.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO vendor_order_confirmations
                    (order_id, vendor_id, confirmed_at)
                VALUES
                    (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    confirmed_at = COALESCE(confirmed_at, NOW())
            ");

            $stmt->execute([
                $order_id,
                $vendor_id
            ]);

            header(
                "Location: order-view.php?id=" .
                $order_id .
                "&confirmed=1"
            );

            exit();

        } catch (PDOException $e) {

            error_log(
                "Vendor order confirmation error: " .
                $e->getMessage()
            );

            $error_message = 'Unable to confirm this order. Please try again.';

        }
    }
}

/* Refresh confirmation status */

$stmt = $pdo->prepare("
    SELECT confirmed_at
    FROM vendor_order_confirmations
    WHERE order_id = ?
      AND vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $order_id,
    $vendor_id
]);

$confirmation = $stmt->fetch(PDO::FETCH_ASSOC);

$vendor_confirmed = (
    $confirmation &&
    !empty($confirmation['confirmed_at'])
);

/* =========================================
   FETCH ONLY THIS VENDOR'S ITEMS
========================================= */

$stmt = $pdo->prepare("
    SELECT
    oi.id,
    oi.product_id,
    oi.product_name,
    oi.quantity,
    oi.unit_price,
    oi.subtotal,
    oi.fulfillment_status,

    p.image

    FROM order_items oi

    LEFT JOIN products p
        ON p.id = oi.product_id

    WHERE oi.order_id = ?
      AND oi.vendor_id = ?

    ORDER BY oi.id ASC
");

$stmt->execute([
    $order_id,
    $vendor_id
]);

$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================
   CALCULATE VENDOR SUBTOTAL
========================================= */

$vendor_subtotal = 0;

foreach ($order_items as $item) {
    $vendor_subtotal += (float) $item['subtotal'];
}

/* =========================================
   VENDOR ITEM COUNT
========================================= */

$vendor_item_count = 0;

foreach ($order_items as $item) {
    $vendor_item_count += (int) $item['quantity'];
}

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

/* =========================================
   PRODUCT IMAGE HELPER
========================================= */

function productImage(string|null $image): string
{
    if (!empty($image)) {
        return '../' . ltrim($image, '/');
    }

    return '../assets/images/product-placeholder.png';
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

    <title>
        Order #<?= htmlspecialchars($order['order_number']) ?>
        - Vendor Dashboard
    </title>

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

    <?php if (isset($_GET['confirmed'])): ?>

    <div class="alert alert-success">
        Order confirmed successfully. The admin can now review your confirmation.
    </div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>

<?php endif; ?>


    <?php if (isset($_GET['updated'])): ?>

    <div class="alert alert-success">
        Product fulfilment status updated successfully.
    </div>

<?php endif; ?>

<?php if (!empty($error_message)): ?>

    <div class="alert alert-danger">
        <?= htmlspecialchars($error_message) ?>
    </div>

<?php endif; ?>

        <!-- =====================================
             TOP BAR
        ====================================== -->

        <div class="vendor-topbar">

            <div>

                <h1>
                    Order #<?= htmlspecialchars($order['order_number']) ?>
                </h1>

                <p>
                    View details of this customer order.
                </p>

            </div>

            <a
                href="orders.php"
                class="btn-secondary"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Orders
            </a>

        </div>


        <!-- =====================================
             ORDER HEADER
        ====================================== -->

        <div class="order-view-header vendor-panel">

            <div class="order-view-number">

                <div class="order-icon">
                    <i class="fa-solid fa-receipt"></i>
                </div>

                <div>

                    <h2>
                        #<?= htmlspecialchars($order['order_number']) ?>
                    </h2>

                    <p>
                        Placed
                        <?= date(
                            'M d, Y \a\t h:i A',
                            strtotime($order['created_at'])
                        ) ?>
                    </p>

                </div>

            </div>


            <div class="order-view-statuses">

                <span
                    class="order-status-badge
                    <?= htmlspecialchars(
                        paymentStatusClass($order['payment_status'])
                    ) ?>"
                >
                    Payment:
                    <?= htmlspecialchars(
                        formatStatus($order['payment_status'])
                    ) ?>
                </span>

                <span
                    class="order-status-badge
                    <?= htmlspecialchars(
                        orderStatusClass($order['order_status'])
                    ) ?>"
                >
                    Order:
                    <?= htmlspecialchars(
                        formatStatus($order['order_status'])
                    ) ?>
                </span>

            </div>

        </div>


        <!-- =====================================
             ORDER CONTENT
        ====================================== -->

        <div class="vendor-order-layout">


            <!-- =================================
                 LEFT COLUMN
            ================================== -->

            <div class="vendor-order-main">


                <!-- =================================
                     CUSTOMER INFORMATION
                ================================== -->

                <div class="vendor-panel order-information-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                <i class="fa-solid fa-user"></i>
                                Customer Information
                            </h2>

                        </div>

                    </div>


                    <div class="customer-information-grid">

                        <div class="customer-info-item">

                            <span class="info-label">
                                Customer Name
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $order['customer_name'] ?? 'Customer'
                                ) ?>
                            </strong>

                        </div>


                        <div class="customer-info-item">

                            <span class="info-label">
                                Email
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $order['customer_email'] ?? 'N/A'
                                ) ?>
                            </strong>

                        </div>


                        <div class="customer-info-item">

                            <span class="info-label">
                                Phone
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $order['customer_phone'] ?? 'N/A'
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>


                <!-- =================================
                     DELIVERY INFORMATION
                ================================== -->

                <div class="vendor-panel order-information-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                <i class="fa-solid fa-location-dot"></i>
                                Delivery Information
                            </h2>

                        </div>

                    </div>


                    <div class="delivery-address">

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $order['delivery_address'] ?? 'N/A'
                                )
                            ) ?>
                        </p>

                    </div>


                    <?php if (!empty($order['customer_note'])): ?>

                        <div class="customer-note">

                            <strong>
                                <i class="fa-solid fa-note-sticky"></i>
                                Customer Note
                            </strong>

                            <p>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $order['customer_note']
                                    )
                                ) ?>
                            </p>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- =================================
                     VENDOR PRODUCTS
                ================================== -->

                <div class="vendor-panel order-items-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                <i class="fa-solid fa-box"></i>
                                Your Products
                            </h2>

                            <p>
                                Only products belonging to your store are shown.
                            </p>

                        </div>

                    </div>


                    <div class="vendor-order-items">

                        <?php foreach ($order_items as $item): ?>

                            <div class="vendor-order-item">


                                <div class="vendor-order-product-image">

                                    <img
                                        src="<?= htmlspecialchars(
                                            productImage($item['image'])
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $item['product_name']
                                        ) ?>"
                                    >

                                </div>


                                <div class="vendor-order-product-info">

                                    <h3>
                                        <?= htmlspecialchars(
                                            $item['product_name']
                                        ) ?>
                                    </h3>

                                    <p>
                                        Product ID:
                                        <?= (int) $item['product_id'] ?>
                                    </p>

                                </div>


                                <div class="vendor-order-product-quantity">

                                    <span>Quantity</span>

                                    <strong>
                                        <?= (int) $item['quantity'] ?>
                                    </strong>

                                </div>


                                <div class="vendor-order-product-price">

                                    <span>
                                        ₦<?= number_format(
                                            (float) $item['unit_price'],
                                            2
                                        ) ?>
                                        each
                                    </span>

                                    <strong>
                                        ₦<?= number_format(
                                            (float) $item['subtotal'],
                                            2
                                        ) ?>
                                    </strong>

                                </div>

                                
                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </div>


            <!-- =================================
                 RIGHT COLUMN
            ================================== -->

            <div class="vendor-order-sidebar">


                <!-- =================================
                     VENDOR ORDER SUMMARY
                ================================== -->

                <div class="vendor-panel vendor-order-summary">

                    <div class="panel-header">

                        <div>

                            <h2>
                                Order Summary
                            </h2>

                        </div>

                    </div>


                    <div class="summary-row">

                        <span>
                            Your Items
                        </span>

                        <strong>
                            <?= $vendor_item_count ?>
                        </strong>

                    </div>


                    <div class="summary-row">

                        <span>
                            Your Subtotal
                        </span>

                        <strong>
                            ₦<?= number_format(
                                $vendor_subtotal,
                                2
                            ) ?>
                        </strong>

                    </div>


                    <div class="summary-divider"></div>


                    <div class="summary-row summary-total">

                        <span>
                            Your Total
                        </span>

                        <strong>
                            ₦<?= number_format(
                                $vendor_subtotal,
                                2
                            ) ?>
                        </strong>

                    </div>


                    <div class="vendor-order-note">

                        <i class="fa-solid fa-circle-info"></i>

                        <p>
                            This total represents only your products
                            in this order.
                        </p>

                    </div>

                </div>


                <!-- =================================
                     ORDER STATUS
                ================================== -->

                <div class="vendor-panel order-status-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                Order Status
                            </h2>

                        </div>

                    </div>


                    <div class="current-order-status">

                        <span
                            class="order-status-badge
                            <?= htmlspecialchars(
                                orderStatusClass($order['order_status'])
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                formatStatus($order['order_status'])
                            ) ?>
                        </span>

                    </div>

                    
<div class="vendor-confirmation-box">

    <h3>Vendor Confirmation</h3>

    <?php if ($vendor_confirmed): ?>

        <span class="order-status-badge status-confirmed">
            <i class="fa-solid fa-circle-check"></i>
            Confirmed
        </span>

        <p>
            You confirmed this order on
            <?= date(
                'M d, Y \a\t h:i A',
                strtotime($confirmation['confirmed_at'])
            ) ?>.
        </p>

    <?php else: ?>

        <span class="order-status-badge status-pending">
            Awaiting Confirmation
        </span>

        <p>
            Confirm this order to notify the admin that you have acknowledged it.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"
            >

            <button
                type="submit"
                name="confirm_order"
                value="1"
                class="btn-primary"
            >
                <i class="fa-solid fa-check"></i>
                Confirm Order
            </button>

        </form>

    <?php endif; ?>

</div>

                    <div class="order-payment-method">

    <span class="info-label">
        Payment Method
    </span>

    <strong>
        <?= htmlspecialchars(
            formatStatus($order['payment_method'])
        ) ?>
    </strong>

</div>

                    <p class="status-description">

                        The current order status is managed at
                        platform level.

                    </p>

                </div>


                <!-- =================================
                     ORDER DATES
                ================================== -->

                <div class="vendor-panel order-dates-panel">

                    <div class="panel-header">

                        <div>

                            <h2>
                                Order Information
                            </h2>

                        </div>

                    </div>


                    <div class="order-date-detail">

                        <span>
                            Order Number
                        </span>

                        <strong>
                            #<?= htmlspecialchars(
                                $order['order_number']
                            ) ?>
                        </strong>

                    </div>


                    <div class="order-date-detail">

                        <span>
                            Date Created
                        </span>

                        <strong>
                            <?= date(
                                'M d, Y',
                                strtotime($order['created_at'])
                            ) ?>
                        </strong>

                    </div>


                    <div class="order-date-detail">

                        <span>
                            Last Updated
                        </span>

                        <strong>
                            <?= date(
                                'M d, Y',
                                strtotime($order['updated_at'])
                            ) ?>
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>