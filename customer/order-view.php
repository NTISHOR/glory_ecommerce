<?php
session_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getDbConnection();

/* ==============================
   CUSTOMER AUTHENTICATION
============================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$customer_id = (int) $_SESSION['user_id'];

/* ==============================
   ORDER ID
============================== */
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

/* ==============================
   CART COUNT
============================== */
$cart_count = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int) ($item['quantity'] ?? 0);
    }
}

/* ==============================
   GET ORDER
============================== */
$stmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        subtotal,
        delivery_fee,
        discount,
        total_amount,
        payment_status,
        order_status,
        delivery_address,
        customer_note,
        created_at,
        updated_at
    FROM orders
    WHERE id = ?
      AND customer_id = ?
    LIMIT 1
");

$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

/* ==============================
   GET ORDER ITEMS
============================== */
$stmt = $pdo->prepare("
    SELECT
        oi.id,
        oi.product_id,
        oi.product_name,
        oi.quantity,
        oi.unit_price,
        oi.subtotal,
        p.image AS product_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");

$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   FORMAT STATUS
============================== */
$payment_status = strtolower($order['payment_status']);
$order_status = strtolower($order['order_status']);

/* ==============================
   ORDER STATUS STEPS
============================== */
$status_steps = [
    'pending' => 'Order Placed',
    'confirmed' => 'Order Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered'
];

$status_order = [
    'pending' => 1,
    'confirmed' => 2,
    'processing' => 3,
    'shipped' => 4,
    'delivered' => 5
];

$current_step = $status_order[$order_status] ?? 1;

if ($order_status === 'cancelled') {
    $current_step = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Order <?= htmlspecialchars($order['order_number']) ?> - GloryMarket
    </title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/customer-dashboard.css">
</head>

<body>

<div class="customer-dashboard">

    <!-- ==============================
         SIDEBAR
    =============================== -->
    <aside class="customer-sidebar">

        <div class="customer-logo">
            <i class="fas fa-store"></i>
            <span>GloryMarket</span>
        </div>

        <nav class="customer-nav">

            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fas fa-shopping-bag"></i>
                <span>Products</span>
            </a>

            <a href="cart.php">
                <i class="fas fa-cart-shopping"></i>
                <span>Cart</span>

                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge">
                        <?= $cart_count ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="orders.php" class="active">
                <i class="fas fa-box"></i>
                <span>My Orders</span>
            </a>

            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>

            <a href="../logout.php">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- ==============================
         MAIN CONTENT
    =============================== -->
    <main class="customer-main">

        <div class="order-view-container">

            <!-- PAGE HEADER -->
            <div class="order-view-header">

                <div>
                    <a href="orders.php" class="order-back-link">
                        <i class="fas fa-arrow-left"></i>
                        Back to My Orders
                    </a>

                    <h1>
                        Order Details
                    </h1>

                    <p>
                        Order #<?= htmlspecialchars($order['order_number']) ?>
                    </p>
                </div>

                <div class="order-header-date">
                    <i class="far fa-calendar"></i>

                    <?= date(
                        'M d, Y',
                        strtotime($order['created_at'])
                    ) ?>

                    <span>
                        <?= date(
                            'h:i A',
                            strtotime($order['created_at'])
                        ) ?>
                    </span>
                </div>

            </div>


            <!-- ==============================
                 ORDER STATUS
            =============================== -->
            <div class="order-status-panel">

                <div class="order-status-panel-header">

                    <div>
                        <h3>
                            <i class="fas fa-truck"></i>
                            Order Status
                        </h3>

                        <p>
                            Current status:
                            <strong class="order-status <?= htmlspecialchars($order_status) ?>">
                                <?= htmlspecialchars(ucfirst($order_status)) ?>
                            </strong>
                        </p>
                    </div>

                    <div class="payment-status-box">

                        <span>Payment</span>

                        <strong class="order-payment-status <?= htmlspecialchars($payment_status) ?>">
                            <?= htmlspecialchars(ucfirst($payment_status)) ?>
                        </strong>

                    </div>

                </div>


                <?php if ($order_status === 'cancelled'): ?>

                    <div class="order-cancelled-message">
                        <i class="fas fa-circle-xmark"></i>

                        <div>
                            <strong>Order Cancelled</strong>
                            <p>
                                This order has been cancelled.
                            </p>
                        </div>
                    </div>

                <?php else: ?>

                    <div class="order-progress">

                        <?php
                        $step_number = 0;

                        foreach ($status_steps as $status_key => $status_label):
                            $step_number++;

                            $completed =
                                $current_step >= $step_number;

                            $current =
                                $order_status === $status_key;
                        ?>

                            <div class="order-progress-step
                                <?= $completed ? 'completed' : '' ?>
                                <?= $current ? 'current' : '' ?>">

                                <div class="order-progress-icon">

                                    <?php if ($completed): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <span><?= $step_number ?></span>
                                    <?php endif; ?>

                                </div>

                                <span class="order-progress-label">
                                    <?= htmlspecialchars($status_label) ?>
                                </span>

                            </div>

                            <?php if ($step_number < count($status_steps)): ?>
                                <div class="order-progress-line
                                    <?= $current_step > $step_number ? 'completed' : '' ?>">
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>


            <!-- ==============================
                 ORDER CONTENT
            =============================== -->
            <div class="order-view-layout">

                <!-- ORDER ITEMS -->
                <section class="order-items-section">

                    <div class="order-section-header">
                        <div>
                            <h2>
                                <i class="fas fa-box-open"></i>
                                Items in Your Order
                            </h2>

                            <p>
                                <?= count($order_items) ?>
                                <?= count($order_items) === 1 ? 'item' : 'items' ?>
                            </p>
                        </div>
                    </div>


                    <div class="order-items-list">

                        <?php if (!empty($order_items)): ?>

                            <?php foreach ($order_items as $item): ?>

                                <?php
                                $image = $item['product_image'] ?? '';

                                if (!empty($image)) {
                                    $image_path = '../' . ltrim($image, '/');
                                } else {
                                    $image_path = '../assets/images/product-placeholder.png';
                                }
                                ?>

                                <div class="order-item-row">

                                    <div class="order-item-image">

                                        <img
                                            src="<?= htmlspecialchars($image_path) ?>"
                                            alt="<?= htmlspecialchars($item['product_name']) ?>"
                                            onerror="this.src='../assets/images/product-placeholder.png';"
                                        >

                                    </div>


                                    <div class="order-item-info">

                                        <h3>
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </h3>

                                        <p>
                                            Quantity:
                                            <strong>
                                                <?= (int) $item['quantity'] ?>
                                            </strong>
                                        </p>

                                        <span>
                                            ₦<?= number_format(
                                                (float) $item['unit_price'],
                                                2
                                            ) ?>
                                            each
                                        </span>

                                    </div>


                                    <div class="order-item-total">

                                        ₦<?= number_format(
                                            (float) $item['subtotal'],
                                            2
                                        ) ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="order-no-items">
                                <i class="fas fa-box-open"></i>

                                <p>
                                    No items were found for this order.
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>

                </section>


                <!-- ORDER SUMMARY -->
                <aside class="order-summary-section">

                    <div class="order-summary-card">

                        <h2>
                            <i class="fas fa-receipt"></i>
                            Order Summary
                        </h2>


                        <div class="order-summary-row">
                            <span>Subtotal</span>

                            <strong>
                                ₦<?= number_format(
                                    (float) $order['subtotal'],
                                    2
                                ) ?>
                            </strong>
                        </div>


                        <div class="order-summary-row">
                            <span>Delivery Fee</span>

                            <strong>
                                ₦<?= number_format(
                                    (float) $order['delivery_fee'],
                                    2
                                ) ?>
                            </strong>
                        </div>


                        <?php if ((float) $order['discount'] > 0): ?>

                            <div class="order-summary-row discount">
                                <span>Discount</span>

                                <strong>
                                    -₦<?= number_format(
                                        (float) $order['discount'],
                                        2
                                    ) ?>
                                </strong>
                            </div>

                        <?php endif; ?>


                        <div class="order-summary-total">

                            <span>Total</span>

                            <strong>
                                ₦<?= number_format(
                                    (float) $order['total_amount'],
                                    2
                                ) ?>
                            </strong>

                        </div>

                    </div>


                    <!-- DELIVERY INFORMATION -->
                    <div class="order-info-card">

                        <h2>
                            <i class="fas fa-location-dot"></i>
                            Delivery Information
                        </h2>

                        <div class="delivery-address">

                            <?= nl2br(
                                htmlspecialchars(
                                    $order['delivery_address']
                                )
                            ) ?>

                        </div>

                    </div>


                    <!-- CUSTOMER NOTE -->
                    <?php if (!empty($order['customer_note'])): ?>

                        <div class="order-info-card">

                            <h2>
                                <i class="fas fa-comment"></i>
                                Customer Note
                            </h2>

                            <p class="customer-order-note">
                                <?= nl2br(
                                    htmlspecialchars(
                                        $order['customer_note']
                                    )
                                ) ?>
                            </p>

                        </div>

                    <?php endif; ?>


                    <a href="orders.php" class="order-back-button">
                        <i class="fas fa-arrow-left"></i>
                        Back to My Orders
                    </a>

                </aside>

            </div>

        </div>

    </main>

</div>

</body>
</html>