
<?php

session_start();

require_once __DIR__ . '/../config/db.php';

$pdo = getDbConnection();

/* =====================================
   AUTHENTICATION AND ACCESS CONTROL
===================================== */

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION['role'];

// Allow only Super Admin and Admin
if (!in_array($role, ['super_admin', 'admin'], true)) {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Admin';

$dashboard_role = ($role === 'super_admin')
    ? 'Super Admin'
    : 'Admin';

/* =====================================
   DASHBOARD STATISTICS
===================================== */

// Total customers
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'customer'
");
$stmt->execute();
$total_customers = (int) $stmt->fetchColumn();

// Total vendors
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'vendor'
");
$stmt->execute();
$total_vendors = (int) $stmt->fetchColumn();

// Total products
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
");
$stmt->execute();
$total_products = (int) $stmt->fetchColumn();

// Total orders
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orders
");
$stmt->execute();
$total_orders = (int) $stmt->fetchColumn();

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | Glory E-commerce</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="dashboard-wrapper">

    <!-- Sidebar -->
    <aside class="sidebar">

        <div class="sidebar-brand">
            <h2>Glory<span>Market</span></h2>
            <small>Super Admin Panel</small>
        </div>

        <nav class="sidebar-nav">

            <a href="dashboard.php" class="active">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <a href="admins.php">
                <i class="fas fa-user-shield"></i>
                <span>Manage Admins</span>
            </a>

            <a href="permissions.php">
                <i class="fas fa-lock"></i>
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

            <a href="categories.php">
                <i class="fas fa-tags"></i>
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

            <a href="../logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>

        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($full_name) ?>!</p>
            </div>

            <div class="admin-profile">
                <i class="fas fa-user-circle"></i>
                <div>
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small>Super Admin</small>
                </div>
            </div>
        </header>

        <!-- Dashboard Statistics -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <p>Total Customers</p>
                    <h2><?= number_format($total_customers) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <p>Total Vendors</p>
                    <h2><?= number_format($total_vendors) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-box"></i>
                </div>
                <div>
                    <p>Total Products</p>
                    <h2><?= number_format($total_products) ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div>
                    <p>Total Orders</p>
                    <h2><?= number_format($total_orders) ?></h2>
                </div>
            </div>

        </section>

        <!-- Dashboard Welcome Panel -->
        <section class="welcome-panel">
            <h2>Welcome to Glory E-commerce</h2>
            <p>
                Manage your marketplace, monitor business activities,
                and oversee vendors and customers from one central location.
            </p>
        </section>

    </main>
</div>

</body>
</html>