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
| Initialize Cart
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$message = '';
$message_type = '';

/*
|--------------------------------------------------------------------------
| Get Customer Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.phone,
        cp.address,
        cp.city,
        cp.state,
        cp.country
    FROM users u
    LEFT JOIN customer_profiles cp
        ON cp.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$customer_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: ../logout.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Default Delivery Information
|--------------------------------------------------------------------------
*/

$delivery_address = trim($customer['address'] ?? '');
$city = trim($customer['city'] ?? '');
$state = trim($customer['state'] ?? '');
$country = trim($customer['country'] ?? '');
$customer_note = '';

/*
|--------------------------------------------------------------------------
| Refresh Cart
|--------------------------------------------------------------------------
*/

function getCheckoutCart(PDO $pdo): array
{
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
                p.vendor_id,
                vp.store_name
            FROM products p
            LEFT JOIN vendor_profiles vp
                ON vp.user_id = p.vendor_id
            WHERE p.id = ?
            LIMIT 1
        ");

        $stmt->execute([(int) $product_id]);

        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            unset($_SESSION['cart'][$product_id]);
            continue;
        }

        if (
            $product['status'] !== 'active' ||
            (int) $product['stock'] <= 0
        ) {
            unset($_SESSION['cart'][$product_id]);
            continue;
        }

        $quantity = (int) ($cart_item['quantity'] ?? 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        if ($quantity > (int) $product['stock']) {
            $quantity = (int) $product['stock'];

            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        }

        $item_total =
            (float) $product['price'] * $quantity;

        $subtotal += $item_total;

        $cart_items[] = [
            'id' => (int) $product['id'],
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'image' => $product['image'],
            'stock' => (int) $product['stock'],
            'quantity' => $quantity,
            'item_total' => $item_total,
            'vendor_id' => (int) $product['vendor_id'],
            'store_name' => $product['store_name']
        ];
    }

    return [
        'items' => $cart_items,
        'subtotal' => $subtotal
    ];
}

$cart_data = getCheckoutCart($pdo);

$cart_items = $cart_data['items'];
$subtotal = $cart_data['subtotal'];

if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
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
| Discount
|--------------------------------------------------------------------------
*/

$discount = 0;

/*
|--------------------------------------------------------------------------
| Grand Total
|--------------------------------------------------------------------------
*/

$grand_total = $subtotal + $delivery_fee - $discount;

/*
|--------------------------------------------------------------------------
| Place Order
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $customer_note = trim($_POST['customer_note'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validate Delivery Information
    |--------------------------------------------------------------------------
    */

    if ($delivery_address === '') {

        $message = "Please enter your delivery address.";
        $message_type = "error";

    } elseif ($city === '') {

        $message = "Please enter your city.";
        $message_type = "error";

    } elseif ($state === '') {

        $message = "Please enter your state.";
        $message_type = "error";

    } elseif ($country === '') {

        $message = "Please enter your country.";
        $message_type = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Build Full Delivery Address
        |--------------------------------------------------------------------------
        */

        $full_delivery_address =
            $delivery_address
            . ", "
            . $city
            . ", "
            . $state
            . ", "
            . $country;

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Recheck Cart Products
            |--------------------------------------------------------------------------
            */

            $order_items = [];
            $fresh_subtotal = 0;

            foreach ($_SESSION['cart'] as $product_id => $cart_item) {

                $stmt = $pdo->prepare("
                    SELECT
                        p.id,
                        p.name,
                        p.price,
                        p.stock,
                        p.status,
                        p.vendor_id
                    FROM products p
                    WHERE p.id = ?
                    FOR UPDATE
                ");

                $stmt->execute([(int) $product_id]);

                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception(
                        "One of the products in your cart no longer exists."
                    );
                }

                if ($product['status'] !== 'active') {
                    throw new Exception(
                        $product['name']
                        . " is no longer available."
                    );
                }

                $quantity = (int) ($cart_item['quantity'] ?? 1);

                if ($quantity < 1) {
                    throw new Exception(
                        "Invalid quantity for "
                        . $product['name']
                        . "."
                    );
                }

                if ((int) $product['stock'] < $quantity) {
                    throw new Exception(
                        "Only "
                        . (int) $product['stock']
                        . " unit(s) of "
                        . $product['name']
                        . " are available."
                    );
                }

                $unit_price = (float) $product['price'];

                $item_subtotal =
                    $unit_price * $quantity;

                $fresh_subtotal += $item_subtotal;

                $order_items[] = [
                    'product_id' => (int) $product['id'],
                    'vendor_id' => (int) $product['vendor_id'],
                    'product_name' => $product['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $item_subtotal
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Total
            |--------------------------------------------------------------------------
            */

            $subtotal = $fresh_subtotal;

            $grand_total =
                $subtotal
                + $delivery_fee
                - $discount;

            /*
            |--------------------------------------------------------------------------
            | Generate Order Number
            |--------------------------------------------------------------------------
            */

            do {

                $order_number =
                    'GM-'
                    . date('YmdHis')
                    . '-'
                    . random_int(1000, 9999);

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM orders
                    WHERE order_number = ?
                    LIMIT 1
                ");

                $stmt->execute([$order_number]);

                $order_exists = $stmt->fetchColumn();

            } while ($order_exists);

            /*
            |--------------------------------------------------------------------------
            | Create Order
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number,
                    customer_id,
                    subtotal,
                    delivery_fee,
                    discount,
                    total_amount,
                    payment_status,
                    order_status,
                    delivery_address,
                    customer_note
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $stmt->execute([
                $order_number,
                $customer_id,
                $subtotal,
                $delivery_fee,
                $discount,
                $grand_total,
                'pending',
                'pending',
                $full_delivery_address,
                $customer_note !== ''
                    ? $customer_note
                    : null
            ]);

            $order_id = (int) $pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | Create Order Items + Reduce Stock
            |--------------------------------------------------------------------------
            */

            foreach ($order_items as $item) {

                $stmt = $pdo->prepare("
                    INSERT INTO order_items (
                        order_id,
                        product_id,
                        vendor_id,
                        product_name,
                        quantity,
                        unit_price,
                        subtotal
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['vendor_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ]);

                /*
                |--------------------------------------------------------------------------
                | Reduce Product Stock
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET stock = stock - ?
                    WHERE id = ?
                    AND stock >= ?
                ");

                $stmt->execute([
                    $item['quantity'],
                    $item['product_id'],
                    $item['quantity']
                ]);

                if ($stmt->rowCount() !== 1) {

                    throw new Exception(
                        "Unable to update stock for "
                        . $item['product_name']
                        . "."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Complete Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | Clear Cart
            |--------------------------------------------------------------------------
            */

            $_SESSION['cart'] = [];

            /*
            |--------------------------------------------------------------------------
            | Redirect To Orders
            |--------------------------------------------------------------------------
            */

            header(
                "Location: orders.php?success=1&order="
                . urlencode($order_number)
            );

            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message =
                "Order could not be placed. "
                . $e->getMessage();

            $message_type = "error";
        }
    }
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Checkout | GloryMarket</title>

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

            <a href="cart.php">
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
                    Checkout
                </h1>

                <p>
                    Enter your delivery details and place your order.
                </p>

            </div>

        </header>


        <div class="checkout-container">

            <?php if (!empty($message)): ?>

                <div
                    class="product-message <?= htmlspecialchars($message_type) ?>"
                >
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>


            <div class="checkout-layout">

                <!-- =================================================
                     DELIVERY INFORMATION
                ================================================== -->

                <div class="checkout-form-section">

                    <div class="checkout-section-header">

                        <h2>
                            <i class="fas fa-location-dot"></i>
                            Delivery Information
                        </h2>

                        <p>
                            Where should we deliver your order?
                        </p>

                    </div>


                    <form method="POST">

                        <div class="checkout-form-group">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                value="<?= htmlspecialchars(
                                    $customer['full_name']
                                ) ?>"
                                readonly
                            >

                        </div>


                        <div class="checkout-form-row">

                            <div class="checkout-form-group">

                                <label for="email">
                                    Email
                                </label>

                                <input
                                    type="email"
                                    id="email"
                                    value="<?= htmlspecialchars(
                                        $customer['email']
                                    ) ?>"
                                    readonly
                                >

                            </div>


                            <div class="checkout-form-group">

                                <label for="phone">
                                    Phone
                                </label>

                                <input
                                    type="text"
                                    id="phone"
                                    value="<?= htmlspecialchars(
                                        $customer['phone']
                                    ) ?>"
                                    readonly
                                >

                            </div>

                        </div>


                        <div class="checkout-form-group">

                            <label for="delivery_address">
                                Delivery Address
                                <span>*</span>
                            </label>

                            <textarea
                                name="delivery_address"
                                id="delivery_address"
                                rows="4"
                                required
                                placeholder="Enter your complete delivery address"
                            ><?= htmlspecialchars(
                                $delivery_address
                            ) ?></textarea>

                        </div>


                        <div class="checkout-form-row">

                            <div class="checkout-form-group">

                                <label for="city">
                                    City
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="city"
                                    id="city"
                                    value="<?= htmlspecialchars(
                                        $city
                                    ) ?>"
                                    required
                                >

                            </div>


                            <div class="checkout-form-group">

                                <label for="state">
                                    State
                                    <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="state"
                                    id="state"
                                    value="<?= htmlspecialchars(
                                        $state
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="checkout-form-group">

                            <label for="country">
                                Country
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="country"
                                id="country"
                                value="<?= htmlspecialchars(
                                    $country ?: 'Nigeria'
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="checkout-form-group">

                            <label for="customer_note">
                                Order Note
                                <small>(Optional)</small>
                            </label>

                            <textarea
                                name="customer_note"
                                id="customer_note"
                                rows="4"
                                placeholder="Any special instructions for your order?"
                            ><?= htmlspecialchars(
                                $customer_note
                            ) ?></textarea>

                        </div>


                        <div class="checkout-form-actions">

                            <a
                                href="cart.php"
                                class="checkout-back-btn"
                            >
                                <i class="fas fa-arrow-left"></i>
                                Back to Cart
                            </a>

                            <button
                                type="submit"
                                class="place-order-btn"
                            >
                                <i class="fas fa-check-circle"></i>
                                Place Order
                            </button>

                        </div>

                    </form>

                </div>


                <!-- =================================================
                     ORDER SUMMARY
                ================================================== -->

                <div class="checkout-summary">

                    <h2>
                        Order Summary
                    </h2>


                    <div class="checkout-summary-items">

                        <?php foreach ($cart_items as $item): ?>

                            <div class="checkout-summary-item">

                                <div class="checkout-summary-image">

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

                                        <i class="fas fa-image"></i>

                                    <?php endif; ?>

                                </div>


                                <div class="checkout-summary-details">

                                    <h3>
                                        <?= htmlspecialchars(
                                            $item['name']
                                        ) ?>
                                    </h3>

                                    <p>
                                        <?= $item['quantity'] ?>
                                        ×
                                        ₦<?= number_format(
                                            $item['price'],
                                            2
                                        ) ?>
                                    </p>

                                </div>


                                <strong>
                                    ₦<?= number_format(
                                        $item['item_total'],
                                        2
                                    ) ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <div class="checkout-summary-calculation">

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


                        <?php if ($discount > 0): ?>

                            <div class="summary-row">

                                <span>
                                    Discount
                                </span>

                                <strong>
                                    -₦<?= number_format(
                                        $discount,
                                        2
                                    ) ?>
                                </strong>

                            </div>

                        <?php endif; ?>


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

                    </div>


                    <div class="checkout-secure">

                        <i class="fas fa-shield-halved"></i>

                        <div>

                            <strong>
                                Secure Checkout
                            </strong>

                            <p>
                                Your order information is securely processed.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>