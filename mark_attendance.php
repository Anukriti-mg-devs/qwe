<?php
require_once 'config.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    try {
        $action = $_GET['action'];
        $user_id = $_SESSION['user_id'];
        $current_date = date('Y-m-d');

        if ($action === 'check_in') {
            // Check if already checked in today
            $stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE user_id = ? AND DATE(check_in) = ? AND check_out IS NULL
            ");
            $stmt->execute([$user_id, $current_date]);
            
            if ($stmt->fetch()) {
                throw new Exception("Already checked in for today");
            }

            // Create new attendance record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (user_id, check_in, status)
                VALUES (?, NOW(), 'present')
            ");
            $stmt->execute([$user_id]);

            // Store attendance ID in session
            $_SESSION['current_attendance_id'] = $pdo->lastInsertId();
            $_SESSION['success'] = "Successfully checked in";

        } elseif ($action === 'check_out') {
            // Find active attendance record
            $stmt = $pdo->prepare("
                SELECT id, check_in 
                FROM attendance 
                WHERE user_id = ? AND DATE(check_in) = ? AND check_out IS NULL
            ");
            $stmt->execute([$user_id, $current_date]);
            $attendance = $stmt->fetch();

            if (!$attendance) {
                throw new Exception("No active check-in found");
            }

            // Calculate duration
            $check_in = new DateTime($attendance['check_in']);
            $check_out = new DateTime();
            $duration = ($check_out->getTimestamp() - $check_in->getTimestamp()) / 3600; // Convert to hours

            // Update attendance record
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET check_out = NOW(),
                    duration = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$duration, $attendance['id']]);

            // Remove attendance ID from session
            unset($_SESSION['current_attendance_id']);
            $_SESSION['success'] = "Successfully checked out";
        }

        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action_type, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            "attendance_$action",
            "$action recorded at " . date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR']
        ]);

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Redirect back
$redirect = $_SESSION['role'] === ROLE_ADMIN ? 'admin/dashboard.php' : 
           ($_SESSION['role'] === ROLE_TL ? 'tl/dashboard.php' : 'dashboard.php');

header("Location: $redirect");
exit;
?>