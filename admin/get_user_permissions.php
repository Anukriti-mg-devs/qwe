<?php
require_once '../config.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

try {
    $user_id = $_GET['user_id'];

    // Get user's current permissions
    $stmt = $pdo->prepare("
        SELECT permission_id 
        FROM user_permissions 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    $permissions = array_column($stmt->fetchAll(), 'permission_id');

    // Get user details
    $stmt = $pdo->prepare("
        SELECT username, role, full_name 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'user' => $user,
        'permissions' => $permissions
    ]);

} catch (Exception $e) {
    error_log("Error fetching user permissions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>