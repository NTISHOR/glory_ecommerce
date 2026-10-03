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

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Add Category
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {

        $error = "Category name is required.";

    } else {

        /*
        | Check duplicate
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM categories
            WHERE name = ?
            LIMIT 1
        ");

        $stmt->execute([$name]);

        if ($stmt->fetch()) {

            $error = "A category with this name already exists.";

        } else {

            $stmt = $pdo->prepare("
                INSERT INTO categories (
                    name,
                    description,
                    status
                )
                VALUES (?, ?, 'active')
            ");

            $stmt->execute([
                $name,
                $description
            ]);

            logAdminActivity(
                $pdo,
                $_SESSION['user_id'],
                'CREATE_CATEGORY',
                "Created category: {$name}."
            );

            $success = "Category created successfully.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Get Categories
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
    ORDER BY created_at DESC
");

$stmt->execute();

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Categories | Glory E-commerce</title>

    <link
        rel="stylesheet"
        href="../assets/css/admin-dashboard.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

</head>

<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">

        <div class="sidebar-brand">

            <h2>
                Glory<span>Market</span>
            </h2>

            <small>
                Super Admin Panel
            </small>

        </div>


        <nav class="sidebar-nav">

            <a href="dashboard.php">
                <i class="fas fa-dashboard"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fas fa-key"></i>
                <span>Permissions</span>
            </a>

            <a href="vendors.php">
                <i class="fas fa-store"></i>
                <span>Vendors</span>
            </a>

            <a href="customers.php">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>

            <a href="products.php">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>

            <a href="categories.php" class="active">
                <i class="fas fa-list"></i>
                <span>Categories</span>
            </a>

            <a href="orders.php">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>

            <a href="payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <a href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>

            <a href="activity-logs.php">
                <i class="fas fa-history"></i>
                <span>Activity Logs</span>
            </a>

            <a href="settings.php">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>

            <a
                href="../logout.php"
                class="logout-link"
            >
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <main class="main-content">

        <?php if ($success): ?>

            <div class="success-message">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="topbar">

            <div>

                <h1>Manage Categories</h1>

                <p>
                    Create and manage product categories.
                </p>

            </div>

        </div>


        <!-- Add Category -->

        <div class="form-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Add Category
                    </h2>

                    <p>
                        Create a category for vendor products.
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
                        maxlength="100"
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
                        rows="4"
                    ></textarea>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        <i class="fas fa-plus"></i>
                        Add Category
                    </button>

                </div>

            </form>

        </div>


        <!-- Category List -->

        <div class="table-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Product Categories
                    </h2>

                    <p>
                        Categories currently available on GloryMarket.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="orders-table">

                    <thead>

                        <tr>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date Created</th>
                            <th>Action</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!empty($categories)): ?>

                        <?php foreach ($categories as $category): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </strong>
                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $category['description'] ?: '-'
                                    ) ?>

                                </td>


                                <td>

                                    <span class="status-badge status-<?= htmlspecialchars($category['status']) ?>">

                                        <?= htmlspecialchars(
                                            ucfirst($category['status'])
                                        ) ?>

                                    </span>

                                </td>


                                <td>
                                    <?= htmlspecialchars(
                                        $category['created_at']
                                    ) ?>
                                </td>


                                <td>

                                    <a
                                        href="category-edit.php?id=<?= $category['id'] ?>"
                                        class="view-btn"
                                    >
                                        Edit
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty-state"
                            >

                                <i class="fas fa-list"></i>

                                <p>
                                    No categories have been created yet.
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

</div>

</body>

</html>