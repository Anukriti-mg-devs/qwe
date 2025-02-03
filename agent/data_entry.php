<?php
$pageTitle = 'Data Entry';
require_once '../header.php';

// Check if user is an agent and has checked in
if ($_SESSION['role'] !== ROLE_AGENT) {
    header('Location: ../dashboard.php');
    exit;
}

// Check if user has checked in
$stmt = $pdo->prepare("
    SELECT id FROM attendance 
    WHERE user_id = ? AND DATE(check_in) = CURDATE() AND check_out IS NULL
");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = "Please check in before entering data";
    header('Location: ../dashboard.php');
    exit;
}

// Get entry categories
$categories = [
    'ACA' => 'ACA',
    'DEBT' => 'Debt',
    'MEDICARE' => 'Medicare',
    'FE' => 'FE',
    'AUTO' => 'Auto',
    'SSDI' => 'SSDI'
];

// Get today's entries count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM data_entries 
    WHERE user_id = ? AND DATE(created_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$today_entries = $stmt->fetch()['count'];
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold" style="color: <?php echo COLOR_PRIMARY; ?>">Data Entry</h1>
                <p class="text-gray-600">Today's Entries: <?php echo $today_entries; ?></p>
            </div>
            <div>
                <button type="button" onclick="viewHistory()" class="btn text-white px-4 py-2 rounded-lg" 
                        style="background: <?php echo COLOR_SECONDARY; ?>">
                    View History
                </button>
            </div>
        </div>
    </div>

    <!-- Entry Form -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <form id="entryForm" onsubmit="return submitForm(event)">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
            
            <!-- Category Selection -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Category *</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <?php foreach ($categories as $key => $label): ?>
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:border-blue-500 transition-colors">
                        <input type="radio" name="category" value="<?php echo $key; ?>" required 
                               class="form-radio text-blue-600">
                        <span class="ml-2"><?php echo $label; ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Customer Name *</label>
                    <input type="text" name="customer_name" required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Phone Number *</label>
                    <input type="tel" name="phone" pattern="\d{10}" required
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="10 digits">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Email</label>
                    <input type="email" name="email"
                           class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Status *</label>
                    <select name="status" required
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="pending">Pending</option>
                        <option value="completed">Completed</option>
                        <option value="follow_up">Follow Up</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Notes</label>
                <textarea name="notes" rows="4"
                          class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" id="submitButton" 
                        class="px-6 py-2 rounded-lg text-white font-medium transition-all"
                        style="background: <?php echo COLOR_PRIMARY; ?>">
                    Save Entry
                </button>
            </div>
        </form>
    </div>
</div>

<!-- History Modal -->
<div id="historyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
    <div class="bg-white rounded-lg max-w-4xl mx-auto mt-20 p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Entry History</h2>
            <button onclick="closeHistory()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="historyContent" class="max-h-96 overflow-y-auto">
            <!-- History content will be loaded here -->
        </div>
    </div>
</div>

<script>
async function submitForm(event) {
    event.preventDefault();
    const form = event.target;
    const submitButton = form.querySelector('#submitButton');
    const originalText = submitButton.textContent;
    
    try {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';

        const response = await fetch('save_entry.php', {
            method: 'POST',
            body: new FormData(form)
        });

        const result = await response.json();
        
        if (result.success) {
            alert('Entry saved successfully!');
            form.reset();
            updateEntryCount();
        } else {
            throw new Error(result.error || 'Failed to save entry');
        }
    } catch (error) {
        alert(error.message);
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
    
    return false;
}

function viewHistory() {
    const modal = document.getElementById('historyModal');
    const content = document.getElementById('historyContent');
    
    // Load history data
    fetch('get_entries.php')
        .then(response => response.json())
        .then(data => {
            content.innerHTML = generateHistoryTable(data);
            modal.classList.remove('hidden');
        })
        .catch(error => {
            alert('Error loading history: ' + error.message);
        });
}

function closeHistory() {
    document.getElementById('historyModal').classList.add('hidden');
}

function generateHistoryTable(data) {
    return `
        <table class="min-w-full">
            <thead>
                <tr>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Category</th>
                    <th class="px-4 py-2 text-left">Customer</th>
                    <th class="px-4 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                ${data.map(entry => `
                    <tr class="border-t">
                        <td class="px-4 py-2">${formatDate(entry.created_at)}</td>
                        <td class="px-4 py-2">${entry.category}</td>
                        <td class="px-4 py-2">${entry.customer_name}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-1 rounded-full text-xs ${getStatusClass(entry.status)}">
                                ${entry.status}
                            </span>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getStatusClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'follow_up':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function updateEntryCount() {
    fetch('get_entry_count.php')
        .then(response => response.json())
        .then(data => {
            document.querySelector('.text-gray-600').textContent = `Today's Entries: ${data.count}`;
        })
        .catch(error => console.error('Error updating entry count:', error));
}

// Form validation and formatting
document.addEventListener('DOMContentLoaded', function() {
    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.slice(0, 10);
            e.target.value = value;
        });
    }

    // Form validation
    const form = document.getElementById('entryForm');
    if (form) {
        form.addEventListener('input', function(e) {
            const submitButton = document.getElementById('submitButton');
            const isValid = form.checkValidity();
            submitButton.disabled = !isValid;
            submitButton.style.opacity = isValid ? '1' : '0.5';
        });
    }
});

// Auto-save draft functionality
let autoSaveTimeout;
const AUTO_SAVE_DELAY = 30000; // 30 seconds

function autoSaveDraft() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(() => {
        const form = document.getElementById('entryForm');
        const formData = new FormData(form);
        formData.append('draft', '1');

        fetch('save_entry.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Draft saved', 'success');
            }
        })
        .catch(error => console.error('Error saving draft:', error));
    }, AUTO_SAVE_DELAY);
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white transform transition-all duration-500 translate-y-full`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateY(0)';
    }, 100);
    
    // Animate out
    setTimeout(() => {
        notification.style.transform = 'translateY(full)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}

// Category selection enhancement
document.querySelectorAll('input[name="category"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Remove highlight from all labels
        document.querySelectorAll('label').forEach(label => {
            label.style.borderColor = '';
            label.style.backgroundColor = '';
        });
        
        // Highlight selected category
        if (this.checked) {
            const label = this.closest('label');
            label.style.borderColor = COLOR_PRIMARY;
            label.style.backgroundColor = `${COLOR_BACKGROUND}50`;
        }
    });
});

// Handle offline/online status
window.addEventListener('online', function() {
    showNotification('Back online - all changes will be synced', 'success');
    syncOfflineEntries();
});

window.addEventListener('offline', function() {
    showNotification('You are offline - changes will be saved locally', 'warning');
});

// Sync offline entries when connection is restored
async function syncOfflineEntries() {
    const offlineEntries = JSON.parse(localStorage.getItem('offlineEntries') || '[]');
    if (offlineEntries.length === 0) return;

    try {
        for (const entry of offlineEntries) {
            await fetch('save_entry.php', {
                method: 'POST',
                body: JSON.stringify(entry),
                headers: {
                    'Content-Type': 'application/json'
                }
            });
        }
        
        localStorage.removeItem('offlineEntries');
        showNotification(`${offlineEntries.length} entries synced successfully`, 'success');
    } catch (error) {
        console.error('Error syncing offline entries:', error);
        showNotification('Error syncing some entries', 'error');
    }
}

// Handle beforeunload event to warn about unsaved changes
window.addEventListener('beforeunload', function(e) {
    const form = document.getElementById('entryForm');
    if (form && formHasChanges(form)) {
        e.preventDefault();
        e.returnValue = '';
    }
});

function formHasChanges(form) {
    const formData = new FormData(form);
    for (const [name, value] of formData.entries()) {
        if (value) return true;
    }
    return false;
}
</script>

<?php require_once '../footer.php'; ?>