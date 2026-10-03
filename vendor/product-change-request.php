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

$vendor_id = (int) $_SESSION['user_id'];

$product_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($product_id <= 0) {
    header("Location: products.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        c.name AS category_name
    FROM products p
    LEFT JOIN categories c
        ON c.id = p.category_id
    WHERE p.id = ?
      AND p.vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $vendor_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
}

$page_title = 'Request Product Change';
$full_name = $_SESSION['full_name'] ?? 'Vendor';

$success_message = '';
$error_message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        $error_message = 'Invalid security token. Please try again.';
    } else {

        $requested_name = trim($_POST['requested_name'] ?? '');
        $requested_description = trim(
            $_POST['requested_description'] ?? ''
        );

        $requested_price = $_POST['requested_price'] ?? '';
        $requested_stock = $_POST['requested_stock'] ?? '';

        $reason = trim($_POST['reason'] ?? '');

        if (
            $requested_name === '' ||
            $requested_price === '' ||
            $requested_stock === '' ||
            $reason === ''
        ) {

            $error_message = 'Please complete all required fields.';

        } elseif (
            !is_numeric($requested_price) ||
            (float) $requested_price < 0
        ) {

            $error_message = 'Please enter a valid price.';

        } elseif (
            filter_var(
                $requested_stock,
                FILTER_VALIDATE_INT
            ) === false ||
            (int) $requested_stock < 0
        ) {

            $error_message = 'Please enter a valid stock quantity.';

        } else {

            try {

                /*
                 * Prevent multiple pending requests
                 * for the same product.
                 */
                $check = $pdo->prepare("
                    SELECT id
                    FROM product_change_requests
                    WHERE product_id = ?
                      AND vendor_id = ?
                      AND status = 'pending'
                    LIMIT 1
                ");

                $check->execute([
                    $product_id,
                    $vendor_id
                ]);

                if ($check->fetch()) {

                    $error_message =
                        'You already have a pending change request for this product.';

                } else {

                    $insert = $pdo->prepare("
                        INSERT INTO product_change_requests (
                            product_id,
                            vendor_id,
                            requested_name,
                            requested_description,
                            requested_price,
                            requested_stock,
                            reason,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");

                    $insert->execute([
                        $product_id,
                        $vendor_id,
                        $requested_name,
                        $requested_description,
                        (float) $requested_price,
                        (int) $requested_stock,
                        $reason
                    ]);

                    $success_message =
                        'Your product change request has been submitted successfully.';

                    /*
                     * Regenerate CSRF token after successful submission.
                     */
                    $_SESSION['csrf_token'] =
                        bin2hex(random_bytes(32));

                    $csrf_token = $_SESSION['csrf_token'];
                }

            } catch (PDOException $e) {

                $error_message =
                    'Unable to submit your change request. Please try again.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($page_title) ?> | GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/vendor-dashboard.css">
<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>
</head>

<body>
    <body>

<div class="vendor-layout">

    <aside class="vendor-sidebar">

        <div class="sidebar-logo">
            Glory<span>Market</span>
        </div>

        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i>
                    <span>My Products</span>
                </a>
            </li>

            <li>
                <a href="product-change-requests.php" class="active">
                    <i class="fas fa-file-pen"></i>
                    <span>Change Requests</span>
                </a>
            </li>

            <li>
                <a href="orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>

            <li>
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>

            <li>
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>

        </ul>

    </aside>

    <main class="vendor-main">

<div class="request-container">

    <div class="request-card">
        <?php if ($success_message): ?>

    <div class="success-message">
        <?= htmlspecialchars($success_message) ?>
    </div>

<?php endif; ?>

<?php if ($error_message): ?>

    <div class="error-message">
        <?= htmlspecialchars($error_message) ?>
    </div>

<?php endif; ?>

        <h2>Request Product Change</h2>

        <p>
            Submit a request to change your product.
            The changes will only take effect after admin approval.
        </p>

        <div class="request-info">

            <strong>Current Product:</strong>

            <p>
                <?= htmlspecialchars($product['name']) ?>
            </p>

            <strong>Current Price:</strong>

            <p>
                ₦<?= number_format((float) $product['price'], 2) ?>
            </p>

            <strong>Current Stock:</strong>

            <p>
                <?= (int) $product['stock'] ?>
            </p>

            <strong>Current Category:</strong>

            <p>
                <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
            </p>

        </div>

        <form method="POST">

    <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($csrf_token) ?>"
    >

            <div class="form-group">

                <label for="requested_name">
                    New Product Name
                </label>

                <input
                    type="text"
                    id="requested_name"
                    name="requested_name"
                    value="<?= htmlspecialchars($product['name']) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="requested_description">
                    New Description
                </label>

                <textarea
                    id="requested_description"
                    name="requested_description"
                ><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

            </div>

            <div class="form-group">

                <label for="requested_price">
                    New Price
                </label>

                <input
                    type="number"
                    id="requested_price"
                    name="requested_price"
                    step="0.01"
                    min="0"
                    value="<?= htmlspecialchars($product['price']) ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="requested_stock">
                    New Stock
                </label>

                <input
                    type="number"
                    id="requested_stock"
                    name="requested_stock"
                    min="0"
                    value="<?= (int) $product['stock'] ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="reason">
                    Reason for Change
                </label>

                <textarea
                    id="reason"
                    name="reason"
                    placeholder="Explain why you want to change this product..."
                    required
                ></textarea>

            </div>

            <div class="request-actions">

                <button
                    type="submit"
                    class="request-btn"
                >
                    Submit Change Request
                </button>

            </div>

        </form>

    </div>

</div>
</main>
</body>
</html>