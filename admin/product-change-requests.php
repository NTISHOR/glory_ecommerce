<?php
session_start();

require_once '../config/db.php';
require_once 'admin_activity.php';

$pdo = getDbConnection();

/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int) $_SESSION['user_id'];

$full_name = $_SESSION['full_name'] ?? 'Super Admin';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| FETCH PRODUCT CHANGE REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        pcr.*,

        p.name AS product_name,

        u.full_name AS vendor_name,
        u.email AS vendor_email,

        reviewer.full_name AS reviewer_name,

        current_category.name AS current_category_name,
        requested_category.name AS requested_category_name

    FROM product_change_requests pcr

    INNER JOIN products p
        ON p.id = pcr.product_id

    INNER JOIN users u
        ON u.id = pcr.vendor_id

    LEFT JOIN users reviewer
        ON reviewer.id = pcr.reviewed_by

    LEFT JOIN categories current_category
        ON current_category.id = p.category_id

    LEFT JOIN categories requested_category
        ON requested_category.id = pcr.requested_category_id

    ORDER BY
        CASE
            WHEN pcr.status = 'pending' THEN 1
            WHEN pcr.status = 'approved' THEN 2
            WHEN pcr.status = 'rejected' THEN 3
        END,
        pcr.created_at DESC
");

$stmt->execute();

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Product Change Requests';
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
        <?= htmlspecialchars($page_title) ?> | GloryMarket
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>
</head>

<body>

<div class="admin-layout">

    <aside class="admin-sidebar">

        <div class="sidebar-logo">
            Glory<span>Market</span>
        </div>

        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>

            <li>
                <a
                    href="product-change-requests.php"
                    class="active"
                >
                    <i class="fas fa-file-pen"></i>
                    <span>Product Change Requests</span>
                </a>
            </li>

            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>

            <li>
                <a href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>

            <li>
                <a href="vendors.php">
                    <i class="fas fa-store"></i>
                    <span>Vendors</span>
                </a>
            </li>

            <li>
                <a href="customers.php">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>

            <li>
                <a href="categories.php">
                    <i class="fas fa-list"></i>
                    <span>Categories</span>
                </a>
            </li>

            <li>
                <a href="admin-activity-logs.php">
                    <i class="fas fa-history"></i>
                    <span>Activity Logs</span>
                </a>
            </li>

            <li>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>

        </ul>

    </aside>

    <main class="admin-main">

        <div class="requests-container">

    <a
        href="javascript:history.back()"
        class="back-link"
    >
        ← Back
    </a>

    <div class="requests-card">

        <div class="page-header">

            <div>

                <h2>
                    Product Change Requests
                </h2>

                <p>
                    Review product changes submitted by vendors.
                </p>

            </div>

            <div class="admin-info">

                Logged in as:
                <strong>
                    <?= htmlspecialchars($full_name) ?>
                </strong>

            </div>

        </div>

        <?php if (empty($requests)): ?>

            <div class="empty-state">

                <h3>
                    No Product Change Requests
                </h3>

                <p>
                    There are currently no product change requests.
                </p>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table class="requests-table">

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Vendor
                            </th>

                            <th>
                                Requested Price
                            </th>

                            <th>
                                Requested Stock
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>

                                    <div class="product-info">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $request['product_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            Product
                                            #<?= (int) $request['product_id'] ?>
                                        </small>

                                    </div>

                                </td>

                                <td>

                                    <div class="vendor-info">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $request['vendor_name']
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars(
                                                $request['vendor_email']
                                            ) ?>
                                        </small>

                                    </div>

                                </td>

                                <td>

                                    <span class="price-value">

                                        ₦<?= number_format(
                                            (float) $request['requested_price'],
                                            2
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <span class="stock-value">

                                        <?= (int) $request['requested_stock'] ?>

                                    </span>

                                </td>

                                <td>

                                    <span
                                        class="status-badge status-<?= htmlspecialchars(
                                            $request['status']
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            ucfirst($request['status'])
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?= date(
                                        'M d, Y h:i A',
                                        strtotime(
                                            $request['created_at']
                                        )
                                    ) ?>

                                </td>

                                <td>

                                    <a
                                        href="product-change-request-view.php?id=<?= (int) $request['id'] ?>"
                                        class="review-btn"
                                    >
                                        Review
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

</body>

</html>