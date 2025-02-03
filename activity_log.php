<?php
require_once 'config.php';
requireAuth();

// Check permission
if (!checkPermission('view_logs')) {
    header('Location: dashboard.php');
    exit;
}

// Get filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userFilter = $_GET['user_id'] ?? '';
$activityType = $_GET['activity_type'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        al.*,
        u.username as user_name,
        u.role as user_role,
        u2.username as affected_user_name
    FROM activity_log al
    JOIN users u ON al.user_id = u.id
    LEFT JOIN users u2 ON al.affected_user_id = u2.id
    WHERE al.timestamp BETWEEN ? AND ?
";

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
$types = "ss";

if ($userFilter) {
    $query .= " AND al.user_id = ?";
    $params[] = $userFilter;
    $types .= "ss";
}

$query .= " ORDER BY al.timestamp DESC LIMIT 1000";

// Execute query
$stmt = $pdo->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique activity types for filter
$activityTypes = $pdo->query("
    SELECT DISTINCT activity_type 
    FROM activity_log 
    ORDER BY activity_type
")->fetch_all(MYSQLI_ASSOC);

// Get users for filter
$users = $pdo->query("
    SELECT id, username, role 
    FROM users 
    ORDER BY username
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Call Center Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= COLOR_PRIMARY ?>;
            --secondary: <?= COLOR_SECONDARY ?>;
            --accent: <?= COLOR_ACCENT ?>;
            --highlight: <?= COLOR_HIGHLIGHT ?>;
            --background: <?= COLOR_BACKGROUND ?>;
        }

        .status-icon {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-success { background-color: #10B981; }
        .status-warning { background-color: #F59E0B; }
        .status-error { background-color: #EF4444; }
        .status-info { background-color: #3B82F6; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date" name="start_date" value="<?= $startDate ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                    <input type="date" name="end_date" value="<?= $endDate ?>" 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">User</label>
                    <select name="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" 
                                <?= $userFilter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?> 
                                (<?= ucfirst($user['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Activity Type</label>
                    <select name="activity_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Activities</option>
                        <?php foreach ($activityTypes as $type): ?>
                            <option value="<?= $type['activity_type'] ?>" 
                                <?= $activityType == $type['activity_type'] ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $type['activity_type'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                           placeholder="Search in description..." 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="bg-primary text-white px-4 py-2 rounded-md hover:bg-opacity-90">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Activity Log Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <h2 class="text-xl font-semibold mb-4">Activity Log</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Timestamp
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    User
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Activity
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('m/d/Y H:i:s', strtotime($log['timestamp'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($log['user_name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= ucfirst($log['user_role']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                   <?= getActivityTypeClass($log['activity_type']) ?>">
                                            <?= ucwords(str_replace('_', ' ', $log['activity_type'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </div>
                                        <?php if ($log['affected_user_name']): ?>
                                            <div class="text-sm text-gray-500">
                                                Affected User: <?= htmlspecialchars($log['affected_user_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-icon status-<?= $log['status'] ?>"></span>
                                        <?= ucfirst($log['status']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="mt-6 flex gap-4">
            <button onclick="exportLogs('excel')" 
                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                Export to Excel
            </button>
            <button onclick="exportLogs('pdf')" 
                    class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                Export to PDF
            </button>
        </div>
    </div>

    <script>
    function exportLogs(format) {
        const currentFilters = new URLSearchParams(window.location.search);
        currentFilters.append('format', format);
        window.location.href = 'export_logs.php?' + currentFilters.toString();
    }

    function getActivityTypeClass(type) {
        switch (type) {
            case 'login': return 'bg-blue-100 text-blue-800';
            case 'data_entry': return 'bg-green-100 text-green-800';
            case 'attendance': return 'bg-yellow-100 text-yellow-800';
            case 'user_update': return 'bg-purple-100 text-purple-800';
            case 'system_update': return 'bg-pink-100 text-pink-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    }
    </script>
</body>
</html>
