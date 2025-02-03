<?php
require_once 'config.php';
requireAuth();

// Check if user has permission to view reports
if (!checkPermission('view_reports')) {
    header('Location: dashboard.php');
    exit;
}

// Get date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'performance';

// Function to get agent performance data
function getAgentPerformance($pdo, $start_date, $end_date) {
    $query = "SELECT 
        u.id,
        u.full_name,
        u.position,
        COUNT(d.id) as total_entries,
        AVG(TIMESTAMPDIFF(HOUR, a.check_in, COALESCE(a.check_out, NOW()))) as avg_hours,
        COUNT(DISTINCT DATE(d.created_at)) as working_days
    FROM users u
    LEFT JOIN data_entries d ON u.id = d.user_id 
        AND DATE(d.created_at) BETWEEN ? AND ?
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND DATE(a.check_in) BETWEEN ? AND ?
    WHERE u.role = 'agent'
    GROUP BY u.id, u.full_name, u.position
    ORDER BY total_entries DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    return $stmt->fetchAll();
}

// Function to get attendance statistics
function getAttendanceStats($pdo, $start_date, $end_date) {
    $query = "SELECT 
        u.id,
        u.full_name,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
        AVG(a.duration) as avg_duration
    FROM users u
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND DATE(a.check_in) BETWEEN ? AND ?
    GROUP BY u.id, u.full_name
    ORDER BY present_days DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll();
}

// Function to get category-wise entry distribution
function getCategoryDistribution($pdo, $start_date, $end_date) {
    $query = "SELECT 
        category,
        COUNT(*) as total_entries,
        COUNT(DISTINCT user_id) as unique_agents
    FROM data_entries
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY category
    ORDER BY total_entries DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    return $stmt->fetchAll();
}

// Get report data based on type
switch ($report_type) {
    case 'performance':
        $report_data = getAgentPerformance($pdo, $start_date, $end_date);
        break;
    case 'attendance':
        $report_data = getAttendanceStats($pdo, $start_date, $end_date);
        break;
    case 'category':
        $report_data = getCategoryDistribution($pdo, $start_date, $end_date);
        break;
    default:
        $report_data = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Call Center Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Filters Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?= $start_date ?>" 
                           class="w-full rounded border-gray-300">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?= $end_date ?>" 
                           class="w-full rounded border-gray-300">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-2">Report Type</label>
                    <select name="report_type" class="w-full rounded border-gray-300">
                        <option value="performance" <?= $report_type === 'performance' ? 'selected' : '' ?>>
                            Agent Performance
                        </option>
                        <option value="attendance" <?= $report_type === 'attendance' ? 'selected' : '' ?>>
                            Attendance Statistics
                        </option>
                        <option value="category" <?= $report_type === 'category' ? 'selected' : '' ?>>
                            Category Distribution
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg">
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Display Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-semibold mb-6">
                <?= ucfirst($report_type) ?> Report
                (<?= date('m/d/Y', strtotime($start_date)) ?> - 
                 <?= date('m/d/Y', strtotime($end_date)) ?>)
            </h2>

            <?php if ($report_type === 'performance'): ?>
                <!-- Agent Performance Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2">Agent Name</th>
                                <th class="px-4 py-2">Position</th>
                                <th class="px-4 py-2">Total Entries</th>
                                <th class="px-4 py-2">Avg Hours/Day</th>
                                <th class="px-4 py-2">Working Days</th>
                                <th class="px-4 py-2">Productivity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['position']) ?></td>
                                    <td class="px-4 py-2"><?= $row['total_entries'] ?></td>
                                    <td class="px-4 py-2"><?= number_format($row['avg_hours'], 2) ?></td>
                                    <td class="px-4 py-2"><?= $row['working_days'] ?></td>
                                    <td class="px-4 py-2">
                                        <?= $row['working_days'] > 0 
                                            ? number_format($row['total_entries'] / $row['working_days'], 2) 
                                            : '0.00' ?> entries/day
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'attendance'): ?>
                <!-- Attendance Statistics Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2">Agent Name</th>
                                <th class="px-4 py-2">Present Days</th>
                                <th class="px-4 py-2">Absent Days</th>
                                <th class="px-4 py-2">Late Days</th>
                                <th class="px-4 py-2">Avg Hours/Day</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['full_name']) ?></td>
                                    <td class="px-4 py-2"><?= $row['present_days'] ?></td>
                                    <td class="px-4 py-2"><?= $row['absent_days'] ?></td>
                                    <td class="px-4 py-2"><?= $row['late_days'] ?></td>
                                    <td class="px-4 py-2"><?= number_format($row['avg_duration'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'category'): ?>
                <!-- Category Distribution Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2">Category</th>
                                <th class="px-4 py-2">Total Entries</th>
                                <th class="px-4 py-2">Unique Agents</th>
                                <th class="px-4 py-2">Avg Entries/Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr class="border-b">
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['category']) ?></td>
                                    <td class="px-4 py-2"><?= $row['total_entries'] ?></td>
                                    <td class="px-4 py-2"><?= $row['unique_agents'] ?></td>
                                    <td class="px-4 py-2">
                                        <?= $row['unique_agents'] > 0 
                                            ? number_format($row['total_entries'] / $row['unique_agents'], 2) 
                                            : '0.00' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Export Buttons -->
            <div class="mt-6 flex gap-4">
                <button onclick="exportToExcel()" class="bg-green-500 text-white px-4 py-2 rounded">
                    Export to Excel
                </button>
                <button onclick="exportToPDF()" class="bg-red-500 text-white px-4 py-2 rounded">
                    Export to PDF
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/reports.js"></script>
</body>
</html>