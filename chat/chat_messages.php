<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    $last_id = $_GET['last_id'] ?? 0;

    if (isset($_GET['user_id'])) {
        // Direct messages
        $other_user_id = $_GET['user_id'];
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.full_name as sender_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id > ?
            AND (
                (m.sender_id = ? AND m.receiver_id = ?) 
                OR 
                (m.sender_id = ? AND m.receiver_id = ?)
            )
            AND m.group_id IS NULL
            ORDER BY m.created_at ASC
        ");
        
        $stmt->execute([$last_id, $user_id, $other_user_id, $other_user_id, $user_id]);
        
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$other_user_id, $user_id]);

    } elseif (isset($_GET['group_id'])) {
        // Group messages
        $group_id = $_GET['group_id'];
        
        // Verify membership
        $stmt = $pdo->prepare("
            SELECT 1 FROM group_members 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Not a member of this group');
        }

        // Get messages
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                u.full_name as sender_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$group_id, $last_id]);

        // Update last read
        $stmt = $pdo->prepare("
            UPDATE group_members 
            SET last_read_at = NOW() 
            WHERE group_id = ? AND user_id = ?
        ");
        $stmt->execute([$group_id, $user_id]);
    } else {
        throw new Exception('Invalid request parameters');
    }

    $messages = [];
    while ($row = $stmt->fetch()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['sender_name'],
            'content' => $row['content'],
            'created_at' => $row['created_at']
        ];
    }

    // Get member count for groups
    $member_count = null;
    if (isset($group_id)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM group_members WHERE group_id = ?
        ");
        $stmt->execute([$group_id]);
        $member_count = $stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'member_count' => $member_count
    ]);

} catch (Exception $e) {
    error_log("Chat error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}