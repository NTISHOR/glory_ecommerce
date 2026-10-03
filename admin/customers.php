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
| Fetch Customers
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

        cp.date_of_birth,
        cp.gender,
        cp.address,
        cp.city,
        cp.state,
        cp.country,
        cp.profile_picture

    FROM users u

    LEFT JOIN customer_profiles cp
        ON cp.user_id = u.id

    WHERE u.role = 'customer'

    ORDER BY u.created_at DESC
");

$stmt->execute();

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Customers - GloryMarket</title>

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


            <a href="vendors.php">

                <i class="fa-solid fa-store"></i>

                <span>Vendors</span>

            </a>


            <a href="customers.php" class="active">

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


            <a
                href="../logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                <span>Logout</span>

            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">


        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <h1>Customer Accounts</h1>

                <p>
                    Manage registered customer accounts.
                </p>

            </div>

        </div>


        <!-- CUSTOMER TABLE -->

        <div class="table-panel">


            <div class="panel-header">

                <div>

                    <h2>

                        <i class="fa-solid fa-users"></i>

                        Customers

                    </h2>

                    <p>

                        <?= count($customers) ?>
                        customer(s) registered.

                    </p>

                </div>


                <div>

                    <a
                        href="customer-create.php"
                        class="primary-btn"
                    >

                        <i class="fa-solid fa-user-plus"></i>

                        Create Customer

                    </a>
                    <a href="vendor-applications.php">
    <i class="fas fa-user-check"></i>
    <span>Vendor Applications</span>
</a>

                </div>

            </div>


            <?php if (empty($customers)): ?>

                <div class="empty-state">

                    <i class="fa-solid fa-users"></i>

                    <h3>No Customers</h3>

                    <p>
                        No customer accounts have been registered yet.
                    </p>

                </div>

            <?php else: ?>


                <div class="table-wrapper">

                    <table class="orders-table">

                        <thead>
    <tr>
        <th>Customer</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Gender</th>
        <th>Location</th>
        <th>Status</th>
        <th>Date Registered</th>
        <th>Action</th>
    </tr>
</thead>


                        <tbody>


                        <?php foreach ($customers as $customer): ?>

                            <tr>


                                <!-- CUSTOMER -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $customer['full_name']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- EMAIL -->

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['email']
                                    ) ?>

                                </td>


                                <!-- PHONE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['phone']
                                        ?: 'Not provided'
                                    ) ?>

                                </td>

<td>
    <?= !empty($customer['gender'])
        ? htmlspecialchars(
            ucwords(str_replace('_', ' ', $customer['gender']))
        )
        : 'Not provided'
    ?>
</td>
                                <!-- LOCATION -->

                                <td>

                                    <?php

                                    $locationParts = array_filter([
                                        $customer['city'],
                                        $customer['state'],
                                        $customer['country']
                                    ]);

                                    echo htmlspecialchars(
                                        !empty($locationParts)
                                            ? implode(', ', $locationParts)
                                            : 'Not provided'
                                    );

                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span class="status-badge status-<?=
                                        htmlspecialchars(
                                            $customer['status']
                                        )
                                    ?>">

                                        <?= htmlspecialchars(
                                            ucfirst(
                                                $customer['status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= htmlspecialchars(
                                        $customer['created_at']
                                    ) ?>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="customer-view.php?id=<?= (int) $customer['id'] ?>"
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