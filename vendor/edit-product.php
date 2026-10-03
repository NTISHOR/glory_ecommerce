<?php
session_start();

require_once '../config/db.php';
$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'vendor'
) {
    header("Location: ../login.php");
    exit;
}

/* ==============================
   VENDOR AUTHENTICATION
============================== */

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header("Location: ../login.php");
    exit();
}

$vendor_id = (int) $_SESSION['user_id'];

/* ==============================
   DIRECT PRODUCT EDIT DISABLED
   VENDORS MUST SUBMIT A REQUEST
============================== */

$product_id = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);

if ($product_id > 0) {
    header(
        "Location: product-change-request.php?id=" .
        urlencode($product_id)
    );
    exit();
}

header("Location: products.php");
exit();



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
   GET PRODUCT
   OWNERSHIP CHECK INCLUDED
============================== */

$stmt = $pdo->prepare("
    SELECT
        id,
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
    FROM products
    WHERE id = ?
      AND vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $vendor_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
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

$name = $product['name'];
$description = $product['description'];
$price = $product['price'];
$stock = $product['stock'];
$category_id = (int) $product['category_id'];
$status = $product['status'];

$error = '';

/* ==============================
   UPDATE PRODUCT
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

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

    } elseif (
        $stock === '' ||
        filter_var($stock, FILTER_VALIDATE_INT) === false
    ) {

        $error = 'Please enter a valid stock quantity.';

    } elseif ((int) $stock < 0) {

        $error = 'Stock quantity cannot be negative.';

    } elseif ($category_id <= 0) {

        $error = 'Please select a product category.';

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = 'Invalid product status.';
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
       IMAGE UPLOAD
    ============================== */

    $new_image_path = null;

    if (
        $error === '' &&
        isset($_FILES['image']) &&
        $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

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
                    pathinfo(
                        $original_name,
                        PATHINFO_EXTENSION
                    )
                );

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime_type = $finfo->file(
                    $_FILES['image']['tmp_name']
                );

                if (
                    !in_array(
                        $extension,
                        $allowed_extensions,
                        true
                    )
                ) {

                    $error =
                        'Only JPG, JPEG, PNG, and WEBP images are allowed.';

                } elseif (
                    !in_array(
                        $mime_type,
                        $allowed_mime_types,
                        true
                    )
                ) {

                    $error =
                        'The uploaded file is not a valid image.';
                }
            }


            /* ==============================
               SAVE NEW IMAGE
            ============================== */

            if ($error === '') {

                $upload_directory = '../uploads/products/';

                if (!is_dir($upload_directory)) {
                    mkdir($upload_directory, 0755, true);
                }

                $file_name =
                    'product_' .
                    $vendor_id .
                    '_' .
                    time() .
                    '_' .
                    bin2hex(random_bytes(5)) .
                    '.' .
                    $extension;

                $destination =
                    $upload_directory . $file_name;

                if (!move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $destination
                )) {

                    $error =
                        'Failed to save the product image.';

                } else {

                    $new_image_path =
                        'uploads/products/' . $file_name;
                }
            }
        }
    }


    /* ==============================
       UPDATE DATABASE
    ============================== */

    if ($error === '') {

        try {

            if ($new_image_path !== null) {

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        name = ?,
                        description = ?,
                        price = ?,
                        image = ?,
                        category_id = ?,
                        stock = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND vendor_id = ?
                ");

                $stmt->execute([
                    $name,
                    $description,
                    (float) $price,
                    $new_image_path,
                    $category_id,
                    (int) $stock,
                    $status,
                    $product_id,
                    $vendor_id
                ]);

            } else {

                $stmt = $pdo->prepare("
                    UPDATE products
                    SET
                        name = ?,
                        description = ?,
                        price = ?,
                        category_id = ?,
                        stock = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND vendor_id = ?
                ");

                $stmt->execute([
                    $name,
                    $description,
                    (float) $price,
                    $category_id,
                    (int) $stock,
                    $status,
                    $product_id,
                    $vendor_id
                ]);
            }


            /* ==============================
               DELETE OLD IMAGE
            ============================== */

            if (
                $new_image_path !== null &&
                !empty($product['image'])
            ) {

                $old_image = '../' .
                    ltrim($product['image'], '/');

                if (
                    file_exists($old_image) &&
                    is_file($old_image)
                ) {
                    unlink($old_image);
                }
            }


            header(
                "Location: products.php?updated=1&product=" .
                urlencode($product_id)
            );

            exit();

        } catch (PDOException $e) {

            /*
             * If database update failed,
             * remove the newly uploaded image.
             */

            if ($new_image_path !== null) {

                $uploaded_file =
                    '../' . $new_image_path;

                if (file_exists($uploaded_file)) {
                    unlink($uploaded_file);
                }
            }

            $error =
                'Unable to update the product. Please try again.';
        }
    }
}


/* ==============================
   IMAGE PATH
============================== */

$current_image = !empty($product['image'])
    ? '../' . ltrim($product['image'], '/')
    : '../assets/images/product-placeholder.png';


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

    <title>Edit Product - Vendor Dashboard</title>

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
                        $vendor['store_name']
                            ?: $vendor['full_name']
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

            <a href="products.php" class="active">
                <i class="fas fa-box"></i>
                <span>My Products</span>
            </a>

            <a href="add-product.php">
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

                <h1>Edit Product</h1>

                <p>
                    Update your product information.
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
                        Edit Product
                    </h2>

                    <p>
                        Update the information for this product.
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

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int) $product_id ?>"
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
                                Update the main details of your product.
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
                                    min="0.01"
                                    step="0.01"
                                    required
                                >

                            </div>

                        </div>


                        <div class="vendor-form-row">

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
                                    min="0"
                                    step="1"
                                    required
                                >

                            </div>


                            <div class="vendor-form-group">

                                <label for="status">
                                    Product Status
                                    <span>*</span>
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    required
                                >

                                    <option
                                        value="active"
                                        <?= $status === 'active'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= $status === 'inactive'
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                </select>

                            </div>

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
                                required
                            ><?= htmlspecialchars($description) ?></textarea>

                            <small class="vendor-input-help">
                                Keep the description clear and useful to customers.
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
                                Upload a new image or keep the current one.
                            </p>

                        </div>

                    </div>


                    <div class="vendor-form-body">

                        <div class="vendor-image-upload">

                            <div class="vendor-image-preview">

                                <img
                                    id="productImagePreview"
                                    src="<?= htmlspecialchars($current_image) ?>"
                                    alt="Product Image"
                                    onerror="this.src='../assets/images/product-placeholder.png';"
                                >

                            </div>


                            <div class="vendor-image-upload-content">

                                <label
                                    for="image"
                                    class="vendor-upload-btn"
                                >
                                    <i class="fas fa-upload"></i>
                                    Change Image
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
                        <i class="fas fa-save"></i>
                        Save Changes
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

        };

        reader.readAsDataURL(file);

    });

}

</script>

</body>
</html>