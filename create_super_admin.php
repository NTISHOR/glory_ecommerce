<?php

require_once 'config/db.php';

// Super Admin details
$full_name = "Augustine Akpotu";
$email = "admin@gloryecommerce.com";
$phone = "08000000000";

// Choose your own secure password
$password = "ChangeThisPassword123!";

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check if the email already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    die("An account with this email already exists.");
}

// Create Super Admin
$stmt = $pdo->prepare("
    INSERT INTO users
    (full_name, email, phone, password, role, status)
    VALUES (?, ?, ?, ?, 'super_admin', 'active')
");

$stmt->execute([
    $full_name,
    $email,
    $phone,
    $hashedPassword
]);

echo "Super Admin account created successfully!";

?>