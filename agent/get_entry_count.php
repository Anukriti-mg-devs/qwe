<?php
require_once '../config.php';
requireAuth();

header('Content-Type: application/json');

try {
    if ($_SESSION['role'] !== ROLE_AGENT) {
        throw new Exception('Unauthorized access');
    }

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as today_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            SUM(CASE 
                WHEN status = 'completed' THEN 1
                ELSE 0.5
            END) as incentive_amount
        FROM data_entries
        WHERE user_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    $stats = $stmt->fetch();

    // Get category breakdown
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
        FROM data_entries
        WHERE user_id = ? 
        AND DATE(created_at) = CURDATE()
        GROUP BY category
    ");
    $stmt->execute([$_SESSION['user_id']]);
    
    $categories = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $stats['today_count'],
            'completed' => $stats['completed_count'],
            'incentive' => $stats['incentive_amount']
        ],
        'categories' => $categories
    ]);

} catch (Exception $e) {
    error_log("Error getting entry count: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>