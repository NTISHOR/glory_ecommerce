<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_activity.php';

$pdo = getDbConnection();

/*
|--------------------------------------------------------------------------
| Super Admin Access
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'super_admin'
) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Only POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: permissions.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Submitted Data
|--------------------------------------------------------------------------
*/

$admin_id = filter_input(
    INPUT_POST,
    'admin_id',
    FILTER_VALIDATE_INT
);

$permissions = $_POST['permissions'] ?? [];

/*
|--------------------------------------------------------------------------
| Validate Administrator
|--------------------------------------------------------------------------
*/

if (!$admin_id) {

    header("Location: permissions.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, full_name, email
    FROM users
    WHERE id = ?
      AND role = 'admin'
    LIMIT 1
");

$stmt->execute([$admin_id]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {

    header("Location: permissions.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Allowed Permissions
|--------------------------------------------------------------------------
*/

$allowed_permissions = [
    'manage_vendors',
    'manage_customers',
    'manage_products',
    'manage_categories',
    'manage_orders',
    'manage_payments',
    'view_reports',
    'view_activity_logs',
    'manage_settings'
];

/*
|--------------------------------------------------------------------------
| Clean Submitted Permissions
|--------------------------------------------------------------------------
*/

$permissions = array_values(
    array_intersect(
        $permissions,
        $allowed_permissions
    )
);


/*
|--------------------------------------------------------------------------
| Save Permissions
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    | Remove existing permissions
    */

    $stmt = $pdo->prepare("
        DELETE FROM admin_permissions
        WHERE admin_id = ?
    ");

    $stmt->execute([$admin_id]);


    /*
    | Insert new permissions
    */

    if (!empty($permissions)) {

        $stmt = $pdo->prepare("
            INSERT INTO admin_permissions (
                admin_id,
                permission,
                granted_by
            )
            VALUES (?, ?, ?)
        ");

        foreach ($permissions as $permission) {

            $stmt->execute([
                $admin_id,
                $permission,
                $_SESSION['user_id']
            ]);
        }
    }


    /*
    | Activity Log
    */

    if (!empty($permissions)) {

        $permission_text = implode(', ', $permissions);

        $description =
            "Updated permissions for administrator {$admin['full_name']} ({$admin['email']}). " .
            "Granted permissions: {$permission_text}.";

    } else {

        $description =
            "Removed all permissions from administrator " .
            "{$admin['full_name']} ({$admin['email']}).";
    }


    logAdminActivity(
        $pdo,
        $_SESSION['user_id'],
        'UPDATE_ADMIN_PERMISSIONS',
        $description
    );


    /*
    | Commit
    */

    $pdo->commit();


    /*
    | Return to Permissions Page
    */

    header(
        "Location: permissions.php?admin_id=" .
        $admin_id .
        "&success=1"
    );

    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "Permission Update Error: " .
        $e->getMessage()
    );

    header(
        "Location: permissions.php?admin_id=" .
        $admin_id .
        "&error=1"
    );

    exit;
}

