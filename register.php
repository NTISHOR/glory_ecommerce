<?php
require_once 'config/db.php';

$pdo = getDbConnection();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $account_type = $_POST['account_type'] ?? 'customer';

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Vendor information
    $store_name = trim($_POST['store_name'] ?? '');
    $business_description = trim($_POST['business_description'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? 'Nigeria');

    /*
    |--------------------------------------------------------------------------
    | Validate Account Type
    |--------------------------------------------------------------------------
    */

    if (!in_array($account_type, ['customer', 'vendor'], true)) {

        $message = "Invalid account type selected.";
        $messageType = "error";

    /*
    |--------------------------------------------------------------------------
    | Validate Common Fields
    |--------------------------------------------------------------------------
    */

    } elseif (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {

        $message = "Password must contain at least 8 characters.";
        $messageType = "error";

    /*
    |--------------------------------------------------------------------------
    | Validate Vendor Fields
    |--------------------------------------------------------------------------
    */

    } elseif (
        $account_type === 'vendor' &&
        (
            empty($store_name) ||
            empty($business_phone) ||
            empty($business_email) ||
            empty($business_address) ||
            empty($city) ||
            empty($state)
        )
    ) {

        $message = "Please fill in all required vendor/business information.";
        $messageType = "error";

    } elseif (
        $account_type === 'vendor' &&
        !filter_var($business_email, FILTER_VALIDATE_EMAIL)
    ) {

        $message = "Please enter a valid business email address.";
        $messageType = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Email
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $message = "This email address is already registered.";
            $messageType = "error";

        } else {

            try {

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Hash Password
                |--------------------------------------------------------------------------
                */

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /*
                |--------------------------------------------------------------------------
                | CUSTOMER REGISTRATION
                |--------------------------------------------------------------------------
                */

                if ($account_type === 'customer') {

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            full_name,
                            email,
                            phone,
                            password,
                            role,
                            status
                        ) VALUES (?, ?, ?, ?, 'customer', 'active')
                    ");

                    $stmt->execute([
                        $full_name,
                        $email,
                        $phone,
                        $hashed_password
                    ]);

                    $user_id = $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | Create Customer Profile
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $pdo->prepare("
                        INSERT INTO customer_profiles (
                            user_id
                        ) VALUES (?)
                    ");

                    $stmt->execute([
                        $user_id
                    ]);

                    $pdo->commit();

                    $message = "Customer account created successfully! You can now log in.";
                    $messageType = "success";

                }

                /*
                |--------------------------------------------------------------------------
                | VENDOR REGISTRATION
                |--------------------------------------------------------------------------
                */

                elseif ($account_type === 'vendor') {

                    /*
                    Vendor accounts start as INACTIVE.
                    They cannot log in until Admin approval.
                    */

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            full_name,
                            email,
                            phone,
                            password,
                            role,
                            status
                        ) VALUES (?, ?, ?, ?, 'vendor', 'inactive')
                    ");

                    $stmt->execute([
                        $full_name,
                        $email,
                        $phone,
                        $hashed_password
                    ]);

                    $user_id = $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | Create Vendor Profile
                    |--------------------------------------------------------------------------
                    */

                    $store_slug = strtolower(
                        trim(
                            preg_replace(
                                '/[^A-Za-z0-9]+/',
                                '-',
                                $store_name
                            ),
                            '-'
                        )
                    );

                    /*
                    Make sure the store slug is unique.
                    */

                    $base_slug = $store_slug;
                    $counter = 1;

                    while (true) {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM vendor_profiles
                            WHERE store_slug = ?
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $store_slug
                        ]);

                        if (!$stmt->fetch()) {
                            break;
                        }

                        $store_slug = $base_slug . '-' . $counter;
                        $counter++;
                    }

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
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $stmt->execute([
                        $user_id,
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

                    $pdo->commit();

                    $message = "Vendor registration submitted successfully. Your account is awaiting Admin approval.";
                    $messageType = "success";
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $message = "Registration could not be completed. Please try again.";
                $messageType = "error";
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

    <title>Create Account | GloryMarket</title>

    <link
        rel="stylesheet"
        href="assets/css/register.css"
    >

</head>

<body>

    <div class="register-container">

        <div class="register-card">

            <h1>GloryMarket</h1>

            <h2>Create Your Account</h2>

            <p class="register-subtitle">
                Choose the type of account you want to create.
            </p>

            <?php if (!empty($message)): ?>

                <div class="message <?= htmlspecialchars($messageType) ?>">

                    <?= htmlspecialchars($message) ?>

                </div>

            <?php endif; ?>


            <form method="POST" action="">

                <!-- Account Type -->

                <div class="form-group">

                    <label for="account_type">
                        Account Type
                    </label>

                    <select
                        name="account_type"
                        id="account_type"
                        onchange="toggleVendorFields()"
                        required
                    >

                        <option
                            value="customer"
                            <?= (($account_type ?? 'customer') === 'customer') ? 'selected' : '' ?>
                        >
                            Customer
                        </option>

                        <option
                            value="vendor"
                            <?= (($account_type ?? '') === 'vendor') ? 'selected' : '' ?>
                        >
                            Vendor
                        </option>

                    </select>

                </div>


                <!-- Common Information -->

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
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
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
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


                <!-- Vendor Information -->

                <div
                    id="vendor-fields"
                    style="display: none;"
                >

                    <h3>Business Information</h3>

                    <p class="vendor-note">
                        Vendor accounts require Admin approval before you can access the vendor dashboard.
                    </p>


                    <div class="form-group">

                        <label for="store_name">
                            Store Name
                        </label>

                        <input
                            type="text"
                            id="store_name"
                            name="store_name"
                            value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="business_description">
                            Business Description
                        </label>

                        <textarea
                            id="business_description"
                            name="business_description"
                            rows="4"
                        ><?= htmlspecialchars($_POST['business_description'] ?? '') ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="business_phone">
                            Business Phone
                        </label>

                        <input
                            type="tel"
                            id="business_phone"
                            name="business_phone"
                            value="<?= htmlspecialchars($_POST['business_phone'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['business_email'] ?? '') ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="business_address">
                            Business Address
                        </label>

                        <textarea
                            id="business_address"
                            name="business_address"
                            rows="3"
                        ><?= htmlspecialchars($_POST['business_address'] ?? '') ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="city">
                            City
                        </label>

                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
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
                            value="<?= htmlspecialchars($_POST['country'] ?? 'Nigeria') ?>"
                        >

                    </div>

                </div>


                <button
                    type="submit"
                    class="register-button"
                >
                    Create Account
                </button>

            </form>


            <p class="login-link">

                Already have an account?

                <a href="login.php">
                    Log in
                </a>

            </p>

        </div>

    </div>


    <script>

        function toggleVendorFields() {

            const accountType =
                document.getElementById('account_type').value;

            const vendorFields =
                document.getElementById('vendor-fields');

            const vendorInputs =
                vendorFields.querySelectorAll('input, textarea');

            if (accountType === 'vendor') {

                vendorFields.style.display = 'block';

                vendorInputs.forEach(function(input) {

                    input.required = true;

                });

            } else {

                vendorFields.style.display = 'none';

                vendorInputs.forEach(function(input) {

                    input.required = false;

                });

            }

        }

        // Keep vendor fields visible after validation errors
        document.addEventListener('DOMContentLoaded', function() {

            toggleVendorFields();

        });

    </script>

</body>

</html>