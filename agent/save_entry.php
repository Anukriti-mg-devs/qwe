<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== ROLE_AGENT) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Check if user is checked in
$stmt = $pdo->prepare("
    SELECT id FROM attendance 
    WHERE user_id = ? AND DATE(check_in) = CURDATE() AND check_out IS NULL
");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Please check in before entering data']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['category', 'customer_name', 'phone', 'status'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate phone number format
    if (!preg_match('/^\d{10}$/', $_POST['phone'])) {
        throw new Exception('Invalid phone number format');
    }

    // Validate email if provided
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Check for duplicate entry
    $stmt = $pdo->prepare("
        SELECT id FROM data_entries 
        WHERE phone = ? AND category = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$_POST['phone'], $_POST['category']]);
    if ($stmt->fetch()) {
        throw new Exception('Duplicate entry for this phone and category today');
    }

    // Insert the entry
    $stmt = $pdo->prepare("
        INSERT INTO data_entries (
            user_id,
            category,
            customer_name,
            phone,
            email,
            status,
            notes,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $_POST['category'],
        $_POST['customer_name'],
        $_POST['phone'],
        $_POST['email'] ?? null,
        $_POST['status'],
        $_POST['notes'] ?? null
    ]);

    $entry_id = $pdo->lastInsertId();

    // Log the activity
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, action_type, details, ip_address)
        VALUES (?, 'data_entry', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Added new entry ID: $entry_id in category: {$_POST['category']}",
        $_SERVER['REMOTE_ADDR']
    ]);

    // Calculate and update incentive
    $stmt = $pdo->prepare("
        UPDATE users 
        SET entries_count = entries_count + 1,
            incentive_amount = incentive_amount + 1
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Entry saved successfully',
        'entry_id' => $entry_id
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Data entry error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>