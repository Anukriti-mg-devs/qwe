<?php
require_once '../config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_salaries.php');
    exit;
}

try {
    $user_id = $_POST['user_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Validate inputs
    if (!$user_id || !$month || !$year) {
        throw new Exception('Missing required fields');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Get entry count for incentive calculation
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_entries,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_entries
        FROM data_entries
        WHERE user_id = ? 
        AND MONTH(created_at) = ? 
        AND YEAR(created_at) = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    $entries = $stmt->fetch();

    // Calculate amounts
    $basic_salary = floatval($_POST['basic_salary']);
    $entry_incentive = floatval($_POST['entry_incentive']);
    $other_incentive = floatval($_POST['other_incentive']);
    
    // Calculate automatic entry incentive (₹1 per entry)
    $auto_incentive = $entries['total_entries'];
    $entry_incentive += $auto_incentive;

    $total_amount = $basic_salary + $entry_incentive + $other_incentive;

    // Check if salary record exists
    $stmt = $pdo->prepare("
        SELECT id FROM salaries
        WHERE user_id = ? AND month = ? AND year = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    
    if ($stmt->fetch()) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE salaries SET
                basic_salary = ?,
                entry_incentive = ?,
                other_incentive = ?,
                total_amount = ?,
                total_entries = ?,
                completed_entries = ?,
                processed_by = ?,
                processed_at = NOW()
            WHERE user_id = ? AND month = ? AND year = ?
        ");
        $stmt->execute([
            $basic_salary,
            $entry_incentive,
            $other_incentive,
            $total_amount,
            $entries['total_entries'],
            $entries['completed_entries'],
            $_SESSION['user_id'],
            $user_id,
            $month,
            $year
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO salaries (
                user_id,
                month,
                year,
                basic_salary,
                entry_incentive,
                other_incentive,
                total_amount,
                total_entries,
                completed_entries,
                processed_by,
                processed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $month,
            $year,
            $basic_salary,
            $entry_incentive,
            $other_incentive,
            $total_amount,
            $entries['total_entries'],
            $entries['completed_entries'],
            $_SESSION['user_id']
        ]);
    }

    // Log the action
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (
            user_id, 
            action_type, 
            details, 
            ip_address
        ) VALUES (?, 'process_salary', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Processed salary for user ID: $user_id for $month/$year",
        $_SERVER['REMOTE_ADDR']
    ]);

    $pdo->commit();
    $_SESSION['success'] = "Salary processed successfully";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error processing salary: " . $e->getMessage());
    $_SESSION['error'] = "Error processing salary: " . $e->getMessage();
}

header('Location: manage_salaries.php');
exit;
?>