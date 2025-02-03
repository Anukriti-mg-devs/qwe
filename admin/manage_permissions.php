<?php
$pageTitle = 'Manage Permissions';
require_once '../header.php';
requireAdmin();

// Get all permissions
$stmt = $pdo->query("
    SELECT * FROM permissions 
    ORDER BY name
");
$permissions = $stmt->fetchAll();

// Get all users except admins
$stmt = $pdo->query("
    SELECT 
        u.*,
        GROUP_CONCAT(p.name) as permissions
    FROM users u
    LEFT JOIN user_permissions up ON u.id = up.user_id
    LEFT JOIN permissions p ON up.permission_id = p.id
    WHERE u.role != 'admin'
    GROUP BY u.id
    ORDER BY u.role, u.full_name
");
$users = $stmt->fetchAll();

// Handle permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $user_id = $_POST['user_id'];
        $user_permissions = $_POST['permissions'] ?? [];

        // Remove existing permissions
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Add new permissions
        if (!empty($user_permissions)) {
            $stmt = $pdo->prepare("
                INSERT INTO user_permissions (user_id, permission_id, granted_by) 
                VALUES (?, ?, ?)
            ");

            foreach ($user_permissions as $permission_id) {
                $stmt->execute([$user_id, $permission_id, $_SESSION['user_id']]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Permissions updated successfully";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating permissions: " . $e->getMessage();
    }

    header('Location: manage_permissions.php');
    exit;
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold" style="color: <?php echo COLOR_PRIMARY; ?>">Manage Permissions</h1>
            <button onclick="showAddPermissionModal()" 
                    class="px-4 py-2 rounded-lg text-white"
                    style="background: <?php echo COLOR_SECONDARY; ?>">
                Add New Permission
            </button>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Users Permissions Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Permissions
                        </th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900">
                                    <?php
                                    if ($user['permissions']) {
                                        $perms = explode(',', $user['permissions']);
                                        foreach ($perms as $perm) {
                                            echo "<span class='inline-block bg-gray-100 rounded-full px-3 py-1 text-xs font-semibold text-gray-700 mr-2 mb-2'>";
                                            echo htmlspecialchars($perm);
                                            echo "</span>";
                                        }
                                    } else {
                                        echo "<span class='text-gray-500'>No permissions</span>";
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="editPermissions(<?php echo $user['id']; ?>)"
                                        class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Permissions Modal -->
<div id="editPermissionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3">
            <h3 class="text-lg font-medium">Edit Permissions</h3>
            <button onclick="closeModal('editPermissionsModal')" class="text-gray-400 hover:text-gray-500">
                <span class="text-2xl">&times;</span>
            </button>
        </div>
        <form id="permissionsForm" method="POST">
            <input type="hidden" id="editUserId" name="user_id">
            <div class="mt-2">
                <div class="grid grid-cols-1 gap-2">
                    <?php foreach ($permissions as $permission): ?>
                        <label class="inline-flex items-center mt-3">
                            <input type="checkbox" 
                                   name="permissions[]" 
                                   value="<?php echo $permission['id']; ?>"
                                   class="form-checkbox h-5 w-5 text-blue-600">
                            <span class="ml-2 text-gray-700">
                                <?php echo htmlspecialchars($permission['name']); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" 
                        onclick="closeModal('editPermissionsModal')"
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

<!-- Add Permission Modal -->
<div id="addPermissionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center pb-3">
            <h3 class="text-lg font-medium">Add New Permission</h3>
            <button onclick="closeModal('addPermissionModal')" class="text-gray-400 hover:text-gray-500">
                <span class="text-2xl">&times;</span>
            </button>
        </div>
        <form id="addPermissionForm" action="add_permission.php" method="POST">
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">Permission Name</label>
                <input type="text" 
                       name="name" 
                       required
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="mt-2">
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description"
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                          rows="3"></textarea>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" 
                        onclick="closeModal('addPermissionModal')"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg mr-2">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 text-white rounded-lg"
                        style="background: <?php echo COLOR_PRIMARY; ?>">
                    Add Permission
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editPermissions(userId) {
    document.getElementById('editUserId').value = userId;
    
    // Reset checkboxes
    document.querySelectorAll('#permissionsForm input[type="checkbox"]')
        .forEach(cb => cb.checked = false);
    
    // Get current permissions
    fetch(`get_user_permissions.php?user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.permissions) {
                data.permissions.forEach(permId => {
                    const cb = document.querySelector(`input[name="permissions[]"][value="${permId}"]`);
                    if (cb) cb.checked = true;
                });
            }
            document.getElementById('editPermissionsModal').classList.remove('hidden');
        })
        .catch(error => console.error('Error:', error));
}

function showAddPermissionModal() {
    document.getElementById('addPermissionModal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('bg-gray-600')) {
        event.target.classList.add('hidden');
    }
}
</script>

<?php require_once '../footer.php'; ?>