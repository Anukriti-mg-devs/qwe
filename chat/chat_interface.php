<?php
require_once '../config.php';
requireAuth();

// Get all users except current user
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.role,
        CASE 
            WHEN u.last_activity >= NOW() - INTERVAL 5 MINUTE THEN 'online'
            ELSE 'offline'
        END as status,
        (SELECT COUNT(*) FROM chat_messages 
         WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u
    WHERE u.id != ? AND u.is_active = 1
    ORDER BY u.role DESC, u.full_name ASC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$users = $stmt->fetchAll();

// Get group chats
$stmt = $pdo->prepare("
    SELECT 
        g.id,
        g.name,
        g.created_by,
        (SELECT COUNT(*) FROM chat_messages 
         WHERE group_id = g.id AND created_at > IFNULL(
             (SELECT last_read_at FROM group_members 
              WHERE group_id = g.id AND user_id = ?), 
             '1970-01-01'
         )) as unread_count
    FROM chat_groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ?
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$groups = $stmt->fetchAll();
?>

<div class="flex h-full bg-gray-100">
    <!-- User List -->
    <div id="userList" class="w-1/4 bg-white border-r">
        <!-- Search Box -->
        <div class="p-4 border-b">
            <input type="text" id="searchUsers" placeholder="Search users..."
                   class="w-full px-3 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <!-- Group Chats -->
        <div class="p-2">
            <h3 class="px-4 py-2 text-sm font-semibold text-gray-600">Group Chats</h3>
            <?php foreach ($groups as $group): ?>
                <div class="group-chat-item p-2 hover:bg-gray-100 rounded-lg cursor-pointer flex items-center justify-between"
                     onclick="openGroupChat(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name']); ?>')">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white">
                            <?php echo strtoupper(substr($group['name'], 0, 1)); ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($group['name']); ?></p>
                            <?php if ($group['unread_count'] > 0): ?>
                                <span class="text-xs text-blue-600"><?php echo $group['unread_count']; ?> new messages</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Users List -->
        <div class="p-2">
            <h3 class="px-4 py-2 text-sm font-semibold text-gray-600">Direct Messages</h3>
            <?php foreach ($users as $user): ?>
                <div class="user-chat-item p-2 hover:bg-gray-100 rounded-lg cursor-pointer flex items-center justify-between"
                     onclick="openChat(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                    <div class="flex items-center">
                        <div class="relative">
                            <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center text-white">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <div class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white
                                      <?php echo $user['status'] === 'online' ? 'bg-green-500' : 'bg-gray-400'; ?>">
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo ucfirst($user['role']); ?></p>
                        </div>
                    </div>
                    <?php if ($user['unread_count'] > 0): ?>
                        <span class="bg-blue-500 text-white text-xs rounded-full px-2 py-1">
                            <?php echo $user['unread_count']; ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div id="chatArea" class="flex-1 flex flex-col bg-white">
        <!-- Chat Header -->
        <div id="chatHeader" class="px-6 py-4 border-b flex items-center justify-between">
            <div class="flex items-center">
                <button id="backButton" class="mr-4 text-gray-600 hover:text-gray-800 lg:hidden">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div>
                    <h2 id="chatTitle" class="text-lg font-semibold">Select a chat</h2>
                    <p id="chatSubtitle" class="text-sm text-gray-500"></p>
                </div>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="messagesArea" class="flex-1 overflow-y-auto p-6">
            <div class="flex items-center justify-center h-full text-gray-500">
                Select a chat to start messaging
            </div>
        </div>

        <!-- Input Area -->
        <div id="inputArea" class="p-4 border-t">
            <div class="flex items-center space-x-4">
                <input type="text" id="messageInput" 
                       class="flex-1 px-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Type a message..."
                       disabled>
                <button id="sendButton" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        disabled>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentChat = {
    id: null,
    type: null, // 'user' or 'group'
    lastMessageId: 0
};

function openChat(userId, userName) {
    currentChat = {
        id: userId,
        type: 'user',
        lastMessageId: 0
    };

    document.getElementById('chatTitle').textContent = userName;
    document.getElementById('chatSubtitle').textContent = 'Loading messages...';
    document.getElementById('messageInput').disabled = false;
    document.getElementById('sendButton').disabled = false;

    loadMessages();
    startPolling();

    // Show chat area on mobile
    if (window.innerWidth < 1024) {
        document.getElementById('userList').classList.add('hidden');
        document.getElementById('chatArea').classList.remove('hidden');
    }
}

function openGroupChat(groupId, groupName) {
    currentChat = {
        id: groupId,
        type: 'group',
        lastMessageId: 0
    };

    document.getElementById('chatTitle').textContent = groupName;
    document.getElementById('chatSubtitle').textContent = 'Group Chat';
    document.getElementById('messageInput').disabled = false;
    document.getElementById('sendButton').disabled = false;

    loadMessages();
    startPolling();

    if (window.innerWidth < 1024) {
        document.getElementById('userList').classList.add('hidden');
        document.getElementById('chatArea').classList.remove('hidden');
    }
}

async function loadMessages() {
    if (!currentChat.id) return;

    try {
        const response = await fetch(`chat_messages.php?${currentChat.type}_id=${currentChat.id}&last_id=${currentChat.lastMessageId}`);
        const data = await response.json();

        if (data.messages) {
            const messagesArea = document.getElementById('messagesArea');
            if (currentChat.lastMessageId === 0) {
                messagesArea.innerHTML = '';
            }

            data.messages.forEach(message => {
                if (message.id > currentChat.lastMessageId) {
                    appendMessage(message);
                    currentChat.lastMessageId = message.id;
                }
            });

            messagesArea.scrollTop = messagesArea.scrollHeight;
            document.getElementById('chatSubtitle').textContent = 
                currentChat.type === 'group' ? `${data.member_count} members` : '';
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

function appendMessage(message) {
    const isCurrentUser = message.sender_id === <?php echo $_SESSION['user_id']; ?>;
    const messageDiv = document.createElement('div');
    messageDiv.className = `flex ${isCurrentUser ? 'justify-end' : 'justify-start'} mb-4`;

    messageDiv.innerHTML = `
        <div class="max-w-[70%] ${isCurrentUser ? 'bg-blue-500 text-white' : 'bg-gray-200'} rounded-lg px-4 py-2">
            ${!isCurrentUser ? `<p class="text-xs text-gray-600 mb-1">${message.sender_name}</p>` : ''}
            <p>${escapeHtml(message.content)}</p>
            <p class="text-xs ${isCurrentUser ? 'text-blue-100' : 'text-gray-500'} text-right mt-1">
                ${formatTime(message.created_at)}
            </p>
        </div>
    `;

    document.getElementById('messagesArea').appendChild(messageDiv);
}

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message || !currentChat.id) return;

    try {
        const formData = new FormData();
        formData.append(currentChat.type === 'group' ? 'group_id' : 'receiver_id', currentChat.id);
        formData.append('content', message);

        const response = await fetch('send_message.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            input.value = '';
            appendMessage({
                id: result.message_id,
                sender_id: <?php echo $_SESSION['user_id']; ?>,
                content: message,
                created_at: new Date().toISOString()
            });
        }
    } catch (error) {
        console.error('Error sending message:', error);
    }
}

// Utility functions
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

let pollingInterval;
function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(loadMessages, 3000);
}

// Event Listeners
document.getElementById('messageInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.getElementById('sendButton').addEventListener('click', sendMessage);

document.getElementById('backButton').addEventListener('click', function() {
    document.getElementById('userList').classList.remove('hidden');
    document.getElementById('chatArea').classList.add('hidden');
});

document.getElementById('searchUsers').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    document.querySelectorAll('.user-chat-item').forEach(item => {
        const userName = item.querySelector('.font-medium').textContent.toLowerCase();
        item.style.display = userName.includes(searchTerm) ? 'flex' : 'none';
    });
});

// Update online status periodically
setInterval(async function() {
    try {
        await fetch('update_status.php');
    } catch (error) {
        console.error('Error updating status:', error);
    }
}, 60000);
</script>