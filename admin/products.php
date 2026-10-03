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
| Get Products
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
        p.created_at,
        u.full_name AS vendor_name
    FROM products p
    INNER JOIN users u
        ON p.vendor_id = u.id
        AND u.role = 'vendor'
    ORDER BY p.created_at DESC
");

$stmt->execute();

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Products | Glory E-commerce</title>

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

            <a href="../logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Manage Products</h1>

                <p>
                    View and manage products listed by vendors.
                </p>

            </div>

        </div>


        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>Product Catalogue</h2>

                    <p>
                        Products currently listed by vendors.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>

                        <tr>
                            <th>Product</th>
                            <th>Vendor</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Date Added</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($products)): ?>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($product['name']) ?>
                                    </strong>

                                    <?php if (!empty($product['description'])): ?>

                                        <small>
                                            <?= htmlspecialchars(
                                                mb_strimwidth(
                                                    $product['description'],
                                                    0,
                                                    60,
                                                    '...'
                                                )
                                            ) ?>
                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>
                                    <?= htmlspecialchars($product['vendor_name']) ?>
                                </td>


                                <td>
                                    ₦<?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?>
                                </td>


                                <td>
                                    <?= number_format(
                                        (int) $product['stock']
                                    ) ?>
                                </td>


                                <td>

                                    <span class="status-badge status-<?= htmlspecialchars($product['status']) ?>">

                                        <?= htmlspecialchars(
                                            ucfirst($product['status'])
                                        ) ?>

                                    </span>

                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $product['created_at']
                                    ) ?>
                                </td>


                                <td>

                                    <a
                                        href="product-view.php?id=<?= $product['id'] ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="7"
                                class="empty-state"
                            >

                                <i class="fas fa-box-open"></i>

                                <p>
                                    No products have been added yet.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>

</html>