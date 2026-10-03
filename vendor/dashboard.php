<?php
session_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getDbConnection();

/* ==============================
   VENDOR AUTHENTICATION
============================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

/* ==============================
   GET VENDOR INFORMATION
============================== */
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.status,
        vp.store_name,
        vp.store_slug,
        vp.business_description,
        vp.business_phone,
        vp.business_email,
        vp.business_address,
        vp.city,
        vp.state,
        vp.country,
        vp.logo,
        vp.banner,
        vp.verification_status
    FROM users u
    LEFT JOIN vendor_profiles vp
        ON vp.user_id = u.id
    WHERE u.id = ?
      AND u.role = 'vendor'
    LIMIT 1
");

$stmt->execute([$vendor_id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

/* ==============================
   VENDOR ACCESS
============================== */
$verification_status = strtolower(
    $vendor['verification_status'] ?? 'pending'
);

if ($verification_status !== 'verified') {
    $dashboard_message = true;
} else {
    $dashboard_message = false;
}


/* ==============================
   PRODUCT STATISTICS
============================== */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE vendor_id = ?
");

$stmt->execute([$vendor_id]);

$total_products = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE vendor_id = ?
      AND status = 'active'
");

$stmt->execute([$vendor_id]);

$active_products = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE vendor_id = ?
      AND stock <= 5
");

$stmt->execute([$vendor_id]);

$low_stock = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE vendor_id = ?
      AND stock = 0
");

$stmt->execute([$vendor_id]);

$out_of_stock = (int) $stmt->fetchColumn();


/* ==============================
   ORDER STATISTICS
============================== */

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id)
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE oi.vendor_id = ?
");

$stmt->execute([$vendor_id]);

$total_orders = (int) $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT oi.order_id)
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE oi.vendor_id = ?
      AND o.order_status = 'pending'
");

$stmt->execute([$vendor_id]);

$pending_orders = (int) $stmt->fetchColumn();


/* ==============================
   VENDOR SALES
============================== */

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.subtotal), 0)
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE oi.vendor_id = ?
      AND o.payment_status = 'paid'
      AND o.order_status != 'cancelled'
");

$stmt->execute([$vendor_id]);

$total_sales = (float) $stmt->fetchColumn();


/* ==============================
   RECENT PRODUCTS
============================== */

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.price,
        p.stock,
        p.status,
        p.image,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.vendor_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");

$stmt->execute([$vendor_id]);

$recent_products = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==============================
   RECENT ORDERS
============================== */

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.full_name AS customer_name
    FROM orders o
    INNER JOIN order_items oi
        ON oi.order_id = o.id
    INNER JOIN users u
        ON u.id = o.customer_id
    WHERE oi.vendor_id = ?
    GROUP BY
        o.id,
        o.order_number,
        o.total_amount,
        o.payment_status,
        o.order_status,
        o.created_at,
        u.full_name
    ORDER BY o.created_at DESC
    LIMIT 5
");

$stmt->execute([$vendor_id]);

$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==============================
   VENDOR LOGO
============================== */

if (!empty($vendor['logo'])) {
    $vendor_logo = '../' . ltrim($vendor['logo'], '/');
} else {
    $vendor_logo = '../assets/images/vendor-default.png';
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
        Vendor Dashboard - GloryMarket
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/vendor-dashboard.css"
    >

</head>

<body>

<div class="vendor-dashboard">

    <!-- ==============================
         SIDEBAR
    =============================== -->

    <aside class="vendor-sidebar">

        <div class="vendor-logo">

            <i class="fas fa-store"></i>

            <span>
                GloryMarket
            </span>

        </div>


        <div class="vendor-store-mini">

            <img
                src="<?= htmlspecialchars($vendor_logo) ?>"
                alt="Store Logo"
                onerror="this.src='../assets/images/vendor-default.png';"
            >

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $vendor['store_name']
                        ?: $vendor['full_name']
                    ) ?>
                </strong>

                <small>
                    Vendor
                </small>

            </div>

        </div>


        <nav class="vendor-nav">

            <a
                href="dashboard.php"
                class="active"
            >
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>


            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>My Products</span>
            </a>


            <a href="add-product.php">
                <i class="fas fa-plus"></i>
                <span>Add Product</span>
            </a>


            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>


            <a href="sales.php">
                <i class="fas fa-chart-column"></i>
                <span>Sales</span>
            </a>


            <a href="store.php">
                <i class="fas fa-store"></i>
                <span>My Store</span>
            </a>


            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>


            <a href="security.php">
                <i class="fas fa-shield-halved"></i>
                <span>Security</span>
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

    <main class="vendor-main">

        <!-- TOP BAR -->

        <header class="vendor-topbar">

            <div>

                <h1>
                    Vendor Dashboard
                </h1>

                <p>
                    Welcome back,
                    <?= htmlspecialchars($vendor['full_name']) ?>.
                </p>

            </div>


            <div class="vendor-topbar-user">

                <img
                    src="<?= htmlspecialchars($vendor_logo) ?>"
                    alt="Vendor"
                    onerror="this.src='../assets/images/vendor-default.png';"
                >

                <div>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['store_name']
                            ?: $vendor['full_name']
                        ) ?>
                    </strong>

                    <span>
                        Vendor
                    </span>

                </div>

            </div>

        </header>


        <!-- ==============================
             VERIFICATION MESSAGE
        =============================== -->

        <?php if ($verification_status === 'pending'): ?>

            <div class="vendor-alert pending">

                <i class="fas fa-clock"></i>

                <div>

                    <strong>
                        Store Verification Pending
                    </strong>

                    <p>
                        Your vendor application is waiting for admin approval.
                        You can prepare your store information and products
                        while your account is being reviewed.
                    </p>

                </div>

            </div>

        <?php elseif ($verification_status === 'rejected'): ?>

            <div class="vendor-alert rejected">

                <i class="fas fa-circle-xmark"></i>

                <div>

                    <strong>
                        Vendor Application Rejected
                    </strong>

                    <p>
                        Your vendor application has not been approved.
                        Please contact the administrator for more information.
                    </p>

                </div>

            </div>

        <?php elseif ($verification_status === 'verified'): ?>

            <div class="vendor-alert verified">

                <i class="fas fa-circle-check"></i>

                <div>

                    <strong>
                        Store Verified
                    </strong>

                    <p>
                        Your vendor account has been verified and your store
                        is ready to operate.
                    </p>

                </div>

            </div>

        <?php endif; ?>


        <!-- ==============================
             STATISTICS
        =============================== -->

        <section class="vendor-stats">

            <div class="vendor-stat-card">

                <div class="vendor-stat-icon blue">
                    <i class="fas fa-box"></i>
                </div>

                <div>

                    <span>
                        Total Products
                    </span>

                    <strong>
                        <?= number_format($total_products) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="vendor-stat-icon green">
                    <i class="fas fa-circle-check"></i>
                </div>

                <div>

                    <span>
                        Active Products
                    </span>

                    <strong>
                        <?= number_format($active_products) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="vendor-stat-icon orange">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>

                <div>

                    <span>
                        Low Stock
                    </span>

                    <strong>
                        <?= number_format($low_stock) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="vendor-stat-icon red">
                    <i class="fas fa-box-open"></i>
                </div>

                <div>

                    <span>
                        Out of Stock
                    </span>

                    <strong>
                        <?= number_format($out_of_stock) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="vendor-stat-icon purple">
                    <i class="fas fa-shopping-cart"></i>
                </div>

                <div>

                    <span>
                        Orders
                    </span>

                    <strong>
                        <?= number_format($total_orders) ?>
                    </strong>

                </div>

            </div>


            <div class="vendor-stat-card">

                <div class="vendor-stat-icon teal">
                    <i class="fas fa-naira-sign"></i>
                </div>

                <div>

                    <span>
                        Total Sales
                    </span>

                    <strong>
                        ₦<?= number_format($total_sales, 2) ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- ==============================
             DASHBOARD GRID
        =============================== -->

        <div class="vendor-dashboard-grid">

            <!-- RECENT PRODUCTS -->

            <section class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            My Recent Products
                        </h2>

                        <p>
                            Recently added products.
                        </p>

                    </div>

                    <a href="products.php">
                        View All
                    </a>

                </div>


                <div class="vendor-products-list">

                    <?php if (!empty($recent_products)): ?>

                        <?php foreach ($recent_products as $product): ?>

                            <?php

                            if (!empty($product['image'])) {
                                $product_image =
                                    '../' . ltrim(
                                        $product['image'],
                                        '/'
                                    );
                            } else {
                                $product_image =
                                    '../assets/images/product-placeholder.png';
                            }

                            $stock_class = '';

                            if ((int) $product['stock'] === 0) {
                                $stock_class = 'out';
                            } elseif ((int) $product['stock'] <= 5) {
                                $stock_class = 'low';
                            } else {
                                $stock_class = 'good';
                            }

                            ?>

                            <div class="vendor-product-row">

                                <img
                                    src="<?= htmlspecialchars($product_image) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                    onerror="this.src='../assets/images/product-placeholder.png';"
                                >

                                <div class="vendor-product-info">

                                    <strong>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $product['category_name']
                                            ?: 'Uncategorized'
                                        ) ?>
                                    </span>

                                </div>

                                <div class="vendor-product-price">

                                    ₦<?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?>

                                </div>

                                <div class="vendor-stock <?= $stock_class ?>">

                                    <?= (int) $product['stock'] ?>
                                    in stock

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="vendor-empty-state">

                            <i class="fas fa-box-open"></i>

                            <p>
                                You have not added any products yet.
                            </p>

                            <a href="add-product.php">
                                Add Your First Product
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <!-- RECENT ORDERS -->

            <section class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            Recent Orders
                        </h2>

                        <p>
                            Latest orders containing your products.
                        </p>

                    </div>

                    <a href="orders.php">
                        View All
                    </a>

                </div>


                <div class="vendor-orders-list">

                    <?php if (!empty($recent_orders)): ?>

                        <?php foreach ($recent_orders as $order): ?>

                            <div class="vendor-order-row">

                                <div class="vendor-order-icon">
                                    <i class="fas fa-receipt"></i>
                                </div>

                                <div class="vendor-order-info">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $order['order_number']
                                        ) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars(
                                            $order['customer_name']
                                        ) ?>
                                    </span>

                                </div>

                                <div class="vendor-order-total">

                                    ₦<?= number_format(
                                        (float) $order['total_amount'],
                                        2
                                    ) ?>

                                </div>

                                <span class="vendor-order-status
                                    <?= htmlspecialchars(
                                        strtolower(
                                            $order['order_status']
                                        )
                                    ) ?>">

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $order['order_status']
                                        )
                                    ) ?>

                                </span>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="vendor-empty-state">

                            <i class="fas fa-shopping-cart"></i>

                            <p>
                                No orders yet.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>


        <!-- ==============================
             STORE INFORMATION
        =============================== -->

        <section class="vendor-panel vendor-store-overview">

            <div class="vendor-panel-header">

                <div>

                    <h2>
                        Store Overview
                    </h2>

                    <p>
                        Your current vendor account information.
                    </p>

                </div>

                <a href="store.php">
                    Manage Store
                </a>

            </div>


            <div class="vendor-store-details">

                <div>

                    <span>
                        Store Name
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['store_name']
                            ?: 'Not set'
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Verification
                    </span>

                    <strong class="verification
                        <?= htmlspecialchars($verification_status) ?>">

                        <?= htmlspecialchars(
                            ucfirst($verification_status)
                        ) ?>

                    </strong>

                </div>


                <div>

                    <span>
                        Business Email
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['business_email']
                            ?: $vendor['email']
                        ) ?>
                    </strong>

                </div>


                <div>

                    <span>
                        Business Phone
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['business_phone']
                            ?: $vendor['phone']
                            ?: 'Not set'
                        ) ?>
                    </strong>

                </div>

            </div>

        </section>

    </main>

</div>

</body>
</html>