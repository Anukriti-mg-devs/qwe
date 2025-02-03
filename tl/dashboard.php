<?php
$pageTitle = 'Team Leader Dashboard';
require_once '../header.php';

// Verify TL role
if ($_SESSION['role'] !== ROLE_TL) {
    header('Location: ../dashboard.php');
    exit;
}

// Get team members
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.position,
        u.last_activity,
        COUNT(DISTINCT CASE WHEN DATE(d.created_at) = CURDATE() THEN d.id END) as today_entries,
        COUNT(DISTINCT CASE WHEN d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN d.id END) as month_entries,
        (SELECT check_in FROM attendance 
         WHERE user_id = u.id AND DATE(check_in) = CURDATE() 
         ORDER BY check_in DESC LIMIT 1) as today_check_in,
        (SELECT check_out FROM attendance 
         WHERE user_id = u.id AND DATE(check_in) = CURDATE() 
         ORDER BY check_in DESC LIMIT 1) as today_check_out
    FROM users u
    LEFT JOIN data_entries d ON u.id = d.user_id
    WHERE u.role = 'agent'
    GROUP BY u.id
    ORDER BY today_entries DESC
");
$stmt->execute();
$team_members = $stmt->fetchAll();

// Get team performance by category
$stmt = $pdo->prepare("
    SELECT 
        category,
        COUNT(*) as total_entries,
        COUNT(DISTINCT user_id) as agent_count,
        COUNT(DISTINCT CASE WHEN DATE(created_at) = CURDATE() THEN id END) as today_entries
    FROM data_entries
    WHERE user_id IN (SELECT id FROM users WHERE role = 'agent')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY category
");
$stmt->execute();
$category_performance = $stmt->fetchAll();

// Get attendance overview
$stmt = $pdo->prepare("
    SELECT 
        DATE(a.check_in) as date,
        COUNT(DISTINCT a.user_id) as total_present,
        AVG(a.duration) as avg_hours
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE u.role = 'agent'
    AND a.check_in >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(a.check_in)
    ORDER BY date DESC
");
$stmt->execute();
$attendance_overview = $stmt->fetchAll();

// Get recent activities
$stmt = $pdo->prepare("
    SELECT 
        'entry' as type,
        d.created_at as timestamp,
        u.full_name,
        d.category,
        d.customer_name as details
    FROM data_entries d
    JOIN users u ON d.user_id = u.id
    WHERE u.role = 'agent'
    AND d.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    UNION ALL
    
    SELECT 
        'attendance' as type,
        a.check_in as timestamp,
        u.full_name,
        NULL as category,
        CASE 
            WHEN a.check_out IS NULL THEN 'Checked In'
            ELSE CONCAT('Worked for ', ROUND(a.duration, 1), ' hours')
        END as details
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE u.role = 'agent'
    AND a.check_in >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    
    ORDER BY timestamp DESC
    LIMIT 20
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();
?>

<div class="container mx-auto px-4 py-8">
    <!-- Team Overview -->
    <div class="rounded-lg shadow-lg p-6 mb-8" 
         style="background: linear-gradient(135deg, <?php echo COLOR_PRIMARY; ?>, <?php echo COLOR_SECONDARY; ?>);">
        <div class="text-white">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h1 class="text-3xl font-bold">Team Overview</h1>
                    <p class="opacity-90">Managing <?php echo count($team_members); ?> team members</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-bold"><?php 
                        echo array_sum(array_column($team_members, 'today_entries')); 
                    ?></p>
                    <p class="opacity-90">Total Entries Today</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Active Agents -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Active Agents</h3>
                <i class="fas fa-users text-2xl" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php 
                echo count(array_filter($team_members, function($m) {
                    return strtotime($m['today_check_in']) !== false && $m['today_check_out'] === null;
                }));
                ?>
            </p>
            <p class="text-sm text-gray-500">Currently working</p>
        </div>

        <!-- Average Hours -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Avg Hours</h3>
                <i class="fas fa-clock text-2xl" style="color: <?php echo COLOR_SECONDARY; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php 
                $avg_hours = array_sum(array_column($attendance_overview, 'avg_hours')) / count($attendance_overview);
                echo number_format($avg_hours, 1);
                ?>h
            </p>
            <p class="text-sm text-gray-500">Per agent per day</p>
        </div>

        <!-- Entry Rate -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Entry Rate</h3>
                <i class="fas fa-tachometer-alt text-2xl" style="color: <?php echo COLOR_ACCENT; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php
                $active_agents = count(array_filter($team_members, function($m) {
                    return $m['today_entries'] > 0;
                }));
                echo $active_agents ? 
                    number_format(array_sum(array_column($team_members, 'today_entries')) / $active_agents, 1) : 
                    '0';
                ?>
            </p>
            <p class="text-sm text-gray-500">Entries per agent today</p>
        </div>

        <!-- Overall Performance -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Performance</h3>
                <i class="fas fa-chart-line text-2xl" style="color: <?php echo COLOR_HIGHLIGHT; ?>"></i>
            </div>
            <p class="text-3xl font-bold">
                <?php
                $total_month_entries = array_sum(array_column($team_members, 'month_entries'));
                echo number_format($total_month_entries / 30, 1);
                ?>
            </p>
            <p class="text-sm text-gray-500">Daily average this month</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Team Members List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">Team Members</h2>
                    <button onclick="window.location.href='manage_team.php'" class="text-sm font-medium px-4 py-2 rounded-lg"
                            style="background: <?php echo COLOR_PRIMARY; ?>; color: white;">
                        Manage Team
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Agent
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Today's Entries
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($team_members as $member): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($member['position']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">
                                        <div class="font-medium"><?php echo $member['today_entries']; ?> entries</div>
                                        <div class="text-gray-500"><?php echo $member['month_entries']; ?> this month</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($member['today_check_in'] && !$member['today_check_out']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Working
                                        </span>
                                    <?php elseif ($member['today_check_out']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Not Started
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <button onclick="viewPerformance(<?php echo $member['id']; ?>)" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        View Details
                                    </button>
                                    <button onclick="messageAgent(<?php echo $member['id']; ?>)" 
                                            class="text-green-600 hover:text-green-900">
                                        Message
                                    </button>
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
            <!-- Category Performance -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Category Performance</h2>
                <div class="space-y-4">
                    <?php foreach ($category_performance as $category): ?>
                    <div class="p-4 rounded-lg" style="background-color: <?php echo COLOR_BACKGROUND; ?>">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-medium"><?php echo htmlspecialchars($category['category']); ?></span>
                            <span class="text-sm"><?php echo $category['total_entries']; ?> total</span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <?php echo $category['today_entries']; ?> entries today by <?php echo $category['agent_count']; ?> agents
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-6">Recent Activity</h2>
                <div class="space-y-4">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <?php if ($activity['type'] === 'entry'): ?>
                                <i class="fas fa-file-alt p-2 rounded-full" style="color: <?php echo COLOR_PRIMARY; ?>"></i>
                            <?php else: ?>
                                <i class="fas fa-clock p-2 rounded-full" style="color: <?php echo COLOR_ACCENT; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow">
                            <p class="text-sm">
                                <span class="font-medium"><?php echo htmlspecialchars($activity['full_name']); ?></span>
                                <?php if ($activity['type'] === 'entry'): ?>
                                    added entry in 
                                    <span class="font-medium"><?php echo htmlspecialchars($activity['category']); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($activity['details']); ?>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-gray-500">
                                <?php echo date('g:i A', strtotime($activity['timestamp'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewPerformance(agentId) {
    window.location.href = `agent_performance.php?id=${agentId}`;
}

function messageAgent(agentId) {
    // Open chat with specific agent
    window.parent.postMessage({
        type: 'openChat',
        userId: agentId
    }, '*');
}
</script>

<?php require_once '../footer.php'; ?>