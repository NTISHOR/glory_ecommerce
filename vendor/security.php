<?php
session_start();

require_once '../config/db.php';
$pdo = getDbConnection();

/* ==================================
   VENDOR AUTHENTICATION
================================== */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

$success = '';
$error = '';

/* ==================================
   GET VENDOR INFORMATION
================================== */

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.status,
        vp.store_name,
        vp.logo,
        vp.verification_status
    FROM users u
    LEFT JOIN vendor_profiles vp
        ON vp.user_id = u.id
    WHERE u.id = ?
    LIMIT 1
");

$stmt->execute([$vendor_id]);

$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

/* ==================================
   CHANGE PASSWORD
================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '') {

        $error = 'Please enter your current password.';

    } elseif ($new_password === '') {

        $error = 'Please enter a new password.';

    } elseif (strlen($new_password) < 8) {

        $error = 'Your new password must be at least 8 characters long.';

    } elseif ($new_password === $current_password) {

        $error = 'Your new password must be different from your current password.';

    } elseif ($confirm_password === '') {

        $error = 'Please confirm your new password.';

    } elseif ($new_password !== $confirm_password) {

        $error = 'The new passwords do not match.';

    } else {

        /* ==================================
           GET CURRENT PASSWORD
        ================================== */

        $stmt = $pdo->prepare("
            SELECT password
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$vendor_id]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($current_password, $user['password'])) {

            $error = 'Your current password is incorrect.';

        } else {

            /* ==================================
               HASH NEW PASSWORD
            ================================== */

            $hashed_password = password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );

            try {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        password = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $hashed_password,
                    $vendor_id
                ]);

                $success = 'Your password has been changed successfully.';

            } catch (Throwable $e) {

                $error = 'Unable to change your password. Please try again.';

            }
        }
    }
}

/* ==================================
   CART COUNT
================================== */

$cart_count = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $item) {

        $cart_count += (int) ($item['quantity'] ?? 0);

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

    <title>Security - GloryMarket</title>

    <link
        rel="stylesheet"
        href="../assets/css/vendor-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>

<div class="vendor-dashboard">

    <!-- ==================================
         SIDEBAR
    ================================== -->

    <aside class="vendor-sidebar">

        <div class="vendor-logo">

            <h2>GloryMarket</h2>

            <p>Vendor Panel</p>

        </div>


        <div class="vendor-store-mini">

            <?php if (!empty($vendor['logo'])): ?>

                <img
                    src="../<?= htmlspecialchars($vendor['logo']) ?>"
                    alt="Store Logo"
                >

            <?php else: ?>

                <img
                    src="../assets/images/vendor-default.png"
                    alt="Store Logo"
                >

            <?php endif; ?>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $vendor['store_name'] ?: 'My Store'
                    ) ?>
                </strong>

                <span>Vendor</span>

            </div>

        </div>


        <nav class="vendor-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i>
                Dashboard
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                My Products
            </a>

            <a href="add-product.php">
                <i class="fa-solid fa-plus"></i>
                Add Product
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                Orders
            </a>

            <a href="sales.php">
                <i class="fa-solid fa-chart-line"></i>
                Sales
            </a>

            <a href="store.php">
                <i class="fa-solid fa-store"></i>
                My Store
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                Profile
            </a>

            <a
                href="security.php"
                class="active"
            >
                <i class="fa-solid fa-lock"></i>
                Security
            </a>

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </nav>

    </aside>


    <!-- ==================================
         MAIN CONTENT
    ================================== -->

    <main class="vendor-main">

        <div class="vendor-topbar">

            <div>

                <h1>Security</h1>

                <p>
                    Manage your vendor account password and security.
                </p>

            </div>

        </div>


        <?php if ($success): ?>

            <div class="vendor-alert vendor-alert-success">

                <i class="fa-solid fa-circle-check"></i>

                <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="vendor-alert vendor-alert-error">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <!-- ==================================
             SECURITY LAYOUT
        ================================== -->

        <div class="vendor-security-layout">

            <!-- CHANGE PASSWORD -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-key"></i>
                            Change Password
                        </h2>

                        <p>
                            Use a strong password to protect your vendor account.
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    class="security-form"
                >

                    <!-- CURRENT PASSWORD -->

                    <div class="security-form-group">

                        <label for="current_password">
                            Current Password
                        </label>

                        <div class="password-input-wrapper">

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="current_password"
                                aria-label="Show password"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>

                        </div>

                    </div>


                    <!-- NEW PASSWORD -->

                    <div class="security-form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <div class="password-input-wrapper">

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="new_password"
                                aria-label="Show password"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>

                        </div>

                        <div class="password-requirements">

                            <span id="lengthRequirement">
                                <i class="fa-solid fa-circle"></i>
                                At least 8 characters
                            </span>

                            <span id="differentRequirement">
                                <i class="fa-solid fa-circle"></i>
                                Different from your current password
                            </span>

                        </div>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="security-form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <div class="password-input-wrapper">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                minlength="8"
                                autocomplete="new-password"
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirm_password"
                                aria-label="Show password"
                            >
                                <i class="fa-solid fa-eye"></i>
                            </button>

                        </div>

                        <div
                            class="password-match"
                            id="passwordMatch"
                        ></div>

                    </div>


                    <div class="security-form-actions">

                        <button
                            type="submit"
                            class="vendor-primary-btn"
                        >
                            <i class="fa-solid fa-key"></i>
                            Change Password
                        </button>

                    </div>

                </form>

            </div>


            <!-- SECURITY INFORMATION -->

            <div class="vendor-panel security-information">

                <div class="security-info-icon">

                    <i class="fa-solid fa-shield-halved"></i>

                </div>

                <h2>
                    Account Security
                </h2>

                <p>
                    Keep your GloryMarket vendor account secure by
                    following these recommendations.
                </p>


                <div class="security-tips">

                    <div class="security-tip">

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Use a password that is at least 8 characters long.
                        </span>

                    </div>


                    <div class="security-tip">

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Do not share your password with other people.
                        </span>

                    </div>


                    <div class="security-tip">

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Avoid using the same password on multiple websites.
                        </span>

                    </div>


                    <div class="security-tip">

                        <i class="fa-solid fa-check"></i>

                        <span>
                            Always log out when using a shared computer.
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- ==================================
             ACCOUNT SECURITY STATUS
        ================================== -->

        <div class="vendor-panel security-account-status">

            <div class="security-status-item">

                <div class="security-status-icon">
                    <i class="fa-solid fa-envelope"></i>
                </div>

                <div>

                    <span>Email Address</span>

                    <strong>
                        <?= htmlspecialchars($vendor['email']) ?>
                    </strong>

                </div>

                <span class="security-status-badge">
                    Account Email
                </span>

            </div>


            <div class="security-status-item">

                <div class="security-status-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>

                <div>

                    <span>Account Type</span>

                    <strong>
                        Vendor
                    </strong>

                </div>

                <span class="security-status-badge">
                    Vendor Account
                </span>

            </div>


            <div class="security-status-item">

                <div class="security-status-icon">
                    <i class="fa-solid fa-store"></i>
                </div>

                <div>

                    <span>Store Verification</span>

                    <strong>

                        <?php if ($vendor['verification_status'] === 'verified'): ?>

                            Verified

                        <?php elseif ($vendor['verification_status'] === 'rejected'): ?>

                            Rejected

                        <?php else: ?>

                            Pending

                        <?php endif; ?>

                    </strong>

                </div>

                <span class="security-status-badge">
                    Verification Status
                </span>

            </div>

        </div>

    </main>

</div>


<script>

/* ==================================
   PASSWORD VISIBILITY
================================== */

document.querySelectorAll('.password-toggle').forEach(function(button) {

    button.addEventListener('click', function() {

        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

            this.setAttribute(
                'aria-label',
                'Hide password'
            );

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

            this.setAttribute(
                'aria-label',
                'Show password'
            );

        }

    });

});


/* ==================================
   PASSWORD REQUIREMENTS
================================== */

const currentPassword =
    document.getElementById('current_password');

const newPassword =
    document.getElementById('new_password');

const confirmPassword =
    document.getElementById('confirm_password');

const lengthRequirement =
    document.getElementById('lengthRequirement');

const differentRequirement =
    document.getElementById('differentRequirement');

const passwordMatch =
    document.getElementById('passwordMatch');


function updatePasswordRequirements() {

    const newValue = newPassword.value;
    const currentValue = currentPassword.value;

    /* LENGTH */

    if (newValue.length >= 8) {

        lengthRequirement.classList.add('valid');

        lengthRequirement.innerHTML =
            '<i class="fa-solid fa-circle-check"></i>' +
            ' At least 8 characters';

    } else {

        lengthRequirement.classList.remove('valid');

        lengthRequirement.innerHTML =
            '<i class="fa-solid fa-circle"></i>' +
            ' At least 8 characters';

    }


    /* DIFFERENT PASSWORD */

    if (
        newValue.length > 0 &&
        newValue !== currentValue
    ) {

        differentRequirement.classList.add('valid');

        differentRequirement.innerHTML =
            '<i class="fa-solid fa-circle-check"></i>' +
            ' Different from your current password';

    } else {

        differentRequirement.classList.remove('valid');

        differentRequirement.innerHTML =
            '<i class="fa-solid fa-circle"></i>' +
            ' Different from your current password';

    }


    /* PASSWORD MATCH */

    if (confirmPassword.value === '') {

        passwordMatch.textContent = '';

    } else if (
        newPassword.value === confirmPassword.value
    ) {

        passwordMatch.className =
            'password-match valid';

        passwordMatch.innerHTML =
            '<i class="fa-solid fa-circle-check"></i>' +
            ' Passwords match';

    } else {

        passwordMatch.className =
            'password-match invalid';

        passwordMatch.innerHTML =
            '<i class="fa-solid fa-circle-xmark"></i>' +
            ' Passwords do not match';

    }

}


currentPassword.addEventListener(
    'input',
    updatePasswordRequirements
);

newPassword.addEventListener(
    'input',
    updatePasswordRequirements
);

confirmPassword.addEventListener(
    'input',
    updatePasswordRequirements
);

</script>

</body>
</html>