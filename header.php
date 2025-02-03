<?php
if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__FILE__));
}
require_once BASEPATH . '/config.php';
requireAuth();

// Get unread message count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM chat_messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetch()['count'];

// Get user attendance status for today
$stmt = $pdo->prepare("
    SELECT id, check_in, check_out 
    FROM attendance 
    WHERE user_id = ? AND DATE(check_in) = CURDATE()
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$attendance = $stmt->fetch();

// Calculate working hours if checked in
$workingHours = '';
if ($attendance) {
    if ($attendance['check_out']) {
        $workingHours = getWorkingHours($attendance['check_in'], $attendance['check_out']);
    } else {
        $workingHours = getWorkingHours($attendance['check_in'], date('Y-m-d H:i:s'));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Call Center Management System'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <style>
        :root {
            --color-primary: <?php echo COLOR_PRIMARY; ?>;
            --color-secondary: <?php echo COLOR_SECONDARY; ?>;
            --color-accent: <?php echo COLOR_ACCENT; ?>;
            --color-highlight: <?php echo COLOR_HIGHLIGHT; ?>;
            --color-background: <?php echo COLOR_BACKGROUND; ?>;
        }

        .navbar {
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--color-highlight);
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            animation: pulse 2s infinite;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.5rem 1rem;
        }

        .working-hours {
            background: var(--color-highlight);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .dropdown-menu {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
            position: absolute;
            min-width: 200px;
            z-index: 1000;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            color: #374151;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: var(--color-background);
            color: var(--color-primary);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="text-white text-xl font-bold">
                    CCMS
                </a>
                
                <?php if (checkPermission('view_reports')): ?>
                <a href="reports.php" class="nav-link">
                    <i class="fas fa-chart-bar mr-2"></i>Reports
                </a>
                <?php endif; ?>

                <?php if (checkPermission('manage_users')): ?>
                <a href="manage_users.php" class="nav-link">
                    <i class="fas fa-users mr-2"></i>Users
                </a>
                <?php endif; ?>

                <?php if ($_SESSION['role'] === ROLE_AGENT): ?>
                <a href="data_entry.php" class="nav-link">
                    <i class="fas fa-edit mr-2"></i>Data Entry
                </a>
                <?php endif; ?>
            </div>

            <div class="flex items-center space-x-6">
                <!-- Chat Icon with Notification -->
                <div class="relative">
                    <button onclick="toggleChat()" class="nav-link">
                        <i class="fas fa-comments"></i>
                        <?php if ($unreadMessages > 0): ?>
                        <span class="notification-badge"><?php echo $unreadMessages; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- User Info & Working Hours -->
                <div class="user-info flex items-center space-x-4">
                    <div class="text-white">
                        <div class="font-medium"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                        <div class="text-sm opacity-75"><?php echo htmlspecialchars($_SESSION['position']); ?></div>
                    </div>
                    <?php if ($workingHours !== ''): ?>
                    <div class="working-hours">
                        <i class="fas fa-clock mr-1"></i>
                        <?php echo number_format($workingHours, 1); ?> hrs
                    </div>
                    <?php endif; ?>
                </div>

                <!-- User Menu -->
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="nav-link">
                        <i class="fas fa-user-circle text-xl"></i>
                    </button>
                    <div id="userMenu" class="dropdown-menu right-0 mt-2">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user mr-2"></i>Profile
                        </a>
                        <?php if (!$attendance || ($attendance && $attendance['check_out'])): ?>
                        <a href="mark_attendance.php?action=check_in" class="dropdown-item">
                            <i class="fas fa-sign-in-alt mr-2"></i>Check In
                        </a>
                        <?php else: ?>
                        <a href="mark_attendance.php?action=check_out" class="dropdown-item">
                            <i class="fas fa-sign-out-alt mr-2"></i>Check Out
                        </a>
                        <?php endif; ?>
                        <div class="border-t border-gray-100 my-1"></div>
                        <a href="logout.php" class="dropdown-item text-red-600">
                            <i class="fas fa-power-off mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <script>
        function toggleUserMenu() {
            document.getElementById('userMenu').classList.toggle('show');
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.nav-link')) {
                var dropdowns = document.getElementsByClassName('dropdown-menu');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }

        // Chat toggle function to be implemented
        function toggleChat() {
            // This will be implemented when we create the chat system
            console.log('Toggle chat');
        }
    </script>
</body>
</html>