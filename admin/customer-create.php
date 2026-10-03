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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';

    if ($full_name === '' || $email === '' || $password === '') {

        $error = "Please fill in all required fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } elseif (
        $gender !== '' &&
        !in_array(
            $gender,
            ['male', 'female', 'other', 'prefer_not_to_say'],
            true
        )
    ) {

        $error = "Invalid gender selection.";

    } else {

        try {

            /* ==============================
               CHECK DUPLICATE EMAIL
            ============================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            if ($stmt->fetch()) {

                $error = "A user with this email already exists.";

            } else {

                $pdo->beginTransaction();

                /* ==============================
                   CREATE USER ACCOUNT
                ============================== */

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
                    VALUES (?, ?, ?, ?, 'customer', 'active')
                ");

                $stmt->execute([
                    $full_name,
                    $email,
                    $phone !== '' ? $phone : null,
                    $hashed_password
                ]);

                $customer_id = (int) $pdo->lastInsertId();

               /* ==============================
   CREATE CUSTOMER PROFILE
============================== */

$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$country = trim($_POST['country'] ?? '');

$stmt = $pdo->prepare("
    INSERT INTO customer_profiles (
        user_id,
        date_of_birth,
        gender,
        address,
        city,
        state,
        country
    )
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $customer_id,
    $date_of_birth !== '' ? $date_of_birth : null,
    $gender !== '' ? $gender : null,
    $address !== '' ? $address : null,
    $city !== '' ? $city : null,
    $state !== '' ? $state : null,
    $country !== '' ? $country : null
]);

                /* ==============================
                   ACTIVITY LOG
                ============================== */

                logAdminActivity(
                    $pdo,
                    $_SESSION['user_id'],
                    'CREATE_CUSTOMER',
                    "Created customer account for {$full_name} ({$email})."
                );

                $pdo->commit();

                header("Location: customers.php?success=1");
                exit;
            }

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to create customer account. Please try again.";
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

    <title>Create Customer - GloryMarket</title>

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
                <h1>Create Customer</h1>
                <p>Create a new customer account.</p>
            </div>

        </div>


        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="form-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-user-plus"></i>
                        Customer Account
                    </h2>

                    <p>
                        Enter the customer's account information.
                    </p>

                </div>

            </div>


            <form method="POST">


                <!-- ACCOUNT INFORMATION -->

                <div class="form-section">

                    <h3>Account Information</h3>


                    <div class="form-group">

                        <label for="full_name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            required
                            value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
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
                            required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
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
                            required
                        >

                        <small>
                            Password must be at least 8 characters.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            required
                        >

                    </div>

                </div>


                <!-- CUSTOMER PROFILE -->

                <div class="form-section">

                    <h3>Customer Profile</h3>


                    <div class="form-group">

                        <label for="date_of_birth">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="gender">
                            Gender
                        </label>

                        <select id="gender" name="gender">

                            <option value="">
                                Select Gender
                            </option>

                            <option
                                value="male"
                                <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>
                            >
                                Male
                            </option>

                            <option
                                value="female"
                                <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>
                            >
                                Female
                            </option>

                            <option
                                value="other"
                                <?= (($_POST['gender'] ?? '') === 'other') ? 'selected' : '' ?>
                            >
                                Other
                            </option>

                            <option
                                value="prefer_not_to_say"
                                <?= (($_POST['gender'] ?? '') === 'prefer_not_to_say') ? 'selected' : '' ?>
                            >
                                Prefer not to say
                            </option>

                        </select>

                    </div>

                </div>
<div class="form-group">

    <label for="address">Address</label>

    <input
        type="text"
        id="address"
        name="address"
        value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
        placeholder="Enter residential address"
    >

</div>

<div class="form-group">

    <label for="city">City</label>

    <input
        type="text"
        id="city"
        name="city"
        value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
        placeholder="Enter city"
    >

</div>

<div class="form-group">

    <label for="state">State</label>

    <input
        type="text"
        id="state"
        name="state"
        value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
        placeholder="Enter state"
    >

</div>

<div class="form-group">

    <label for="country">Country</label>

    <input
        type="text"
        id="country"
        name="country"
        value="<?= htmlspecialchars($_POST['country'] ?? 'Nigeria') ?>"
        placeholder="Enter country"
    >

</div>

                <!-- ACTIONS -->

                <div class="form-actions">

                    <a
                        href="customers.php"
                        class="secondary-btn"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-user-plus"></i>
                        Create Customer
                    </button>

                </div>


            </form>

        </div>

    </main>

</div>

</body>

</html>