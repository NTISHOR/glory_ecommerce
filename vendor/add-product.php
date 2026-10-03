<?php
session_start();

require_once '../config/db.php';
$pdo = getDbConnection();

/* ==============================
   VENDOR AUTHENTICATION
============================== */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

/* ==============================
   VENDOR INFORMATION
============================== */

$stmt = $pdo->prepare("
    SELECT
        u.full_name,
        u.email,
        u.status,
        vp.store_name,
        vp.logo,
        vp.verification_status
    FROM users u
    LEFT JOIN vendor_profiles vp ON vp.user_id = u.id
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

/* ==============================
   CATEGORIES
============================== */

$stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ==============================
   FORM VARIABLES
============================== */

$name = '';
$description = '';
$price = '';
$stock = '';
$category_id = '';

$error = '';
$success = '';

/* ==============================
   ADD PRODUCT
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);

    /* ==============================
       VALIDATION
    ============================== */

    if ($name === '') {
        $error = 'Product name is required.';

    } elseif (mb_strlen($name) < 2) {
        $error = 'Product name must contain at least 2 characters.';

    } elseif ($description === '') {
        $error = 'Product description is required.';

    } elseif ($price === '' || !is_numeric($price)) {
        $error = 'Please enter a valid product price.';

    } elseif ((float) $price <= 0) {
        $error = 'Product price must be greater than zero.';

    } elseif ($stock === '' || filter_var($stock, FILTER_VALIDATE_INT) === false) {
        $error = 'Please enter a valid stock quantity.';

    } elseif ((int) $stock < 0) {
        $error = 'Stock quantity cannot be negative.';

    } elseif ($category_id <= 0) {
        $error = 'Please select a product category.';
    }

    /* ==============================
       VERIFY CATEGORY
    ============================== */

    if ($error === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$category_id]);

        if (!$stmt->fetch()) {
            $error = 'The selected category does not exist.';
        }
    }

    /* ==============================
       IMAGE VALIDATION
    ============================== */

    $image_path = null;

    if ($error === '' && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = 'There was a problem uploading the product image.';
        } else {

            $max_size = 5 * 1024 * 1024;

            if ($_FILES['image']['size'] > $max_size) {
                $error = 'Product image must not exceed 5MB.';
            }

            if ($error === '') {

                $allowed_extensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ];

                $allowed_mime_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                $original_name = $_FILES['image']['name'];

                $extension = strtolower(
                    pathinfo($original_name, PATHINFO_EXTENSION)
                );

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime_type = $finfo->file(
                    $_FILES['image']['tmp_name']
                );

                if (!in_array($extension, $allowed_extensions, true)) {
                    $error = 'Only JPG, JPEG, PNG, and WEBP images are allowed.';
                } elseif (!in_array($mime_type, $allowed_mime_types, true)) {
                    $error = 'The uploaded file is not a valid image.';
                }
            }

            /* ==============================
               UPLOAD IMAGE
            ============================== */

            if ($error === '') {

                $upload_directory = '../uploads/products/';

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0755, true);
                }

                $file_name = 'product_' .
                    $vendor_id . '_' .
                    time() . '_' .
                    bin2hex(random_bytes(5)) .
                    '.' .
                    $extension;

                $destination = $upload_directory . $file_name;

                if (!move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $destination
                )) {
                    $error = 'Failed to save the product image.';
                } else {

                    /*
                     * Store the path relative to the project root.
                     * Example:
                     * uploads/products/product_1_xxxxx.jpg
                     */
                    $image_path = 'uploads/products/' . $file_name;
                }
            }
        }
    }

    /* ==============================
       INSERT PRODUCT
    ============================== */

    if ($error === '') {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO products
                (
                    vendor_id,
                    name,
                    description,
                    price,
                    image,
                    category_id,
                    stock,
                    status,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW()
                )
            ");

            $stmt->execute([
                $vendor_id,
                $name,
                $description,
                (float) $price,
                $image_path,
                $category_id,
                (int) $stock
            ]);

            $product_id = $pdo->lastInsertId();

            header(
                "Location: products.php?success=1&product=" .
                urlencode($product_id)
            );

            exit();

        } catch (PDOException $e) {

            /*
             * If the database insert fails after an image
             * has already been uploaded, remove the image.
             */
            if ($image_path !== null) {

                $uploaded_file = '../' . $image_path;

                if (file_exists($uploaded_file)) {
                    unlink($uploaded_file);
                }
            }

            $error = 'Unable to create the product. Please try again.';
        }
    }
}

/* ==============================
   VENDOR LOGO
============================== */

$vendor_logo = !empty($vendor['logo'])
    ? '../' . ltrim($vendor['logo'], '/')
    : '../assets/images/vendor-default.png';

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Product - Vendor Dashboard</title>

    <link
        rel="stylesheet"
        href="../assets/css/vendor-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

</head>

<body>

<div class="vendor-dashboard">

    <!-- ==============================
         SIDEBAR
    =============================== -->

    <aside class="vendor-sidebar">

        <div class="vendor-logo">

            <h2>GloryMarket</h2>

            <span>Vendor Panel</span>

        </div>


        <div class="vendor-store-mini">

            <img
                src="<?= htmlspecialchars($vendor_logo) ?>"
                alt="Store Logo"
                onerror="this.src='../assets/images/vendor-default.png';"
            >

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $vendor['store_name'] ?: $vendor['full_name']
                    ) ?>
                </strong>

                <small>Vendor</small>

            </div>

        </div>


        <nav class="vendor-nav">

            <a href="dashboard.php">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>My Products</span>
            </a>

            <a href="add-product.php" class="active">
                <i class="fas fa-plus-circle"></i>
                <span>Add Product</span>
            </a>

            <a href="orders.php">
                <i class="fas fa-shopping-bag"></i>
                <span>Orders</span>
            </a>

            <a href="sales.php">
                <i class="fas fa-chart-column"></i>
                <span>Sales</span>
            </a>

            <a href="store.php">
                <i class="fas fa-store"></i>
                <span>My Store</span>
            </a>

            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>

            <a href="security.php">
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

    <main class="vendor-main">

        <div class="vendor-topbar">

            <div>

                <h1>Add Product</h1>

                <p>
                    Add a new product to your store.
                </p>

            </div>


            <div class="vendor-user">

                <img
                    src="<?= htmlspecialchars($vendor_logo) ?>"
                    alt="Vendor"
                    onerror="this.src='../assets/images/vendor-default.png';"
                >

                <div>

                    <strong>
                        <?= htmlspecialchars($vendor['full_name']) ?>
                    </strong>

                    <small>Vendor</small>

                </div>

            </div>

        </div>


        <!-- VERIFICATION NOTICE -->

        <?php if (($vendor['verification_status'] ?? '') === 'pending'): ?>

            <div class="vendor-alert vendor-alert-warning">

                <i class="fas fa-clock"></i>

                <div>

                    <strong>
                        Vendor verification pending
                    </strong>

                    <p>
                        Your vendor account is awaiting administrator verification.
                    </p>

                </div>

            </div>

        <?php elseif (($vendor['verification_status'] ?? '') === 'rejected'): ?>

            <div class="vendor-alert vendor-alert-danger">

                <i class="fas fa-circle-xmark"></i>

                <div>

                    <strong>
                        Vendor application rejected
                    </strong>

                    <p>
                        Your vendor application has been rejected.
                    </p>

                </div>

            </div>

        <?php endif; ?>


        <div class="vendor-add-product-container">

            <div class="vendor-add-product-header">

                <div>

                    <h2>
                        Product Information
                    </h2>

                    <p>
                        Enter the details of the product you want to sell.
                    </p>

                </div>

                <a
                    href="products.php"
                    class="vendor-back-btn"
                >
                    <i class="fas fa-arrow-left"></i>
                    Back to Products
                </a>

            </div>


            <?php if ($error !== ''): ?>

                <div class="vendor-form-alert vendor-form-alert-danger">

                    <i class="fas fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($error) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                enctype="multipart/form-data"
                class="vendor-product-form"
            >

                <!-- BASIC INFORMATION -->

                <div class="vendor-form-card">

                    <div class="vendor-form-card-header">

                        <i class="fas fa-box"></i>

                        <div>

                            <h3>
                                Basic Information
                            </h3>

                            <p>
                                Provide the main details of your product.
                            </p>

                        </div>

                    </div>


                    <div class="vendor-form-body">

                        <div class="vendor-form-group">

                            <label for="name">
                                Product Name
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="<?= htmlspecialchars($name) ?>"
                                placeholder="Enter product name"
                                maxlength="255"
                                required
                            >

                        </div>


                        <div class="vendor-form-row">

                            <div class="vendor-form-group">

                                <label for="category_id">
                                    Category
                                    <span>*</span>
                                </label>

                                <select
                                    name="category_id"
                                    id="category_id"
                                    required
                                >

                                    <option value="">
                                        Select category
                                    </option>

                                    <?php foreach ($categories as $category): ?>

                                        <option
                                            value="<?= (int) $category['id'] ?>"
                                            <?= $category_id == $category['id']
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= htmlspecialchars(
                                                $category['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="vendor-form-group">

                                <label for="price">
                                    Price (₦)
                                    <span>*</span>
                                </label>

                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    value="<?= htmlspecialchars($price) ?>"
                                    placeholder="0.00"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>


                        <div class="vendor-form-group">

                            <label for="stock">
                                Stock Quantity
                                <span>*</span>
                            </label>

                            <input
                                type="number"
                                id="stock"
                                name="stock"
                                value="<?= htmlspecialchars($stock) ?>"
                                placeholder="Enter available quantity"
                                min="0"
                                step="1"
                                required
                            >

                        </div>


                        <div class="vendor-form-group">

                            <label for="description">
                                Product Description
                                <span>*</span>
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="7"
                                maxlength="5000"
                                placeholder="Describe your product..."
                                required
                            ><?= htmlspecialchars($description) ?></textarea>

                            <small class="vendor-input-help">
                                Give customers useful information about the product.
                            </small>

                        </div>

                    </div>

                </div>


                <!-- PRODUCT IMAGE -->

                <div class="vendor-form-card">

                    <div class="vendor-form-card-header">

                        <i class="fas fa-image"></i>

                        <div>

                            <h3>
                                Product Image
                            </h3>

                            <p>
                                Upload an image customers can use to identify the product.
                            </p>

                        </div>

                    </div>


                    <div class="vendor-form-body">

                        <div class="vendor-image-upload">

                            <div class="vendor-image-preview">

                                <img
                                    id="productImagePreview"
                                    src="../assets/images/product-placeholder.png"
                                    alt="Product Preview"
                                    onerror="this.style.display='none';"
                                >

                                <i
                                    id="productImageIcon"
                                    class="fas fa-image"
                                ></i>

                            </div>


                            <div class="vendor-image-upload-content">

                                <label
                                    for="image"
                                    class="vendor-upload-btn"
                                >
                                    <i class="fas fa-upload"></i>
                                    Choose Image
                                </label>

                                <input
                                    type="file"
                                    id="image"
                                    name="image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >

                                <p>
                                    JPG, JPEG, PNG or WEBP.
                                    Maximum size: 5MB.
                                </p>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- FORM ACTIONS -->

                <div class="vendor-product-form-actions">

                    <a
                        href="products.php"
                        class="vendor-cancel-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="vendor-save-btn"
                    >
                        <i class="fas fa-plus"></i>
                        Add Product
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>


<script>

/* =========================================
   PRODUCT IMAGE PREVIEW
========================================= */

const imageInput = document.getElementById('image');
const imagePreview = document.getElementById('productImagePreview');
const imageIcon = document.getElementById('productImageIcon');

if (imageInput) {

    imageInput.addEventListener('change', function () {

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

            alert(
                'Please select a JPG, JPEG, PNG, or WEBP image.'
            );

            this.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {

            alert(
                'The selected image is larger than 5MB.'
            );

            this.value = '';
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {

            imagePreview.src = event.target.result;
            imagePreview.style.display = 'block';
            imageIcon.style.display = 'none';

        };

        reader.readAsDataURL(file);

    });

}

</script>

</body>
</html>