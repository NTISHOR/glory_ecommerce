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

/*
|--------------------------------------------------------------------------
| Initialize Cart
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| Update Cart
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Update Quantity
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $product_id = filter_input(
            INPUT_POST,
            'product_id',
            FILTER_VALIDATE_INT
        );

        $quantity = filter_input(
            INPUT_POST,
            'quantity',
            FILTER_VALIDATE_INT
        );

        if (!$product_id || !isset($_SESSION['cart'][$product_id])) {

            $message = "Invalid cart item.";
            $message_type = "error";

        } elseif (!$quantity || $quantity < 1) {

            $message = "Quantity must be at least 1.";
            $message_type = "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check Current Stock
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT stock, status
                FROM products
                WHERE id = ?
            ");

            $stmt->execute([$product_id]);

            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {

                unset($_SESSION['cart'][$product_id]);

                $message = "Product no longer exists.";
                $message_type = "error";

            } elseif ($product['status'] !== 'approved') {

                unset($_SESSION['cart'][$product_id]);

                $message = "This product is no longer available.";
                $message_type = "error";

            } elseif ((int)$product['stock'] <= 0) {

                unset($_SESSION['cart'][$product_id]);

                $message = "This product is out of stock.";
                $message_type = "error";

            } elseif ($quantity > (int)$product['stock']) {

                $message =
                    "Only "
                    . (int)$product['stock']
                    . " item(s) are available.";

                $message_type = "error";

            } else {

                $_SESSION['cart'][$product_id]['quantity'] =
                    $quantity;

                $message = "Cart updated successfully.";
                $message_type = "success";
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Item
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'remove') {

        $product_id = filter_input(
            INPUT_POST,
            'product_id',
            FILTER_VALIDATE_INT
        );

        if ($product_id && isset($_SESSION['cart'][$product_id])) {

            unset($_SESSION['cart'][$product_id]);

            $message = "Item removed from cart.";
            $message_type = "success";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Clear Cart
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'clear') {

        $_SESSION['cart'] = [];

        $message = "Cart cleared successfully.";
        $message_type = "success";
    }
}

/*
|--------------------------------------------------------------------------
| Refresh Cart Product Information
|--------------------------------------------------------------------------
*/

$cart_items = [];
$subtotal = 0;

foreach ($_SESSION['cart'] as $product_id => $cart_item) {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.price,
            p.image,
            p.stock,
            p.status,
            vp.store_name
        FROM products p
        LEFT JOIN vendor_profiles vp
            ON vp.user_id = p.vendor_id
        WHERE p.id = ?
    ");

    $stmt->execute([(int)$product_id]);

    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {

        unset($_SESSION['cart'][$product_id]);
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Unavailable Products
    |--------------------------------------------------------------------------
    */

    if (
        $product['status'] !== 'approved' ||
        (int)$product['stock'] <= 0
    ) {

        unset($_SESSION['cart'][$product_id]);
        continue;
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent Quantity From Exceeding Stock
    |--------------------------------------------------------------------------
    */

    $quantity = (int)$cart_item['quantity'];

    if ($quantity > (int)$product['stock']) {

        $quantity = (int)$product['stock'];

        $_SESSION['cart'][$product_id]['quantity'] =
            $quantity;
    }

    $item_total =
        (float)$product['price'] * $quantity;

    $subtotal += $item_total;

    $cart_items[] = [
        'id'         => (int)$product['id'],
        'name'       => $product['name'],
        'price'      => (float)$product['price'],
        'image'      => $product['image'],
        'stock'      => (int)$product['stock'],
        'quantity'   => $quantity,
        'item_total' => $item_total,
        'store_name' => $product['store_name']
    ];
}

/*
|--------------------------------------------------------------------------
| Cart Count
|--------------------------------------------------------------------------
*/

$cart_count = 0;

foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}

/*
|--------------------------------------------------------------------------
| Delivery Fee
|--------------------------------------------------------------------------
*/

$delivery_fee = 0;

$stmt = $pdo->prepare("
    SELECT setting_value
    FROM system_settings
    WHERE setting_key = 'delivery_fee'
    LIMIT 1
");

$stmt->execute();

$delivery_setting = $stmt->fetchColumn();

if ($delivery_setting !== false) {
    $delivery_fee = (float) $delivery_setting;
}

/*
|--------------------------------------------------------------------------
| Grand Total
|--------------------------------------------------------------------------
*/

$grand_total = $subtotal + $delivery_fee;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Cart | GloryMarket</title>

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
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fas fa-shopping-bag"></i>
                <span>Products</span>
            </a>

            <a
                href="cart.php"
                class="active"
            >
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>

                <?php if ($cart_count > 0): ?>

                    <span class="cart-badge">
                        <?= $cart_count ?>
                    </span>

                <?php endif; ?>

            </a>

            <a href="orders.php">
                <i class="fas fa-box"></i>
                <span>My Orders</span>
            </a>

            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>

            <a
                href="../logout.php"
                class="logout-link"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
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
                    My Cart
                </h1>

                <p>
                    Review your selected products before checkout.
                </p>

            </div>

        </header>


        <div class="cart-container">

            <!-- =================================================
                 MESSAGE
            ================================================== -->

            <?php if (!empty($message)): ?>

                <div
                    class="product-message <?= htmlspecialchars($message_type) ?>"
                >
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>


            <?php if (empty($cart_items)): ?>

                <!-- =================================================
                     EMPTY CART
                ================================================== -->

                <div class="empty-cart">

                    <i class="fas fa-shopping-cart"></i>

                    <h2>
                        Your cart is empty
                    </h2>

                    <p>
                        You haven't added any products to your cart yet.
                    </p>

                    <a
                        href="products.php"
                        class="continue-shopping-btn"
                    >
                        <i class="fas fa-shopping-bag"></i>
                        Continue Shopping
                    </a>

                </div>

            <?php else: ?>

                <div class="cart-layout">

                    <!-- =================================================
                         CART ITEMS
                    ================================================== -->

                    <div class="cart-items-section">

                        <div class="cart-section-header">

                            <h2>
                                Cart Items
                            </h2>

                            <span>
                                <?= $cart_count ?>
                                item(s)
                            </span>

                        </div>


                        <?php foreach ($cart_items as $item): ?>

                            <div class="cart-item">

                                <!-- Product Image -->

                                <div class="cart-item-image">

                                    <?php if (!empty($item['image'])): ?>

                                        <img
                                            src="../<?= htmlspecialchars(
                                                ltrim(
                                                    $item['image'],
                                                    '/'
                                                )
                                            ) ?>"
                                            alt="<?= htmlspecialchars(
                                                $item['name']
                                            ) ?>"
                                        >

                                    <?php else: ?>

                                        <div class="cart-no-image">
                                            <i class="fas fa-image"></i>
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- Product Details -->

                                <div class="cart-item-details">

                                    <h3>
                                        <?= htmlspecialchars(
                                            $item['name']
                                        ) ?>
                                    </h3>

                                    <p>
                                        Sold by:
                                        <strong>
                                            <?= htmlspecialchars(
                                                $item['store_name']
                                                ?? 'Unknown Vendor'
                                            ) ?>
                                        </strong>
                                    </p>

                                    <div class="cart-item-price">

                                        ₦<?= number_format(
                                            $item['price'],
                                            2
                                        ) ?>

                                    </div>

                                </div>


                                <!-- Quantity -->

                                <div class="cart-item-quantity">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="update"
                                        >

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= $item['id'] ?>"
                                        >

                                        <input
                                            type="number"
                                            name="quantity"
                                            value="<?= $item['quantity'] ?>"
                                            min="1"
                                            max="<?= $item['stock'] ?>"
                                            onchange="this.form.submit()"
                                        >

                                    </form>

                                </div>


                                <!-- Item Total -->

                                <div class="cart-item-total">

                                    ₦<?= number_format(
                                        $item['item_total'],
                                        2
                                    ) ?>

                                </div>


                                <!-- Remove -->

                                <div class="cart-item-remove">

                                    <form method="POST">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="remove"
                                        >

                                        <input
                                            type="hidden"
                                            name="product_id"
                                            value="<?= $item['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            title="Remove item"
                                            onclick="return confirm('Remove this item from your cart?');"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>

                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>


                        <!-- Clear Cart -->

                        <div class="cart-actions">

                            <a
                                href="products.php"
                                class="continue-shopping-btn"
                            >
                                <i class="fas fa-arrow-left"></i>
                                Continue Shopping
                            </a>

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="clear"
                                >

                                <button
                                    type="submit"
                                    class="clear-cart-btn"
                                    onclick="return confirm('Clear all items from your cart?');"
                                >
                                    <i class="fas fa-trash"></i>
                                    Clear Cart
                                </button>

                            </form>

                        </div>

                    </div>


                    <!-- =================================================
                         ORDER SUMMARY
                    ================================================== -->

                    <div class="cart-summary">

                        <h2>
                            Order Summary
                        </h2>

                        <div class="summary-row">

                            <span>
                                Subtotal
                            </span>

                            <strong>
                                ₦<?= number_format(
                                    $subtotal,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <div class="summary-row">

                            <span>
                                Delivery Fee
                            </span>

                            <strong>
                                ₦<?= number_format(
                                    $delivery_fee,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <hr>


                        <div class="summary-total">

                            <span>
                                Total
                            </span>

                            <strong>
                                ₦<?= number_format(
                                    $grand_total,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <a
                            href="checkout.php"
                            class="checkout-btn"
                        >
                            <i class="fas fa-lock"></i>
                            Proceed to Checkout
                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

</div>

</body>

</html>