
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

// Super Admin access only
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

// Validate customer ID
$customer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$customer_id || $customer_id <= 0) {
    header("Location: customers.php?error=invalid_id");
    exit;
}

// Process deletion only through POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: customer-view.php?id=" . $customer_id);
    exit;
}

try {

    // Confirm that the account exists and belongs to a customer
    $stmt = $pdo->prepare("
        SELECT id, full_name, email
        FROM users
        WHERE id = ?
        AND role = 'customer'
        LIMIT 1
    ");

    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        header("Location: customers.php?error=not_found");
        exit;
    }

    // Check whether the customer has existing orders
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM orders
        WHERE customer_id = ?
    ");

    $stmt->execute([$customer_id]);
    $order_count = (int) $stmt->fetchColumn();

    if ($order_count > 0) {
        header(
            "Location: customer-view.php?id=" .
            $customer_id .
            "&error=has_orders"
        );
        exit;
    }

    // Begin transaction
    $pdo->beginTransaction();

    // Delete customer profile
    $stmt = $pdo->prepare("
        DELETE FROM customer_profiles
        WHERE user_id = ?
    ");

    $stmt->execute([$customer_id]);

    // Delete customer account
    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id = ?
        AND role = 'customer'
    ");

    $stmt->execute([$customer_id]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception("Customer account could not be deleted.");
    }

    // Record activity
    logAdminActivity(
        $pdo,
        (int) $_SESSION['user_id'],
        'DELETE_CUSTOMER',
        'Permanently deleted customer: ' .
        $customer['full_name'] .
        ' (' . $customer['email'] . ')'
    );

    // Commit transaction
    $pdo->commit();

    header("Location: customers.php?success=deleted");
    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Customer deletion error: " . $e->getMessage());

    header(
        "Location: customer-view.php?id=" .
        $customer_id .
        "&error=delete_failed"
    );
    exit;
}
