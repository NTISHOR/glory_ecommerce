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

/* ==================================
   FETCH VENDOR
================================== */

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
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


/* ==================================
   UPDATE VENDOR
================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $store_name = trim($_POST['store_name'] ?? '');
    $store_slug = trim($_POST['store_slug'] ?? '');

    $business_description =
        trim($_POST['business_description'] ?? '');

    $business_phone =
        trim($_POST['business_phone'] ?? '');

    $business_email =
        trim($_POST['business_email'] ?? '');

    $business_address =
        trim($_POST['business_address'] ?? '');

    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');


    /* ==================================
       VALIDATION
    ================================== */

    if ($full_name === '') {

        $error = "Vendor full name is required.";

    } elseif ($email === '') {

        $error = "Vendor email is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif ($store_name === '') {

        $error = "Store name is required.";

    } elseif ($country === '') {

        $error = "Country is required.";

    } else {

        /* ==================================
           CHECK EMAIL
        ================================== */

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $email,
            $vendor_id
        ]);

        if ($stmt->fetch()) {

            $error =
                "Another account is already using this email address.";

        } else {

            /* ==================================
               CHECK STORE NAME
            ================================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM vendor_profiles
                WHERE store_name = ?
                  AND user_id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $store_name,
                $vendor_id
            ]);

            if ($stmt->fetch()) {

                $error =
                    "Another vendor is already using this store name.";

            } else {

                /* ==================================
                   GENERATE SLUG
                ================================== */

                if ($store_slug === '') {

                    $store_slug = strtolower(
                        preg_replace(
                            '/[^a-zA-Z0-9]+/',
                            '-',
                            $store_name
                        )
                    );

                    $store_slug = trim(
                        $store_slug,
                        '-'
                    );
                }


                /* ==================================
                   CHECK STORE SLUG
                ================================== */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM vendor_profiles
                    WHERE store_slug = ?
                      AND user_id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $store_slug,
                    $vendor_id
                ]);

                if ($stmt->fetch()) {

                    $error =
                        "Another vendor is already using this store slug.";

                } else {

                    try {

                        $pdo->beginTransaction();


                        /* ==================================
                           UPDATE USER ACCOUNT
                        ================================== */

                        $stmt = $pdo->prepare("
                            UPDATE users

                            SET
                                full_name = ?,
                                email = ?,
                                phone = ?

                            WHERE id = ?
                              AND role = 'vendor'
                        ");

                        $stmt->execute([
                            $full_name,
                            $email,
                            $phone,
                            $vendor_id
                        ]);


                        /* ==================================
                           UPDATE STORE PROFILE
                        ================================== */

                        $stmt = $pdo->prepare("
                            UPDATE vendor_profiles

                            SET
                                store_name = ?,
                                store_slug = ?,
                                business_description = ?,
                                business_phone = ?,
                                business_email = ?,
                                business_address = ?,
                                city = ?,
                                state = ?,
                                country = ?

                            WHERE user_id = ?
                        ");

                        $stmt->execute([
                            $store_name,
                            $store_slug,
                            $business_description,
                            $business_phone,
                            $business_email,
                            $business_address,
                            $city,
                            $state,
                            $country,
                            $vendor_id
                        ]);


                        /* ==================================
                           LOG ACTIVITY
                        ================================== */

                        logAdminActivity(
                            $pdo,
                            $_SESSION['user_id'],
                            'EDIT_VENDOR',
                            "Updated vendor {$full_name}'s account and store profile."
                        );


                        $pdo->commit();

                        $success =
                            "Vendor account and store profile updated successfully.";


                        /* ==================================
                           REFRESH VENDOR
                        ================================== */

                        $stmt = $pdo->prepare("
                            SELECT
                                u.id,
                                u.full_name,
                                u.email,
                                u.phone,
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

                            LEFT JOIN vendor_profiles vp
                                ON vp.user_id = u.id

                            WHERE u.id = ?
                              AND u.role = 'vendor'

                            LIMIT 1
                        ");

                        $stmt->execute([$vendor_id]);

                        $vendor =
                            $stmt->fetch(PDO::FETCH_ASSOC);

                    } catch (Exception $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        error_log(
                            "Vendor Edit Error: " .
                            $e->getMessage()
                        );

                        $error =
                            "Unable to update the vendor. Please try again.";
                    }
                }
            }
        }
    }
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

    <title>Edit Vendor - GloryMarket</title>

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

            <a href="admin_activity.php">
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


        <!-- TOPBAR -->

        <div class="topbar">

            <div>

                <h1>Edit Vendor</h1>

                <p>
                    Update vendor account and store information.
                </p>

            </div>

        </div>


        <!-- MESSAGES -->

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


        <!-- FORM -->

        <form method="POST">


            <!-- ACCOUNT INFORMATION -->

            <div class="form-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-user"></i>
                            Account Information
                        </h2>

                        <p>
                            Update the vendor's personal account details.
                        </p>

                    </div>

                </div>


                <div class="admin-details">


                    <div class="form-group">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            value="<?= htmlspecialchars($vendor['full_name']) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= htmlspecialchars($vendor['email']) ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Account Status
                        </label>

                        <div>

                            <span class="status-badge status-<?= htmlspecialchars($vendor['status']) ?>">
                                <?= htmlspecialchars(ucfirst($vendor['status'])) ?>
                            </span>

                            <p>
                                Account status is managed from
                                the vendor details page.
                            </p>

                        </div>

                    </div>

                </div>

            </div>


            <!-- STORE INFORMATION -->

            <div class="form-panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-store"></i>
                            Store Information
                        </h2>

                        <p>
                            Update the vendor's business information.
                        </p>

                    </div>

                </div>


                <div class="admin-details">


                    <div class="form-group">

                        <label for="store_name">
                            Store Name
                        </label>

                        <input
                            type="text"
                            id="store_name"
                            name="store_name"
                            value="<?= htmlspecialchars($vendor['store_name'] ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="store_slug">
                            Store Slug
                        </label>

                        <input
                            type="text"
                            id="store_slug"
                            name="store_slug"
                            value="<?= htmlspecialchars($vendor['store_slug'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="business_phone">
                            Business Phone
                        </label>

                        <input
                            type="text"
                            id="business_phone"
                            name="business_phone"
                            value="<?= htmlspecialchars($vendor['business_phone'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="business_email">
                            Business Email
                        </label>

                        <input
                            type="email"
                            id="business_email"
                            name="business_email"
                            value="<?= htmlspecialchars($vendor['business_email'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="country">
                            Country
                        </label>

                        <input
                            type="text"
                            id="country"
                            name="country"
                            value="<?= htmlspecialchars($vendor['country'] ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="state">
                            State
                        </label>

                        <input
                            type="text"
                            id="state"
                            name="state"
                            value="<?= htmlspecialchars($vendor['state'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="city">
                            City
                        </label>

                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= htmlspecialchars($vendor['city'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="business_address">
                            Business Address
                        </label>

                        <textarea
                            id="business_address"
                            name="business_address"
                            rows="4"
                        ><?= htmlspecialchars($vendor['business_address'] ?? '') ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="business_description">
                            Business Description
                        </label>

                        <textarea
                            id="business_description"
                            name="business_description"
                            rows="5"
                        ><?= htmlspecialchars($vendor['business_description'] ?? '') ?></textarea>

                    </div>

                </div>

            </div>


            <!-- ACTIONS -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="primary-btn"
                >
                    <i class="fa-solid fa-save"></i>
                    Save Changes
                </button>

                <a
                    href="vendor-view.php?id=<?= $vendor_id ?>"
                    class="secondary-btn"
                >
                    Cancel
                </a>

            </div>


        </form>

    </main>

</div>

</body>

</html>