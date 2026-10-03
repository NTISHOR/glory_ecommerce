<?php
session_start();

require_once '../config/db.php';
require_once 'admin_activity.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$admin_id = (int) $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Super Admin';

$request_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($request_id <= 0) {
    header("Location: product-change-requests.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$stmt = $pdo->prepare("
    SELECT
        pcr.*,

        p.name AS current_product_name,
        p.description AS current_description,
        p.price AS current_price,
        p.stock AS current_stock,
        p.category_id AS current_category_id,

        current_category.name AS current_category_name,
        requested_category.name AS requested_category_name,

        u.full_name AS vendor_name,
        u.email AS vendor_email,

        reviewer.full_name AS reviewer_name

    FROM product_change_requests pcr

    INNER JOIN products p
        ON p.id = pcr.product_id

    INNER JOIN users u
        ON u.id = pcr.vendor_id

    LEFT JOIN categories current_category
        ON current_category.id = p.category_id

    LEFT JOIN categories requested_category
        ON requested_category.id = pcr.requested_category_id

    LEFT JOIN users reviewer
        ON reviewer.id = pcr.reviewed_by

    WHERE pcr.id = ?

    LIMIT 1
");

$stmt->execute([$request_id]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header("Location: product-change-requests.php");
    exit;
}

$success_message = '';
$error_message = '';

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

        $action = $_POST['action'] ?? '';
        $review_note = trim($_POST['review_note'] ?? '');

        if (!in_array($action, ['approve', 'reject'], true)) {

            $error_message = 'Invalid request action.';

        } elseif ($request['status'] !== 'pending') {

            $error_message = 'This request has already been reviewed.';

        } elseif ($action === 'reject' && $review_note === '') {

            $error_message = 'Please provide a reason for rejecting this request.';

        } else {

            try {

                $pdo->beginTransaction();

                /*
                 * APPROVE REQUEST
                 */
                if ($action === 'approve') {

                    $update_product = $pdo->prepare("
                        UPDATE products
                        SET
                            name = COALESCE(?, name),
                            description = COALESCE(?, description),
                            price = COALESCE(?, price),
                            stock = COALESCE(?, stock),
                            category_id = COALESCE(?, category_id),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");

                    $update_product->execute([
                        $request['requested_name'],
                        $request['requested_description'],
                        $request['requested_price'],
                        $request['requested_stock'],
                        $request['requested_category_id'],
                        $request['product_id']
                    ]);

                    $update_request = $pdo->prepare("
                        UPDATE product_change_requests
                        SET
                            status = 'approved',
                            reviewed_by = ?,
                            reviewed_at = NOW(),
                            review_note = ?
                        WHERE id = ?
                          AND status = 'pending'
                    ");

                    $update_request->execute([
                        $admin_id,
                        $review_note !== '' ? $review_note : null,
                        $request_id
                    ]);

                    logAdminActivity(
                        $pdo,
                        $admin_id,
                        'Approved Product Change Request',
                        'Approved change request #' . $request_id .
                        ' for product #' . $request['product_id']
                    );

                    $pdo->commit();

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header(
                        "Location: product-change-request-view.php?id=" .
                        $request_id .
                        "&success=approved"
                    );
                    exit;
                }

                /*
                 * REJECT REQUEST
                 */
                if ($action === 'reject') {

                    $update_request = $pdo->prepare("
                        UPDATE product_change_requests
                        SET
                            status = 'rejected',
                            reviewed_by = ?,
                            reviewed_at = NOW(),
                            review_note = ?
                        WHERE id = ?
                          AND status = 'pending'
                    ");

                    $update_request->execute([
                        $admin_id,
                        $review_note,
                        $request_id
                    ]);

                    logAdminActivity(
                        $pdo,
                        $admin_id,
                        'Rejected Product Change Request',
                        'Rejected change request #' . $request_id .
                        ' for product #' . $request['product_id']
                    );

                    $pdo->commit();

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header(
                        "Location: product-change-request-view.php?id=" .
                        $request_id .
                        "&success=rejected"
                    );
                    exit;
                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error_message =
                    'Unable to process the request. Please try again.';
            }
        }
    }
}

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'approved') {
        $success_message = 'Product change request approved successfully.';
    }

    if ($_GET['success'] === 'rejected') {
        $success_message = 'Product change request rejected successfully.';
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Review Product Change Request
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 250px;
            min-height: 100vh;
            background: #111827;
            color: #fff;
            padding: 20px 0;
            flex-shrink: 0;
        }

        .sidebar-logo {
            padding: 0 22px 25px;
            font-size: 21px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .sidebar-logo span {
            color: #3b82f6;
        }

        .sidebar-menu {
            list-style: none;
            margin: 20px 0 0;
            padding: 0;
        }

        .sidebar-menu li {
            margin: 4px 12px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: #d1d5db;
            text-decoration: none;
            border-radius: 7px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #2563eb;
            color: #fff;
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }

        .admin-main {
            flex: 1;
            min-width: 0;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            color: #6b7280;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .request-card {
            background: #fff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .request-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .vendor-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }

        .info-label {
            display: block;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 600;
        }

        .comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .comparison-column {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .comparison-title {
            padding: 15px;
            font-weight: 700;
            background: #f3f4f6;
        }

        .comparison-column.requested .comparison-title {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .comparison-row {
            padding: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .comparison-row strong {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: #6b7280;
        }

        .comparison-row p {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .reason-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 18px;
            border-radius: 8px;
        }

        .reason-box strong {
            display: block;
            margin-bottom: 8px;
        }

        textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            border: none;
            padding: 12px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
            color: #fff;
        }

        .btn-approve {
            background: #16a34a;
        }

        .btn-reject {
            background: #dc2626;
        }

        .btn-back {
            background: #6b7280;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 800px) {

            .admin-layout {
                display: block;
            }

            .admin-sidebar {
                width: 100%;
                min-height: auto;
            }

            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding: 0 10px 10px;
            }

            .sidebar-menu li {
                flex-shrink: 0;
            }

            .admin-main {
                padding: 20px;
            }

            .comparison,
            .vendor-info {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="admin-layout">

    <aside class="admin-sidebar">

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
                    <span>Products</span>
                </a>
            </li>

            <li>
                <a
                    href="product-change-requests.php"
                    class="active"
                >
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
                <a href="payments.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payments</span>
                </a>
            </li>

            <li>
                <a href="vendors.php">
                    <i class="fas fa-store"></i>
                    <span>Vendors</span>
                </a>
            </li>

            <li>
                <a href="customers.php">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>

            <li>
                <a href="categories.php">
                    <i class="fas fa-list"></i>
                    <span>Categories</span>
                </a>
            </li>

            <li>
                <a href="admin-activity-logs.php">
                    <i class="fas fa-history"></i>
                    <span>Activity Logs</span>
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

    <main class="admin-main">

        <div class="page-header">

            <h1>
                Review Product Change Request
            </h1>

            <p>
                Review the vendor's requested product changes before approval.
            </p>

        </div>

        <?php if ($success_message): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($success_message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error_message): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error_message) ?>
            </div>

        <?php endif; ?>

        <div class="request-card">

            <h2>Request Information</h2>

            <div class="vendor-info">

                <div class="info-box">

                    <span class="info-label">
                        Vendor
                    </span>

                    <span class="info-value">
                        <?= htmlspecialchars($request['vendor_name']) ?>
                    </span>

                </div>

                <div class="info-box">

                    <span class="info-label">
                        Email
                    </span>

                    <span class="info-value">
                        <?= htmlspecialchars($request['vendor_email']) ?>
                    </span>

                </div>

                <div class="info-box">

                    <span class="info-label">
                        Request Status
                    </span>

                    <span class="status status-<?= htmlspecialchars($request['status']) ?>">
                        <?= htmlspecialchars(ucfirst($request['status'])) ?>
                    </span>

                </div>

                <div class="info-box">

                    <span class="info-label">
                        Date Submitted
                    </span>

                    <span class="info-value">
                        <?= date(
                            'M d, Y h:i A',
                            strtotime($request['created_at'])
                        ) ?>
                    </span>

                </div>

            </div>

        </div>

        <div class="request-card">

            <h2>
                Product Changes
            </h2>

            <div class="comparison">

                <!-- CURRENT PRODUCT -->

                <div class="comparison-column">

                    <div class="comparison-title">
                        Current Product
                    </div>

                    <div class="comparison-row">

                        <strong>
                            Product Name
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['current_product_name']
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Description
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['current_description'] ?? '—'
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Price
                        </strong>

                        <p>
                            ₦<?= number_format(
                                (float) $request['current_price'],
                                2
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Stock
                        </strong>

                        <p>
                            <?= number_format(
                                (int) $request['current_stock']
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Category
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['current_category_name'] ?? '—'
                            ) ?>
                        </p>

                    </div>

                </div>


                <!-- REQUESTED CHANGES -->

                <div class="comparison-column requested">

                    <div class="comparison-title">
                        Requested Changes
                    </div>

                    <div class="comparison-row">

                        <strong>
                            Product Name
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['requested_name']
                                ?? $request['current_product_name']
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Description
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['requested_description']
                                ?? $request['current_description']
                                ?? '—'
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Price
                        </strong>

                        <p>
                            ₦<?= number_format(
                                (float) (
                                    $request['requested_price']
                                    ?? $request['current_price']
                                ),
                                2
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Stock
                        </strong>

                        <p>
                            <?= number_format(
                                (int) (
                                    $request['requested_stock']
                                    ?? $request['current_stock']
                                )
                            ) ?>
                        </p>

                    </div>

                    <div class="comparison-row">

                        <strong>
                            Category
                        </strong>

                        <p>
                            <?= htmlspecialchars(
                                $request['requested_category_name']
                                ?? $request['current_category_name']
                                ?? '—'
                            ) ?>
                        </p>

                    </div>

                </div>

            </div>

        </div>

        <div class="request-card">

            <h2>
                Vendor's Reason
            </h2>

            <div class="reason-box">

                <strong>
                    Reason for Change
                </strong>

                <p>
                    <?= nl2br(
                        htmlspecialchars(
                            $request['reason'] ?? 'No reason provided.'
                        )
                    ) ?>
                </p>

            </div>

        </div>

                <?php if ($request['status'] === 'pending'): ?>

            <div class="request-card">

                <h2>
                    Admin Review
                </h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($csrf_token) ?>"
                    >

                    <div style="margin-bottom: 20px;">

                        <label
                            for="review_note"
                            style="
                                display:block;
                                margin-bottom:8px;
                                font-weight:600;
                            "
                        >
                            Review Note
                        </label>

                        <textarea
                            name="review_note"
                            id="review_note"
                            placeholder="Enter a note for the vendor. A reason is required when rejecting."
                        ></textarea>

                    </div>

                    <div class="action-buttons">

                        <button
                            type="submit"
                            name="action"
                            value="approve"
                            class="btn btn-approve"
                            onclick="return confirm(
                                'Are you sure you want to approve this product change request?'
                            );"
                        >
                            <i class="fas fa-check"></i>
                            Approve Request
                        </button>

                        <button
                            type="submit"
                            name="action"
                            value="reject"
                            class="btn btn-reject"
                            onclick="return confirm(
                                'Are you sure you want to reject this product change request?'
                            );"
                        >
                            <i class="fas fa-times"></i>
                            Reject Request
                        </button>

                        <a
                            href="product-change-requests.php"
                            class="btn btn-back"
                        >
                            <i class="fas fa-arrow-left"></i>
                            Back to Requests
                        </a>

                    </div>

                </form>

            </div>

        <?php else: ?>

            <div class="request-card">

                <h2>
                    Review Information
                </h2>

                <div class="vendor-info">

                    <div class="info-box">

                        <span class="info-label">
                            Reviewed By
                        </span>

                        <span class="info-value">
                            <?= htmlspecialchars(
                                $request['reviewer_name'] ?? 'Admin'
                            ) ?>
                        </span>

                    </div>

                    <div class="info-box">

                        <span class="info-label">
                            Reviewed At
                        </span>

                        <span class="info-value">

                            <?php if (!empty($request['reviewed_at'])): ?>

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime($request['reviewed_at'])
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </span>

                    </div>

                </div>

                <div
                    class="reason-box"
                    style="margin-top:20px;"
                >

                    <strong>
                        Admin Review Note
                    </strong>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $request['review_note']
                                ?? 'No review note provided.'
                            )
                        ) ?>
                    </p>

                </div>

                <div class="action-buttons">

                    <a
                        href="product-change-requests.php"
                        class="btn btn-back"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back to Requests
                    </a>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>

</html>