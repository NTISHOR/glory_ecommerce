<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

/* ==============================
   SUPER ADMIN ACCESS
============================== */

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

/* ==============================
   GET CUSTOMER ID
============================== */

$customer_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$customer_id) {
    header("Location: customers.php");
    exit;
}

/* ==============================
   FETCH CUSTOMER
============================== */

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.role,
        u.status,
        u.created_at,
        u.updated_at,

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

    WHERE u.id = ?
      AND u.role = 'customer'

    LIMIT 1
");

$stmt->execute([$customer_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers.php");
    exit;
}

/* ==============================
   HANDLE ACCOUNT STATUS
============================== */

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $new_status = $_POST['status'] ?? '';

    if ($action !== 'change_status') {

        $error = "Invalid action.";

    } elseif (
        !in_array(
            $new_status,
            ['active', 'inactive', 'suspended'],
            true
        )
    ) {

        $error = "Invalid account status.";

    } elseif ($new_status === $customer['status']) {

        $error = "The customer already has this status.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
                  AND role = 'customer'
            ");

            $stmt->execute([
                $new_status,
                $customer_id
            ]);

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'CHANGE_CUSTOMER_STATUS',
                "Changed {$customer['full_name']}'s status from {$customer['status']} to {$new_status}."
            );

            $success = "Customer account status updated successfully.";

            /* REFRESH CUSTOMER DATA */

            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.role,
                    u.status,
                    u.created_at,
                    u.updated_at,

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

                WHERE u.id = ?
                  AND u.role = 'customer'

                LIMIT 1
            ");

            $stmt->execute([$customer_id]);

            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {

            $error = "Unable to update customer status. Please try again.";

        }
    }
}

/* ==============================
   CUSTOMER LOCATION
============================== */

$location_parts = array_filter([
    $customer['address'] ?? '',
    $customer['city'] ?? '',
    $customer['state'] ?? '',
    $customer['country'] ?? ''
]);

$location = !empty($location_parts)
    ? implode(', ', $location_parts)
    : 'Not provided';

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
        View Customer - GloryMarket
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
                <i class="fa-solid fa-list"></i>
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
                <i class="fa-solid fa-chart-line"></i>
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

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>

                <h1>Customer Details</h1>

                <p>
                    View and manage customer account information.
                </p>

            </div>

        </div>


        <!-- MESSAGES -->

      <?php if (isset($_GET['success']) && $_GET['success'] === 'password'): ?>

    <div class="success-message">
        Customer password reset successfully.
    </div>

<?php elseif (isset($_GET['success']) && $_GET['success'] === 'updated'): ?>

    <div class="success-message">
        Customer information updated successfully.
    </div>

<?php elseif (!empty($success)): ?>

    <div class="success-message">
        <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'has_orders'): ?>

    <div class="error-message">
        This customer has existing orders and cannot be permanently deleted.
        Please deactivate the account instead to preserve order history.
    </div>

<?php elseif (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>

    <div class="error-message">
        Customer deletion failed. Please try again or check the server error log.
    </div>

<?php endif; ?>

        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- CUSTOMER ACCOUNT -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-user"></i>
                        Customer Account
                    </h2>

                    <p>
                        Personal and account information.
                    </p>

                </div>

                <div>

                    <a
                        href="customer-edit.php?id=<?= (int) $customer_id ?>"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit Customer
                    </a>

                    <a
                        href="customer-password.php?id=<?= (int) $customer_id ?>"
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
                        <?= htmlspecialchars($customer['full_name']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Email Address</strong>

                    <span>
                        <?= htmlspecialchars($customer['email']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Phone Number</strong>

                    <span>
                        <?= !empty($customer['phone'])
                            ? htmlspecialchars($customer['phone'])
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Customer ID</strong>

                    <span>
                        <?= (int) $customer['id'] ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Account Role</strong>

                    <span>Customer</span>

                </div>


                <div class="detail-item">

                    <strong>Account Status</strong>

                    <span class="status-badge status-<?= htmlspecialchars($customer['status']) ?>">
                        <?= htmlspecialchars(ucfirst($customer['status'])) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Date Registered</strong>

                    <span>
                        <?= date(
                            'M d, Y h:i A',
                            strtotime($customer['created_at'])
                        ) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Last Updated</strong>

                    <span>
                        <?= date(
                            'M d, Y h:i A',
                            strtotime($customer['updated_at'])
                        ) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- CUSTOMER PROFILE -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-id-card"></i>
                        Customer Profile
                    </h2>

                    <p>
                        Personal details and location information.
                    </p>

                </div>

            </div>


            <div class="admin-details">

                <div class="detail-item">

                    <strong>Date of Birth</strong>

                    <span>
                        <?= !empty($customer['date_of_birth'])
                            ? date(
                                'M d, Y',
                                strtotime($customer['date_of_birth'])
                            )
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Gender</strong>

                    <span>
                        <?= !empty($customer['gender'])
                            ? htmlspecialchars(
                                ucwords(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $customer['gender']
                                    )
                                )
                            )
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Address</strong>

                    <span>
                        <?= !empty($customer['address'])
                            ? htmlspecialchars($customer['address'])
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>City</strong>

                    <span>
                        <?= !empty($customer['city'])
                            ? htmlspecialchars($customer['city'])
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>State</strong>

                    <span>
                        <?= !empty($customer['state'])
                            ? htmlspecialchars($customer['state'])
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Country</strong>

                    <span>
                        <?= !empty($customer['country'])
                            ? htmlspecialchars($customer['country'])
                            : 'Not provided'
                        ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Complete Location</strong>

                    <span>
                        <?= htmlspecialchars($location) ?>
                    </span>

                </div>

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
                        Manage the customer's access to GloryMarket.
                    </p>

                </div>

            </div>


           
<form method="POST" class="status-form">

    <input
        type="hidden"
        name="action"
        value="change_status"
    >

    <?php if ($customer['status'] !== 'active'): ?>

        <button
            type="submit"
            name="status"
            value="active"
            class="status-action activate-action"
        >
            <i class="fa-solid fa-check"></i>
            Activate Customer
        </button>

    <?php endif; ?>

    <?php if ($customer['status'] !== 'inactive'): ?>

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

    <?php if ($customer['status'] !== 'suspended'): ?>

        <button
            type="submit"
            name="status"
            value="suspended"
            class="status-action suspend-action"
        >
            <i class="fa-solid fa-ban"></i>
            Suspend Customer
        </button>

    <?php endif; ?>

</form>

<!-- DELETE CUSTOMER FORM (SEPARATE FROM STATUS FORM) -->

<form
    method="POST"
    action="customer-delete.php?id=<?= (int) $customer['id'] ?>"
    onsubmit="return confirm('Are you sure you want to permanently delete this customer? This action cannot be undone.');"
    style="display:inline; margin-top: 15px;"
>
    <button type="submit" class="danger-btn">
        <i class="fa-solid fa-trash"></i>
        Delete Customer
    </button>
</form>
</div>
</div>

        <!-- BACK BUTTON -->

        <div class="form-actions">

            <a
                href="customers.php"
                class="secondary-btn"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Customers
            </a>

        </div>

    </main>

</div>

</body>
</html>