<?php
$pageTitle = 'Admin Dashboard';
require_once '../header.php';
requireAdmin();

// Get overall statistics
$stats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role != 'admin') as total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'agent') as total_agents,
        (SELECT COUNT(*) FROM data_entries WHERE DATE(created_at) = CURDATE()) as today_entries,
        (SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURDATE()) as today_attendance,
        (SELECT COUNT(*) FROM users WHERE role = 'agent' AND last_activity > NOW() - INTERVAL 5 MINUTE) as active_agents
")->fetch();

// Get today's performance by category
$category_stats = $pdo->query("
    SELECT 
        category,
        COUNT(*) as entry_count,
        COUNT(DISTINCT user_id) as agent_count
    FROM data_entries 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY category
")->fetchAll();

// Get top performing agents (last 30 days)
$top_agents = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.position,
        COUNT(d.id) as entry_count,
        COALESCE(s.total_amount, 0) as salary
    FROM users u
    LEFT JOIN data_entries d ON u.id = d.user_id 
        AND d.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    LEFT JOIN salaries s ON u.id = s.user_id 
        AND s.month = MONTH(CURDATE()) 
        AND s.year = YEAR(CURDATE())
    WHERE u.role = 'agent'
    GROUP BY u.id
    ORDER BY entry_count DESC
    LIMIT 5
")->fetchAll();

// Get recent system activity
$recent_activity = $pdo->query("
    SELECT 
        'data_entry' as type,
        u.full_name as user,
        'added new entry' as action,
        d.category,
        d.created_at
    FROM data_entries d
    JOIN users u ON d.user_id = u.id
    WHERE d.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    UNION ALL
    
    SELECT 
        'attendance' as type,
        u.full_name as user,
        CASE 
            WHEN a.check_out IS NULL THEN 'checked in'
            ELSE 'checked out'
        END as action,
        NULL as category,
        CASE 
            WHEN a.check_out IS NULL THEN a.check_in
            ELSE a.check_out
        END as created_at
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.check_in >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Total Users</h3>
                <i class="fas fa-users text-2xl" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['total_users']; ?></p>
            <p class="text-sm text-gray-500">
                <?php echo $stats['total_agents']; ?> agents
            </p>
        </div>

        <!-- Active Agents -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Active Agents</h3>
                <i class="fas fa-user-check text-2xl" style="color: <?php echo COLOR_SECONDARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['active_agents']; ?></p>
            <p class="text-sm text-gray-500">Currently online</p>
        </div>

        <!-- Today's Entries -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Today's Entries</h3>
                <i class="fas fa-file-alt text-2xl" style="color: <?php echo COLOR_ACCENT; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['today_entries']; ?></p>
            <p class="text-sm text-gray-500">New entries today</p>
        </div>

        <!-- Attendance -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Attendance</h3>
                <i class="fas fa-clock text-2xl" style="color: <?php echo COLOR_HIGHLIGHT; ?>"></i>
            </div>
            <p class="text-3xl font-bold"><?php echo $stats['today_attendance']; ?></p>
            <p class="text-sm text-gray-500">Agents checked in</p>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Quick Actions</h3>
                <i class="fas fa-bolt text-2xl" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
            </div>
            <div class="space-y-2">
                <a href="manage_users.php" class="block text-sm text-blue-600 hover:text-blue-800">Manage Users</a>
                <a href="permissions.php" class="block text-sm text-blue-600 hover:text-blue-800">Update Permissions</a>
                <a href="reports.php" class="block text-sm text-blue-600 hover:text-blue-800">View Reports</a>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column -->
        <div class="lg:col-span-2">
            <!-- Category Performance -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-6">Today's Performance by Category</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($category_stats as $stat): ?>
                        <div class="p-4 rounded-lg" style="background-color: <?php echo COLOR_BACKGROUND; ?>">
                            <h4 class="font-medium text-sm mb-2"><?php echo htmlspecialchars($stat['category']); ?></h4>
                            <p class="text-2xl font-bold"><?php echo $stat['entry_count']; ?></p>
                            <p class="text-sm text-gray-500"><?php echo $stat['agent_count']; ?> agents</p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Recent Activity</h2>
                <div class="space-y-4">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <?php if ($activity['type'] === 'data_entry'): ?>
                                    <i class="fas fa-file-alt p-2 rounded-full" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock p-2 rounded-full" style="color: <?php echo COLOR_ACCENT; ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <p class="text-sm">
                                    <span class="font-medium"><?php echo htmlspecialchars($activity['user']); ?></span>
                                    <?php echo htmlspecialchars($activity['action']); ?>
                                    <?php if ($activity['category']): ?>
                                        in <span class="font-medium"><?php echo htmlspecialchars($activity['category']); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <!-- Top Performing Agents -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-6">Top Performing Agents</h2>
                <div class="space-y-4">
                    <?php foreach ($top_agents as $agent): ?>
                        <div class="p-4 rounded-lg hover:bg-gray-50">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-medium"><?php echo htmlspecialchars($agent['full_name']); ?></span>
                                <span class="text-sm text-gray-500"><?php echo $agent['entry_count']; ?> entries</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-500"><?php echo htmlspecialchars($agent['position']); ?></span>
                                <span class="font-medium">₹<?php echo number_format($agent['salary'], 2); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="reports.php" class="mt-4 inline-block text-sm font-medium" 
                   style="color: <?php echo COLOR_PRIMARY; ?>">
                    View Full Report →
                </a>
            </div>

            <!-- Permission Updates -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Quick Permission Updates</h2>
                <form action="update_permissions.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Select User</label>
                        <select name="user_id" class="form-select w-full rounded-lg border-gray-300">
                            <?php
                            $users = $pdo->query("SELECT id, full_name, role FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();
                            foreach ($users as $user):
                            ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']) . ' (' . ucfirst($user['role']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Permissions</label>
                        <div class="space-y-2">
                            <?php
                            $permissions = $pdo->query("SELECT * FROM permissions ORDER BY name")->fetchAll();
                            foreach ($permissions as $permission):
                            ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="permissions[]" 
                                           value="<?php echo $permission['id']; ?>"
                                           class="rounded border-gray-300 text-blue-600">
                                    <span class="ml-2 text-sm">
                                        <?php echo htmlspecialchars($permission['name']); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2 px-4 rounded-lg text-white font-medium"
                            style="background: linear-gradient(135deg, <?php echo COLOR_PRIMARY; ?>, <?php echo COLOR_SECONDARY; ?>)">
                        Update Permissions
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Initialize Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Add any necessary chart initializations here
    document.addEventListener('DOMContentLoaded', function() {
        // You can add chart initializations or other JS functionality here
    });
</script>

<?php require_once '../footer.php'; ?>