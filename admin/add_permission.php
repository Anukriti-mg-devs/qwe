<?php
require_once '../config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            throw new Exception('Permission name is required');
        }

        // Check if permission already exists
        $stmt = $pdo->prepare("
            SELECT 1 FROM permissions WHERE name = ?
        ");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            throw new Exception('Permission already exists');
        }

        // Add new permission
        $stmt = $pdo->prepare("
            INSERT INTO permissions (name, description, created_by) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$name, $description, $_SESSION['user_id']]);

        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, 
                action_type, 
                details, 
                ip_address
            ) VALUES (?, 'add_permission', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Added new permission: $name",
            $_SERVER['REMOTE_ADDR']
        ]);

        $_SESSION['success'] = "Permission added successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

header('Location: manage_permissions.php');
exit;
?>