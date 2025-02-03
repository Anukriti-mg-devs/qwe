<?php
require_once 'config.php';
requireAuth();

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(d.id) as total_entries,
           COUNT(DISTINCT DATE(a.check_in)) as attendance_days
    FROM users u
    LEFT JOIN data_entries d ON u.id = d.user_id
    LEFT JOIN attendance a ON u.id = a.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Validate and update basic info
        if (isset($_POST['update_basic'])) {
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
            
            if (!$email) {
                throw new Exception('Invalid email address');
            }

            $stmt = $pdo->prepare("
                UPDATE users SET 
                    email = ?,
                    phone = ?,
                    emergency_contact = ?,
                    address = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $email,
                $phone,
                $_POST['emergency_contact'],
                $_POST['address'],
                $userId
            ]);
            
            $success = 'Profile updated successfully';
        }

        // Handle password change
        if (isset($_POST['change_password'])) {
            $current = $_POST['current_password'];
            $new = $_POST['new_password'];
            $confirm = $_POST['confirm_password'];

            if (!password_verify($current, $user['password'])) {
                throw new Exception('Current password is incorrect');
            }

            if ($new !== $confirm) {
                throw new Exception('New passwords do not match');
            }

            if (strlen($new) < 8) {
                throw new Exception('Password must be at least 8 characters long');
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            $success = 'Password changed successfully';
        }

        // Handle profile picture upload
        if (isset($_FILES['profile_picture'])) {
            $file = $_FILES['profile_picture'];
            if ($file['error'] === 0) {
                $allowedTypes = ['image/jpeg', 'image/png'];
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception('Invalid file type. Only JPG and PNG allowed.');
                }

                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $maxSize) {
                    throw new Exception('File too large. Maximum size is 5MB.');
                }

                $fileName = 'profile_' . $userId . '_' . time() . '.jpg';
                $uploadPath = 'uploads/profiles/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$fileName, $userId]);
                    $success = 'Profile picture updated successfully';
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get recent activities
$stmt = $pdo->prepare("
    SELECT 
        'entry' as type,
        category as details,
        created_at
    FROM data_entries 
    WHERE user_id = ?
    UNION ALL
    SELECT 
        'attendance' as type,
        status as details,
        check_in as created_at
    FROM attendance 
    WHERE user_id = ?
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$userId, $userId]);
$recent_activities = $stmt->fetchAll();

// Get performance stats
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_entries,
        COUNT(DISTINCT DATE(created_at)) as working_days
    FROM data_entries
    WHERE user_id = ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$userId]);
$performance_stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Call Center Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: <?= COLOR_PRIMARY ?>;
            --secondary: <?= COLOR_SECONDARY ?>;
            --accent: <?= COLOR_ACCENT ?>;
            --highlight: <?= COLOR_HIGHLIGHT ?>;
            --background: <?= COLOR_BACKGROUND ?>;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Profile Overview -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="text-center mb-6">
                    <img src="<?= $user['profile_picture'] ? 'uploads/profiles/' . $user['profile_picture'] : 'assets/images/default-avatar.png' ?>" 
                         alt="Profile Picture" 
                         class="w-32 h-32 rounded-full mx-auto mb-4">
                    <h2 class="text-xl font-semibold"><?= htmlspecialchars($user['full_name']) ?></h2>
                    <p class="text-gray-600"><?= htmlspecialchars($user['position']) ?></p>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Entries:</span>
                        <span class="font-semibold"><?= $user['total_entries'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Days Present:</span>
                        <span class="font-semibold"><?= $user['attendance_days'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Role:</span>
                        <span class="font-semibold"><?= ucfirst($user['role']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Joined:</span>
                        <span class="font-semibold"><?= date('m/d/Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Profile Update Form -->
            <div class="bg-white rounded-lg shadow-md p-6 md:col-span-2">
                <h3 class="text-lg font-semibold mb-4">Update Profile</h3>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Emergency Contact</label>
                        <input type="text" name="emergency_contact" value="<?= htmlspecialchars($user['emergency_contact'] ?? '') ?>" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" rows="3" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Profile Picture</label>
                        <input type="file" name="profile_picture" accept="image/jpeg,image/png" 
                               class="mt-1 block w-full">
                    </div>

                    <button type="submit" name="update_basic" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        Update Profile
                    </button>
                </form>

                <!-- Change Password Form -->
                <div class="mt-8 pt-8 border-t">
                    <h3 class="text-lg font-semibold mb-4">Change Password</h3>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="new_password" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" name="confirm_password" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <button type="submit" name="change_password" 
                                class="bg-yellow-500 text-white px-4 py-2 rounded-md hover:bg-yellow-600">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
                <div class="space-y-4">
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center space-x-3">
                            <div class="<?= $activity['type'] === 'entry' ? 'bg-blue-100' : 'bg-green-100' ?> p-2 rounded-full">
                                <i class="fas <?= $activity['type'] === 'entry' ? 'fa-file-alt' : 'fa-clock' ?>"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    <?= ucfirst($activity['type']) ?>: <?= htmlspecialchars($activity['details']) ?>
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?= date('m/d/Y h:i A', strtotime($activity['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Performance Stats -->
            <div class="bg-white rounded-lg shadow-md p-6 md:col-span-2">
                <h3 class="text-lg font-semibold mb-4">Performance Statistics</h3>
                <div class="space-y-4">
                    <?php foreach ($performance_stats as $stat): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">
                                <?= date('F Y', strtotime($stat['month'] . '-01')) ?>
                            </span>
                            <div class="flex space-x-4">
                                <span class="text-sm font-medium">
                                    Entries: <?= $stat['total_entries'] ?>
                                </span>
                                <span class="text-sm font-medium">
                                    Working Days: <?= $stat['working_days'] ?>
                                </span>
                                <span class="text-sm font-medium">
                                    Avg: <?= number_format($stat['total_entries'] / ($stat['working_days'] ?: 1), 1) ?>/day
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/profile.js"></script>
</body>
</html>