<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    try {
        // Mark attendance checkout
        if (isset($_SESSION['current_attendance_id'])) {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET check_out = NOW(),
                    duration = TIMESTAMPDIFF(SECOND, check_in, NOW()) / 3600.0
                WHERE id = ? AND user_id = ? AND check_out IS NULL
            ");
            $stmt->execute([
                $_SESSION['current_attendance_id'],
                $_SESSION['user_id']
            ]);
        }

        // Log the logout
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, 
                activity_type, 
                description, 
                ip_address
            ) VALUES (?, 'logout', 'User logged out', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR']
        ]);

        // Update user's last activity
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_activity = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);

        // Clear all session data
        $_SESSION = array();

        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy the session
        session_destroy();
        
        // Set a success message in a temporary cookie
        setcookie('logout_message', 'You have been successfully logged out and your attendance has been marked.', time() + 5, '/');
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        setcookie('logout_error', 'An error occurred during logout.', time() + 5, '/');
    }
}

// Redirect to login page
header('Location: login.php');
exit;
?>