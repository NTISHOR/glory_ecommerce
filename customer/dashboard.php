
<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

/* =====================================
   CUSTOMER AUTHENTICATION
===================================== */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'customer'
) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Customer';

/* =====================================
   CUSTOMER STATISTICS
===================================== */

// Total orders
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders
    WHERE customer_id = ?
");
$stmt->execute([$customer_id]);
$total_orders = (int) $stmt->fetchColumn();

// Pending orders
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders
    WHERE customer_id = ?
    AND order_status = 'pending'
");
$stmt->execute([$customer_id]);
$pending_orders = (int) $stmt->fetchColumn();

/* =====================================
   RECENT ORDERS
===================================== */

$stmt = $pdo->prepare("
    SELECT
        id,
        order_number,
        total_amount,
        payment_status,
        order_status,
        created_at
    FROM orders
    WHERE customer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->execute([$customer_id]);

$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Dashboard | GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/customer-dashboard.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="customer-dashboard">

    <!-- Sidebar -->
    <aside class="customer-sidebar">

        <div class="customer-brand">
            <h2>Glory<span>Market</span></h2>
            <small>Customer Panel</small>
        </div>

        <nav class="customer-nav">

            <a href="dashboard.php" class="active">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-store"></i>
                <span>Browse Products</span>
            </a>

            <a href="cart.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>My Cart</span>
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-box"></i>
                <span>My Orders</span>
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                <span>My Profile</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>

    <!-- Main Content -->
    <main class="customer-main">

        <header class="customer-topbar">

            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($full_name) ?>!</p>
            </div>

            <div class="customer-profile">
                <i class="fa-solid fa-circle-user"></i>

                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Customer</small>
                </div>
            </div>

        </header>

        <!-- Statistics -->
        <section class="customer-stats">

            <div class="customer-stat-card">
                <div class="customer-stat-icon blue">
                    <i class="fa-solid fa-shopping-bag"></i>
                </div>

                <div>
                    <p>Total Orders</p>
                    <h2><?= number_format($total_orders) ?></h2>
                </div>
            </div>

            <div class="customer-stat-card">
                <div class="customer-stat-icon orange">
                    <i class="fa-solid fa-clock"></i>
                </div>

                <div>
                    <p>Pending Orders</p>
                    <h2><?= number_format($pending_orders) ?></h2>
                </div>
            </div>

        </section>

        <!-- Welcome Panel -->
        <section class="customer-welcome">

            <div>
                <h2>Welcome to GloryMarket!</h2>

                <p>
                    Discover products from different vendors,
                    shop with confidence, and track your purchases
                    from one convenient location.
                </p>

                <a href="products.php" class="customer-primary-btn">
                    <i class="fa-solid fa-bag-shopping"></i>
                    Start Shopping
                </a>
            </div>

            <i class="fa-solid fa-store welcome-icon"></i>

        </section>

        <!-- Recent Orders -->
        <section class="customer-panel">

            <div class="customer-panel-header">
                <h2>Recent Orders</h2>

                <a href="orders.php">View All</a>
            </div>

            <div class="customer-table-wrapper">

                <table class="customer-table">

                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Order Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($recent_orders) > 0): ?>

                        <?php foreach ($recent_orders as $order): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($order['order_number']) ?>
                                </td>

                                <td>
                                    ₦<?= number_format((float)$order['total_amount'], 2) ?>
                                </td>

                                <td>
                                    <span class="customer-status">
                                        <?= htmlspecialchars(ucfirst($order['payment_status'] ?? 'pending')) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="customer-status">
                                        <?= htmlspecialchars(ucfirst($order['order_status'] ?? 'pending')) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(date(
                                        'd M Y',
                                        strtotime($order['created_at'])
                                    )) ?>
                                </td>

                                <td>
                                    <a
                                        href="order-view.php?id=<?= (int)$order['id'] ?>"
                                        class="customer-view-btn">
                                        View
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="6" class="customer-empty">
                                You have not placed any orders yet.
                                <br><br>
                                <a href="products.php">Start shopping</a>
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