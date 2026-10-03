<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Vendors
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

        vp.store_name,
        vp.store_slug,
        vp.city,
        vp.state,
        vp.country,
        vp.verification_status

    FROM users u

    LEFT JOIN vendor_profiles vp
        ON vp.user_id = u.id

    WHERE u.role = 'vendor'

    ORDER BY u.created_at DESC
");

$stmt->execute();

$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Vendors - GloryMarket</title>

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

        <div class="topbar">

            <div>

                <h1>Vendors</h1>

                <p>
                    Manage and review registered vendors.
                </p>

            </div>

        </div>


        <!-- VENDOR TABLE -->

        <div class="table-panel">

            <div class="panel-header">

    <div>

        <h2>Vendor Accounts</h2>

        <p>
            <?= count($vendors) ?> vendor(s) registered.
        </p>

    </div>

    <div>

        <a
            href="vendor-create.php"
            class="primary-btn"
        >
            <i class="fa-solid fa-user-plus"></i>
            Create Vendor
        </a>

    </div>

</div>

            <?php if (empty($vendors)): ?>

                <div class="empty-state">

                    <i class="fa-solid fa-store"></i>

                    <h3>No Vendors Found</h3>

                    <p>
                        There are currently no vendor accounts.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="orders-table">

                        <thead>

                            <tr>

                                <th>Vendor</th>

                                <th>Store</th>

                                <th>Location</th>

                                <th>Verification</th>

                                <th>Account Status</th>

                                <th>Date Joined</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($vendors as $vendor): ?>

                            <tr>

                                <td>

                                    <strong>
                                        <?= htmlspecialchars($vendor['full_name']) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars($vendor['email']) ?>
                                    </small>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $vendor['store_name'] ?? 'Not Set'
                                    ) ?>

                                </td>


                                <td>

                                    <?php

                                    $location = [];

                                    if (!empty($vendor['city'])) {
                                        $location[] = $vendor['city'];
                                    }

                                    if (!empty($vendor['state'])) {
                                        $location[] = $vendor['state'];
                                    }

                                    echo htmlspecialchars(
                                        !empty($location)
                                            ? implode(', ', $location)
                                            : 'Not Set'
                                    );

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    $verification =
                                        $vendor['verification_status']
                                        ?? 'pending';

                                    ?>

                                    <span class="status-badge">

                                        <?= htmlspecialchars(
                                            ucfirst(str_replace(
                                                '_',
                                                ' ',
                                                $verification
                                            ))
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <span class="status-badge status-<?=
                                        htmlspecialchars($vendor['status'])
                                    ?>">

                                        <?= htmlspecialchars(
                                            ucfirst($vendor['status'])
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $vendor['created_at']
                                    ) ?>

                                </td>


                                <td>

                                    <a
                                        href="vendor-view.php?id=<?= (int) $vendor['id'] ?>"
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

    </main>

</div>

</body>

</html>