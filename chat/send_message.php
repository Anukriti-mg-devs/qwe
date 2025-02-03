<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $user_id = $_SESSION['user_id'];
    $content = trim($_POST['content'] ?? '');

    if (empty($content)) {
        throw new Exception('Message content cannot be empty');
    }

    $pdo->beginTransaction();

    if (isset($_POST['receiver_id'])) {
        // Direct message
        $receiver_id = $_POST['receiver_id'];

        // Verify receiver exists and is active
        $stmt = $pdo->prepare("
            SELECT 1 FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$receiver_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid receiver');
        }

        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                sender_id, 
                receiver_id, 
                content, 
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $receiver_id, $content]);

    } elseif (isset($_POST['group_id'])) {
        // Group message
        $group_id = $_POST['group_id'];

        // Verify membership
        $stmt = $pdo->prepare("
            SELECT 1 FROM group_members 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Not a member of this group');
        }

        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (
                sender_id, 
                group_id, 
                content, 
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $group_id, $content]);

        // Update last activity for group
        $stmt = $pdo->prepare("
            UPDATE chat_groups 
            SET last_activity = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$group_id]);

    } else {
        throw new Exception('Invalid message target');
    }

    $message_id = $pdo->lastInsertId();

    // Log activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, 
            action_type, 
            details, 
            ip_address
        ) VALUES (?, 'send_message', ?, ?)
    ");
    $stmt->execute([
        $user_id,
        isset($receiver_id) ? "Sent message to user ID: $receiver_id" : "Sent message to group ID: $group_id",
        $_SERVER['REMOTE_ADDR']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message_id' => $message_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Message send error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>