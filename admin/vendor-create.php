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

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Account Information
    |--------------------------------------------------------------------------
    */

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Store Information
    |--------------------------------------------------------------------------
    */

    $store_name = trim($_POST['store_name'] ?? '');
    $store_slug = trim($_POST['store_slug'] ?? '');
    $business_description = trim(
        $_POST['business_description'] ?? ''
    );

    $business_phone = trim(
        $_POST['business_phone'] ?? ''
    );

    $business_email = trim(
        $_POST['business_email'] ?? ''
    );

    $business_address = trim(
        $_POST['business_address'] ?? ''
    );

    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($full_name === '') {

        $error = "Vendor full name is required.";

    } elseif ($email === '') {

        $error = "Vendor email is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid vendor email address.";

    } elseif ($password === '') {

        $error = "Password is required.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif ($store_name === '') {

        $error = "Store name is required.";

    } elseif ($country === '') {

        $error = "Country is required.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Email
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $error = "An account with this email already exists.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check Store Name
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM vendor_profiles
                WHERE store_name = ?
                LIMIT 1
            ");

            $stmt->execute([$store_name]);

            if ($stmt->fetch()) {

                $error = "A store with this name already exists.";

            } else {

                try {

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | Create User Account
                    |--------------------------------------------------------------------------
                    */

                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            full_name,
                            email,
                            phone,
                            password,
                            role,
                            status
                        )
                        VALUES (?, ?, ?, ?, 'vendor', 'active')
                    ");

                    $stmt->execute([
                        $full_name,
                        $email,
                        $phone,
                        $hashed_password
                    ]);

                    $new_vendor_id = $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | Generate Store Slug
                    |--------------------------------------------------------------------------
                    */

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

                    /*
                    |--------------------------------------------------------------------------
                    | Check Store Slug
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM vendor_profiles
                        WHERE store_slug = ?
                        LIMIT 1
                    ");

                    $stmt->execute([$store_slug]);

                    if ($stmt->fetch()) {

                        $store_slug .= '-' . $new_vendor_id;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Create Vendor Profile
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        INSERT INTO vendor_profiles (
                            user_id,
                            store_name,
                            store_slug,
                            business_description,
                            business_phone,
                            business_email,
                            business_address,
                            city,
                            state,
                            country,
                            verification_status
                        )
                        VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            'pending'
                        )
                    ");

                    $stmt->execute([
                        $new_vendor_id,
                        $store_name,
                        $store_slug,
                        $business_description,
                        $business_phone,
                        $business_email,
                        $business_address,
                        $city,
                        $state,
                        $country
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Activity Log
                    |--------------------------------------------------------------------------
                    */

                    logAdminActivity(
                        $pdo,
                        $_SESSION['user_id'],
                        'CREATE_VENDOR',
                        "Created vendor account for {$full_name} ({$email}) with store '{$store_name}'."
                    );

                    $pdo->commit();

                    $success =
                        "Vendor account and store profile created successfully.";

                    /*
                    |--------------------------------------------------------------------------
                    | Clear Form
                    |--------------------------------------------------------------------------
                    */

                    $_POST = [];

                } catch (Exception $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        "Vendor Creation Error: " .
                        $e->getMessage()
                    );

                    $error =
                        "Unable to create the vendor account. Please try again.";
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

    <title>Create Vendor - GloryMarket</title>

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

                <h1>Create Vendor</h1>

                <p>
                    Create a vendor account and store profile.
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
                            Information used to access the vendor account.
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
                            value="<?= htmlspecialchars(
                                $_POST['full_name'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['email'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['phone'] ?? ''
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            required
                        >

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
                            Information about the vendor's business.
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
                            value="<?= htmlspecialchars(
                                $_POST['store_name'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['store_slug'] ?? ''
                            ) ?>"
                            placeholder="example-store"
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
                            value="<?= htmlspecialchars(
                                $_POST['business_phone'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['business_email'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['country'] ?? 'Nigeria'
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['state'] ?? ''
                            ) ?>"
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
                            value="<?= htmlspecialchars(
                                $_POST['city'] ?? ''
                            ) ?>"
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
                        ><?= htmlspecialchars(
                            $_POST['business_address'] ?? ''
                        ) ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="business_description">
                            Business Description
                        </label>

                        <textarea
                            id="business_description"
                            name="business_description"
                            rows="5"
                        ><?= htmlspecialchars(
                            $_POST['business_description'] ?? ''
                        ) ?></textarea>

                    </div>

                </div>


                <div class="form-actions">

                    <a
                        href="vendors.php"
                        class="secondary-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Create Vendor
                    </button>

                </div>

            </div>

        </form>

    </main>

</div>

</body>

</html>