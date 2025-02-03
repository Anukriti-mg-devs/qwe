<?php
require_once '../config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_POST['user_id'];
        $permissions = $_POST['permissions'] ?? [];

        $pdo->beginTransaction();

        // Remove existing permissions
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Add new permissions
        if (!empty($permissions)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_permissions (user_id, permission_id, granted_by) 
                VALUES (?, ?, ?)
            ");

            foreach ($permissions as $permission_id) {
                $stmt->execute([$user_id, $permission_id, $_SESSION['user_id']]);
            }
        }

        // Log the change
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action_type, details, ip_address)
            VALUES (?, 'permissions_update', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Updated permissions for user ID: $user_id",
            $_SERVER['REMOTE_ADDR']
        ]);

        $pdo->commit();
        $_SESSION['success'] = "Permissions updated successfully";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating permissions: " . $e->getMessage();
    }
}

header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>