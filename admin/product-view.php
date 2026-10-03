<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'], ['super_admin', 'admin'], true)
) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Product ID
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
| Get Product
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.vendor_id,
        p.name,
        p.description,
        p.price,
        p.image,
        p.stock,
        p.status,
        p.created_at,
        p.updated_at,
        u.full_name AS vendor_name,
        u.email AS vendor_email,
        u.phone AS vendor_phone
    FROM products p
    INNER JOIN users u
        ON p.vendor_id = u.id
        AND u.role = 'vendor'
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$product_id]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
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
        <?= htmlspecialchars($product['name']) ?> |
        Glory E-commerce
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>
                Glory<span>Market</span>
            </h2>

            <small>
                Super Admin Panel
            </small>

        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fas fa-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fas fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php">
                <i class="fas fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php" class="active">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>

            <a href="product-change-requests.php">
    <i class="fas fa-file-pen"></i>
    <span>Product Change Requests</span>
</a>

            <a href="categories.php">
                <i class="fas fa-list"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
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


    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Product Details</h1>

                <p>
                    View product information and vendor details.
                </p>

            </div>

            <a
                href="products.php"
                class="secondary-btn"
            >
                <i class="fas fa-arrow-left"></i>
                Back to Products
            </a>

        </div>


        <div class="product-details-layout">

            <!-- Product Information -->

            <div class="table-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Product Information
                        </h2>

                    </div>

                </div>


                <?php if (!empty($product['image'])): ?>

                    <div class="product-image-container">

                        <img
                            src="../uploads/products/<?= htmlspecialchars($product['image']) ?>"
                            alt="<?= htmlspecialchars($product['name']) ?>"
                            class="product-detail-image"
                        >

                    </div>

                <?php endif; ?>


                <div class="admin-details">

                    <div class="detail-item">

                        <strong>
                            Product Name
                        </strong>

                        <span>
                            <?= htmlspecialchars($product['name']) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Price
                        </strong>

                        <span>
                            ₦<?= number_format(
                                (float) $product['price'],
                                2
                            ) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Stock
                        </strong>

                        <span>
                            <?= number_format(
                                (int) $product['stock']
                            ) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Status
                        </strong>

                        <span>

                            <span class="status-badge status-<?= htmlspecialchars($product['status']) ?>">

                                <?= htmlspecialchars(
                                    ucfirst($product['status'])
                                ) ?>

                            </span>

                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Date Created
                        </strong>

                        <span>
                            <?= htmlspecialchars($product['created_at']) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Last Updated
                        </strong>

                        <span>
                            <?= htmlspecialchars($product['updated_at']) ?>
                        </span>

                    </div>

                </div>


                <div class="product-description">

                    <h3>
                        Description
                    </h3>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $product['description'] ?? 'No description provided.'
                            )
                        ) ?>
                    </p>

                </div>

            </div>


            <!-- Vendor Information -->

            <div class="table-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            Vendor Information
                        </h2>

                    </div>

                </div>


                <div class="admin-details">

                    <div class="detail-item">

                        <strong>
                            Vendor Name
                        </strong>

                        <span>
                            <?= htmlspecialchars($product['vendor_name']) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Email
                        </strong>

                        <span>
                            <?= htmlspecialchars($product['vendor_email']) ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <strong>
                            Phone
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $product['vendor_phone'] ?? '-'
                            ) ?>
                        </span>

                    </div>

                </div>


                <div class="product-notice">

                    <i class="fas fa-shield-alt"></i>

                    <p>
                        Product information is controlled by the vendor.
                        Administrators cannot directly change the product
                        or price without vendor authorization.
                    </p>

                </div>

            </div>

        </div>

    </main>

</div>

</body>

</html>