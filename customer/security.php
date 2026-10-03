<?php
session_start();

require_once __DIR__ . '/../config/db.php';
$pdo = getDbConnection();

/* ==============================
   CUSTOMER AUTHENTICATION
============================== */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header("Location: ../login.php");
    exit();
}

$customer_id = (int) $_SESSION['user_id'];

$success_message = '';
$error_message = '';

/* ==============================
   CART COUNT
============================== */
$cart_count = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += (int) ($item['quantity'] ?? 0);
    }
}

/* ==============================
   CHANGE PASSWORD
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {

        $error_message = 'Please fill in all password fields.';

    } elseif ($new_password !== $confirm_password) {

        $error_message = 'New password and confirmation password do not match.';

    } elseif (strlen($new_password) < 8) {

        $error_message = 'New password must be at least 8 characters long.';

    } elseif ($current_password === $new_password) {

        $error_message = 'Your new password must be different from your current password.';

    } else {

        try {

            /* ==============================
               GET CURRENT PASSWORD
            ============================== */

            $stmt = $pdo->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                  AND role = 'customer'
                LIMIT 1
            ");

            $stmt->execute([$customer_id]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $error_message = 'Customer account could not be found.';

            } elseif (!password_verify($current_password, $user['password'])) {

                $error_message = 'Your current password is incorrect.';

            } else {

                /* ==============================
                   HASH NEW PASSWORD
                ============================== */

                $hashed_password = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        password = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND role = 'customer'
                ");

                $stmt->execute([
                    $hashed_password,
                    $customer_id
                ]);

                $success_message = 'Your password has been changed successfully.';
            }

        } catch (PDOException $e) {

            $error_message = 'Unable to change your password right now. Please try again.';
        }
    }
}


/* ==============================
   GET CUSTOMER DETAILS
============================== */

$stmt = $pdo->prepare("
    SELECT
        full_name,
        email,
        status,
        created_at
    FROM users
    WHERE id = ?
      AND role = 'customer'
    LIMIT 1
");

$stmt->execute([$customer_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    session_destroy();
    header("Location: ../login.php");
    exit();
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
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/customer-dashboard.css"
    >

</head>

<body>

<div class="customer-dashboard">

    <!-- ==============================
         SIDEBAR
    =============================== -->

    <aside class="customer-sidebar">

        <div class="customer-logo">
            <i class="fas fa-store"></i>
            <span>GloryMarket</span>
        </div>

        <nav class="customer-nav">

            <a href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fas fa-shopping-bag"></i>
                <span>Products</span>
            </a>

            <a href="cart.php">

                <i class="fas fa-cart-shopping"></i>

                <span>Cart</span>

                <?php if ($cart_count > 0): ?>

                    <span class="cart-badge">
                        <?= $cart_count ?>
                    </span>

                <?php endif; ?>

            </a>

            <a href="orders.php">
                <i class="fas fa-box"></i>
                <span>My Orders</span>
            </a>

            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>

            <a href="security.php" class="active">
                <i class="fas fa-shield-halved"></i>
                <span>Security</span>
            </a>

            <a href="../logout.php">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- ==============================
         MAIN CONTENT
    =============================== -->

    <main class="customer-main">

        <div class="security-container">

            <!-- PAGE HEADER -->

            <div class="security-page-header">

                <h1>
                    Security
                </h1>

                <p>
                    Manage your account password and security.
                </p>

            </div>


            <!-- ALERTS -->

            <?php if ($success_message): ?>

                <div class="security-alert success">

                    <i class="fas fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars($success_message) ?>
                    </span>

                </div>

            <?php endif; ?>


            <?php if ($error_message): ?>

                <div class="security-alert error">

                    <i class="fas fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($error_message) ?>
                    </span>

                </div>

            <?php endif; ?>


            <div class="security-layout">

                <!-- ==============================
                     CHANGE PASSWORD
                =============================== -->

                <section class="security-card">

                    <div class="security-card-header">

                        <div class="security-card-icon">
                            <i class="fas fa-lock"></i>
                        </div>

                        <div>

                            <h2>
                                Change Password
                            </h2>

                            <p>
                                Update your password to keep your account secure.
                            </p>

                        </div>

                    </div>


                    <form
                        method="POST"
                        class="security-form"
                    >

                        <div class="security-form-group">

                            <label for="current_password">
                                Current Password
                            </label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    id="current_password"
                                    name="current_password"
                                    required
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="current_password"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>

                            </div>

                        </div>


                        <div class="security-form-group">

                            <label for="new_password">
                                New Password
                            </label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    id="new_password"
                                    name="new_password"
                                    required
                                    minlength="8"
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="new_password"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>

                            </div>

                            <small>
                                Password must contain at least 8 characters.
                            </small>

                        </div>


                        <div class="security-form-group">

                            <label for="confirm_password">
                                Confirm New Password
                            </label>

                            <div class="password-input">

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    required
                                    minlength="8"
                                >

                                <button
                                    type="button"
                                    class="toggle-password"
                                    data-target="confirm_password"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>

                            </div>

                        </div>


                        <div class="password-requirements">

                            <h3>
                                <i class="fas fa-circle-info"></i>
                                Password Requirements
                            </h3>

                            <ul>

                                <li>
                                    <i class="fas fa-check"></i>
                                    At least 8 characters
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Different from your current password
                                </li>

                                <li>
                                    <i class="fas fa-check"></i>
                                    Use a password you do not share with others
                                </li>

                            </ul>

                        </div>


                        <div class="security-form-actions">

                            <button
                                type="submit"
                                class="change-password-button"
                            >
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>

                        </div>

                    </form>

                </section>


                <!-- ==============================
                     ACCOUNT SECURITY
                =============================== -->

                <aside>

                    <div class="security-card account-security-card">

                        <div class="security-card-header">

                            <div class="security-card-icon">
                                <i class="fas fa-shield-halved"></i>
                            </div>

                            <div>

                                <h2>
                                    Account Security
                                </h2>

                                <p>
                                    Your account information.
                                </p>

                            </div>

                        </div>


                        <div class="security-account-info">

                            <div>
                                <span>
                                    Account
                                </span>

                                <strong>
                                    <?= htmlspecialchars($customer['email']) ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    Status
                                </span>

                                <strong class="security-active">
                                    <i class="fas fa-circle"></i>
                                    <?= htmlspecialchars(
                                        ucfirst($customer['status'])
                                    ) ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    Member Since
                                </span>

                                <strong>
                                    <?= date(
                                        'M d, Y',
                                        strtotime($customer['created_at'])
                                    ) ?>
                                </strong>
                            </div>

                        </div>

                    </div>


                    <div class="security-card security-tip-card">

                        <i class="fas fa-lightbulb"></i>

                        <h3>
                            Security Tip
                        </h3>

                        <p>
                            Never share your GloryMarket password with
                            anyone. Use a unique password for your account
                            and avoid using easily guessed information.
                        </p>

                    </div>

                </aside>

            </div>

        </div>

    </main>

</div>


<script>
document.querySelectorAll('.toggle-password').forEach(function(button) {

    button.addEventListener('click', function() {

        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {

            input.type = 'text';

            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');

        } else {

            input.type = 'password';

            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');

        }

    });

});
</script>

</body>
</html>