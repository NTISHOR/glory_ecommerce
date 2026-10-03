<?php

session_start();

require_once '../config/db.php';

$pdo = getDbConnection();

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['super_admin', 'admin'], true)
) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Vendor Approval or Rejection
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vendor_id = filter_input(
        INPUT_POST,
        'vendor_id',
        FILTER_VALIDATE_INT
    );

    $action = $_POST['action'] ?? '';

    if (!$vendor_id || !in_array($action, ['approve', 'reject'], true)) {

        $message = "Invalid request.";
        $messageType = "error";

    } else {

        try {

            $pdo->beginTransaction();

            // Confirm the vendor exists
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.status,
                    vp.verification_status
                FROM users u
                INNER JOIN vendor_profiles vp
                    ON vp.user_id = u.id
                WHERE u.id = ?
                  AND u.role = 'vendor'
                FOR UPDATE
            ");

            $stmt->execute([$vendor_id]);

            $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$vendor) {

                throw new Exception("Vendor application not found.");

            }

            if ($vendor['verification_status'] !== 'pending') {

                throw new Exception(
                    "This application has already been processed."
                );

            }

            /*
            |--------------------------------------------------------------------------
            | Approve Vendor
            |--------------------------------------------------------------------------
            */

            if ($action === 'approve') {

                // Activate vendor account
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET status = 'active'
                    WHERE id = ?
                      AND role = 'vendor'
                ");

                $stmt->execute([$vendor_id]);

                // Mark vendor application as verified
                $stmt = $pdo->prepare("
                    UPDATE vendor_profiles
                    SET verification_status = 'verified'
                    WHERE user_id = ?
                ");

                $stmt->execute([$vendor_id]);

                $message = "Vendor approved successfully.";
                $messageType = "success";

            }

            /*
            |--------------------------------------------------------------------------
            | Reject Vendor
            |--------------------------------------------------------------------------
            */

            elseif ($action === 'reject') {

                // Keep vendor account inactive
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET status = 'inactive'
                    WHERE id = ?
                      AND role = 'vendor'
                ");

                $stmt->execute([$vendor_id]);

                // Mark application as rejected
                $stmt = $pdo->prepare("
                    UPDATE vendor_profiles
                    SET verification_status = 'rejected'
                    WHERE user_id = ?
                ");

                $stmt->execute([$vendor_id]);

                $message = "Vendor application rejected.";
                $messageType = "success";
            }

            $pdo->commit();

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Vendor Applications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.created_at,
        u.status,

        vp.store_name,
        vp.store_slug,
        vp.business_description,
        vp.business_phone,
        vp.business_email,
        vp.business_address,
        vp.city,
        vp.state,
        vp.country,
        vp.verification_status

    FROM users u

    INNER JOIN vendor_profiles vp
        ON vp.user_id = u.id

    WHERE u.role = 'vendor'

    ORDER BY
        CASE
            WHEN vp.verification_status = 'pending' THEN 0
            ELSE 1
        END,
        u.created_at DESC
");

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

    <title>Vendor Applications | GloryMarket</title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <!-- Admin Dashboard CSS -->
    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>
                Glory<span>Market</span>
            </h2>

            <small>Admin Panel</small>

        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fas fa-lock"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php">
                <i class="fas fa-store"></i>
                <span>Vendors</span>
            </a>

            <a
                href="vendor-applications.php"
                class="active"
            >
                <i class="fas fa-user-check"></i>
                <span>Vendor Applications</span>
            </a>

            <a href="customers.php">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php">
                <i class="fas fa-tags"></i>
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


    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->

    <main class="main-content">

        <div class="applications-container">

            <h1>Vendor Applications</h1>

            <p>
                Review and manage vendor registration requests.
            </p>


            <!-- =================================================
                 MESSAGE
            ================================================== -->

            <?php if (!empty($message)): ?>

                <div class="message <?= htmlspecialchars($messageType) ?>">

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 APPLICATIONS TABLE
            ================================================== -->

            <div class="table-responsive">

                <table class="applications-table">

                    <thead>

                        <tr>

                            <th>Vendor</th>

                            <th>Store</th>

                            <th>Business Details</th>

                            <th>Registered</th>

                            <th>Status</th>

                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (empty($vendors)): ?>

                        <tr>

                            <td
                                colspan="6"
                                class="empty-state"
                            >
                                No vendor applications found.
                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach ($vendors as $vendor): ?>

                            <tr>

                                <!-- Vendor -->
                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $vendor['full_name']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <?= htmlspecialchars(
                                        $vendor['email']
                                    ) ?>

                                    <br>

                                    <?= htmlspecialchars(
                                        $vendor['phone'] ?? ''
                                    ) ?>

                                </td>


                                <!-- Store -->
                                <td>

                                    <strong>
                                        <?= htmlspecialchars(
                                            $vendor['store_name']
                                        ) ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?= htmlspecialchars(
                                            $vendor['store_slug']
                                        ) ?>
                                    </small>

                                </td>


                                <!-- Business Details -->
                                <td class="business-details">

                                    <?= htmlspecialchars(
                                        $vendor['business_description'] ?? ''
                                    ) ?>

                                    <br><br>

                                    <strong>
                                        Business Email:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vendor['business_email'] ?? ''
                                    ) ?>

                                    <br>

                                    <strong>
                                        Business Phone:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vendor['business_phone'] ?? ''
                                    ) ?>

                                    <br>

                                    <strong>
                                        Address:
                                    </strong>

                                    <?= htmlspecialchars(
                                        $vendor['business_address'] ?? ''
                                    ) ?>

                                    <br>

                                    <?= htmlspecialchars(
                                        $vendor['city'] ?? ''
                                    ) ?>,

                                    <?= htmlspecialchars(
                                        $vendor['state'] ?? ''
                                    ) ?>,

                                    <?= htmlspecialchars(
                                        $vendor['country'] ?? ''
                                    ) ?>

                                </td>


                                <!-- Registered -->
                                <td>

                                    <?= htmlspecialchars(
                                        $vendor['created_at']
                                    ) ?>

                                </td>


                                <!-- Status -->
                                <td>

                                    <span
                                        class="status-badge <?= htmlspecialchars(
                                            $vendor['verification_status']
                                        ) ?>"
                                    >

                                        <?= ucfirst(
                                            htmlspecialchars(
                                                $vendor['verification_status']
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Action -->
                                <td>

                                    <?php if (
                                        $vendor['verification_status'] === 'pending'
                                    ): ?>

                                        <!-- Approve -->
                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="vendor_id"
                                                value="<?= (int)$vendor['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="approve"
                                                class="approve-btn"
                                                onclick="return confirm('Approve this vendor application?');"
                                            >
                                                Approve
                                            </button>

                                        </form>


                                        <!-- Reject -->
                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="vendor_id"
                                                value="<?= (int)$vendor['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="reject"
                                                class="reject-btn"
                                                onclick="return confirm('Reject this vendor application?');"
                                            >
                                                Reject
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span>
                                            Processed
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>

</html>