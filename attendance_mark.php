<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$attendanceStatus = checkAttendanceStatus($userId);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentTime = date('Y-m-d H:i:s');
    $currentDate = date('Y-m-d');

    try {
        if ($action === 'check_in') {
            $stmt = $conn->prepare("
                INSERT INTO attendance (user_id, date, check_in, status)
                VALUES (?, ?, ?, 'present')
            ");
            $stmt->bind_param("iss", $userId, $currentDate, $currentTime);
            
            if ($stmt->execute()) {
                $message = "Check-in successful! Redirecting to dashboard...";
                $messageType = 'success';
                // Redirect to dashboard after 2 seconds
                header("refresh:2;url=dashboard.php");
            }
        } 
        elseif ($action === 'check_out') {
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET check_out = ?,
                    duration = TIMESTAMPDIFF(MINUTE, check_in, ?)
                WHERE user_id = ? AND date = ? AND check_out IS NULL
            ");
            $stmt->bind_param("ssis", $currentTime, $currentTime, $userId, $currentDate);
            
            if ($stmt->execute()) {
                $message = "Check-out successful! Have a great day!";
                $messageType = 'success';
                // Redirect to login after 2 seconds
                header("refresh:2;url=logout.php");
            }
        }
    } catch (Exception $e) {
        $message = "Error processing attendance: " . $e->getMessage();
        $messageType = 'error';
        error_log("Attendance error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo COLORS['primary']; ?>;
            --secondary: <?php echo COLORS['secondary']; ?>;
            --accent: <?php echo COLORS['accent']; ?>;
            --highlight: <?php echo COLORS['highlight']; ?>;
            --background: <?php echo COLORS['background']; ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 20px;
        }

        .attendance-container {
            width: 100%;
            max-width: 500px;
            background: var(--background);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .attendance-header {
            margin-bottom: 30px;
        }

        .attendance-header h1 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .user-info {
            color: var(--secondary);
            font-size: 16px;
            margin-bottom: 20px;
        }

        .time-display {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 30px;
        }

        .date-display {
            font-size: 18px;
            color: var(--secondary);
            margin-bottom: 30px;
        }

        .attendance-btn {
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 500;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .check-in-btn {
            background: linear-gradient(135deg, var(--highlight), var(--secondary));
            color: white;
        }

        .check-out-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .attendance-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .message {
            margin-top: 20px;
            padding: 10px;
            border-radius: 10px;
            font-size: 14px;
        }

        .message.success {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }

        .message.error {
            background: rgba(228, 61, 18, 0.1);
            color: var(--primary);
        }

        @media (max-width: 480px) {
            .attendance-container {
                padding: 20px;
            }
            .time-display {
                font-size: 36px;
            }
        }
    </style>
</head>
<body>
    <div class="attendance-container">
        <div class="attendance-header">
            <h1>Mark Attendance</h1>
            <div class="user-info">
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
            </div>
        </div>

        <div class="time-display" id="clock">00:00:00</div>
        <div class="date-display" id="date"></div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if ($attendanceStatus === 'not_marked'): ?>
                <button type="submit" name="action" value="check_in" class="attendance-btn check-in-btn">
                    Check In
                </button>
            <?php elseif ($attendanceStatus === 'checked_in'): ?>
                <button type="submit" name="action" value="check_out" class="attendance-btn check-out-btn">
                    Check Out
                </button>
            <?php else: ?>
                <p class="message success">You have already checked out for today.</p>
            <?php endif; ?>
        </form>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const clock = document.getElementById('clock');
            const dateDisplay = document.getElementById('date');
            
            // Update time
            clock.textContent = now.toLocaleTimeString('en-US', { 
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',