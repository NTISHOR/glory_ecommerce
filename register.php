<?php

require_once 'config/db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields

    if (
        empty($full_name) ||
        empty($email) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {

        $message = "Password must contain at least 8 characters.";
        $messageType = "error";

    } else {

        // Check if email already exists

        $stmt = $pdo->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $stmt->execute([$email]);

        if ($stmt->fetch()) {

            $message = "This email address is already registered.";
            $messageType = "error";

        } else {

            // Hash password

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert new customer

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    full_name,
                    email,
                    phone,
                    password,
                    role,
                    status
                ) VALUES (?, ?, ?, ?, 'customer', 'active')
            ");

            $stmt->execute([
                $full_name,
                $email,
                $phone,
                $hashed_password
            ]);

            $message = "Registration successful! You can now log in.";
            $messageType = "success";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Account | Glory E-commerce</title>
</head>

<body>

    <h2>Create Your Account</h2>

    <?php if (!empty($message)): ?>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

    <?php endif; ?>

    <form method="POST" action="">

        <label>Full Name</label>
        <input
            type="text"
            name="full_name"
            required
        >

        <br><br>

        <label>Email Address</label>
        <input
            type="email"
            name="email"
            required
        >

        <br><br>

        <label>Phone Number</label>
        <input
            type="tel"
            name="phone"
        >

        <br><br>

        <label>Password</label>
        <input
            type="password"
            name="password"
            minlength="8"
            required
        >

        <br><br>

        <label>Confirm Password</label>
        <input
            type="password"
            name="confirm_password"
            minlength="8"
            required
        >

        <br><br>

        <button type="submit">
            Create Account
        </button>

    </form>

    <p>
        Already have an account?
        <a href="login.php">Log in</a>
    </p>

</body>
</html>