<?php
$pageTitle = 'Manage Salaries';
require_once '../header.php';
requireAdmin();

// Get current month and year
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get all agents' salary data
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.position,
        u.role,
        COALESCE(s.basic_salary, 0) as basic_salary,
        COUNT(d.id) as total_entries,
        COUNT(CASE WHEN d.status = 'completed' THEN 1 END) as completed_entries,
        COALESCE(s.entry_incentive, 0) as entry_incentive,
        COALESCE(s.other_incentive, 0) as other_incentive,
        COALESCE(s.total_amount, 0) as total_amount,
        s.processed_at
    FROM users u
    LEFT JOIN salaries s ON u.id = s.user_id 
        AND s.month = ? AND s.year = ?
    LEFT JOIN data_entries d ON u.id = d.user_id 
        AND MONTH(d.created_at) = ? 
        AND YEAR(d.created_at) = ?
    WHERE u.role = 'agent' AND u.is_active = 1
    GROUP BY u.id
");
$stmt->execute([$month, $year, $month, $year]);
$salaries = $stmt->fetchAll();

// Calculate totals
$totals = [
    'total_entries' => 0,
    'completed_entries' => 0,
    'total_amount' => 0
];

foreach ($salaries as $salary) {
    $totals['total_entries'] += $salary['total_entries'];
    $totals['completed_entries'] += $salary['completed_entries'];
    $totals['total_amount'] += $salary['total_amount'];
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold" style="color: <?php echo COLOR_PRIMARY; ?>">Salary Management</h1>
                <p class="text-gray-600">
                    <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </p>
            </div>
            <div class="flex space-x-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Select Month</label>
                    <input type="month" 
                           value="<?php echo "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT); ?>"
                           onchange="changePeriod(this.value)"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <button onclick="processAllSalaries()" 
                        class="px-4 py-2 text-white rounded-lg h-10 mt-auto"
                        style="background: <?php echo COLOR_SECONDARY; ?>">
                    Process All Salaries
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">Total Entries</h3>
                <p class="text-3xl font-bold"><?php echo number_format($totals['total_entries']); ?></p>
                <p class="text-sm text-gray-500">
                    <?php echo number_format($totals['completed_entries']); ?> completed
                </p>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">Total Payout</h3>
                <p class="text-3xl font-bold">₹<?php echo number_format($totals['total_amount'], 2); ?></p>
                <p class="text-sm text-gray-500">For <?php echo count($salaries); ?> agents</p>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-2">Average Earnings</h3>
                <p class="text-3xl font-bold">
                    ₹<?php 
                    echo $salaries ? 
                        number_format($totals['total_amount'] / count($salaries), 2) : 
                        '0.00'; 
                    ?>
                </p>
                <p class="text-sm text-gray-500">Per agent</p>
            </div>
        </div>

        <!-- Salary Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Agent
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Basic Salary
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Entries
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Entry Incentive
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Other Incentive
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total Amount
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($salaries as $salary): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($salary['full_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($salary['position']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    ₹<?php echo number_format($salary['basic_salary'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php echo number_format($salary['total_entries']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo number_format($salary['completed_entries']); ?> completed
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    ₹<?php echo number_format($salary['entry_incentive'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    ₹<?php echo number_format($salary['other_incentive'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium" style="color: <?php echo COLOR_PRIMARY; ?>">
                                    ₹<?php echo number_format($salary['total_amount'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editSalary(<?php echo htmlspecialchars(json_encode($salary)); ?>)"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                    Edit
                                </button>
                                <button onclick="generatePayslip(<?php echo $salary['id']; ?>)"
                                        class="text-green-600 hover:text-green-900">
                                    Payslip
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Salary Modal -->
<div id="editSalaryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3">
            <h3 class="text-lg font-medium">Edit Salary Details</h3>
            <button onclick="closeModal('editSalaryModal')" class="text-gray-400 hover:text-gray-500">
                <span class="text-2xl">&times;</span>
            </button>
        </div>
        <form id="salaryForm" action="process_salary.php" method="POST">
            <input type="hidden" id="editUserId" name="user_id">
            <input type="hidden" name="month" value="<?php echo $month; ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">
            
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">Basic Salary</label>
                <input type="number" 
                       id="basicSalary"
                       name="basic_salary" 
                       step="0.01"
                       required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>

            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">Entry Incentive</label>
                <input type="number" 
                       id="entryIncentive"
                       name="entry_incentive" 
                       step="0.01"
                       required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>

            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">Other Incentive</label>
                <input type="number" 
                       id="otherIncentive"
                       name="other_incentive" 
                       step="0.01"
                       required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                <p class="mt-1 text-sm text-gray-500">
                    Additional incentives, bonuses, etc.
                </p>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" 
                        onclick="closeModal('editSalaryModal')"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg mr-2">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-white rounded-lg"
                        style="background: <?php echo COLOR_PRIMARY; ?>">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function changePeriod(value) {
    const [year, month] = value.split('-');
    window.location.href = `?month=${month}&year=${year}`;
}

function editSalary(salary) {
    document.getElementById('editUserId').value = salary.id;
    document.getElementById('basicSalary').value = salary.basic_salary;
    document.getElementById('entryIncentive').value = salary.entry_incentive;
    document.getElementById('otherIncentive').value = salary.other_incentive;
    
    document.getElementById('editSalaryModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function generatePayslip(userId) {
    window.open(`generate_payslip.php?user_id=${userId}&month=<?php echo $month; ?>&year=<?php echo $year; ?>`, '_blank');
}

async function processAllSalaries() {
    if (!confirm('Are you sure you want to process salaries for all agents?')) {
        return;
    }

    try {
        const response = await fetch('process_all_salaries.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                month: <?php echo $month; ?>,
                year: <?php echo $year; ?>
            })
        });

        const result = await response.json();
        if (result.success) {
            alert('All salaries processed successfully');
            window.location.reload();
        } else {
            throw new Error(result.error);
        }
    } catch (error) {
        alert('Error processing salaries: ' + error.message);
    }
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('bg-gray-600')) {
        event.target.classList.add('hidden');
    }
}

// Calculate totals
document.getElementById('salaryForm').addEventListener('input', function(e) {
    if (e.target.type === 'number') {
        const basic = parseFloat(document.getElementById('basicSalary').value) || 0;
        const entry = parseFloat(document.getElementById('entryIncentive').value) || 0;
        const other = parseFloat(document.getElementById('otherIncentive').value) || 0;
        
        const total = basic + entry + other;
        document.getElementById('totalAmount').textContent = 
            '₹' + total.toFixed(2);
    }
});
</script>

<?php require_once '../footer.php'; ?>