<?php
require_once '../config.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $month = $data['month'];
    $year = $data['year'];

    if (!$month || !$year) {
        throw new Exception('Missing required fields');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Get all active agents
    $stmt = $pdo->prepare("
        SELECT id, basic_salary 
        FROM users 
        WHERE role = 'agent' AND is_active = 1
    ");
    $stmt->execute();
    $agents = $stmt->fetchAll();

    $processed = 0;
    $errors = [];

    foreach ($agents as $agent) {
        try {
            // Get entry counts
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_entries,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_entries
                FROM data_entries
                WHERE user_id = ? 
                AND MONTH(created_at) = ? 
                AND YEAR(created_at) = ?
            ");
            $stmt->execute([$agent['id'], $month, $year]);
            $entries = $stmt->fetch();

            // Calculate incentives
            $entry_incentive = $entries['total_entries']; // ₹1 per entry
            $total_amount = $agent['basic_salary'] + $entry_incentive;

            // Update or insert salary record
            $stmt = $pdo->prepare("
                INSERT INTO salaries (
                    user_id, month, year,
                    basic_salary, entry_incentive,
                    total_amount, total_entries,
                    completed_entries, processed_by,
                    processed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    basic_salary = VALUES(basic_salary),
                    entry_incentive = VALUES(entry_incentive),
                    total_amount = VALUES(total_amount),
                    total_entries = VALUES(total_entries),
                    completed_entries = VALUES(completed_entries),
                    processed_by = VALUES(processed_by),
                    processed_at = NOW()
            ");
            
            $stmt->execute([
                $agent['id'],
                $month,
                $year,
                $agent['basic_salary'],
                $entry_incentive,
                $total_amount,
                $entries['total_entries'],
                $entries['completed_entries'],
                $_SESSION['user_id']
            ]);

            $processed++;

        } catch (Exception $e) {
            $errors[] = "Error processing agent ID {$agent['id']}: " . $e->getMessage();
        }
    }

    // Log the bulk action
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, 
            action_type, 
            details, 
            ip_address
        ) VALUES (?, 'bulk_salary_process', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Processed $processed salaries for $month/$year. Errors: " . count($errors),
        $_SERVER['REMOTE_ADDR']
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error processing bulk salaries: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>