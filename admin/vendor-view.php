<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$vendor_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$vendor_id) {
    header("Location: vendors.php");
    exit;
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Fetch Vendor
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.status,
        u.created_at,
        u.updated_at,

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
        vp.verification_status,
        vp.created_at AS profile_created_at,
        vp.updated_at AS profile_updated_at

    FROM users u

    LEFT JOIN vendor_profiles vp
        ON vp.user_id = u.id

    WHERE u.id = ?
      AND u.role = 'vendor'

    LIMIT 1
");

$stmt->execute([$vendor_id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    header("Location: vendors.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Change Vendor Account Status
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    /* ==================================
       ACCOUNT STATUS CHANGE
    ================================== */

    if ($action === 'change_status') {

        $new_status = $_POST['status'] ?? '';

        if (!in_array($new_status, ['active', 'inactive', 'suspended'], true)) {

            $error = "Invalid vendor account status.";

        } elseif ($new_status === $vendor['status']) {

            $error = "The vendor already has this status.";

        } else {

            $old_status = $vendor['status'];

            $stmt = $pdo->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
                  AND role = 'vendor'
            ");

            $stmt->execute([
                $new_status,
                $vendor_id
            ]);

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'CHANGE_VENDOR_STATUS',
                "Changed vendor {$vendor['full_name']}'s account status from {$old_status} to {$new_status}."
            );

            $success = "Vendor account status updated successfully.";
        }
    }


    /* ==================================
       VENDOR VERIFICATION CHANGE
    ================================== */

    elseif ($action === 'change_verification') {

        $new_verification_status =
            $_POST['verification_status'] ?? '';

        if (
            !in_array(
                $new_verification_status,
                ['pending', 'verified', 'rejected'],
                true
            )
        ) {

            $error = "Invalid vendor verification status.";

        } elseif (
            $new_verification_status ===
            $vendor['verification_status']
        ) {

            $error = "The vendor already has this verification status.";

        } else {

            $old_verification_status =
                $vendor['verification_status'];

            $stmt = $pdo->prepare("
                UPDATE vendor_profiles
                SET verification_status = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $new_verification_status,
                $vendor_id
            ]);

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'CHANGE_VENDOR_VERIFICATION',
                "Changed vendor {$vendor['full_name']}'s verification status from {$old_verification_status} to {$new_verification_status}."
            );

            $success =
                "Vendor verification status updated successfully.";
        }
    }


    /* ==================================
       REFRESH VENDOR DATA
    ================================== */

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            u.updated_at,
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
            vp.verification_status,
            vp.created_at AS profile_created_at,
            vp.updated_at AS profile_updated_at
        FROM users u
        LEFT JOIN vendor_profiles vp
            ON vp.user_id = u.id
        WHERE u.id = ?
          AND u.role = 'vendor'
        LIMIT 1
    ");

    $stmt->execute([$vendor_id]);

    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Count Vendor Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE vendor_id = ?
");

$stmt->execute([$vendor_id]);

$product_count = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Fetch Vendor Products
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        price,
        stock,
        status,
        created_at
    FROM products
    WHERE vendor_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$vendor_id]);

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

    <title>
        <?= htmlspecialchars(
            $vendor['store_name'] ?: $vendor['full_name']
        ) ?>
        - Vendor
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

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>GloryMarket</h2>

            <small>Super Admin Panel</small>

        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fa-solid fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fa-solid fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php" class="active">
                <i class="fa-solid fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php">
                <i class="fa-solid fa-tags"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

    <?php if (!empty($success)): ?>

    <div class="success-message">
        <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<?php if (!empty($error)): ?>

    <div class="error-message">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>

        <div class="topbar">

            <div>

                <h1>
                    <?= htmlspecialchars(
                        $vendor['store_name']
                        ?: $vendor['full_name']
                    ) ?>
                </h1>

                <p>
                    Vendor account and store information.
                </p>

            </div>

        </div>


        <!-- VENDOR ACCOUNT -->

        <div class="table-panel">

           <div class="panel-header">

    <div>

        <h2>
            <i class="fa-solid fa-user"></i>
            Vendor Account
        </h2>

    </div>

    <div>

        <a
            href="vendor-edit.php?id=<?= $vendor_id ?>"
            class="primary-btn"
        >
            <i class="fa-solid fa-pen"></i>
            Edit Vendor
        </a>

        <a
            href="vendor-password.php?id=<?= $vendor_id ?>"
            class="secondary-btn"
        >
            <i class="fa-solid fa-key"></i>
            Change Password
        </a>

    </div>

</div>


            <div class="admin-details">

                <div class="detail-item">

                    <strong>Full Name</strong>

                    <span>
                        <?= htmlspecialchars($vendor['full_name']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Email</strong>

                    <span>
                        <?= htmlspecialchars($vendor['email']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Phone</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['phone'] ?: 'Not provided'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Account Status</strong>

                    <span>

                        <span class="status-badge status-<?=
                            htmlspecialchars($vendor['status'])
                        ?>">

                            <?= htmlspecialchars(
                                ucfirst($vendor['status'])
                            ) ?>

                        </span>

                    </span>

                </div>


                <div class="detail-item">

                    <strong>Vendor ID</strong>

                    <span>
                        #<?= (int) $vendor['id'] ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Registration Date</strong>

                    <span>
                        <?= htmlspecialchars($vendor['created_at']) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- STORE INFORMATION -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-store"></i>
                        Store Information
                    </h2>

                </div>

            </div>


            <div class="admin-details">

                <div class="detail-item">

                    <strong>Store Name</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['store_name'] ?: 'Not Set'
                        ) ?>
                    </span>

                </div>



                <div class="detail-item">

                    <strong>Store Slug</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['store_slug'] ?: 'Not Set'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Business Phone</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['business_phone']
                            ?: 'Not provided'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Business Email</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['business_email']
                            ?: 'Not provided'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>City</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['city'] ?: 'Not Set'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>State</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['state'] ?: 'Not Set'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Country</strong>

                    <span>
                        <?= htmlspecialchars(
                            $vendor['country'] ?: 'Not Set'
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Verification Status</strong>

                    <span class="status-badge">

                        <?= htmlspecialchars(
                            ucfirst(
                                str_replace(
                                    '_',
                                    ' ',
                                    $vendor['verification_status']
                                    ?: 'pending'
                                )
                            )
                        ) ?>

                    </span>

                </div>

            </div>


            <div class="product-description">

                <h3>Business Address</h3>

                <p>

                    <?= nl2br(
                        htmlspecialchars(
                            $vendor['business_address']
                            ?: 'No business address provided.'
                        )
                    ) ?>

                </p>

            </div>


            <div class="product-description">

                <h3>Business Description</h3>

                <p>

                    <?= nl2br(
                        htmlspecialchars(
                            $vendor['business_description']
                            ?: 'No business description provided.'
                        )
                    ) ?>

                </p>

            </div>

                </div>


                <!-- VENDOR VERIFICATION MANAGEMENT -->

<div class="table-panel">

    <div class="panel-header">

        <div>

            <h2>
                <i class="fa-solid fa-circle-check"></i>
                Vendor Verification
            </h2>

            <p>
                Manage the verification status of this vendor.
            </p>

        </div>

    </div>

    <div class="product-description">

        <p>
            The current verification status is:
            <strong>
                <?= htmlspecialchars(
                    ucfirst($vendor['verification_status'])
                ) ?>
            </strong>
        </p>

        <form method="POST" class="status-form">

            <input
                type="hidden"
                name="action"
                value="change_verification"
            >

            <?php if ($vendor['verification_status'] !== 'pending'): ?>

                <button
                    type="submit"
                    name="verification_status"
                    value="pending"
                    class="status-action inactive-action"
                >
                    <i class="fa-solid fa-clock"></i>
                    Set Pending
                </button>

            <?php endif; ?>

            <?php if ($vendor['verification_status'] !== 'verified'): ?>

                <button
                    type="submit"
                    name="verification_status"
                    value="verified"
                    class="status-action activate-action"
                >
                    <i class="fa-solid fa-circle-check"></i>
                    Verify Vendor
                </button>

            <?php endif; ?>

            <?php if ($vendor['verification_status'] !== 'rejected'): ?>

                <button
                    type="submit"
                    name="verification_status"
                    value="rejected"
                    class="status-action suspend-action"
                >
                    <i class="fa-solid fa-circle-xmark"></i>
                    Reject Vendor
                </button>

            <?php endif; ?>

        </form>

    </div>

</div>

        <!-- ACCOUNT STATUS MANAGEMENT -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-user-gear"></i>
                        Account Status Management
                    </h2>

                    <p>
                        Manage the vendor's account status.
                    </p>

                </div>

            </div>


            <div class="product-description">

                <p>
                    The Super Admin can activate, deactivate, or suspend
                    this vendor account.
                </p>


                <form method="POST" class="status-form">
    <input type="hidden" name="action" value="change_status">

                    <?php if ($vendor['status'] !== 'active'): ?>

                        <button
                            type="submit"
                            name="status"
                            value="active"
                            class="status-action activate-action"
                        >
                            <i class="fa-solid fa-check"></i>
                            Activate
                        </button>

                    <?php endif; ?>


                    <?php if ($vendor['status'] !== 'inactive'): ?>

                        <button
                            type="submit"
                            name="status"
                            value="inactive"
                            class="status-action inactive-action"
                        >
                            <i class="fa-solid fa-pause"></i>
                            Set Inactive
                        </button>

                    <?php endif; ?>


                    <?php if ($vendor['status'] !== 'suspended'): ?>

                        <button
                            type="submit"
                            name="status"
                            value="suspended"
                            class="status-action suspend-action"
                        >
                            <i class="fa-solid fa-ban"></i>
                            Suspend
                        </button>

                    <?php endif; ?>

                </form>

            </div>

        </div>


        <!-- PRODUCT SUMMARY -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-box"></i>
                        Vendor Products
                    </h2>

                    <p>
                        <?= (int) $product_count ?>
                        product(s) belonging to this vendor.
                    </p>

                </div>

            </div>


            <?php if (empty($products)): ?>

                <div class="empty-state">

                    <i class="fa-solid fa-box-open"></i>

                    <h3>No Products</h3>

                    <p>
                        This vendor has not added any products yet.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="orders-table">

                        <thead>

                            <tr>

                                <th>Product</th>

                                <th>Price</th>

                                <th>Stock</th>

                                <th>Status</th>

                                <th>Date Added</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $product['name']
                                        ) ?>
                                    </strong>

                                </td>


                                <td>

                                    ₦<?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?>

                                </td>


                                <td>

                                    <?= (int) $product['stock'] ?>

                                </td>


                                <td>

                                    <span class="status-badge">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $product['status']
                                                )
                                            )
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
                                        href="product-view.php?id=<?= (int) $product['id'] ?>"
                                        class="view-btn"
                                    >

                                        <i class="fa-solid fa-eye"></i>

                                        View

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>


        <!-- ADMIN NOTICE -->

        <div class="product-notice">

            <i class="fa-solid fa-circle-info"></i>

            <p>

                <strong>Vendor Product Protection:</strong>

                Administrators can view vendor products, but cannot
                directly modify a vendor's product information or price
                unless the vendor has submitted an authorization request
                for the specific change.

            </p>

        </div>


        <!-- ACTIONS -->

        <div class="form-actions">

            <a
                href="vendors.php"
                class="secondary-btn"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Vendors
            </a>

        </div>

    </main>

</div>

</body>

</html>