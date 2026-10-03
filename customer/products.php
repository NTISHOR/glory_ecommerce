
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
   SEARCH AND CATEGORY FILTER
===================================== */

$search = trim($_GET['search'] ?? '');
$category_id = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);

if (!$category_id || $category_id < 1) {
    $category_id = null;
}

/* =====================================
   FETCH CATEGORIES
===================================== */

$stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================================
   FETCH PRODUCTS
===================================== */

$sql = "
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
        vp.store_name
    FROM products p
    LEFT JOIN categories c
        ON p.category_id = c.id
    LEFT JOIN users u
        ON p.vendor_id = u.id
    LEFT JOIN vendor_profiles vp
        ON vp.user_id = p.vendor_id
    WHERE p.status = 'active'
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            p.name LIKE ?
            OR p.description LIKE ?
            OR c.name LIKE ?
            OR vp.store_name LIKE ?
        )
    ";

    $search_term = "%{$search}%";

    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($category_id !== null) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Products | GloryMarket</title>

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

            <a href="dashboard.php">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php" class="active">
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
                <h1>Browse Products</h1>
                <p>Discover products from GloryMarket vendors.</p>
            </div>

            <div class="customer-profile">
                <i class="fa-solid fa-circle-user"></i>

                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Customer</small>
                </div>
            </div>

        </header>

        <!-- Search and Filters -->
        <section class="customer-panel product-search-panel">

            <form method="GET" action="products.php" class="product-filter-form">

                <div class="product-search-input">
                    <i class="fa-solid fa-search"></i>

                    <input
                        type="text"
                        name="search"
                        placeholder="Search products, categories, or vendors..."
                        value="<?= htmlspecialchars($search) ?>">
                </div>

                <select name="category">
                    <option value="">All Categories</option>

                    <?php foreach ($categories as $category): ?>

                        <option
                            value="<?= (int)$category['id'] ?>"
                            <?= $category_id === (int)$category['id'] ? 'selected' : '' ?>>

                            <?= htmlspecialchars($category['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <button type="submit" class="customer-primary-btn">
                    <i class="fa-solid fa-filter"></i>
                    Apply Filters
                </button>

                <a href="products.php" class="product-reset-btn">
                    Reset
                </a>

            </form>

        </section>

        <!-- Product Listing -->
        <section class="product-listing-section">

            <div class="customer-panel-header">
                <h2>Available Products</h2>

                <span>
                    <?= count($products) ?>
                    product(s) found
                </span>
            </div>

            <?php if (count($products) > 0): ?>

                <div class="product-grid">

                    <?php foreach ($products as $product): ?>

                        <div class="product-card">

                            <div class="product-image">

                                <?php if (!empty($product['image'])): ?>

                                    <img
                                        src="../<?= htmlspecialchars(ltrim($product['image'], '/')) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>">

                                <?php else: ?>

                                    <div class="product-no-image">
                                        <i class="fa-solid fa-image"></i>
                                        <span>No Image</span>
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="product-card-body">

                                <span class="product-category">
                                    <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                                </span>

                                <h3>
                                    <?= htmlspecialchars($product['name']) ?>
                                </h3>

                                <p class="product-vendor">
                                    <i class="fa-solid fa-store"></i>

                                    <?= htmlspecialchars(
                                        $product['store_name']
                                        ?: $product['vendor_name']
                                        ?: 'Vendor'
                                    ) ?>
                                </p>

                                <p class="product-description">
                                    <?= htmlspecialchars(
                                        mb_strimwidth(
                                            $product['description'] ?? '',
                                            0,
                                            100,
                                            '...'
                                        )
                                    ) ?>
                                </p>

                                <div class="product-card-footer">

                                    <strong class="product-price">
                                        ₦<?= number_format((float)$product['price'], 2) ?>
                                    </strong>

                                    <span class="product-stock">
                                        <?= (int)$product['stock'] > 0
                                            ? 'In Stock'
                                            : 'Out of Stock' ?>
                                    </span>

                                </div>

                                <a
                                    href="product-view.php?id=<?= (int)$product['id'] ?>"
                                    class="customer-view-btn product-view-link">

                                    View Product

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="customer-empty product-empty">

                    <i class="fa-solid fa-box-open"></i>

                    <h3>No Products Found</h3>

                    <p>
                        We couldn't find any products matching your search.
                        Try another keyword or category.
                    </p>

                    <a href="products.php">View All Products</a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>