<?php
require_once '../config.php';
requireAuth();

// Only admin and HR can generate payslips
if (!in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_HR])) {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_GET['user_id'] ?? null;
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

try {
    // Get employee details
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            d.bank_name,
            d.account_number,
            d.ifsc_code,
            d.pan_number
        FROM users u
        LEFT JOIN user_details d ON u.id = d.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch();

    // Get attendance data
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT DATE(check_in)) as total_days,
            SUM(duration) as total_hours
        FROM attendance
        WHERE user_id = ? 
        AND MONTH(check_in) = ? 
        AND YEAR(check_in) = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    $attendance = $stmt->fetch();

    // Get data entries for incentive calculation
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_entries,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_entries
        FROM data_entries
        WHERE user_id = ? 
        AND MONTH(created_at) = ? 
        AND YEAR(created_at) = ?
    ");
    $stmt->execute([$user_id, $month, $year]);
    $entries = $stmt->fetch();

    // Calculate incentives
    $entry_incentive = $entries['total_entries'] * 1; // ₹1 per entry
    $completion_bonus = $entries['completed_entries'] * 0.5; // ₹0.5 bonus for completed entries

    // Get base salary and other components
    $stmt = $pdo->prepare("
        SELECT * FROM salary_structure
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $salary = $stmt->fetch();

    // Calculate deductions
    $pf = ($salary['basic_salary'] * 0.12); // 12% PF
    $professional_tax = 200; // Fixed PT
    $tds = ($salary['basic_salary'] * 0.1); // 10% TDS

    // Calculate total salary
    $gross_salary = $salary['basic_salary'] + 
                   $salary['hra'] + 
                   $salary['da'] + 
                   $salary['special_allowance'] + 
                   $entry_incentive + 
                   $completion_bonus;

    $total_deductions = $pf + $professional_tax + $tds;
    $net_salary = $gross_salary - $total_deductions;

    // Store salary record
    $stmt = $pdo->prepare("
        INSERT INTO salaries (
            user_id, month, year,
            basic_salary, hra, da, special_allowance,
            entry_incentive, completion_bonus,
            gross_salary, total_deductions, net_salary,
            generated_by, generated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");
    $stmt->execute([
        $user_id, $month, $year,
        $salary['basic_salary'], $salary['hra'], $salary['da'], $salary['special_allowance'],
        $entry_incentive, $completion_bonus,
        $gross_salary, $total_deductions, $net_salary,
        $_SESSION['user_id']
    ]);

    $salary_id = $pdo->lastInsertId();

} catch (Exception $e) {
    error_log("Error generating payslip: " . $e->getMessage());
    header('Location: dashboard.php?error=payslip_generation_failed');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($employee['full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
        }
        .payslip {
            background: linear-gradient(135deg, <?php echo COLOR_PRIMARY; ?>, <?php echo COLOR_SECONDARY; ?>);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <!-- Print/Download Controls -->
        <div class="mb-4 no-print">
            <button onclick="window.print()" class="bg-blue-500 text-white px-4 py-2 rounded">
                Print Payslip
            </button>
            <button onclick="downloadPDF()" class="bg-green-500 text-white px-4 py-2 rounded ml-2">
                Download PDF
            </button>
            <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded ml-2 inline-block">
                Back to Dashboard
            </a>
        </div>

        <!-- Payslip Content -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden" id="payslip">
            <!-- Header -->
            <div class="payslip text-white p-6">
                <div class="text-center">
                    <h1 class="text-2xl font-bold mb-2">Call Center Management System</h1>
                    <p class="text-lg">Payslip for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                </div>
            </div>

            <!-- Employee Details -->
            <div class="p-6 border-b">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h3 class="font-bold mb-2">Employee Details</h3>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['full_name']); ?></p>
                        <p><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['id']); ?></p>
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
                        <p><strong>PAN:</strong> <?php echo htmlspecialchars($employee['pan_number']); ?></p>
                    </div>
                    <div>
                        <h3 class="font-bold mb-2">Bank Details</h3>
                        <p><strong>Bank:</strong> <?php echo htmlspecialchars($employee['bank_name']); ?></p>
                        <p><strong>Account:</strong> <?php echo htmlspecialchars($employee['account_number']); ?></p>
                        <p><strong>IFSC:</strong> <?php echo htmlspecialchars($employee['ifsc_code']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Salary Details -->
            <div class="p-6 grid grid-cols-2 gap-6">
                <!-- Earnings -->
                <div>
                    <h3 class="font-bold mb-4 text-green-600">Earnings</h3>
                    <table class="w-full">
                        <tr>
                            <td class="py-2">Basic Salary</td>
                            <td class="text-right">₹<?php echo number_format($salary['basic_salary'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">HRA</td>
                            <td class="text-right">₹<?php echo number_format($salary['hra'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">Dearness Allowance</td>
                            <td class="text-right">₹<?php echo number_format($salary['da'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">Special Allowance</td>
                            <td class="text-right">₹<?php echo number_format($salary['special_allowance'], 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">Entry Incentive</td>
                            <td class="text-right">₹<?php echo number_format($entry_incentive, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">Completion Bonus</td>
                            <td class="text-right">₹<?php echo number_format($completion_bonus, 2); ?></td>
                        </tr>
                        <tr class="border-t font-bold">
                            <td class="py-2">Total Earnings</td>
                            <td class="text-right">₹<?php echo number_format($gross_salary, 2); ?></td>
                        </tr>
                    </table>
                </div>

                <!-- Deductions -->
                <div>
                    <h3 class="font-bold mb-4 text-red-600">Deductions</h3>
                    <table class="w-full">
                        <tr>
                            <td class="py-2">Provident Fund</td>
                            <td class="text-right">₹<?php echo number_format($pf, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">Professional Tax</td>
                            <td class="text-right">₹<?php echo number_format($professional_tax, 2); ?></td>
                        </tr>
                        <tr>
                            <td class="py-2">TDS</td>
                            <td class="text-right">₹<?php echo number_format($tds, 2); ?></td>
                        </tr>
                        <tr class="border-t font-bold">
                            <td class="py-2">Total Deductions</td>
                            <td class="text-right">₹<?php echo number_format($total_deductions, 2); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Net Salary -->
            <div class="p-6 bg-gray-100">
                <div class="flex justify-between items-center">
                    <div class="text-xl font-bold">Net Salary</div>
                    <div class="text-2xl font-bold">₹<?php echo number_format($net_salary, 2); ?></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 text-sm text-gray-600 border-t">
                <p class="mb-2">This is a computer-generated document. No signature is required.</p>
                <p>Generated on: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('payslip');
            const opt = {
                margin: 1,
                filename: 'payslip-<?php echo $year . '-' . $month . '-' . $employee['id']; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>