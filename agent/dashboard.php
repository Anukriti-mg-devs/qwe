<?php
$pageTitle = 'Agent Dashboard';
require_once '../header.php';

// Check if user is an agent
if ($_SESSION['role'] !== ROLE_AGENT) {
    header('Location: ../dashboard.php');
    exit;
}

// Get agent's current month performance
$currentMonth = date('m');
$currentYear = date('Y');

$stmt = $pdo->prepare("
    SELECT 
        d.*,
        COUNT(*) as total_entries,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_entries,
        COUNT(DISTINCT DATE(created_at)) as active_days
    FROM data_entries d
    WHERE user_id = ? 
    AND MONTH(created_at) = ? 
    AND YEAR(created_at) = ?
    GROUP BY user_id
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$performance = $stmt->fetch();

// Get attendance records for current month
$stmt = $pdo->prepare("
    SELECT 
        DATE(check_in) as date,
        MIN(check_in) as check_in,
        MAX(check_out) as check_out,
        SUM(duration) as total_hours
    FROM attendance
    WHERE user_id = ? 
    AND MONTH(check_in) = ? 
    AND YEAR(check_in) = ?
    GROUP BY DATE(check_in)
    ORDER BY date DESC
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$attendance_records = $stmt->fetchAll();

// Get salary information
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        u.position
    FROM salaries s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ? 
    AND s.month = ? 
    AND s.year = ?
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$salary = $stmt->fetch();

// Calculate incentives based on entries
$entries_incentive = ($performance['total_entries'] ?? 0) * 1; // ₹1 per entry

// Get category-wise breakdown
$stmt = $pdo->prepare("
    SELECT 
        category,
        COUNT(*) as count
    FROM data_entries
    WHERE user_id = ? 
    AND MONTH(created_at) = ? 
    AND YEAR(created_at) = ?
    GROUP BY category
");
$stmt->execute([$_SESSION['user_id'], $currentMonth, $currentYear]);
$category_breakdown = $stmt->fetchAll();

// Get recent entries
$stmt = $pdo->prepare("
    SELECT *
    FROM data_entries
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_entries = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Banner -->
    <div class="rounded-lg shadow-lg p-6 mb-8" 
         style="background: linear-gradient(135deg, <?php echo COLOR_PRIMARY; ?>, <?php echo COLOR_SECONDARY; ?>);">
        <div class="text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                    <p class="opacity-90"><?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($_SESSION['position']); ?></p>
                    <?php if ($salary): ?>
                        <p class="text-2xl font-bold">₹<?php echo number_format($salary['total_amount'], 2); ?></p>
                        <p class="text-sm opacity-75">Current Month Earnings</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Entries -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Total Entries</h3>
                <i class="fas fa-file-alt text-2xl" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $performance['total_entries'] ?? 0; ?></p>
            <p class="text-sm text-gray-500"><?php echo $performance['today_entries'] ?? 0; ?> today</p>
        </div>

        <!-- Entry Incentives -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Entry Incentives</h3>
                <i class="fas fa-money-bill-wave text-2xl" style="color: <?php echo COLOR_HIGHLIGHT; ?>"></i>
            </div>
            <p class="text-3xl font-bold">₹<?php echo number_format($entries_incentive, 2); ?></p>
            <p class="text-sm text-gray-500">₹1 per entry</p>
        </div>

        <!-- Working Hours -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Working Hours</h3>
                <i class="fas fa-clock text-2xl" style="color: <?php echo COLOR_SECONDARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php 
                $total_hours = array_sum(array_column($attendance_records, 'total_hours'));
                echo number_format($total_hours, 1); 
                ?>h
            </p>
            <p class="text-sm text-gray-500"><?php echo count($attendance_records); ?> days this month</p>
        </div>

        <!-- Average Performance -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Daily Average</h3>
                <i class="fas fa-chart-line text-2xl" style="color: <?php echo COLOR_ACCENT; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php
                $avg_entries = $performance['active_days'] ? 
                    round($performance['total_entries'] / $performance['active_days'], 1) : 0;
                echo $avg_entries;
                ?>
            </p>
            <p class="text-sm text-gray-500">entries per day</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Entries Table -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">Recent Entries</h2>
                    <a href="data_entry.php" class="text-sm font-medium" style="color: <?php echo COLOR_PRIMARY; ?>">
                        New Entry
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recent_entries as $entry): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo date('m/d/Y', strtotime($entry['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          style="background-color: <?php echo COLOR_BACKGROUND; ?>">
                                        <?php echo htmlspecialchars($entry['category']); ?>
                                    </span>
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
        </div>

        <!-- Right Sidebar -->
        <div class="space-y-6">
            <!-- Category Breakdown -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Category Breakdown</h2>
                <div class="space-y-4">
                    <?php foreach ($category_breakdown as $category): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium"><?php echo htmlspecialchars($category['category']); ?></span>
                        <span class="text-sm"><?php echo $category['count']; ?></span>
                    </div>
                    <div class="relative pt-1">
                        <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                            <div class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center"
                                 style="width: <?php echo ($category['count'] / $performance['total_entries'] * 100); ?>%; 
                                        background: <?php echo COLOR_PRIMARY; ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Attendance Summary -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Recent Attendance</h2>
                <div class="space-y-4">
                    <?php foreach (array_slice($attendance_records, 0, 5) as $record): ?>
                    <div class="p-4 rounded-lg" style="background-color: <?php echo COLOR_BACKGROUND; ?>">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium">
                                <?php echo date('M j, Y', strtotime($record['date'])); ?>
                            </span>
                            <span class="text-sm">
                                <?php echo number_format($record['total_hours'], 1); ?>h
                            </span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php
                            echo date('g:i A', strtotime($record['check_in'])) . ' - ' . 
                                 ($record['check_out'] ? date('g:i A', strtotime($record['check_out'])) : 'Active');
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>