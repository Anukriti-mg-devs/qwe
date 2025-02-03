<?php
$pageTitle = 'Dashboard';
require_once 'header.php';

// Get user stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entries,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_entries
    FROM data_entries 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get current month's salary info
$currentMonth = date('m');
$currentYear = date('Y');
$stmt = $pdo->prepare("
    SELECT 
        basic_salary,
        entry_incentive,
        other_incentive,
        total_amount
    FROM salaries
    WHERE user_id = ? AND month = ? AND year = ?
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$salary = $stmt->fetch();

// Get recent activities
$stmt = $pdo->prepare("
    (SELECT 'entry' as type, created_at, customer_name as description
     FROM data_entries 
     WHERE user_id = ?)
    UNION
    (SELECT 'attendance' as type, check_in as created_at, 
     CASE 
         WHEN check_out IS NULL THEN 'Checked in'
         ELSE CONCAT('Worked for ', ROUND(duration, 1), ' hours')
     END as description
     FROM attendance 
     WHERE user_id = ?)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$activities = $stmt->fetchAll();

// Get unread messages
$stmt = $pdo->prepare("
    SELECT m.*, u.full_name as sender_name
    FROM chat_messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ? AND m.is_read = 0
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8" 
         style="background: linear-gradient(135deg, <?php echo COLOR_PRIMARY; ?>, <?php echo COLOR_SECONDARY; ?>);">
        <div class="text-white">
            <h1 class="text-3xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
            <p class="opacity-90"><?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Entry Stats -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Total Entries</h3>
                <i class="fas fa-file-alt text-2xl" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_entries']; ?></p>
            <p class="text-sm text-gray-500">
                <?php echo $stats['today_entries']; ?> entries today
            </p>
        </div>

        <!-- Salary Info -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Monthly Earnings</h3>
                <i class="fas fa-money-bill-wave text-2xl" style="color: <?php echo COLOR_HIGHLIGHT; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                ₹<?php echo number_format($salary['total_amount'] ?? 0, 2); ?>
            </p>
            <p class="text-sm text-gray-500">
                Including ₹<?php echo number_format(($salary['entry_incentive'] ?? 0) + ($salary['other_incentive'] ?? 0), 2); ?> incentives
            </p>
        </div>

        <!-- Attendance -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Today's Hours</h3>
                <i class="fas fa-clock text-2xl" style="color: <?php echo COLOR_ACCENT; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $workingHours ? number_format($workingHours, 1) : '0.0'; ?>h</p>
            <p class="text-sm text-gray-500">
                <?php echo $attendance ? 'Checked in at ' . date('h:i A', strtotime($attendance['check_in'])) : 'Not checked in'; ?>
            </p>
        </div>

        <!-- Messages -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Messages</h3>
                <i class="fas fa-envelope text-2xl" style="color: <?php echo COLOR_SECONDARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $unreadMessages; ?></p>
            <p class="text-sm text-gray-500">Unread messages</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Activity -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                <div class="space-y-4">
                    <?php foreach ($activities as $activity): ?>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <?php if ($activity['type'] === 'entry'): ?>
                                    <i class="fas fa-file-alt p-2 rounded-full" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock p-2 rounded-full" style="color: <?php echo COLOR_ACCENT; ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm"><?php echo htmlspecialchars($activity['description']); ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Entries Table -->
            <?php if ($_SESSION['role'] === ROLE_AGENT): ?>
            <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
                <h2 class="text-xl font-semibold mb-4">Recent Entries</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Category
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT *
                                FROM data_entries
                                WHERE user_id = ?
                                ORDER BY created_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $entries = $stmt->fetchAll();
                            foreach ($entries as $entry):
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo date('m/d/Y', strtotime($entry['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($entry['category']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo htmlspecialchars($entry['customer_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $entry['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($entry['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Sidebar -->
        <div class="space-y-6">
            <!-- Messages Preview -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Messages</h2>
                <?php if (count($unreadMessages) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($unreadMessages as $message): ?>
                            <div class="p-4 rounded-lg bg-gray-50">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-medium text-sm">
                                        <?php echo htmlspecialchars($message['sender_name']); ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 50)) . '...'; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        <a href="chat.php" class="block text-center text-sm font-medium" 
                           style="color: <?php echo COLOR_PRIMARY; ?>">
                            View All Messages
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm">No unread messages</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
                <div class="space-y-2">
                    <?php if ($_SESSION['role'] === ROLE_AGENT): ?>
                        <a href="data_entry.php" class="block p-3 rounded-lg hover:bg-gray-50 transition duration-150">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-edit" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
                                <span class="font-medium">New Entry</span>
                            </div>
                        </a>
                    <?php endif; ?>
                    <a href="chat.php" class="block p-3 rounded-lg hover:bg-gray-50 transition duration-150">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-comments" style="color: <?php echo COLOR_SECONDARY; ?>"></i>
                            <span class="font-medium">Open Chat</span>
                        </div>
                    </a>
                    <?php if (checkPermission('view_reports')): ?>
                        <a href="reports.php" class="block p-3 rounded-lg hover:bg-gray-50 transition duration-150">
                            <div class="flex items-center space-x-3">
                                <i class="fas fa-chart-bar" style="color: <?php echo COLOR_HIGHLIGHT; ?>"></i>
                                <span class="font-medium">View Reports</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chat Window (Initially Hidden) -->
<div id="chatWindow" class="hidden fixed bottom-4 right-4 w-96 bg-white rounded-lg shadow-xl" 
     style="height: 500px; z-index: 1000;">
    <!-- Chat interface will be loaded here -->
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        new Tooltip(tooltip, {
            placement: 'top',
            title: tooltip.dataset.tooltip
        });
    });

    // Initialize chat functionality
    const chatWindow = document.getElementById('chatWindow');
    window.toggleChat = function() {
        chatWindow.classList.toggle('hidden');
        if (!chatWindow.classList.contains('hidden')) {
            loadChatInterface();
        }
    };

    // Load chat interface
    function loadChatInterface() {
        fetch('chat_interface.php')
            .then(response => response.text())
            .then(html => {
                chatWindow.innerHTML = html;
                initializeChat();
            })
            .catch(error => console.error('Error loading chat:', error));
    }

    // Add any additional initialization code here
});
</script>

<?php
// Include footer
require_once 'footer.php';
?>