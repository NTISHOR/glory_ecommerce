<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$category_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$category_id) {
    header("Location: categories.php");
    exit;
}

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Fetch Category
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        description,
        status,
        created_at,
        updated_at
    FROM categories
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$category_id]);

$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header("Location: categories.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Update Category
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? '';

    if ($name === '') {

        $error = "Category name is required.";

    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = "Invalid category status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Category Name
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
              AND id != ?
            LIMIT 1
        ");

        $stmt->execute([
            $name,
            $category_id
        ]);

        if ($stmt->fetch()) {

            $error = "Another category with this name already exists.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Update Category
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE categories
                SET
                    name = ?,
                    description = ?,
                    status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name,
                $description,
                $status,
                $category_id
            ]);

            /*
            |--------------------------------------------------------------------------
            | Activity Log
            |--------------------------------------------------------------------------
            */

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'EDIT_CATEGORY',
                "Updated category from '{$category['name']}' to '{$name}'. Status: {$status}."
            );

            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $success = "Category updated successfully.";

            /*
            |--------------------------------------------------------------------------
            | Refresh Category Data
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    name,
                    description,
                    status,
                    created_at,
                    updated_at
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$category_id]);

            $category = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Category - GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>GloryMarket</h2>

            <small>Super Admin Panel</small>

        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fa-solid fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fa-solid fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php">
                <i class="fa-solid fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php" class="active">
                <i class="fa-solid fa-tags"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>

            <a href="../logout.php" class="logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <main class="main-content">

        <div class="topbar">

            <div>
                <h1>Edit Category</h1>
                <p>Update category information and status.</p>
            </div>

        </div>


        <!-- MESSAGES -->

        <?php if (!empty($success)): ?>

            <div class="success-message">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if (!empty($error)): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- EDIT FORM -->

        <div class="form-panel">

            <div class="panel-header">

                <div>
                    <h2>Edit Category</h2>

                    <p>
                        Modify the category information below.
                    </p>
                </div>

            </div>


            <form method="POST">

                <div class="form-group">

                    <label for="name">
                        Category Name
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($category['name']) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="description">
                        Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                    ><?= htmlspecialchars($category['description'] ?? '') ?></textarea>

                </div>


                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="active"
                            <?= $category['status'] === 'active' ? 'selected' : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $category['status'] === 'inactive' ? 'selected' : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <div class="form-actions">

                    <a
                        href="categories.php"
                        class="secondary-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fa-solid fa-save"></i>
                        Update Category
                    </button>

                </div>

            </form>

        </div>


        <!-- CATEGORY INFORMATION -->

        <div class="table-panel">

            <div class="panel-header">

                <div>
                    <h2>Category Information</h2>
                </div>

            </div>


            <div class="admin-details">

                <div class="detail-item">

                    <strong>Category ID</strong>

                    <span>
                        #<?= htmlspecialchars($category['id']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Current Status</strong>

                    <span>
                        <span class="status-badge status-<?= htmlspecialchars($category['status']) ?>">
                            <?= htmlspecialchars(ucfirst($category['status'])) ?>
                        </span>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Date Created</strong>

                    <span>
                        <?= htmlspecialchars($category['created_at']) ?>
                    </span>

                </div>


                <div class="detail-item">

                    <strong>Last Updated</strong>

                    <span>
                        <?= htmlspecialchars($category['updated_at']) ?>
                    </span>

                </div>

            </div>

        </div>

    </main>

</div>

</body>
</html>