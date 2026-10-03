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
   UPDATE PROFILE
============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');

    $allowed_genders = [
        'male',
        'female',
        'other',
        'prefer_not_to_say'
    ];

    if ($full_name === '') {
        $error_message = 'Full name is required.';
    } elseif ($gender !== '' && !in_array($gender, $allowed_genders, true)) {
        $error_message = 'Invalid gender selected.';
    } else {

        try {

            $pdo->beginTransaction();

            /* ==============================
               UPDATE USER INFORMATION
            ============================== */

            $stmt = $pdo->prepare("
                UPDATE users
                SET
                    full_name = ?,
                    phone = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND role = 'customer'
            ");

            $stmt->execute([
                $full_name,
                $phone !== '' ? $phone : null,
                $customer_id
            ]);


            /* ==============================
               CHECK CUSTOMER PROFILE
            ============================== */

            $stmt = $pdo->prepare("
                SELECT id
                FROM customer_profiles
                WHERE user_id = ?
                LIMIT 1
            ");

            $stmt->execute([$customer_id]);

            $profile_id = $stmt->fetchColumn();


            if ($profile_id) {

                $stmt = $pdo->prepare("
                    UPDATE customer_profiles
                    SET
                        date_of_birth = ?,
                        gender = ?,
                        address = ?,
                        city = ?,
                        state = ?,
                        country = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    $date_of_birth !== '' ? $date_of_birth : null,
                    $gender !== '' ? $gender : null,
                    $address !== '' ? $address : null,
                    $city !== '' ? $city : null,
                    $state !== '' ? $state : null,
                    $country !== '' ? $country : null,
                    $customer_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO customer_profiles
                    (
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
            }


            /* ==============================
               PROFILE PICTURE
            ============================== */

            if (
                isset($_FILES['profile_picture']) &&
                $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('There was a problem uploading the profile picture.');
                }

                $file = $_FILES['profile_picture'];

                $allowed_types = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);

                if (!isset($allowed_types[$mime_type])) {
                    throw new Exception('Only JPG, PNG, and WEBP images are allowed.');
                }

                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Profile picture must not exceed 5MB.');
                }

                $upload_directory = __DIR__ . '/../uploads/customers/';

                if (!is_dir($upload_directory)) {
                    if (!mkdir($upload_directory, 0755, true)) {
                        throw new Exception('Unable to create upload directory.');
                    }
                }

                $extension = $allowed_types[$mime_type];

                $filename =
                    'customer_' .
                    $customer_id .
                    '_' .
                    time() .
                    '.' .
                    $extension;

                $destination = $upload_directory . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new Exception('Unable to save the profile picture.');
                }

                $profile_picture = 'uploads/customers/' . $filename;

                $stmt = $pdo->prepare("
                    UPDATE customer_profiles
                    SET
                        profile_picture = ?,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    $profile_picture,
                    $customer_id
                ]);
            }


            $pdo->commit();

            $_SESSION['fullname'] = $full_name;

            $success_message = 'Your profile has been updated successfully.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error_message = $e->getMessage();
        }
    }
}


/* ==============================
   GET CUSTOMER PROFILE
============================== */

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
        cp.profile_picture,
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
    session_destroy();
    header("Location: ../login.php");
    exit();
}


/* ==============================
   PROFILE IMAGE
============================== */

if (!empty($customer['profile_picture'])) {
    $profile_image = '../' . ltrim(
        $customer['profile_picture'],
        '/'
    );
} else {
    $profile_image = '../assets/images/default-profile.png';
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

    <title>My Profile - GloryMarket</title>

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

            <a href="profile.php" class="active">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
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

        <div class="profile-container">

            <div class="profile-page-header">

                <div>
                    <h1>My Profile</h1>

                    <p>
                        Manage your personal information and account details.
                    </p>
                </div>

            </div>


            <?php if ($success_message): ?>

                <div class="profile-alert success">

                    <i class="fas fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars($success_message) ?>
                    </span>

                </div>

            <?php endif; ?>


            <?php if ($error_message): ?>

                <div class="profile-alert error">

                    <i class="fas fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($error_message) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                enctype="multipart/form-data"
                class="profile-form"
            >

                <!-- ==============================
                     PROFILE CARD
                =============================== -->

                <section class="profile-card profile-photo-card">

                    <div class="profile-photo-wrapper">

                        <img
                            src="<?= htmlspecialchars($profile_image) ?>"
                            alt="Profile Picture"
                            class="customer-profile-image"
                            id="profilePreview"
                            onerror="this.src='../assets/images/default-profile.png';"
                        >

                        <label
                            for="profile_picture"
                            class="profile-camera-button"
                        >
                            <i class="fas fa-camera"></i>
                        </label>

                    </div>

                    <input
                        type="file"
                        name="profile_picture"
                        id="profile_picture"
                        accept="image/jpeg,image/png,image/webp"
                        hidden
                    >

                    <h2>
                        <?= htmlspecialchars($customer['full_name']) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars($customer['email']) ?>
                    </p>

                    <span class="profile-account-status">
                        <i class="fas fa-circle"></i>
                        <?= htmlspecialchars(ucfirst($customer['status'])) ?>
                    </span>

                    <small>
                        JPG, PNG or WEBP. Maximum size: 5MB.
                    </small>

                </section>


                <!-- ==============================
                     PERSONAL INFORMATION
                =============================== -->

                <section class="profile-card">

                    <div class="profile-card-header">

                        <div>
                            <h2>
                                <i class="fas fa-user"></i>
                                Personal Information
                            </h2>

                            <p>
                                Update your basic personal information.
                            </p>
                        </div>

                    </div>


                    <div class="profile-form-grid">

                        <div class="profile-form-group">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?= htmlspecialchars($customer['full_name'] ?? '') ?>"
                                required
                            >

                        </div>


                        <div class="profile-form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                                readonly
                            >

                            <small>
                                Email address cannot be changed here.
                            </small>

                        </div>


                        <div class="profile-form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars($customer['phone'] ?? '') ?>"
                                placeholder="Enter your phone number"
                            >

                        </div>


                        <div class="profile-form-group">

                            <label for="date_of_birth">
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                value="<?= htmlspecialchars($customer['date_of_birth'] ?? '') ?>"
                            >

                        </div>


                        <div class="profile-form-group">

                            <label for="gender">
                                Gender
                            </label>

                            <select
                                id="gender"
                                name="gender"
                            >

                                <option value="">
                                    Select Gender
                                </option>

                                <option
                                    value="male"
                                    <?= ($customer['gender'] ?? '') === 'male' ? 'selected' : '' ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="female"
                                    <?= ($customer['gender'] ?? '') === 'female' ? 'selected' : '' ?>
                                >
                                    Female
                                </option>

                                <option
                                    value="other"
                                    <?= ($customer['gender'] ?? '') === 'other' ? 'selected' : '' ?>
                                >
                                    Other
                                </option>

                                <option
                                    value="prefer_not_to_say"
                                    <?= ($customer['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>
                                >
                                    Prefer not to say
                                </option>

                            </select>

                            
                        </div>

                    </div>

                </section>


                <!-- ==============================
                     ADDRESS
                =============================== -->

                <section class="profile-card">

                    <div class="profile-card-header">

                        <div>
                            <h2>
                                <i class="fas fa-location-dot"></i>
                                Address Information
                            </h2>

                            <p>
                                Your saved address can be used during checkout.
                            </p>
                        </div>

                    </div>


                    <div class="profile-form-grid">

                        <div class="profile-form-group profile-full-width">

                            <label for="address">
                                Address
                            </label>

                            <textarea
                                id="address"
                                name="address"
                                rows="3"
                                placeholder="Enter your full address"
                            ><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>

                        </div>


                        <div class="profile-form-group">

                            <label for="city">
                                City
                            </label>

                            <input
                                type="text"
                                id="city"
                                name="city"
                                value="<?= htmlspecialchars($customer['city'] ?? '') ?>"
                                placeholder="Enter city"
                            >

                        </div>


                        <div class="profile-form-group">

                            <label for="state">
                                State
                            </label>

                            <input
                                type="text"
                                id="state"
                                name="state"
                                value="<?= htmlspecialchars($customer['state'] ?? '') ?>"
                                placeholder="Enter state"
                            >

                        </div>


                        <div class="profile-form-group">

                            <label for="country">
                                Country
                            </label>

                            <input
                                type="text"
                                id="country"
                                name="country"
                                value="<?= htmlspecialchars($customer['country'] ?? 'Nigeria') ?>"
                                placeholder="Enter country"
                            >

                        </div>

                    </div>

                </section>


                <!-- ==============================
                     ACCOUNT INFORMATION
                =============================== -->

                <section class="profile-card">

                    <div class="profile-card-header">

                        <div>
                            <h2>
                                <i class="fas fa-shield-halved"></i>
                                Account Information
                            </h2>

                            <p>
                                Information about your GloryMarket account.
                            </p>
                        </div>

                    </div>


                    <div class="account-information-grid">

                        <div class="account-information-item">

                            <span>
                                Account Type
                            </span>

                            <strong>
                                Customer
                            </strong>

                        </div>


                        <div class="account-information-item">

                            <span>
                                Account Status
                            </span>

                            <strong class="account-active">
                                <?= htmlspecialchars(ucfirst($customer['status'])) ?>
                            </strong>

                        </div>


                        <div class="account-information-item">

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

                </section>


                <!-- ==============================
                     SAVE BUTTON
                =============================== -->

                <div class="profile-form-actions">

                    <a
                        href="dashboard.php"
                        class="profile-cancel-button"
                    >
                        Cancel
                    </a>

                    <button><a href="security.php">
    <i class="fas fa-shield-halved"></i>
    <span>Security</span>
</a></button>

                    <button
                        type="submit"
                        class="profile-save-button"
                    >
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>


<script>
document.getElementById('profile_picture').addEventListener('change', function (event) {

    const file = event.target.files[0];

    if (!file) {
        return;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
        document.getElementById('profilePreview').src = e.target.result;
    };

    reader.readAsDataURL(file);
});
</script>

</body>
</html>