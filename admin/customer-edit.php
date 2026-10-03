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
        u.status,

        cp.date_of_birth,
        cp.gender,
        cp.address,
        cp.city,
        cp.state,
        cp.country

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
   FORM VARIABLES
============================== */

$error = '';
$success = '';

$full_name = $customer['full_name'];
$email = $customer['email'];
$phone = $customer['phone'] ?? '';

$date_of_birth = $customer['date_of_birth'] ?? '';
$gender = $customer['gender'] ?? '';

$address = $customer['address'] ?? '';
$city = $customer['city'] ?? '';
$state = $customer['state'] ?? '';
$country = $customer['country'] ?? '';

/* ==============================
   HANDLE FORM SUBMISSION
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';

    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');

    /* ==============================
       VALIDATION
    ============================== */

    if ($full_name === '' || $email === '') {

        $error = "Full name and email address are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (
        $gender !== '' &&
        !in_array(
            $gender,
            ['male', 'female', 'other', 'prefer_not_to_say'],
            true
        )
    ) {

        $error = "Invalid gender selection.";

    } elseif (
        $date_of_birth !== '' &&
        (
            !DateTime::createFromFormat('Y-m-d', $date_of_birth) ||
            DateTime::createFromFormat('Y-m-d', $date_of_birth)->format('Y-m-d') !== $date_of_birth
        )
    ) {

        $error = "Invalid date of birth.";

    } else {

        try {

            /* ==============================
               CHECK DUPLICATE EMAIL
            ============================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                  AND id != ?
                LIMIT 1
            ");

            $stmt->execute([
                $email,
                $customer_id
            ]);

            if ($stmt->fetch()) {

                $error = "This email address is already in use.";

            } else {

                $pdo->beginTransaction();

                /* ==============================
                   UPDATE USER ACCOUNT
                ============================== */

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        email = ?,
                        phone = ?
                    WHERE id = ?
                      AND role = 'customer'
                ");

                $stmt->execute([
                    $full_name,
                    $email,
                    $phone !== '' ? $phone : null,
                    $customer_id
                ]);

                /* ==============================
                   UPDATE CUSTOMER PROFILE
                ============================== */

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

                    ON DUPLICATE KEY UPDATE
                        date_of_birth = VALUES(date_of_birth),
                        gender = VALUES(gender),
                        address = VALUES(address),
                        city = VALUES(city),
                        state = VALUES(state),
                        country = VALUES(country)
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
                    'EDIT_CUSTOMER',
                    "Updated customer account and profile for {$full_name} ({$email})."
                );

                $pdo->commit();

                header(
                    "Location: customer-view.php?id=" .
                    $customer_id .
                    "&success=updated"
                );

                exit;
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = "Unable to update customer information. Please try again.";
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

    <title>Edit Customer - GloryMarket</title>

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

                <h1>Edit Customer</h1>

                <p>
                    Update customer account and profile information.
                </p>

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
                        <i class="fa-solid fa-user-pen"></i>
                        Customer Information
                    </h2>

                    <p>
                        Editing account for
                        <?= htmlspecialchars($customer['full_name']) ?>
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
                            value="<?= htmlspecialchars($full_name) ?>"
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
                            value="<?= htmlspecialchars($email) ?>"
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
                            value="<?= htmlspecialchars($phone) ?>"
                        >

                    </div>

                </div>


                <!-- PERSONAL INFORMATION -->

                <div class="form-section">

                    <h3>Personal Information</h3>


                    <div class="form-group">

                        <label for="date_of_birth">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?= htmlspecialchars($date_of_birth) ?>"
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
                                <?= $gender === 'male' ? 'selected' : '' ?>
                            >
                                Male
                            </option>

                            <option
                                value="female"
                                <?= $gender === 'female' ? 'selected' : '' ?>
                            >
                                Female
                            </option>

                            <option
                                value="other"
                                <?= $gender === 'other' ? 'selected' : '' ?>
                            >
                                Other
                            </option>

                            <option
                                value="prefer_not_to_say"
                                <?= $gender === 'prefer_not_to_say' ? 'selected' : '' ?>
                            >
                                Prefer not to say
                            </option>

                        </select>

                    </div>

                </div>


                <!-- LOCATION INFORMATION -->

                <div class="form-section">

                    <h3>Location Information</h3>


                    <div class="form-group">

                        <label for="address">
                            Residential Address
                        </label>

                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="<?= htmlspecialchars($address) ?>"
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
                            value="<?= htmlspecialchars($city) ?>"
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
                            value="<?= htmlspecialchars($state) ?>"
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
                            value="<?= htmlspecialchars($country) ?>"
                        >

                    </div>

                </div>


                <!-- FORM ACTIONS -->

                <div class="form-actions">

                    <a
                        href="customer-view.php?id=<?= (int) $customer_id ?>"
                        class="secondary-btn"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>

</body>

</html>