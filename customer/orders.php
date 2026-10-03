<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

/*
|--------------------------------------------------------------------------
| Customer Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'customer'
) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Cart Count
|--------------------------------------------------------------------------
*/

$cart_count = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $cart_item) {

        $cart_count += (int) ($cart_item['quantity'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

$success_message = '';

if (
    isset($_GET['success']) &&
    $_GET['success'] === '1' &&
    !empty($_GET['order'])
) {

    $success_message =
        "Your order "
        . htmlspecialchars($_GET['order'])
        . " has been placed successfully.";
}

/*
|--------------------------------------------------------------------------
| Fetch Customer Orders
|--------------------------------------------------------------------------
*/

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
        created_at
    FROM orders
    WHERE customer_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$customer_id]);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Order Item Counts
|--------------------------------------------------------------------------
*/

$order_item_counts = [];

if (!empty($orders)) {

    $order_ids = array_column($orders, 'id');

    $placeholders = implode(
        ',',
        array_fill(0, count($order_ids), '?')
    );

    $stmt = $pdo->prepare("
        SELECT
            order_id,
            SUM(quantity) AS item_count
        FROM order_items
        WHERE order_id IN ($placeholders)
        GROUP BY order_id
    ");

    $stmt->execute($order_ids);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $order_item_counts[(int) $row['order_id']] =
            (int) $row['item_count'];
    }
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

    <title>My Orders | GloryMarket</title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <!-- Customer CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/customer-dashboard.css"
    >

</head>

<body>

<div class="customer-dashboard">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="customer-sidebar">

        <div class="customer-brand">

            <h2>
                Glory<span>Market</span>
            </h2>

            <small>Customer Panel</small>

        </div>

        <nav class="customer-nav">

            <a href="dashboard.php">

                <i class="fas fa-chart-line"></i>

                <span>
                    Dashboard
                </span>

            </a>


            <a href="products.php">

                <i class="fas fa-shopping-bag"></i>

                <span>
                    Products
                </span>

            </a>


            <a href="cart.php">

                <i class="fas fa-shopping-cart"></i>

                <span>
                    Cart
                </span>

                <?php if ($cart_count > 0): ?>

                    <span class="cart-badge">
                        <?= $cart_count ?>
                    </span>

                <?php endif; ?>

            </a>


            <a
                href="orders.php"
                class="active"
            >

                <i class="fas fa-box"></i>

                <span>
                    My Orders
                </span>

            </a>


            <a href="profile.php">

                <i class="fas fa-user"></i>

                <span>
                    My Profile
                </span>

            </a>


            <a
                href="../logout.php"
                class="logout-link"
            >

                <i class="fas fa-sign-out-alt"></i>

                <span>
                    Logout
                </span>

            </a>

        </nav>

    </aside>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->

    <main class="customer-main">

        <header class="customer-topbar">

            <div>

                <h1>
                    My Orders
                </h1>

                <p>
                    View and track your orders.
                </p>

            </div>

        </header>


        <div class="orders-container">

            <?php if (!empty($success_message)): ?>

                <div class="product-message success">

                    <i class="fas fa-circle-check"></i>

                    <?= $success_message ?>

                </div>

            <?php endif; ?>


            <div class="customer-panel">

                <div class="customer-panel-header">

                    <div>

                        <h2>
                            Order History
                        </h2>

                        <p class="orders-subtitle">
                            <?= count($orders) ?>
                            order(s)
                        </p>

                    </div>

                </div>


                <?php if (empty($orders)): ?>

                    <!-- =================================================
                         NO ORDERS
                    ================================================== -->

                    <div class="customer-empty orders-empty">

                        <i class="fas fa-box-open"></i>

                        <h3>
                            No orders yet
                        </h3>

                        <p>
                            You haven't placed any orders yet.
                        </p>

                        <a href="products.php">

                            <i class="fas fa-shopping-bag"></i>

                            Start Shopping

                        </a>

                    </div>

                <?php else: ?>

                    <!-- =================================================
                         ORDERS TABLE
                    ================================================== -->

                    <div class="customer-table-wrapper">

                        <table class="customer-table orders-table">

                            <thead>

                                <tr>

                                    <th>
                                        Order
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Items
                                    </th>

                                    <th>
                                        Total
                                    </th>

                                    <th>
                                        Payment
                                    </th>

                                    <th>
                                        Order Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($orders as $order): ?>

                                    <tr>

                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $order['order_number']
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?= date(
                                                'M d, Y',
                                                strtotime(
                                                    $order['created_at']
                                                )
                                            ) ?>

                                            <small class="order-time">

                                                <?= date(
                                                    'h:i A',
                                                    strtotime(
                                                        $order['created_at']
                                                    )
                                                ) ?>

                                            </small>

                                        </td>


                                        <td>

                                            <?= $order_item_counts[
                                                (int) $order['id']
                                            ] ?? 0 ?>

                                            item(s)

                                        </td>


                                        <td>

                                            <strong>
                                                ₦<?= number_format(
                                                    (float) $order['total_amount'],
                                                    2
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <span
                                                class="order-payment-status <?= strtolower(
                                                    $order['payment_status']
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $order['payment_status']
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span
                                                class="order-status <?= strtolower(
                                                    $order['order_status']
                                                ) ?>"
                                            >

                                                <?= htmlspecialchars(
                                                    ucfirst(
                                                        $order['order_status']
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <a
                                                href="order-view.php?id=<?= (int) $order['id'] ?>"
                                                class="customer-view-btn"
                                            >

                                                <i class="fas fa-eye"></i>

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

        </div>

    </main>

</div>

</body>

</html>