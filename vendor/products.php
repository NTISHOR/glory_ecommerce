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
   VENDOR INFORMATION
============================== */

$stmt = $pdo->prepare("
    SELECT 
        u.full_name,
        u.email,
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
   SEARCH & FILTERS
============================== */

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$category_id = (int) ($_GET['category_id'] ?? 0);

/* ==============================
   CATEGORIES
============================== */

$stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   PRODUCTS
============================== */

$sql = "
    SELECT
        p.id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.stock,
        p.status,
        p.created_at,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.vendor_id = ?
";

$params = [$vendor_id];

if ($search !== '') {
    $sql .= " AND (
        p.name LIKE ?
        OR p.description LIKE ?
    )";

    $search_term = '%' . $search . '%';

    $params[] = $search_term;
    $params[] = $search_term;
}

if ($status !== '') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   PRODUCT COUNT
============================== */

$total_products = count($products);

/* ==============================
   VENDOR LOGO
============================== */

$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Products - Vendor Dashboard</title>

    <link rel="stylesheet" href="../assets/css/vendor-dashboard.css">

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
                    <?= htmlspecialchars($vendor['store_name'] ?: $vendor['full_name']) ?>
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
                <h1>My Products</h1>

                <p>
                    Manage the products available in your store.
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


        <!-- VERIFICATION NOTICE -->

        <?php if (($vendor['verification_status'] ?? '') === 'pending'): ?>

            <div class="vendor-alert vendor-alert-warning">
                <i class="fas fa-clock"></i>

                <div>
                    <strong>Vendor verification pending</strong>

                    <p>
                        Your vendor account is currently awaiting administrator verification.
                    </p>
                </div>
            </div>

        <?php elseif (($vendor['verification_status'] ?? '') === 'rejected'): ?>

            <div class="vendor-alert vendor-alert-danger">
                <i class="fas fa-circle-xmark"></i>

                <div>
                    <strong>Vendor application rejected</strong>

                    <p>
                        Your vendor application has been rejected.
                    </p>
                </div>
            </div>

        <?php endif; ?>


        <!-- PAGE CONTENT -->

        <div class="vendor-products-container">

            <div class="vendor-page-header">

                <div>
                    <h2>
                        Products
                        <span>(<?= $total_products ?>)</span>
                    </h2>

                    <p>
                        View and manage your products.
                    </p>
                </div>

                <a href="add-product.php" class="vendor-primary-btn">
                    <i class="fas fa-plus"></i>
                    Add Product
                </a>

            </div>


            <!-- FILTERS -->

            <div class="vendor-filter-card">

                <form method="GET" class="vendor-product-filters">

                    <div class="vendor-filter-group search-group">

                        <label for="search">
                            Search
                        </label>

                        <div class="vendor-search-box">

                            <i class="fas fa-search"></i>

                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search products..."
                            >

                        </div>

                    </div>


                    <div class="vendor-filter-group">

                        <label for="category">
                            Category
                        </label>

                        <select name="category_id" id="category">

                            <option value="">
                                All Categories
                            </option>

                            <?php foreach ($categories as $category): ?>

                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= $category_id == $category['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="vendor-filter-group">

                        <label for="status">
                            Status
                        </label>

                       <select name="status" id="status">

    <option value="">
        All Statuses
    </option>

    <option
        value="pending"
        <?= $status === 'pending' ? 'selected' : '' ?>
    >
        Pending
    </option>

    <option
        value="approved"
        <?= $status === 'approved' ? 'selected' : '' ?>
    >
        Approved
    </option>

    <option
        value="rejected"
        <?= $status === 'rejected' ? 'selected' : '' ?>
    >
        Rejected
    </option>

</select>

                    </div>


                    <div class="vendor-filter-actions">

                        <button type="submit" class="vendor-filter-btn">
                            <i class="fas fa-filter"></i>
                            Filter
                        </button>

                        <a href="products.php" class="vendor-reset-btn">
                            Reset
                        </a>

                    </div>

                </form>

            </div>


            <!-- PRODUCTS TABLE -->

            <div class="vendor-products-card">

                <?php if (!empty($products)): ?>

                    <div class="vendor-table-wrapper">

                        <table class="vendor-products-table">

                            <thead>

                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <th>Action</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($products as $product): ?>

                                    <?php

                                    $product_image = !empty($product['image'])
                                        ? '../' . ltrim($product['image'], '/')
                                        : '../assets/images/product-placeholder.png';

                                    $stock = (int) $product['stock'];

                                    ?>

                                    <tr>

                                        <!-- PRODUCT -->

                                        <td>

                                            <div class="vendor-product-info">

                                                <img
                                                    src="<?= htmlspecialchars($product_image) ?>"
                                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                                    onerror="this.src='../assets/images/product-placeholder.png';"
                                                >

                                                <div>

                                                    <strong>
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </strong>

                                                    <?php if (!empty($product['description'])): ?>

                                                        <small>
                                                            <?= htmlspecialchars(
                                                                mb_strimwidth(
                                                                    $product['description'],
                                                                    0,
                                                                    70,
                                                                    '...'
                                                                )
                                                            ) ?>
                                                        </small>

                                                    <?php endif; ?>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- CATEGORY -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $product['category_name'] ?? 'Uncategorized'
                                            ) ?>

                                        </td>


                                        <!-- PRICE -->

                                        <td>

                                            <strong class="vendor-product-price">
                                                ₦<?= number_format(
                                                    (float) $product['price'],
                                                    2
                                                ) ?>
                                            </strong>

                                        </td>


                                        <!-- STOCK -->

                                        <td>

                                            <?php if ($stock <= 0): ?>

                                                <span class="stock-badge stock-out">
                                                    Out of Stock
                                                </span>

                                            <?php elseif ($stock <= 5): ?>

                                                <span class="stock-badge stock-low">
                                                    <?= $stock ?> left
                                                </span>

                                            <?php else: ?>

                                                <span class="stock-badge stock-good">
                                                    <?= $stock ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?php
                                            $product_status = strtolower(
                                                (string) $product['status']
                                            );
                                            ?>

                                            <span class="product-status <?= htmlspecialchars($product_status) ?>">
                                                <?= htmlspecialchars(
                                                    ucfirst($product_status)
                                                ) ?>
                                            </span>

                                        </td>


                                        <!-- DATE -->

                                        <td>

                                            <span class="vendor-product-date">
                                                <?= date(
                                                    'M d, Y',
                                                    strtotime($product['created_at'])
                                                ) ?>
                                            </span>

                                            <small>
                                                <?= date(
                                                    'h:i A',
                                                    strtotime($product['created_at'])
                                                ) ?>
                                            </small>

                                        </td>


                                        <!-- ACTION -->

                                        <td>

                                            <div class="vendor-product-actions">

                                                <a
                                                    href="product-view.php?id=<?= (int) $product['id'] ?>"
                                                    class="vendor-action-btn vendor-view-btn"
                                                    title="View Product"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a
    href="product-change-request.php?id=<?= (int) $product['id'] ?>"
    class="vendor-action-btn vendor-edit-btn"
    title="Request Product Change"
>
    <i class="fas fa-file-pen"></i>
</a>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="vendor-products-empty">

                        <i class="fas fa-box-open"></i>

                        <h3>No Products Found</h3>

                        <p>
                            <?= $search !== '' || $status !== '' || $category_id > 0
                                ? 'No products match your current filters.'
                                : 'You have not added any products to your store yet.' ?>
                        </p>

                        <a href="add-product.php" class="vendor-primary-btn">
                            <i class="fas fa-plus"></i>
                            Add Your First Product
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>
</html>