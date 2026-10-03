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
| Get Product ID
|--------------------------------------------------------------------------
*/

$product_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$product_id) {
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Product
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.stock,
        p.status,

        c.name AS category_name,

        u.full_name AS vendor_name,

        vp.store_name,
        vp.store_slug

    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.id

    LEFT JOIN users u
        ON p.vendor_id = u.id

    LEFT JOIN vendor_profiles vp
        ON vp.user_id = p.vendor_id

    WHERE p.id = ?
      AND p.status = 'active'
");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Product Not Found
|--------------------------------------------------------------------------
*/

if (!$product) {
    header("Location: products.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Add To Cart
|--------------------------------------------------------------------------
*/

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );

    if (!$quantity || $quantity < 1) {

        $message = "Please enter a valid quantity.";
        $message_type = "error";

    } elseif ($product['stock'] <= 0) {

        $message = "This product is currently out of stock.";
        $message_type = "error";

    } elseif ($quantity > $product['stock']) {

        $message = "Only " . $product['stock'] . " item(s) are available.";
        $message_type = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Create Session Cart
        |--------------------------------------------------------------------------
        */

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        /*
        |--------------------------------------------------------------------------
        | Add Product To Cart
        |--------------------------------------------------------------------------
        */

        if (isset($_SESSION['cart'][$product_id])) {

            $new_quantity =
                $_SESSION['cart'][$product_id]['quantity'] + $quantity;

            if ($new_quantity > $product['stock']) {

                $message =
                    "You cannot add more than "
                    . $product['stock']
                    . " item(s) of this product.";

                $message_type = "error";

            } else {

                $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;

                $message = "Product added to cart.";
                $message_type = "success";
            }

        } else {

            $_SESSION['cart'][$product_id] = [
                'product_id' => (int) $product['id'],
                'name'       => $product['name'],
                'price'      => (float) $product['price'],
                'image'      => $product['image'],
                'quantity'   => $quantity,
                'vendor_id'   => null
            ];

            /*
            |--------------------------------------------------------------------------
            | Get Vendor ID
            |--------------------------------------------------------------------------
            */

            $vendor_stmt = $pdo->prepare("
                SELECT vendor_id
                FROM products
                WHERE id = ?
            ");

            $vendor_stmt->execute([$product_id]);

            $vendor_id = $vendor_stmt->fetchColumn();

            $_SESSION['cart'][$product_id]['vendor_id'] =
                $vendor_id ? (int) $vendor_id : null;

            $message = "Product added to cart.";
            $message_type = "success";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Cart Count
|--------------------------------------------------------------------------
*/

$cart_count = 0;

if (!empty($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int) $item['quantity'];
    }
}

/*
|--------------------------------------------------------------------------
| Product Image
|--------------------------------------------------------------------------
*/

$product_image = '';

if (!empty($product['image'])) {

    $product_image =
        '../' . ltrim($product['image'], '/');

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
        <?= htmlspecialchars($product['name']) ?> | GloryMarket
    </title>

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

<div class="customer-wrapper">

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

            <a
                href="products.php"
                class="active"
            >
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

        <!-- Top Bar -->

        <header class="customer-topbar">

            <div>

                <h1>
                    Product Details
                </h1>

                <p>
                    View product information and add items to your cart.
                </p>

            </div>

            <div class="customer-actions">

                <a
                    href="cart.php"
                    class="cart-link"
                >
                    <i class="fas fa-shopping-cart"></i>

                    Cart

                    <?php if ($cart_count > 0): ?>

                        <span class="cart-count">
                            <?= $cart_count ?>
                        </span>

                    <?php endif; ?>

                </a>

            </div>

        </header>


        <!-- =====================================================
             PRODUCT DETAILS
        ====================================================== -->

        <div class="product-details-container">

            <div class="product-details-card">

                <!-- Product Image -->

                <div class="product-image-section">

                    <?php if (!empty($product_image)): ?>

                        <img
                            src="<?= htmlspecialchars($product_image) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                            class="product-details-image"
                        >

                    <?php else: ?>

                        <div class="no-product-image">

                            <i class="fas fa-image"></i>

                            <span>
                                No Image Available
                            </span>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- Product Information -->

                <div class="product-information">

                    <div class="product-category">

                        <?= htmlspecialchars(
                            $product['category_name'] ?? 'Uncategorized'
                        ) ?>

                    </div>


                    <h2>
                        <?= htmlspecialchars($product['name']) ?>
                    </h2>


                    <div class="product-price">

                        ₦<?= number_format(
                            (float) $product['price'],
                            2
                        ) ?>

                    </div>


                    <!-- Vendor -->

                    <div class="product-vendor">

                        <i class="fas fa-store"></i>

                        Sold by:

                        <strong>
                            <?= htmlspecialchars(
                                $product['store_name']
                                ?? $product['vendor_name']
                                ?? 'Unknown Vendor'
                            ) ?>
                        </strong>

                    </div>


                    <!-- Stock -->

                    <div class="product-stock">

                        <?php if ((int) $product['stock'] > 0): ?>

                            <span class="in-stock">

                                <i class="fas fa-check-circle"></i>

                                <?= (int) $product['stock'] ?>
                                item(s) available

                            </span>

                        <?php else: ?>

                            <span class="out-of-stock">

                                <i class="fas fa-times-circle"></i>

                                Out of stock

                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- Description -->

                    <div class="product-description">

                        <h3>
                            Description
                        </h3>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $product['description']
                                    ?? 'No description available.'
                                )
                            ) ?>
                        </p>

                    </div>


                    <!-- Message -->

                    <?php if (!empty($message)): ?>

                        <div
                            class="product-message <?= htmlspecialchars($message_type) ?>"
                        >
                            <?= htmlspecialchars($message) ?>
                        </div>

                    <?php endif; ?>


                    <!-- Add To Cart -->

                    <?php if ((int) $product['stock'] > 0): ?>

                        <form
                            method="POST"
                            class="add-to-cart-form"
                        >

                            <label for="quantity">
                                Quantity
                            </label>

                            <div class="quantity-cart-row">

                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    value="1"
                                    min="1"
                                    max="<?= (int) $product['stock'] ?>"
                                    required
                                >

                                <button
                                    type="submit"
                                    class="add-cart-btn"
                                >
                                    <i class="fas fa-cart-plus"></i>

                                    Add to Cart
                                </button>

                            </div>

                        </form>

                    <?php else: ?>

                        <button
                            type="button"
                            class="add-cart-btn disabled"
                            disabled
                        >
                            Out of Stock
                        </button>

                    <?php endif; ?>


                    <!-- Back -->

                    <a
                        href="products.php"
                        class="back-products"
                    >
                        <i class="fas fa-arrow-left"></i>

                        Back to Products

                    </a>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>