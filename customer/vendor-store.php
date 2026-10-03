
<?php
session_start();

require_once '../config/db.php';
$pdo = getDbConnection();

/* ==================================
   STORE IDENTIFICATION
================================== */

$store_slug = trim($_GET['store'] ?? '');

if ($store_slug === '') {
    http_response_code(404);
    exit('Store not found.');
}

/* ==================================
   GET VENDOR STORE
================================== */

$stmt = $pdo->prepare("
    SELECT
        u.id AS vendor_id,
        u.full_name AS vendor_name,
        u.status AS account_status,

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

    FROM vendor_profiles vp

    INNER JOIN users u
        ON u.id = vp.user_id

    WHERE vp.store_slug = ?
      AND vp.verification_status = 'verified'
      AND u.status = 'active'

    LIMIT 1
");

$stmt->execute([$store_slug]);
$store = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$store) {
    http_response_code(404);
    exit('This store is unavailable.');
}

$vendor_id = (int) $store['vendor_id'];

/* ==================================
   CART COUNT
================================== */

$cart_count = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int) ($item['quantity'] ?? 0);
    }
}

/* ==================================
   GET CATEGORIES
================================== */

$stmt = $pdo->prepare("
    SELECT DISTINCT
        c.id,
        c.name

    FROM categories c

    INNER JOIN products p
        ON p.category_id = c.id

   WHERE p.vendor_id = ?
  AND p.status = 'approved'

    ORDER BY c.name ASC
");

$stmt->execute([$vendor_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==================================
   SEARCH AND FILTERS
================================== */

$search = trim($_GET['search'] ?? '');
$category_id = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);

$where = [
    "p.vendor_id = ?",
    "p.status = 'approved'"
];

$params = [$vendor_id];

if ($search !== '') {

    $where[] = "
        (
            p.name LIKE ?
            OR p.description LIKE ?
        )
    ";

    $search_term = '%' . $search . '%';

    $params[] = $search_term;
    $params[] = $search_term;
}

if ($category_id !== false && $category_id !== null && $category_id > 0) {

    $where[] = "p.category_id = ?";

    $params[] = $category_id;
}

/* ==================================
   GET STORE PRODUCTS
================================== */

$sql = "
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.stock,
        p.category_id,
        p.created_at,

        c.name AS category_name

    FROM products p

    LEFT JOIN categories c
        ON c.id = p.category_id

    WHERE " . implode(' AND ', $where) . "

    ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==================================
   STORE IMAGE PATHS
================================== */

$store_logo = !empty($store['logo'])
    ? '../' . $store['logo']
    : '../assets/images/vendor-default.png';

$store_banner = !empty($store['banner'])
    ? '../' . $store['banner']
    : '';

/* ==================================
   STORE LOCATION
================================== */

$location_parts = array_filter([
    $store['business_address'] ?? '',
    $store['city'] ?? '',
    $store['state'] ?? '',
    $store['country'] ?? ''
]);

$store_location = implode(', ', $location_parts);

/* ==================================
   CUSTOMER LOGIN STATUS
================================== */

$is_customer = (
    isset($_SESSION['user_id']) &&
    ($_SESSION['role'] ?? '') === 'customer'
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
        <?= htmlspecialchars($store['store_name']) ?> - GloryMarket
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/customer-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        /* ==================================
           PUBLIC STORE PAGE
        ================================== */

        .public-store-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .public-store-banner {
            width: 100%;
            height: 280px;
            overflow: hidden;
            border-radius: 16px;
            background: linear-gradient(135deg, #172554, #2563eb);
            margin-bottom: -65px;
        }

        .public-store-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .public-store-header {
            position: relative;
            display: flex;
            align-items: flex-end;
            gap: 25px;
            padding: 0 30px 25px;
            flex-wrap: wrap;
        }

        .public-store-logo {
            width: 130px;
            height: 130px;
            flex-shrink: 0;
            border-radius: 16px;
            border: 5px solid #fff;
            background: #fff;
            object-fit: cover;
            box-shadow: 0 5px 20px rgba(0,0,0,.12);
        }

        .public-store-heading {
            flex: 1;
            min-width: 220px;
            padding-bottom: 8px;
        }

        .public-store-heading h1 {
            font-size: 28px;
            color: #172554;
            margin-bottom: 8px;
        }

        .public-store-heading p {
            color: #64748b;
            line-height: 1.6;
        }

        .store-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 6px 12px;
            background: #dcfce7;
            color: #166534;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .public-store-info {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin: 20px 0 30px;
        }

        .public-store-info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 18px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .public-store-info-item i {
            color: #2563eb;
            font-size: 18px;
            margin-top: 3px;
        }

        .public-store-info-item strong {
            display: block;
            color: #1e293b;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .public-store-info-item span {
            display: block;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .public-store-description {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 30px;
        }

        .public-store-description h2 {
            font-size: 18px;
            color: #172554;
            margin-bottom: 10px;
        }

        .public-store-description p {
            color: #64748b;
            line-height: 1.8;
            white-space: pre-line;
        }

        .public-store-products {
            margin-top: 30px;
        }

        .public-store-products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .public-store-products-header h2 {
            color: #172554;
            font-size: 22px;
        }

        .public-store-products-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }

        .store-filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 18px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .store-filter-form input,
        .store-filter-form select {
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
        }

        .store-filter-form input {
            flex: 1;
            min-width: 200px;
        }

        .store-filter-form select {
            min-width: 180px;
        }

        .store-filter-button {
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            background: #2563eb;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }

        .store-filter-button:hover {
            background: #1d4ed8;
        }

        .store-clear-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
        }

        .store-products-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .store-product-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            transition: transform .2s, box-shadow .2s;
        }

        .store-product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(15,23,42,.08);
        }

        .store-product-image {
            display: block;
            height: 210px;
            background: #f1f5f9;
            overflow: hidden;
        }

        .store-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .store-product-content {
            padding: 16px;
        }

        .store-product-category {
            display: inline-block;
            color: #2563eb;
            background: #eff6ff;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            margin-bottom: 10px;
        }

        .store-product-content h3 {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .store-product-content h3 a {
            color: #1e293b;
            text-decoration: none;
        }

        .store-product-content h3 a:hover {
            color: #2563eb;
        }

        .store-product-description {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
            min-height: 42px;
            margin-bottom: 12px;
        }

        .store-product-price {
            color: #172554;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .store-product-stock {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 14px;
        }

        .store-product-stock.out-of-stock {
            color: #dc2626;
        }

        .store-product-action {
            display: block;
            width: 100%;
            padding: 11px;
            text-align: center;
            background: #2563eb;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .store-product-action:hover {
            background: #1d4ed8;
        }

        .store-product-action.disabled {
            background: #94a3b8;
            pointer-events: none;
        }

        .store-empty-state {
            grid-column: 1 / -1;
            padding: 60px 20px;
            text-align: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
        }

        .store-empty-state i {
            font-size: 42px;
            color: #94a3b8;
            margin-bottom: 15px;
        }

        .store-empty-state h3 {
            color: #334155;
            margin-bottom: 8px;
        }

        .store-empty-state p {
            color: #64748b;
        }

        .store-back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 1100px) {
            .store-products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {
            .public-store-info {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .store-products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .public-store-banner {
                height: 220px;
            }

            .public-store-header {
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        @media (max-width: 550px) {
            .public-store-container {
                padding: 15px;
            }

            .public-store-banner {
                height: 160px;
                margin-bottom: -45px;
            }

            .public-store-logo {
                width: 90px;
                height: 90px;
                border-width: 3px;
            }

            .public-store-heading h1 {
                font-size: 22px;
            }

            .public-store-info {
                grid-template-columns: 1fr;
            }

            .store-products-grid {
                grid-template-columns: 1fr;
            }

            .store-product-image {
                height: 240px;
            }

            .store-filter-form {
                flex-direction: column;
            }

            .store-filter-form input,
            .store-filter-form select {
                width: 100%;
            }

            .store-filter-button,
            .store-clear-button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="customer-dashboard">

    <!-- ==================================
         SIDEBAR
    ================================== -->

    <aside class="customer-sidebar">

        <div class="customer-logo">
            <h2>GloryMarket</h2>
            <p>Online Marketplace</p>
        </div>

        <nav class="customer-nav">

            <a href="products.php">
                <i class="fa-solid fa-store"></i>
                Browse Products
            </a>

            <?php if ($is_customer): ?>

                <a href="dashboard.php">
                    <i class="fa-solid fa-gauge"></i>
                    Dashboard
                </a>

                <a href="cart.php">
                    <i class="fa-solid fa-cart-shopping"></i>
                    My Cart
                    <?php if ($cart_count > 0): ?>
                        (<?= $cart_count ?>)
                    <?php endif; ?>
                </a>

                <a href="orders.php">
                    <i class="fa-solid fa-box"></i>
                    My Orders
                </a>

                <a href="profile.php">
                    <i class="fa-solid fa-user"></i>
                    Profile
                </a>

                <a href="../logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </a>

            <?php else: ?>

                <a href="../login.php">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Login
                </a>

                <a href="../register.php">
                    <i class="fa-solid fa-user-plus"></i>
                    Create Account
                </a>

            <?php endif; ?>

        </nav>

    </aside>

    <!-- ==================================
         MAIN CONTENT
    ================================== -->

    <main class="customer-main">

        <div class="public-store-container">

            <a
                href="products.php"
                class="store-back-link"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Products
            </a>

            <!-- STORE BANNER -->

            <div class="public-store-banner">

                <?php if ($store_banner !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($store_banner) ?>"
                        alt="Store Banner"
                    >

                <?php endif; ?>

            </div>

            <!-- STORE HEADER -->

            <div class="public-store-header">

                <img
                    src="<?= htmlspecialchars($store_logo) ?>"
                    alt="Store Logo"
                    class="public-store-logo"
                >

                <div class="public-store-heading">

                    <h1>
                        <?= htmlspecialchars($store['store_name']) ?>
                    </h1>

                    <p>
                        Owned by
                        <?= htmlspecialchars($store['vendor_name']) ?>
                    </p>

                    <span class="store-verified-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        Verified Store
                    </span>

                </div>

            </div>

            <!-- STORE INFORMATION -->

            <div class="public-store-info">

                <?php if ($store_location !== ''): ?>

                    <div class="public-store-info-item">

                        <i class="fa-solid fa-location-dot"></i>

                        <div>
                            <strong>Store Location</strong>
                            <span>
                                <?= htmlspecialchars($store_location) ?>
                            </span>
                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($store['business_phone'])): ?>

                    <div class="public-store-info-item">

                        <i class="fa-solid fa-phone"></i>

                        <div>
                            <strong>Business Phone</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $store['business_phone']
                                ) ?>
                            </span>
                        </div>

                    </div>

                <?php endif; ?>

                <?php if (!empty($store['business_email'])): ?>

                    <div class="public-store-info-item">

                        <i class="fa-solid fa-envelope"></i>

                        <div>
                            <strong>Business Email</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $store['business_email']
                                ) ?>
                            </span>
                        </div>

                    </div>

                <?php endif; ?>

            </div>

            <!-- STORE DESCRIPTION -->

            <?php if (!empty($store['business_description'])): ?>

                <div class="public-store-description">

                    <h2>About This Store</h2>

                    <p>
                        <?= htmlspecialchars(
                            $store['business_description']
                        ) ?>
                    </p>

                </div>

            <?php endif; ?>

            <!-- STORE PRODUCTS -->

            <section class="public-store-products">

                <div class="public-store-products-header">

                    <div>

                        <h2>Products from this Store</h2>

                        <p>
                            <?= count($products) ?>
                            product(s) found
                        </p>

                    </div>

                </div>

                <!-- SEARCH AND FILTER -->

                <form
                    method="GET"
                    class="store-filter-form"
                >

                    <input
                        type="hidden"
                        name="store"
                        value="<?= htmlspecialchars($store_slug) ?>"
                    >

                    <input
                        type="search"
                        name="search"
                        placeholder="Search this store..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                    <select name="category">

                        <option value="">
                            All Categories
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (
                                    (int) $category_id ===
                                    (int) $category['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($category['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        class="store-filter-button"
                    >
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Search
                    </button>

                    <a
                        href="vendor-store.php?store=<?= urlencode($store_slug) ?>"
                        class="store-clear-button"
                    >
                        Clear Filters
                    </a>

                </form>

                <!-- PRODUCT GRID -->

                <div class="store-products-grid">

                    <?php if (empty($products)): ?>

                        <div class="store-empty-state">

                            <i class="fa-solid fa-box-open"></i>

                            <h3>No Products Found</h3>

                            <p>
                                This store has no products matching
                                your search.
                            </p>

                        </div>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                                $product_image = !empty($product['image'])
                                    ? '../' . $product['image']
                                    : '../assets/images/product-placeholder.png';

                                $is_out_of_stock =
                                    (int) $product['stock'] <= 0;
                            ?>

                            <div class="store-product-card">

                                <a
                                    href="product-view.php?id=<?= (int) $product['id'] ?>"
                                    class="store-product-image"
                                >

                                    <img
                                        src="<?= htmlspecialchars($product_image) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                        loading="lazy"
                                    >

                                </a>

                                <div class="store-product-content">

                                    <?php if (!empty($product['category_name'])): ?>

                                        <span class="store-product-category">
                                            <?= htmlspecialchars(
                                                $product['category_name']
                                            ) ?>
                                        </span>

                                    <?php endif; ?>

                                    <h3>

                                        <a
                                            href="product-view.php?id=<?= (int) $product['id'] ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $product['name']
                                            ) ?>
                                        </a>

                                    </h3>

                                    <p class="store-product-description">
                                        <?= htmlspecialchars(
                                            mb_strimwidth(
                                                $product['description'] ?? '',
                                                0,
                                                85,
                                                '...'
                                            )
                                        ) ?>
                                    </p>

                                    <div class="store-product-price">
                                        ₦<?= number_format(
                                            (float) $product['price'],
                                            2
                                        ) ?>
                                    </div>

                                    <div class="store-product-stock <?= $is_out_of_stock ? 'out-of-stock' : '' ?>">

                                        <?php if ($is_out_of_stock): ?>

                                            Out of Stock

                                        <?php else: ?>

                                            <?= (int) $product['stock'] ?>
                                            available

                                        <?php endif; ?>

                                    </div>

                                    <?php if ($is_out_of_stock): ?>

                                        <span class="store-product-action disabled">
                                            Out of Stock
                                        </span>

                                    <?php else: ?>

                                        <a
                                            href="product-view.php?id=<?= (int) $product['id'] ?>"
                                            class="store-product-action"
                                        >
                                            View Product
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </main>

</div>

</body>
</html>