<?php

function logAdminActivity(
    PDO $pdo,
    int $admin_id,
    string $action,
    string $description
): void {

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $stmt = $pdo->prepare("
        INSERT INTO admin_activity_logs (
            admin_id,
            action,
            description,
            ip_address
        )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $admin_id,
        $action,
        $description,
        $ip_address
    ]);
}