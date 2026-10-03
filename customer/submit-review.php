
<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

/* =====================================
   CUSTOMER AUTHENTICATION
===================================== */

if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header("Location: ../login.php");
    exit;
}

$customer_id = (int) $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Customer';

/* =====================================
   GET PRODUCT AND ORDER
===================================== */

$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
}

if (!$product_id || !$order_id || $product_id < 1 || $order_id < 1) {
    header("Location: orders.php");
    exit;
}

/* =====================================
   VERIFY PURCHASE AND DELIVERY
===================================== */

$stmt = $pdo->prepare("
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        p.image,
        oi.order_id,
        o.order_number,
        o.order_status
    FROM order_items oi
    INNER JOIN orders o
        ON o.id = oi.order_id
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE oi.product_id = ?
      AND oi.order_id = ?
      AND o.customer_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $order_id,
    $customer_id
]);

$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    http_response_code(403);
    exit("You are not authorized to review this product.");
}

if ($purchase['order_status'] !== 'delivered') {
    http_response_code(403);
    exit("You can only review products from delivered orders.");
}

/* =====================================
   CHECK EXISTING REVIEW
===================================== */

$stmt = $pdo->prepare("
    SELECT id, rating, review_text, status
    FROM product_reviews
    WHERE customer_id = ?
      AND product_id = ?
    LIMIT 1
");

$stmt->execute([$customer_id, $product_id]);

$existing_review = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$success = '';

$rating = $existing_review['rating'] ?? 0;
$review_text = $existing_review['review_text'] ?? '';

/* =====================================
   SUBMIT OR UPDATE REVIEW
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($existing_review) {
        $errors[] = "You have already reviewed this product.";
    } else {

        $rating = filter_input(
            INPUT_POST,
            'rating',
            FILTER_VALIDATE_INT
        );

        $review_text = trim($_POST['review_text'] ?? '');

        if (!$rating || $rating < 1 || $rating > 5) {
            $errors[] = "Please select a rating between 1 and 5 stars.";
        }

        if (mb_strlen($review_text) > 3000) {
            $errors[] = "Your review cannot exceed 3000 characters.";
        }

        if (empty($errors)) {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO product_reviews (
                        product_id,
                        customer_id,
                        order_id,
                        rating,
                        review_text,
                        status,
                        created_at,
                        updated_at
                    )
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");

                $stmt->execute([
                    $product_id,
                    $customer_id,
                    $order_id,
                    $rating,
                    $review_text
                ]);

                header(
                    "Location: order-view.php?id=" .
                    $order_id .
                    "&review_submitted=1"
                );

                exit;

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {
                    $errors[] = "You have already reviewed this product.";
                } else {
                    $errors[] = "Unable to submit your review. Please try again.";
                }
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

    <title>Write a Review | GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/customer-dashboard.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


</head>

<body>

<div class="customer-dashboard">

    <aside class="customer-sidebar">

        <div class="customer-brand">
            <h2>Glory<span>Market</span></h2>
            <small>Customer Panel</small>
        </div>

        <nav class="customer-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-house"></i>
                <span>Dashboard</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-store"></i>
                <span>Browse Products</span>
            </a>

            <a href="cart.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>My Cart</span>
            </a>

            <a href="orders.php" class="active">
                <i class="fa-solid fa-box"></i>
                <span>My Orders</span>
            </a>

            <a href="profile.php">
                <i class="fa-solid fa-user"></i>
                <span>My Profile</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>

    <main class="customer-main">

        <header class="customer-topbar">

            <div>
                <h1>Write a Review</h1>
                <p>Share your experience with this product.</p>
            </div>

            <div class="customer-profile">
                <i class="fa-solid fa-circle-user"></i>

                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Customer</small>
                </div>
            </div>

        </header>

        <div class="review-container">

            <div class="review-card">

                <div class="review-product">

                    <?php if (!empty($purchase['image'])): ?>

                        <img
                            src="../<?= htmlspecialchars(ltrim($purchase['image'], '/')) ?>"
                            alt="<?= htmlspecialchars($purchase['product_name']) ?>">

                    <?php else: ?>

                        <img
                            src="../assets/images/product-placeholder.png"
                            alt="Product image">

                    <?php endif; ?>

                    <div>
                        <h2><?= htmlspecialchars($purchase['product_name']) ?></h2>

                        <p>
                            Order:
                            <?= htmlspecialchars($purchase['order_number']) ?>
                        </p>

                        <p>
                            <i class="fa-solid fa-circle-check"
                               style="color:#16a34a"></i>
                            Delivered
                        </p>
                    </div>

                </div>

                <?php if (!empty($errors)): ?>

                    <div class="review-error">

                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

                <?php if ($existing_review): ?>

                    <div class="review-status">
                        <strong>You have already reviewed this product.</strong>
                        <p>
                            Your review status is:
                            <?= htmlspecialchars(ucfirst($existing_review['status'])) ?>
                        </p>
                    </div>

                    <a href="order-view.php?id=<?= $order_id ?>"
                       class="customer-primary-btn"
                       style="display:inline-block;margin-top:20px;text-decoration:none;">
                        Back to Order
                    </a>

                <?php else: ?>

                    <form method="POST">

                        <input type="hidden"
                               name="product_id"
                               value="<?= (int)$product_id ?>">

                        <input type="hidden"
                               name="order_id"
                               value="<?= (int)$order_id ?>">

                        <div class="review-field">

                            <label>How would you rate this product?</label>

                            <div class="review-stars">

                                <?php for ($i = 5; $i >= 1; $i--): ?>

                                    <input
                                        type="radio"
                                        id="star<?= $i ?>"
                                        name="rating"
                                        value="<?= $i ?>"
                                        <?= (int)$rating === $i ? 'checked' : '' ?>
                                        required>

                                    <label for="star<?= $i ?>"
                                           title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                                        ★
                                    </label>

                                <?php endfor; ?>

                            </div>

                        </div>

                        <div class="review-field">

                            <label for="review_text">
                                Your Review (Optional)
                            </label>

                            <textarea
                                id="review_text"
                                name="review_text"
                                maxlength="3000"
                                placeholder="Tell other customers about your experience..."><?= htmlspecialchars($review_text) ?></textarea>

                        </div>

                        <div class="review-actions">

                            <button type="submit"
                                    class="customer-primary-btn review-submit">
                                <i class="fa-solid fa-paper-plane"></i>
                                Submit Review
                            </button>

                            <a href="order-view.php?id=<?= $order_id ?>"
                               class="review-cancel">
                                Cancel
                            </a>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>

</body>
</html>