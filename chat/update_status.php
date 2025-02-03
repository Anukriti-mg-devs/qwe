<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];

    // Update user's last activity timestamp
    $stmt = $pdo->prepare("
        UPDATE users 
        SET last_activity = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);

    // Get unread message count
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) 
             FROM chat_messages 
             WHERE receiver_id = ? 
             AND is_read = 0 
             AND group_id IS NULL) +
            (SELECT COUNT(*) 
             FROM chat_messages m
             JOIN group_members gm ON m.group_id = gm.group_id
             WHERE gm.user_id = ?
             AND m.created_at > IFNULL(gm.last_read_at, '1970-01-01')
             AND m.sender_id != ?) as total_unread
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $unread_count = $stmt->fetchColumn();

    // Get online users
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.role
        FROM users u 
        WHERE u.last_activity >= NOW() - INTERVAL 5 MINUTE
        AND u.id != ?
        AND u.is_active = 1
    ");
    $stmt->execute([$user_id]);
    $online_users = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count,
        'online_users' => $online_users
    ]);

} catch (Exception $e) {
    error_log("Status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>