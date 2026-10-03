<?php
session_start();

require_once '../config/db.php';
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
   PRODUCT ID
============================== */

$product_id = (int) ($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header("Location: products.php");
    exit();
}

/* ==============================
   VENDOR INFORMATION
============================== */

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.phone,
        u.status,
        vp.store_name,
        vp.logo,
        vp.verification_status
    FROM users u
    LEFT JOIN vendor_profiles vp ON vp.user_id = u.id
    WHERE u.id = ?
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
   PRODUCT DETAILS
   OWNERSHIP CHECK
============================== */

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.vendor_id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.category_id,
        p.stock,
        p.status,
        p.created_at,
        p.updated_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.id = ?
      AND p.vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $vendor_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit();
}

/* ==============================
   PRODUCT IMAGE
============================== */

$product_image = !empty($product['image'])
    ? '../' . ltrim($product['image'], '/')
    : '../assets/images/product-placeholder.png';

/* ==============================
   VENDOR LOGO
============================== */

$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';

/* ==============================
   STOCK INFORMATION
============================== */

$stock = (int) $product['stock'];

if ($stock <= 0) {

    $stock_class = 'stock-out';
    $stock_text = 'Out of Stock';

} elseif ($stock <= 5) {

    $stock_class = 'stock-low';
    $stock_text = $stock . ' units remaining';

} else {

    $stock_class = 'stock-good';
    $stock_text = $stock . ' units available';
}

/* ==============================
   STATUS
============================== */

$product_status = strtolower(
    (string) $product['status']
);

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
        <?= htmlspecialchars($product['name']) ?>
        - Vendor Dashboard
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/vendor-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

</head>

<body>

<div class="vendor-dashboard">

    <!-- ==============================
         SIDEBAR
    =============================== -->

    <aside class="vendor-sidebar">

        <div class="vendor-logo">

            <h2>GloryMarket</h2>

            <span>Vendor Panel</span>

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

                <small>Vendor</small>

            </div>

        </div>


        <nav class="vendor-nav">

            <a href="dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php" class="active">
                <i class="fas fa-box"></i>
                <span>My Products</span>
            </a>

            <a href="add-product.php">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </a>

            <a href="orders.php">
                <i class="fas fa-shopping-bag"></i>
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

        <!-- TOPBAR -->

        <div class="vendor-topbar">

            <div>

                <h1>
                    Product Details
                </h1>

                <p>
                    View complete information about your product.
                </p>

            </div>


            <div class="vendor-user">

                <img
                    src="<?= htmlspecialchars($vendor_logo) ?>"
                    alt="Vendor"
                    onerror="this.src='../assets/images/vendor-default.png';"
                >

                <div>

                    <strong>
                        <?= htmlspecialchars($vendor['full_name']) ?>
                    </strong>

                    <small>Vendor</small>

                </div>

            </div>

        </div>


        <!-- PRODUCT VIEW -->

        <div class="vendor-product-view-container">

            <!-- HEADER -->

            <div class="vendor-product-view-header">

                <div>

                    <a
                        href="products.php"
                        class="vendor-product-back"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back to Products
                    </a>

                    <h2>
                        <?= htmlspecialchars($product['name']) ?>
                    </h2>

                </div>


                <div class="vendor-product-view-actions">

                    <a
                        href="edit-product.php?id=<?= (int) $product['id'] ?>"
                        class="vendor-product-edit-btn"
                    >
                        <i class="fas fa-pen"></i>
                        Edit Product
                    </a>

                </div>

            </div>


            <!-- PRODUCT MAIN CARD -->

            <div class="vendor-product-view-card">

                <!-- IMAGE -->

                <div class="vendor-product-view-image">

                    <img
                        src="<?= htmlspecialchars($product_image) ?>"
                        alt="<?= htmlspecialchars($product['name']) ?>"
                        onerror="this.src='../assets/images/product-placeholder.png';"
                    >

                </div>


                <!-- INFORMATION -->

                <div class="vendor-product-view-info">

                    <div class="vendor-product-view-title">

                        <div>

                            <span class="vendor-product-category">

                                <i class="fas fa-tag"></i>

                                <?= htmlspecialchars(
                                    $product['category_name']
                                        ?? 'Uncategorized'
                                ) ?>

                            </span>

                            <h3>
                                <?= htmlspecialchars($product['name']) ?>
                            </h3>

                        </div>


                        <span
                            class="product-status <?= htmlspecialchars($product_status) ?>"
                        >
                            <?= htmlspecialchars(
                                ucfirst($product_status)
                            ) ?>
                        </span>

                    </div>


                    <!-- PRICE -->

                    <div class="vendor-product-view-price">

                        ₦<?= number_format(
                            (float) $product['price'],
                            2
                        ) ?>

                    </div>


                    <!-- STOCK -->

                    <div class="vendor-product-stock-box">

                        <div>

                            <span>
                                Stock
                            </span>

                            <strong>
                                <?= $stock ?>
                            </strong>

                        </div>

                        <span
                            class="stock-badge <?= $stock_class ?>"
                        >
                            <?= htmlspecialchars($stock_text) ?>
                        </span>

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="vendor-product-description">

                        <h4>
                            Product Description
                        </h4>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $product['description']
                                )
                            ) ?>
                        </p>

                    </div>


                    <!-- PRODUCT INFORMATION -->

                    <div class="vendor-product-meta">

                        <div>

                            <span>
                                Product ID
                            </span>

                            <strong>
                                #<?= (int) $product['id'] ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Category
                            </span>

                            <strong>
                                <?= htmlspecialchars(
                                    $product['category_name']
                                        ?? 'Uncategorized'
                                ) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Added
                            </span>

                            <strong>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $product['created_at']
                                    )
                                ) ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                Last Updated
                            </span>

                            <strong>
                                <?= date(
                                    'M d, Y',
                                    strtotime(
                                        $product['updated_at']
                                    )
                                ) ?>
                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- STORE INFORMATION -->

            <div class="vendor-product-store-card">

                <div class="vendor-product-store-icon">

                    <i class="fas fa-store"></i>

                </div>

                <div>

                    <span>
                        Sold through
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['store_name']
                                ?: $vendor['full_name']
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>