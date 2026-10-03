
<?php
session_start();

require_once '../config/db.php';
require_once 'admin_activity.php';

$pdo = getDbConnection();

/* =====================================
   SUPER ADMIN ACCESS CONTROL
===================================== */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Super Admin';

/* =====================================
   DEFAULT SETTINGS
===================================== */

$defaults = [
    'store_name' => 'GloryMarket',
    'store_email' => '',
    'store_phone' => '',
    'store_address' => '',
    'currency' => 'NGN',
    'delivery_fee' => '0',
    'store_status' => 'active',
    'maintenance_mode' => '0'
];

/* =====================================
   FETCH SETTINGS
===================================== */

$settings = $defaults;

$stmt = $pdo->query("
    SELECT setting_key, setting_value
    FROM system_settings
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (array_key_exists($row['setting_key'], $defaults)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$success_message = '';
$error_message = '';

/* =====================================
   SAVE SETTINGS
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $store_name = trim($_POST['store_name'] ?? '');
    $store_email = trim($_POST['store_email'] ?? '');
    $store_phone = trim($_POST['store_phone'] ?? '');
    $store_address = trim($_POST['store_address'] ?? '');
    $currency = $_POST['currency'] ?? 'NGN';
    $delivery_fee = trim($_POST['delivery_fee'] ?? '0');
    $store_status = $_POST['store_status'] ?? 'active';
    $maintenance_mode = $_POST['maintenance_mode'] ?? '0';

    $allowed_currencies = ['NGN', 'USD', 'GBP', 'EUR'];
    $allowed_store_statuses = ['active', 'inactive'];
    $allowed_maintenance = ['0', '1'];

    if ($store_name === '') {
        $error_message = "Store name is required.";
    } elseif (
        $store_email !== '' &&
        !filter_var($store_email, FILTER_VALIDATE_EMAIL)
    ) {
        $error_message = "Please enter a valid store email address.";
    } elseif (
        !is_numeric($delivery_fee) ||
        (float)$delivery_fee < 0
    ) {
        $error_message = "Delivery fee must be a valid non-negative amount.";
    } elseif (!in_array($currency, $allowed_currencies, true)) {
        $error_message = "Invalid currency selected.";
    } elseif (!in_array($store_status, $allowed_store_statuses, true)) {
        $error_message = "Invalid store status.";
    } elseif (!in_array($maintenance_mode, $allowed_maintenance, true)) {
        $error_message = "Invalid maintenance mode setting.";
    } else {

        $new_settings = [
            'store_name' => $store_name,
            'store_email' => $store_email,
            'store_phone' => $store_phone,
            'store_address' => $store_address,
            'currency' => $currency,
            'delivery_fee' => number_format((float)$delivery_fee, 2, '.', ''),
            'store_status' => $store_status,
            'maintenance_mode' => $maintenance_mode
        ];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value)
            ");

            foreach ($new_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            logAdminActivity(
                $pdo,
                (int)$_SESSION['user_id'],
                'UPDATE_SYSTEM_SETTINGS',
                'Super Admin updated GloryMarket system settings.'
            );

            $pdo->commit();

            $settings = $new_settings;
            $success_message = "System settings saved successfully.";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error_message = "Unable to save settings. Please try again.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings | GloryMarket</title>

    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="dashboard-wrapper">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <h2>GloryMarket</h2>
            <p>Admin Panel</p>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>

            <a href="admins.php">
                <i class="fa-solid fa-user-shield"></i> Manage Admins
            </a>

            <a href="permissions.php">
                <i class="fa-solid fa-lock"></i> Permissions
            </a>

            <a href="vendors.php">
                <i class="fa-solid fa-store"></i> Vendors
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i> Customers
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i> Products
            </a>

            <a href="categories.php">
                <i class="fa-solid fa-list"></i> Categories
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i> Orders
            </a>

            <a href="payments.php">
                <i class="fa-solid fa-credit-card"></i> Payments
            </a>

            <a href="reports.php">
                <i class="fa-solid fa-chart-line"></i> Reports
            </a>

            <a href="activity-logs.php">
                <i class="fa-solid fa-clock-rotate-left"></i> Activity Logs
            </a>

            <a href="settings.php" class="active">
    <i class="fa-solid fa-gear"></i> Platform Settings
</a>

            <a href="../logout.php">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </nav>
    </aside>

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Platform Settings</h1>
<p>Manage GloryMarket's platform-wide configuration.</p>
            </div>

            <div class="admin-details">
                <span>Welcome, <?= htmlspecialchars($full_name) ?></span>
            </div>
        </header>

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

        <section class="form-panel">

            <div class="panel-header">
                <h2>
    <i class="fa-solid fa-sliders"></i>
    General Platform Configuration
</h2>
            </div>

            <form method="POST">

                <div class="settings-section">
                    <h3>Store Information</h3>

                    <div class="settings-grid">

                        <div class="form-group">
                            <label for="store_name">Store Name</label>
                            <input
                                type="text"
                                id="store_name"
                                name="store_name"
                                value="<?= htmlspecialchars($settings['store_name']) ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="store_email">Store Email</label>
                            <input
                                type="email"
                                id="store_email"
                                name="store_email"
                                value="<?= htmlspecialchars($settings['store_email']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="store_phone">Store Phone</label>
                            <input
                                type="text"
                                id="store_phone"
                                name="store_phone"
                                value="<?= htmlspecialchars($settings['store_phone']) ?>">
                        </div>

                        <div class="form-group">
                            <label for="store_address">Store Address</label>
                            <textarea
                                id="store_address"
                                name="store_address"
                                rows="3"><?= htmlspecialchars($settings['store_address']) ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="settings-section">
                    <h3>Payment and Delivery</h3>

                    <div class="settings-grid">

                        <div class="form-group">
                            <label for="currency">Store Currency</label>
                            <select id="currency" name="currency" required>
                                <option value="NGN"
                                    <?= $settings['currency'] === 'NGN' ? 'selected' : '' ?>>
                                    NGN - Nigerian Naira
                                </option>

                                <option value="USD"
                                    <?= $settings['currency'] === 'USD' ? 'selected' : '' ?>>
                                    USD - US Dollar
                                </option>

                                <option value="GBP"
                                    <?= $settings['currency'] === 'GBP' ? 'selected' : '' ?>>
                                    GBP - British Pound
                                </option>

                                <option value="EUR"
                                    <?= $settings['currency'] === 'EUR' ? 'selected' : '' ?>>
                                    EUR - Euro
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="delivery_fee">Default Delivery Fee</label>
                            <input
                                type="number"
                                id="delivery_fee"
                                name="delivery_fee"
                                min="0"
                                step="0.01"
                                value="<?= htmlspecialchars($settings['delivery_fee']) ?>"
                                required>
                        </div>

                    </div>
                </div>

                <div class="settings-section">
                    <h3>Store Availability</h3>

                    <div class="settings-grid">

                        <div class="form-group">
                            <label for="store_status">Store Status</label>
                            <select id="store_status" name="store_status">
                                <option value="active"
                                    <?= $settings['store_status'] === 'active' ? 'selected' : '' ?>>
                                    Active
                                </option>

                                <option value="inactive"
                                    <?= $settings['store_status'] === 'inactive' ? 'selected' : '' ?>>
                                    Inactive
                                </option>
                            </select>
                            <small>Controls whether the store is marked active or inactive.</small>
                        </div>

                        <div class="form-group">
                            <label for="maintenance_mode">Maintenance Mode</label>
                            <select id="maintenance_mode" name="maintenance_mode">
                                <option value="0"
                                    <?= $settings['maintenance_mode'] === '0' ? 'selected' : '' ?>>
                                    Disabled
                                </option>

                                <option value="1"
                                    <?= $settings['maintenance_mode'] === '1' ? 'selected' : '' ?>>
                                    Enabled
                                </option>
                            </select>
                            <small>This saves the maintenance setting; customer-facing enforcement will be added separately.</small>
                        </div>

                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="primary-btn">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save Settings
                    </button>
                </div>

            </form>

        </section>

    </main>
</div>

</body>
</html>