<?php
session_start();
require_once 'database.php';
require_once 'maintenance_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$db = Database::getInstance()->getConnection();

// Ensure message_recipients table exists
$db->exec("CREATE TABLE IF NOT EXISTS message_recipients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    recipient_id TEXT NOT NULL,
    recipient_keep INTEGER DEFAULT 1,
    recipient_read_date INTEGER NULL,
    FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY(recipient_id) REFERENCES users(id)
)");

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'search_users':
            $query = $_POST['query'] ?? '';
            if (strlen($query) >= 2) {
                $stmt = $db->prepare("SELECT username FROM users WHERE username LIKE ? AND id != ? LIMIT 10");
                $stmt->execute(['%' . $query . '%', $user_id]);
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo json_encode($users);
            } else {
                echo json_encode([]);
            }
            exit;

        case 'send_message':
            $recipients = json_decode($_POST['recipients'] ?? '[]', true);
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $reply_to = $_POST['reply_to'] ?? null;

            // Validate input
            if (!is_array($recipients) || empty($recipients)) {
                echo json_encode(['success' => false, 'error' => 'At least one recipient is required']);
                exit;
            }

            if (empty($subject)) {
                echo json_encode(['success' => false, 'error' => 'Subject is required']);
                exit;
            }

            if (empty($content)) {
                echo json_encode(['success' => false, 'error' => 'Message content is required']);
                exit;
            }

            // Remove duplicates and filter empty values
            $recipients = array_unique(array_filter($recipients));

            if (empty($recipients)) {
                echo json_encode(['success' => false, 'error' => 'Valid recipients are required']);
                exit;
            }

            try {
                // Validate recipients exist
                $placeholders = str_repeat('?,', count($recipients) - 1) . '?';
                $stmt = $db->prepare("SELECT username FROM users WHERE username IN ($placeholders)");
                $stmt->execute($recipients);
                $valid_recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (count($valid_recipients) !== count($recipients)) {
                    $missing = array_diff($recipients, $valid_recipients);
                    echo json_encode(['success' => false, 'error' => 'User(s) not found: ' . implode(', ', $missing)]);
                    exit;
                }

                $db->beginTransaction();

                // Determine thread_id
                $thread_id = null;
                if ($reply_to) {
                    $stmt = $db->prepare("SELECT thread_id FROM messages WHERE id = ?");
                    $stmt->execute([$reply_to]);
                    $result = $stmt->fetch();
                    if ($result) {
                        $thread_id = $result['thread_id'] ?: $reply_to;
                    }
                }

                // Insert message
                $stmt = $db->prepare("INSERT INTO messages (sender_id, subject, content, reply_to_message_id, thread_id, created_at, sender_keep) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$user_id, $subject, $content, $reply_to, $thread_id, time()]);
                $message_id = $db->lastInsertId();

                if (!$message_id) {
                    throw new Exception("Failed to create message");
                }

                // If this is a new thread, set thread_id to message_id
                if (!$thread_id) {
                    $stmt = $db->prepare("UPDATE messages SET thread_id = ? WHERE id = ?");
                    $stmt->execute([$message_id, $message_id]);
                }

                // Insert recipients
                foreach ($valid_recipients as $recipient_username) {
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$recipient_username]);
                    $recipient_id = $stmt->fetchColumn();

                    if (!$recipient_id) {
                        continue; // Skip if user not found
                    }

                    // Don't add sender as recipient
                    if ($recipient_id === $user_id) {
                        continue;
                    }

                    $stmt = $db->prepare("INSERT INTO message_recipients (message_id, recipient_id, recipient_keep) VALUES (?, ?, 1)");
                    $stmt->execute([$message_id, $recipient_id]);

                    // Create notification
                    $stmt = $db->prepare("INSERT INTO message_notifications (user_id, message_id, message, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$recipient_id, $message_id, "You've received a new message from " . $username, time()]);
                }

                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Message sent successfully']);

            } catch (Exception $e) {
                $db->rollback();
                error_log("Message sending error: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Failed to send message: ' . $e->getMessage()]);
            }
            exit;

        case 'mark_read':
            $message_id = $_POST['message_id'] ?? 0;
            $stmt = $db->prepare("UPDATE message_recipients SET recipient_read_date = ? WHERE message_id = ? AND recipient_id = ?");
            $stmt->execute([time(), $message_id, $user_id]);
            echo json_encode(['success' => true]);
            exit;

        case 'delete_thread':
            $thread_id = $_POST['thread_id'] ?? 0;
            $type = $_POST['type'] ?? 'received'; // 'received' or 'sent'

            if ($type === 'received') {
                // Mark as deleted for recipient
                $stmt = $db->prepare("
                    UPDATE message_recipients 
                    SET recipient_keep = 0 
                    WHERE recipient_id = ? AND message_id IN (
                        SELECT id FROM messages WHERE thread_id = ? OR id = ?
                    )
                ");
                $stmt->execute([$user_id, $thread_id, $thread_id]);
            } else {
                // Mark as deleted for sender
                $stmt = $db->prepare("UPDATE messages SET sender_keep = 0 WHERE (thread_id = ? OR id = ?) AND sender_id = ?");
                $stmt->execute([$thread_id, $thread_id, $user_id]);
            }

            echo json_encode(['success' => true]);
            exit;
    }
}

// Get view type
$view = $_GET['view'] ?? 'inbox';
$thread_id = $_GET['thread'] ?? null;

// Get theme
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Messages - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <style>
        .thread-preview { max-height: 60px; overflow: hidden; }
        .compose-form { transition: all 0.3s ease; }
        .user-suggestion { cursor: pointer; }
        .user-suggestion:hover { background-color: rgb(59, 130, 246); color: white; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">
    <!-- Modern Navigation Bar -->
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg fixed w-full z-10">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                    <div class="flex space-x-4">
                        <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
                        <a href="/?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">Archive</a>
                        <?php if ($user_id): ?>
                            <a href="/?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($user_id): ?>
                        <!-- Notification Bell -->
                        <a href="/notifications.php" class="relative p-2 rounded hover:bg-blue-700 transition-colors">
                            <i class="fas fa-bell text-lg"></i>
                            <?php
                            // Get unread notification count for navigation
                            $stmt = $db->prepare("SELECT COUNT(*) FROM comment_notifications WHERE user_id = ? AND is_read = 0");
                            $stmt->execute([$user_id]);
                            $nav_unread_notifications = $stmt->fetchColumn();
                            if ($nav_unread_notifications > 0):
                            ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] animate-pulse">
                                    <?= $nav_unread_notifications > 99 ? '99+' : $nav_unread_notifications ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <button onclick="toggleTheme()" class="p-2 rounded hover:bg-blue-700">
                        <i class="fas fa-moon"></i>
                    </button>
                    <?php if (!$user_id): ?>
                        <div class="flex items-center space-x-2">
                            <a href="/?page=login" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Login</span>
                            </a>
                            <a href="/?page=signup" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                                <i class="fas fa-user-plus"></i>
                                <span>Sign Up</span>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                                <?php
                                $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                                $stmt->execute([$user_id]);
                                $user_avatar = $stmt->fetch()['profile_image'];
                                ?>
                                <img src="<?= $user_avatar ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=32' ?>" 
                                     class="w-8 h-8 rounded-full" alt="Profile">
                                <span><?= htmlspecialchars($username) ?></span>
                                <i class="fas fa-chevron-down ml-1"></i>
                            </button>
                            <div x-show="open" 
                                 @click.away="open = false"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1">
                                  <!-- Account Group -->
                                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Account</div>
                                  <a href="/?page=edit-profile" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-edit mr-2"></i> Edit Profile
                                  </a>
                                  <a href="/?page=profile&username=<?= urlencode($username) ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user mr-2"></i> View Profile
                                  </a>
                                  <a href="/?page=account" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-crown mr-2"></i> Account
                                  </a>
                                  <a href="/?page=settings" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog mr-2"></i> Edit Settings
                                  </a>

                                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                                  <!-- Messages Group -->
                                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Messages</div>
                                  <a href="/threaded_messages.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 bg-blue-100 dark:bg-blue-900">
                                    <i class="fas fa-envelope mr-2"></i> My Messages
                                  </a>

                                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                                  <!-- Tools Group -->
                                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tools</div>
                                  <a href="/project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-folder-tree mr-2"></i> Projects
                                  </a>
                                  <a href="/following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users mr-2"></i> Following
                                  </a>
                                  <a href="/?page=import-export" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-exchange-alt mr-2"></i> Import/Export
                                  </a>

                                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                                  <!-- Logout -->
                                  <a href="/?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                  </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-6 pt-20">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <!-- Header -->
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold flex items-center">
                        <i class="fas fa-envelope mr-3 text-blue-500"></i>
                        Messages
                    </h1>
                    <button onclick="toggleCompose()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-pen mr-2"></i>Compose
                    </button>
                </div>

                <!-- Tab Navigation -->
                <div class="flex space-x-4 mt-4">
                    <a href="?view=inbox" class="px-4 py-2 rounded-lg <?= $view === 'inbox' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                        <i class="fas fa-inbox mr-2"></i>Inbox
                    </a>
                    <a href="?view=outbox" class="px-4 py-2 rounded-lg <?= $view === 'outbox' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                        <i class="fas fa-paper-plane mr-2"></i>Sent
                    </a>
                </div>
            </div>

            <!-- Compose Form -->
            <div id="composeForm" class="compose-form p-6 border-b border-gray-200 dark:border-gray-700 hidden">
                <h3 class="text-lg font-semibold mb-4">Compose Message</h3>
                <form id="messageForm" class="space-y-4">
                    <div class="relative">
                        <label class="block text-sm font-medium mb-2">To:</label>
                        <div class="flex flex-wrap gap-2 p-2 border border-gray-300 dark:border-gray-600 rounded-lg min-h-[42px] bg-white dark:bg-gray-700" id="recipientContainer">
                            <input type="text" id="recipientInput" placeholder="Start typing username..." 
                                   class="flex-1 min-w-[200px] border-none outline-none bg-transparent">
                        </div>
                        <div id="userSuggestions" class="absolute z-10 w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg mt-1 hidden max-h-48 overflow-y-auto"></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Subject:</label>
                        <input type="text" id="subjectInput" required 
                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Message:</label>
                        <textarea id="contentInput" rows="6" required 
                                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Send
                        </button>
                        <button type="button" onclick="cancelCompose()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Thread View or List View -->
            <div class="p-6">
                <?php if ($thread_id): ?>
                    <!-- Thread Detail View -->
                    <?php
                    // Get thread messages
                    if ($view === 'inbox') {
                        $stmt = $db->prepare("
                            SELECT m.*, u.username as sender_username, mr.recipient_read_date,
                                   GROUP_CONCAT(ur.username) as recipients
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            LEFT JOIN message_recipients mr ON m.id = mr.message_id AND mr.recipient_id = ?
                            LEFT JOIN message_recipients mr2 ON m.id = mr2.message_id
                            LEFT JOIN users ur ON mr2.recipient_id = ur.id
                            WHERE (m.thread_id = ? OR m.id = ?) 
                              AND (mr.recipient_keep = 1 OR m.sender_id = ?)
                            GROUP BY m.id
                            ORDER BY m.created_at ASC
                        ");
                        $stmt->execute([$user_id, $thread_id, $thread_id, $user_id]);
                    } else {
                        $stmt = $db->prepare("
                            SELECT m.*, u.username as sender_username,
                                   GROUP_CONCAT(ur.username) as recipients
                            FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            LEFT JOIN message_recipients mr ON m.id = mr.message_id
                            LEFT JOIN users ur ON mr.recipient_id = ur.id
                            WHERE (m.thread_id = ? OR m.id = ?) AND m.sender_id = ? AND m.sender_keep = 1
                            GROUP BY m.id
                            ORDER BY m.created_at ASC
                        ");
                        $stmt->execute([$thread_id, $thread_id, $user_id]);
                    }
                    $thread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="mb-4">
                        <a href="?view=<?= $view ?>" class="text-blue-500 hover:text-blue-700">
                            <i class="fas fa-arrow-left mr-2"></i>Back to <?= ucfirst($view) ?>
                        </a>
                    </div>

                    <?php if (!empty($thread_messages)): ?>
                        <div class="space-y-4">
                            <?php foreach ($thread_messages as $message): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 <?= $message['sender_id'] === $user_id ? 'ml-8' : 'mr-8' ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <strong class="<?= $message['sender_id'] === $user_id ? 'text-blue-600' : 'text-green-600' ?>">
                                                <?= $message['sender_id'] === $user_id ? 'You' : htmlspecialchars($message['sender_username']) ?>
                                            </strong>
                                            <?php if ($message['recipients']): ?>
                                                <span class="text-gray-500 text-sm">
                                                    to <?= htmlspecialchars($message['recipients']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-gray-500 text-sm">
                                            <?= date('M j, Y g:i A', $message['created_at']) ?>
                                        </span>
                                    </div>
                                    <h4 class="font-medium mb-2"><?= htmlspecialchars($message['subject']) ?></h4>
                                    <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap"><?= htmlspecialchars($message['content']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Reply Form -->
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="font-medium mb-4">Reply to this thread</h4>
                            <form id="replyForm" class="space-y-4">
                                <input type="hidden" id="replyToId" value="<?= $thread_messages[0]['id'] ?>">
                                <div>
                                    <label class="block text-sm font-medium mb-2">Subject:</label>
                                    <input type="text" id="replySubject" value="Re: <?= htmlspecialchars($thread_messages[0]['subject']) ?>" required
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Message:</label>
                                    <textarea id="replyContent" rows="4" required 
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"></textarea>
                                </div>
                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-reply mr-2"></i>Send Reply
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-envelope-open text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">Thread not found or no access.</p>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- List View -->
                    <?php
                    if ($view === 'inbox') {
                        // Get received message threads
                        $stmt = $db->prepare("
                            SELECT 
                                COALESCE(m.thread_id, m.id) as thread_id,
                                m.subject,
                                u.username as sender_username,
                                MAX(m.created_at) as latest_date,
                                COUNT(CASE WHEN mr.recipient_read_date IS NULL THEN 1 END) as unread_count,
                                (SELECT content FROM messages m2 WHERE (m2.thread_id = COALESCE(m.thread_id, m.id) OR m2.id = COALESCE(m.thread_id, m.id)) ORDER BY m2.created_at DESC LIMIT 1) as latest_content,
                                COUNT(*) as message_count
                            FROM messages m
                            JOIN message_recipients mr ON m.id = mr.message_id
                            JOIN users u ON m.sender_id = u.id
                            WHERE mr.recipient_id = ? AND mr.recipient_keep = 1
                            GROUP BY COALESCE(m.thread_id, m.id)
                            ORDER BY latest_date DESC
                        ");
                        $stmt->execute([$user_id]);
                    } else {
                        // Get sent message threads
                        $stmt = $db->prepare("
                            SELECT 
                                COALESCE(m.thread_id, m.id) as thread_id,
                                m.subject,
                                GROUP_CONCAT(DISTINCT u.username) as recipients,
                                MAX(m.created_at) as latest_date,
                                (SELECT content FROM messages m2 WHERE (m2.thread_id = COALESCE(m.thread_id, m.id) OR m2.id = COALESCE(m.thread_id, m.id)) ORDER BY m2.created_at DESC LIMIT 1) as latest_content,
                                COUNT(*) as message_count
                            FROM messages m
                            LEFT JOIN message_recipients mr ON m.id = mr.message_id
                            LEFT JOIN users u ON mr.recipient_id = u.id
                            WHERE m.sender_id = ? AND m.sender_keep = 1
                            GROUP BY COALESCE(m.thread_id, m.id)
                            ORDER BY latest_date DESC
                        ");
                        $stmt->execute([$user_id]);
                    }
                    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (!empty($threads)): ?>
                        <div class="space-y-2">
                            <?php foreach ($threads as $thread): ?>
                                <div class="flex items-center p-4 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                                     onclick="window.location.href='?view=<?= $view ?>&thread=<?= $thread['thread_id'] ?>'">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <h3 class="font-medium truncate <?= $view === 'inbox' && $thread['unread_count'] > 0 ? 'font-bold' : '' ?>">
                                                <?= htmlspecialchars($thread['subject']) ?>
                                            </h3>
                                            <div class="flex items-center space-x-2">
                                                <?php if ($view === 'inbox' && $thread['unread_count'] > 0): ?>
                                                    <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded-full">
                                                        <?= $thread['unread_count'] ?> new
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-gray-500 text-sm">
                                                    <?= date('M j, Y', $thread['latest_date']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                                <?php if ($view === 'inbox'): ?>
                                                    From: <?= htmlspecialchars($thread['sender_username']) ?>
                                                <?php else: ?>
                                                    To: <?= htmlspecialchars($thread['recipients']) ?>
                                                <?php endif; ?>
                                            </p>
                                            <span class="text-gray-500 text-xs">
                                                <?= $thread['message_count'] ?> message<?= $thread['message_count'] > 1 ? 's' : '' ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-500 text-sm thread-preview mt-1">
                                            <?= htmlspecialchars(substr($thread['latest_content'], 0, 120)) ?><?= strlen($thread['latest_content']) > 120 ? '...' : '' ?>
                                        </p>
                                    </div>
                                    <div class="ml-4 flex items-center space-x-2">
                                        <button onclick="event.stopPropagation(); deleteThread(<?= $thread['thread_id'] ?>, '<?= $view === 'inbox' ? 'received' : 'sent' ?>')" 
                                                class="text-red-500 hover:text-red-700 p-1">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <i class="fas fa-chevron-right text-gray-400"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-envelope text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 text-lg mb-2">
                                <?= $view === 'inbox' ? 'No messages received yet.' : 'No messages sent yet.' ?>
                            </p>
                            <p class="text-gray-400">
                                <?= $view === 'inbox' ? 'Messages from other users will appear here.' : 'Click compose to send your first message.' ?>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
            html.classList.remove('dark', 'light');
            html.classList.add(newTheme);
            document.cookie = `theme=${newTheme};path=/`;
        }

        let selectedRecipients = [];
        let searchTimeout = null;

        // Compose form functions
        function toggleCompose() {
            const form = document.getElementById('composeForm');
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                document.getElementById('recipientInput').focus();
            }
        }

        function cancelCompose() {
            document.getElementById('composeForm').classList.add('hidden');
            document.getElementById('messageForm').reset();
            selectedRecipients = [];
            updateRecipientDisplay();
        }

        // Recipient handling
        document.getElementById('recipientInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);