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
   HELPER: CREATE UNIQUE STORE SLUG
================================== */

function createUniqueSlug(PDO $pdo, string $store_name, int $vendor_id): string
{
    $slug = strtolower(trim($store_name));

    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');

    if ($slug === '') {
        $slug = 'store';
    }

    $base_slug = $slug;
    $counter = 1;

    while (true) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM vendor_profiles
            WHERE store_slug = ?
              AND user_id != ?
            LIMIT 1
        ");

        $stmt->execute([$slug, $vendor_id]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $counter++;
        $slug = $base_slug . '-' . $counter;
    }
}

/* ==================================
   GET VENDOR INFORMATION
================================== */

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.phone,
        u.status,

        vp.id AS profile_id,
        vp.store_name,
        vp.store_slug,
        vp.business_description,
        vp.business_phone,
        vp.business_email,
        vp.business_address,
        vp.city,
        vp.state,
        vp.country,
        vp.logo,
        vp.banner,
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
   HANDLE STORE UPDATE
================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $store_name = trim($_POST['store_name'] ?? '');
    $business_description = trim($_POST['business_description'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $country = trim($_POST['country'] ?? '');

    /* ==================================
       VALIDATION
    ================================== */

    if ($store_name === '') {
        $error = 'Store name is required.';
    } elseif ($business_email !== '' && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    }

    /* ==================================
       UPLOAD VARIABLES
    ================================== */

    $new_logo = null;
    $new_banner = null;

    $uploaded_files = [];

    /* ==================================
       UPLOAD DIRECTORY
    ================================== */

    $logo_directory = '../uploads/vendors/';
    $banner_directory = '../uploads/vendors/banners/';

    if (!is_dir($logo_directory)) {
        mkdir($logo_directory, 0755, true);
    }

    if (!is_dir($banner_directory)) {
        mkdir($banner_directory, 0755, true);
    }

    /* ==================================
       IMAGE UPLOAD FUNCTION
    ================================== */

    function uploadVendorImage(
        string $field_name,
        string $directory,
        string $prefix,
        array &$uploaded_files
    ): ?string {

        if (
            !isset($_FILES[$field_name]) ||
            $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        if ($_FILES[$field_name]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('There was an error uploading the ' . $field_name . '.');
        }

        if ($_FILES[$field_name]['size'] > 5 * 1024 * 1024) {
            throw new Exception(
                ucfirst($field_name) . ' must not exceed 5MB.'
            );
        }

        $tmp_name = $_FILES[$field_name]['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed_types[$mime_type])) {
            throw new Exception(
                'Invalid ' . $field_name . ' format. Use JPG, PNG, or WEBP.'
            );
        }

        $extension = $allowed_types[$mime_type];

        $filename =
            $prefix . '_' .
            time() . '_' .
            bin2hex(random_bytes(5)) .
            '.' .
            $extension;

        $destination = $directory . $filename;

        if (!move_uploaded_file($tmp_name, $destination)) {
            throw new Exception(
                'Failed to save the uploaded ' . $field_name . '.'
            );
        }

        $uploaded_files[] = $destination;

        return $destination;
    }

    try {

        if ($error === '') {

            /* ==================================
               GENERATE STORE SLUG
            ================================== */

            $store_slug = createUniqueSlug(
                $pdo,
                $store_name,
                $vendor_id
            );

            /* ==================================
               UPLOAD LOGO
            ================================== */

            $logo_path = uploadVendorImage(
                'logo',
                $logo_directory,
                'vendor_logo_' . $vendor_id,
                $uploaded_files
            );

            /* ==================================
               UPLOAD BANNER
            ================================== */

            $banner_path = uploadVendorImage(
                'banner',
                $banner_directory,
                'vendor_banner_' . $vendor_id,
                $uploaded_files
            );

            /* ==================================
               KEEP OLD IMAGES IF NO NEW IMAGE
            ================================== */

            $final_logo = $logo_path !== null
                ? str_replace('../', '', $logo_path)
                : ($vendor['logo'] ?? null);

            $final_banner = $banner_path !== null
                ? str_replace('../', '', $banner_path)
                : ($vendor['banner'] ?? null);

            /* ==================================
               CREATE PROFILE IF MISSING
            ================================== */

            $pdo->beginTransaction();

            if (!empty($vendor['profile_id'])) {

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
                        country = ?,
                        logo = ?,
                        banner = ?,
                        updated_at = NOW()
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
                    $final_logo,
                    $final_banner,
                    $vendor_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO vendor_profiles
                    (
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
                        logo,
                        banner,
                        verification_status,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        'pending',
                        NOW(),
                        NOW()
                    )
                ");

                $stmt->execute([
                    $vendor_id,
                    $store_name,
                    $store_slug,
                    $business_description,
                    $business_phone,
                    $business_email,
                    $business_address,
                    $city,
                    $state,
                    $country,
                    $final_logo,
                    $final_banner
                ]);
            }

            $pdo->commit();

            /* ==================================
               DELETE OLD LOGO
            ================================== */

            if (
                $logo_path !== null &&
                !empty($vendor['logo'])
            ) {

                $old_logo = '../' . ltrim($vendor['logo'], '/');

                if (
                    file_exists($old_logo) &&
                    is_file($old_logo)
                ) {
                    unlink($old_logo);
                }
            }

            /* ==================================
               DELETE OLD BANNER
            ================================== */

            if (
                $banner_path !== null &&
                !empty($vendor['banner'])
            ) {

                $old_banner = '../' . ltrim($vendor['banner'], '/');

                if (
                    file_exists($old_banner) &&
                    is_file($old_banner)
                ) {
                    unlink($old_banner);
                }
            }

            header("Location: store.php?updated=1");
            exit();
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        /* Remove newly uploaded files if database update failed */

        foreach ($uploaded_files as $file) {

            if (
                file_exists($file) &&
                is_file($file)
            ) {
                unlink($file);
            }
        }

        if ($error === '') {
            $error = 'Unable to update your store. Please try again.';
        }
    }
}

/* ==================================
   SUCCESS MESSAGE
================================== */

if (isset($_GET['updated'])) {
    $success = 'Your store information has been updated successfully.';
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

    <title>My Store - GloryMarket</title>

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

            <a
                href="store.php"
                class="active"
            >
                <i class="fa-solid fa-store"></i>
                My Store
            </a>

            <a href="profile.php">
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

                <h1>My Store</h1>

                <p>
                    Manage your public store information.
                </p>

            </div>

            <div class="vendor-topbar-actions">

                <a
                    href="products.php"
                    class="vendor-outline-btn"
                >
                    <i class="fa-solid fa-box"></i>
                    My Products
                </a>

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
             VERIFICATION STATUS
        ================================== -->

        <div class="store-status-card">

            <div>

                <span class="store-status-label">
                    Store Verification
                </span>

                <?php if ($vendor['verification_status'] === 'verified'): ?>

                    <h3 class="store-status-verified">
                        <i class="fa-solid fa-circle-check"></i>
                        Verified
                    </h3>

                    <p>
                        Your store has been verified by the administrator.
                    </p>

                <?php elseif ($vendor['verification_status'] === 'rejected'): ?>

                    <h3 class="store-status-rejected">
                        <i class="fa-solid fa-circle-xmark"></i>
                        Rejected
                    </h3>

                    <p>
                        Your vendor application was rejected.
                    </p>

                <?php else: ?>

                    <h3 class="store-status-pending">
                        <i class="fa-solid fa-clock"></i>
                        Pending Verification
                    </h3>

                    <p>
                        Your store is waiting for administrator verification.
                    </p>

                <?php endif; ?>

            </div>

            <div class="store-status-icon">

                <i class="fa-solid fa-store"></i>

            </div>

        </div>


        <!-- ==================================
             STORE FORM
        ================================== -->

        <form
            method="POST"
            enctype="multipart/form-data"
            class="store-form"
        >

            <!-- STORE BASIC INFORMATION -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-store"></i>
                            Store Information
                        </h2>

                        <p>
                            Information customers will see about your store.
                        </p>

                    </div>

                </div>

                <div class="store-form-grid">

                    <div class="store-form-group">

                        <label for="store_name">
                            Store Name
                        </label>

                        <input
                            type="text"
                            id="store_name"
                            name="store_name"
                            value="<?= htmlspecialchars(
                                $vendor['store_name'] ?? ''
                            ) ?>"
                            required
                            maxlength="150"
                        >

                    </div>

                    <div class="store-form-group">

                        <label>
                            Store URL Slug
                        </label>

                        <input
                            type="text"
                            value="<?= htmlspecialchars(
                                $vendor['store_slug'] ?? ''
                            ) ?>"
                            readonly
                        >

                        <small>
                            The store URL slug is generated automatically
                            from your store name.
                        </small>

                    </div>

                    <div class="store-form-group store-form-full">

                        <label for="business_description">
                            Store Description
                        </label>

                        <textarea
                            id="business_description"
                            name="business_description"
                            rows="5"
                            maxlength="2000"
                            placeholder="Tell customers about your store..."
                        ><?= htmlspecialchars(
                            $vendor['business_description'] ?? ''
                        ) ?></textarea>

                    </div>

                </div>

            </div>


            <!-- CONTACT INFORMATION -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-address-book"></i>
                            Business Contact
                        </h2>

                        <p>
                            Provide contact information for your business.
                        </p>

                    </div>

                </div>

                <div class="store-form-grid">

                    <div class="store-form-group">

                        <label for="business_phone">
                            Business Phone
                        </label>

                        <input
                            type="text"
                            id="business_phone"
                            name="business_phone"
                            value="<?= htmlspecialchars(
                                $vendor['business_phone'] ?? ''
                            ) ?>"
                            maxlength="30"
                        >

                    </div>

                    <div class="store-form-group">

                        <label for="business_email">
                            Business Email
                        </label>

                        <input
                            type="email"
                            id="business_email"
                            name="business_email"
                            value="<?= htmlspecialchars(
                                $vendor['business_email'] ?? ''
                            ) ?>"
                            maxlength="150"
                        >

                    </div>

                </div>

            </div>


            <!-- BUSINESS ADDRESS -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-location-dot"></i>
                            Business Address
                        </h2>

                        <p>
                            Where your business is located.
                        </p>

                    </div>

                </div>

                <div class="store-form-grid">

                    <div class="store-form-group store-form-full">

                        <label for="business_address">
                            Address
                        </label>

                        <textarea
                            id="business_address"
                            name="business_address"
                            rows="3"
                            maxlength="500"
                        ><?= htmlspecialchars(
                            $vendor['business_address'] ?? ''
                        ) ?></textarea>

                    </div>

                    <div class="store-form-group">

                        <label for="city">
                            City
                        </label>

                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= htmlspecialchars(
                                $vendor['city'] ?? ''
                            ) ?>"
                            maxlength="100"
                        >

                    </div>

                    <div class="store-form-group">

                        <label for="state">
                            State
                        </label>

                        <input
                            type="text"
                            id="state"
                            name="state"
                            value="<?= htmlspecialchars(
                                $vendor['state'] ?? ''
                            ) ?>"
                            maxlength="100"
                        >

                    </div>

                    <div class="store-form-group">

                        <label for="country">
                            Country
                        </label>

                        <input
                            type="text"
                            id="country"
                            name="country"
                            value="<?= htmlspecialchars(
                                $vendor['country'] ?? 'Nigeria'
                            ) ?>"
                            maxlength="100"
                        >

                    </div>

                </div>

            </div>


            <!-- STORE IMAGES -->

            <div class="vendor-panel">

                <div class="vendor-panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-images"></i>
                            Store Branding
                        </h2>

                        <p>
                            Upload your store logo and banner.
                        </p>

                    </div>

                </div>

                <div class="store-branding-grid">

                    <!-- LOGO -->

                    <div class="branding-card">

                        <h3>Store Logo</h3>

                        <div class="branding-preview logo-preview">

                            <?php if (!empty($vendor['logo'])): ?>

                                <img
                                    id="logoPreview"
                                    src="../<?= htmlspecialchars(
                                        $vendor['logo']
                                    ) ?>"
                                    alt="Store Logo"
                                >

                            <?php else: ?>

                                <img
                                    id="logoPreview"
                                    src="../assets/images/vendor-default.png"
                                    alt="Store Logo"
                                >

                            <?php endif; ?>

                        </div>

                        <label
                            for="logo"
                            class="upload-button"
                        >
                            <i class="fa-solid fa-upload"></i>
                            Choose Logo
                        </label>

                        <input
                            type="file"
                            id="logo"
                            name="logo"
                            accept=".jpg,.jpeg,.png,.webp"
                            hidden
                        >

                        <small>
                            JPG, PNG or WEBP. Maximum 5MB.
                        </small>

                    </div>


                    <!-- BANNER -->

                    <div class="branding-card">

                        <h3>Store Banner</h3>

                        <div class="branding-preview banner-preview">

                            <?php if (!empty($vendor['banner'])): ?>

                                <img
                                    id="bannerPreview"
                                    src="../<?= htmlspecialchars(
                                        $vendor['banner']
                                    ) ?>"
                                    alt="Store Banner"
                                >

                            <?php else: ?>

                                <div class="banner-placeholder">
                                    <i class="fa-solid fa-image"></i>
                                    <span>
                                        No banner uploaded
                                    </span>
                                </div>

                            <?php endif; ?>

                        </div>

                        <label
                            for="banner"
                            class="upload-button"
                        >
                            <i class="fa-solid fa-upload"></i>
                            Choose Banner
                        </label>

                        <input
                            type="file"
                            id="banner"
                            name="banner"
                            accept=".jpg,.jpeg,.png,.webp"
                            hidden
                        >

                        <small>
                            JPG, PNG or WEBP. Maximum 5MB.
                        </small>

                    </div>

                </div>

            </div>


            <!-- SAVE -->

            <div class="store-form-actions">

                <a
                    href="dashboard.php"
                    class="vendor-cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="vendor-primary-btn"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Store Information
                </button>

            </div>

        </form>

    </main>

</div>


<script>

/* ==================================
   LOGO PREVIEW
================================== */

const logoInput = document.getElementById('logo');
const logoPreview = document.getElementById('logoPreview');

if (logoInput) {

    logoInput.addEventListener('change', function () {

        const file = this.files[0];

        if (file) {

            const reader = new FileReader();

            reader.onload = function (event) {

                logoPreview.src = event.target.result;

            };

            reader.readAsDataURL(file);
        }

    });

}


/* ==================================
   BANNER PREVIEW
================================== */

const bannerInput = document.getElementById('banner');
const bannerPreview = document.getElementById('bannerPreview');

if (bannerInput) {

    bannerInput.addEventListener('change', function () {

        const file = this.files[0];

        if (file) {

            const reader = new FileReader();

            reader.onload = function (event) {

                if (bannerPreview) {

                    bannerPreview.src = event.target.result;

                } else {

                    const placeholder =
                        document.querySelector('.banner-placeholder');

                    if (placeholder) {

                        placeholder.outerHTML =
                            '<img id="bannerPreview" ' +
                            'src="' + event.target.result + '" ' +
                            'alt="Store Banner">';

                    }

                }

            };

            reader.readAsDataURL(file);
        }

    });

}

</script>

</body>
</html>