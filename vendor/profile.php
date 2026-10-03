
<?php
session_start();

require_once '../config/db.php';
$pdo = getDbConnection();

/* ==================================
   VENDOR AUTHENTICATION
================================== */

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'vendor'
) {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

$success = '';
$error = '';

/* ==================================
   GET VENDOR PROFILE
================================== */

$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.phone,
        u.profile_picture,
        u.status,
        u.created_at,

        vp.store_name,
        vp.store_slug,
        vp.logo,
        vp.verification_status,
        vp.business_phone,
        vp.business_email

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
   HANDLE PROFILE UPDATE
================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $new_profile_picture = null;
    $uploaded_file_path = null;

    if ($full_name === '') {

        $error = 'Full name is required.';

    } elseif (strlen($full_name) < 2) {

        $error = 'Please enter a valid full name.';

    } else {

        try {

            /* ==================================
               HANDLE PROFILE PICTURE UPLOAD
            ================================== */

            if (
                isset($_FILES['profile_picture']) &&
                $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if (
                    $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK
                ) {
                    throw new Exception(
                        'Unable to upload profile picture.'
                    );
                }

                $file = $_FILES['profile_picture'];

                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception(
                        'Profile picture must not exceed 5MB.'
                    );
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file['tmp_name']);

                $allowed_types = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed_types[$mime_type])) {
                    throw new Exception(
                        'Only JPG, PNG, and WEBP images are allowed.'
                    );
                }

                $upload_directory = '../uploads/vendors/profiles/';

                if (!is_dir($upload_directory)) {

                    if (!mkdir($upload_directory, 0755, true)) {
                        throw new Exception(
                            'Unable to create upload directory.'
                        );
                    }
                }

                $filename = 'vendor_' . $vendor_id . '_' .
                    bin2hex(random_bytes(16)) . '.' .
                    $allowed_types[$mime_type];

                $destination = $upload_directory . $filename;

                if (
                    !move_uploaded_file(
                        $file['tmp_name'],
                        $destination
                    )
                ) {
                    throw new Exception(
                        'Failed to save profile picture.'
                    );
                }

                $new_profile_picture =
                    'uploads/vendors/profiles/' . $filename;

                $uploaded_file_path = $destination;
            }

            /* ==================================
               UPDATE PROFILE
            ================================== */

            $pdo->beginTransaction();

            if ($new_profile_picture !== null) {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        phone = ?,
                        profile_picture = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $full_name,
                    $phone,
                    $new_profile_picture,
                    $vendor_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        full_name = ?,
                        phone = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $full_name,
                    $phone,
                    $vendor_id
                ]);
            }

            $pdo->commit();

            /* ==================================
               DELETE OLD PROFILE PICTURE
               Only after successful database update
            ================================== */

            if ($new_profile_picture !== null) {

                $old_picture = $vendor['profile_picture'] ?? '';

                if (
                    !empty($old_picture) &&
                    strpos(
                        $old_picture,
                        'uploads/vendors/profiles/'
                    ) === 0
                ) {

                    $old_picture_path = '../' . $old_picture;

                    if (
                        is_file($old_picture_path) &&
                        realpath($old_picture_path) !==
                        realpath($uploaded_file_path)
                    ) {
                        unlink($old_picture_path);
                    }
                }
            }

            /* ==================================
               SYNCHRONIZE SESSION
            ================================== */

            $_SESSION['fullname'] = $full_name;

            header("Location: profile.php?updated=1");
            exit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /* Remove newly uploaded file if update fails */

            if (
                $uploaded_file_path !== null &&
                is_file($uploaded_file_path)
            ) {
                unlink($uploaded_file_path);
            }

            $error = $e->getMessage();
        }
    }
}

/* ==================================
   SUCCESS MESSAGE
================================== */

if (isset($_GET['updated'])) {
    $success = 'Your profile has been updated successfully.';
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

/* ==================================
   PROFILE IMAGE
================================== */

if (!empty($vendor['profile_picture'])) {

    $profile_image = '../' . $vendor['profile_picture'];

} elseif (!empty($vendor['logo'])) {

    $profile_image = '../' . $vendor['logo'];

} else {

    $profile_image = '../assets/images/vendor-default.png';
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
        href="../assets/css/vendor-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        /* ==================================
           PROFILE PICTURE UPLOAD
        ================================== */

        .vendor-profile-avatar img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #e2e8f0;
            background: #fff;
        }

        .profile-picture-upload {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
        }

        .profile-picture-upload input[type="file"] {
            width: 100%;
            padding: 10px;
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            cursor: pointer;
        }

        .profile-picture-upload small {
            color: #64748b;
            font-size: 13px;
        }

        .profile-picture-preview {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .profile-picture-preview img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #e2e8f0;
            background: #fff;
        }

        .profile-picture-preview span {
            color: #64748b;
            font-size: 13px;
        }
    </style>

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

            <a
                href="profile.php"
                class="active"
            >
                <i class="fa-solid fa-user"></i>
                Profile
            </a>

            <a href="security.php">
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

                <h1>My Profile</h1>

                <p>
                    Manage your personal account information.
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
             PROFILE OVERVIEW
        ================================== -->

        <div class="vendor-profile-layout">

            <!-- PROFILE CARD -->

            <div class="vendor-panel vendor-profile-card">

                <div class="vendor-profile-avatar">

                    <img
                        src="<?= htmlspecialchars($profile_image) ?>"
                        alt="Vendor Profile Picture"
                        id="profileImagePreview"
                    >

                </div>

                <h2>
                    <?= htmlspecialchars($vendor['full_name']) ?>
                </h2>

                <p>
                    <?= htmlspecialchars($vendor['email']) ?>
                </p>

                <span class="vendor-profile-role">
                    Vendor
                </span>

                <div class="vendor-profile-status">

                    <?php if ($vendor['status'] === 'active'): ?>

                        <span class="profile-status-active">
                            <i class="fa-solid fa-circle"></i>
                            Active Account
                        </span>

                    <?php elseif ($vendor['status'] === 'suspended'): ?>

                        <span class="profile-status-suspended">
                            <i class="fa-solid fa-circle"></i>
                            Suspended
                        </span>

                    <?php else: ?>

                        <span class="profile-status-inactive">
                            <i class="fa-solid fa-circle"></i>
                            Inactive
                        </span>

                    <?php endif; ?>

                </div>

            </div>

            <!-- PROFILE INFORMATION -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-user-pen"></i>
                            Personal Information
                        </h2>

                        <p>
                            Update your account details.
                        </p>

                    </div>

                </div>

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="vendor-profile-form"
                >

                    <div class="profile-form-grid">

                        <!-- PROFILE PICTURE -->

                        <div class="profile-form-group profile-form-full">

                            <label for="profile_picture">
                                Profile Picture
                            </label>

                            <div class="profile-picture-upload">

                                <div class="profile-picture-preview">

                                    <img
                                        src="<?= htmlspecialchars($profile_image) ?>"
                                        alt="Profile Picture Preview"
                                        id="uploadImagePreview"
                                    >

                                    <span>
                                        Select a new image to change your
                                        profile picture.
                                    </span>

                                </div>

                                <input
                                    type="file"
                                    id="profile_picture"
                                    name="profile_picture"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <small>
                                    JPG, PNG, or WEBP. Maximum size: 5MB.
                                </small>

                            </div>

                        </div>

                        <!-- FULL NAME -->

                        <div class="profile-form-group">

                            <label for="full_name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                value="<?= htmlspecialchars(
                                    $vendor['full_name']
                                ) ?>"
                                required
                                maxlength="150"
                            >

                        </div>

                        <!-- PHONE -->

                        <div class="profile-form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                value="<?= htmlspecialchars(
                                    $vendor['phone'] ?? ''
                                ) ?>"
                                maxlength="30"
                            >

                        </div>

                        <!-- EMAIL -->

                        <div class="profile-form-group profile-form-full">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                value="<?= htmlspecialchars(
                                    $vendor['email']
                                ) ?>"
                                readonly
                            >

                            <small>
                                Your email address cannot be changed here.
                            </small>

                        </div>

                    </div>

                    <div class="profile-form-actions">

                        <button
                            type="submit"
                            class="vendor-primary-btn"
                        >
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Changes
                        </button>

                    </div>

                </form>

            </div>

        </div>

        <!-- ==================================
             ACCOUNT INFORMATION
        ================================== -->

        <div class="vendor-panel vendor-account-info">

            <div class="vendor-panel-header">

                <div>

                    <h2>
                        <i class="fa-solid fa-circle-info"></i>
                        Account Information
                    </h2>

                    <p>
                        Details about your GloryMarket vendor account.
                    </p>

                </div>

            </div>

            <div class="account-info-grid">

                <div class="account-info-item">

                    <span>Account ID</span>

                    <strong>
                        #<?= (int) $vendor['id'] ?>
                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Account Type</span>

                    <strong>
                        Vendor
                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Store Name</span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['store_name'] ?: 'Not set'
                        ) ?>
                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Verification</span>

                    <strong>

                        <?php if (
                            $vendor['verification_status'] === 'verified'
                        ): ?>

                            Verified

                        <?php elseif (
                            $vendor['verification_status'] === 'rejected'
                        ): ?>

                            Rejected

                        <?php else: ?>

                            Pending

                        <?php endif; ?>

                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Business Phone</span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['business_phone'] ?: 'Not set'
                        ) ?>
                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Business Email</span>

                    <strong>
                        <?= htmlspecialchars(
                            $vendor['business_email'] ?: 'Not set'
                        ) ?>
                    </strong>

                </div>

                <div class="account-info-item">

                    <span>Account Created</span>

                    <strong>
                        <?= date(
                            'M d, Y',
                            strtotime($vendor['created_at'])
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>

        <!-- ==================================
             SECURITY SHORTCUT
        ================================== -->

        <div class="vendor-security-shortcut">

            <div>

                <div class="security-shortcut-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <div>

                    <h3>
                        Keep your account secure
                    </h3>

                    <p>
                        Change your password regularly and protect
                        your vendor account.
                    </p>

                </div>

            </div>

            <a
                href="security.php"
                class="vendor-outline-btn"
            >
                Security Settings
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </div>

    </main>

</div>

<!-- ==================================
     LIVE PROFILE IMAGE PREVIEW
================================== -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    const fileInput = document.getElementById('profile_picture');

    const uploadPreview = document.getElementById('uploadImagePreview');

    const profilePreview = document.getElementById('profileImagePreview');

    if (fileInput) {

        fileInput.addEventListener('change', function () {

            const file = this.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {

                alert('Please select a JPG, PNG, or WEBP image.');

                this.value = '';

                return;
            }

            if (file.size > 5 * 1024 * 1024) {

                alert('Image must not exceed 5MB.');

                this.value = '';

                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {

                uploadPreview.src = event.target.result;

                profilePreview.src = event.target.result;

            };

            reader.readAsDataURL(file);

        });

    }

});
</script>

</body>
</html>