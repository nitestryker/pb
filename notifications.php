<?php
session_start();

// Check for maintenance mode
require_once 'maintenance_check.php';

// Database connection
require_once 'database.php';
$db = Database::getInstance()->getConnection();

// Get user session
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$user_id) {
  header('Location: /?page=login');
  exit;
}

// Create all notification tables if they don't exist
$db->exec("CREATE TABLE IF NOT EXISTS comment_notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id TEXT NOT NULL,
  paste_id INTEGER NOT NULL,
  comment_id INTEGER,
  reply_id INTEGER,
  type TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at INTEGER DEFAULT (strftime('%s', 'now')),
  is_read INTEGER DEFAULT 0,
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(paste_id) REFERENCES pastes(id),
  FOREIGN KEY(comment_id) REFERENCES comments(id),
  FOREIGN KEY(reply_id) REFERENCES comment_replies(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS message_notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id TEXT NOT NULL,
  message_id INTEGER NOT NULL,
  type TEXT DEFAULT 'new_message',
  message TEXT NOT NULL,
  created_at INTEGER DEFAULT (strftime('%s', 'now')),
  is_read INTEGER DEFAULT 0,
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS paste_expiration_notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id TEXT NOT NULL,
  paste_id INTEGER NOT NULL,
  paste_title TEXT NOT NULL,
  expires_at INTEGER NOT NULL,
  reminder_type TEXT NOT NULL,
  message TEXT NOT NULL,
  created_at INTEGER DEFAULT (strftime('%s', 'now')),
  is_read INTEGER DEFAULT 0,
  FOREIGN KEY(user_id) REFERENCES users(id),
  FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS expiration_reminders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  paste_id INTEGER NOT NULL,
  user_id TEXT NOT NULL,
  reminder_type TEXT NOT NULL,
  sent_at INTEGER DEFAULT (strftime('%s', 'now')),
  FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE(paste_id, reminder_type)
)");

// Mark notifications as read
if (isset($_POST['mark_read'])) {
  $notification_id = $_POST['notification_id'];
  $notification_type = $_POST['notification_type'] ?? 'comment';

  if ($notification_type === 'message') {
    $stmt = $db->prepare("UPDATE message_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  } elseif ($notification_type === 'expiration') {
    $stmt = $db->prepare("UPDATE paste_expiration_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  } else {
    $stmt = $db->prepare("UPDATE comment_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  }
  header('Location: notifications.php');
  exit;
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
  $stmt = $db->prepare("UPDATE comment_notifications SET is_read = 1 WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $stmt = $db->prepare("UPDATE message_notifications SET is_read = 1 WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $stmt = $db->prepare("UPDATE paste_expiration_notifications SET is_read = 1 WHERE user_id = ?");
  $stmt->execute([$user_id]);
  header('Location: notifications.php');
  exit;
}

// Delete notification
if (isset($_POST['delete_notification'])) {
  $notification_id = $_POST['notification_id'];
  $notification_type = $_POST['notification_type'] ?? 'comment';

  if ($notification_type === 'message') {
    $stmt = $db->prepare("DELETE FROM message_notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  } elseif ($notification_type === 'expiration') {
    $stmt = $db->prepare("DELETE FROM paste_expiration_notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  } else {
    $stmt = $db->prepare("DELETE FROM comment_notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
  }

  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
  exit;
}

// Delete all notifications
if (isset($_POST['delete_all_notifications'])) {
  $stmt = $db->prepare("DELETE FROM comment_notifications WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $stmt = $db->prepare("DELETE FROM message_notifications WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $stmt = $db->prepare("DELETE FROM paste_expiration_notifications WHERE user_id = ?");
  $stmt->execute([$user_id]);
  header('Location: notifications.php');
  exit;
}

// Get notifications (including message and expiration notifications)
$stmt = $db->prepare("
  SELECT notification_type, id, created_at, is_read, message, paste_title, paste_id,
         related_content, target_comment_id, username, message_id, expires_at, reminder_type
  FROM (
    SELECT 'comment' as notification_type, cn.id, cn.created_at, cn.is_read,
           cn.message, p.title as paste_title, cn.paste_id,
           CASE 
             WHEN cn.comment_id IS NOT NULL THEN c.content
             WHEN cn.reply_id IS NOT NULL THEN cr.content
           END as related_content,
           CASE 
             WHEN cn.reply_id IS NOT NULL THEN cr.parent_comment_id
             ELSE cn.comment_id
           END as target_comment_id,
           CASE 
             WHEN cn.comment_id IS NOT NULL THEN cu.username
             WHEN cn.reply_id IS NOT NULL THEN ru.username
           END as username,
           NULL as message_id, NULL as expires_at, NULL as reminder_type
    FROM comment_notifications cn
    LEFT JOIN pastes p ON cn.paste_id = p.id
    LEFT JOIN comments c ON cn.comment_id = c.id
    LEFT JOIN comment_replies cr ON cn.reply_id = cr.id
    LEFT JOIN users cu ON c.user_id = cu.id
    LEFT JOIN users ru ON cr.user_id = ru.id
    WHERE cn.user_id = ?

    UNION ALL

    SELECT 'message' as notification_type, mn.id, mn.created_at, mn.is_read,
           mn.message, NULL as paste_title, NULL as paste_id,
           m.content as related_content, NULL as target_comment_id,
           u.username, mn.message_id, NULL as expires_at, NULL as reminder_type
    FROM message_notifications mn
    LEFT JOIN messages m ON mn.message_id = m.id
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE mn.user_id = ?

    UNION ALL

    SELECT 'expiration' as notification_type, pen.id, pen.created_at, pen.is_read,
           pen.message, pen.paste_title, pen.paste_id,
           NULL as related_content, NULL as target_comment_id,
           NULL as username, NULL as message_id, pen.expires_at, pen.reminder_type
    FROM paste_expiration_notifications pen
    WHERE pen.user_id = ?
  ) AS combined_notifications
  ORDER BY created_at DESC
  LIMIT 50
");
$stmt->execute([$user_id, $user_id, $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count (comments + messages + expiration notifications)
$stmt = $db->prepare("
  SELECT 
    (SELECT COUNT(*) FROM comment_notifications WHERE user_id = ? AND is_read = 0) +
    (SELECT COUNT(*) FROM message_notifications WHERE user_id = ? AND is_read = 0) +
    (SELECT COUNT(*) FROM paste_expiration_notifications WHERE user_id = ? AND is_read = 0) as total_unread
");
$stmt->execute([$user_id, $user_id, $user_id]);
$unread_count = $stmt->fetchColumn();

// Get theme from cookie
$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
  <title>Notifications - PasteForge</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script>
    tailwind.config = {
      darkMode: 'class'
    }
  </script>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
  <!-- Modern Navigation Bar -->
  <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
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
            <a href="notifications.php" class="relative p-2 rounded bg-blue-700 transition-colors">
              <i class="fas fa-bell text-lg"></i>
              <?php if ($unread_count > 0): ?>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] animate-pulse">
                  <?= $unread_count > 99 ? '99+' : $unread_count ?>
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
                  <a href="/threaded_messages.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
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

  <div class="max-w-4xl mx-auto px-4 py-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">
          <i class="fas fa-bell mr-2"></i>Notifications 
          <?php if ($unread_count > 0): ?>
            <span class="bg-red-500 text-white text-sm px-2 py-1 rounded-full"><?= $unread_count ?></span>
          <?php endif; ?>
        </h1>
        <div class="flex gap-2">
          <?php if ($unread_count > 0): ?>
            <form method="POST" class="inline">
              <button type="submit" name="mark_all_read" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                <i class="fas fa-check-double mr-2"></i>Mark All Read
              </button>
            </form>
          <?php endif; ?>
          <?php if (!empty($notifications)): ?>
            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete all notifications? This cannot be undone.')">
              <button type="submit" name="delete_all_notifications" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                <i class="fas fa-trash mr-2"></i>Delete All
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="space-y-4">
        <?php if (empty($notifications)): ?>
          <div class="text-center py-8">
            <i class="fas fa-bell-slash text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500 text-lg mb-4">No notifications yet.</p>
            <p class="text-gray-400">You'll receive notifications when someone comments on your pastes or replies to your comments.</p>
          </div>
        <?php else: ?>
          <?php foreach ($notifications as $notification): ?>
            <div class="flex items-start gap-4 p-4 rounded-lg <?= $notification['is_read'] ? 'bg-gray-50 dark:bg-gray-700' : 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500' ?>">
              <div class="flex-shrink-0">
                <?php if ($notification['notification_type'] === 'message'): ?>
                  <i class="fas fa-envelope text-green-500 text-xl"></i>
                <?php elseif ($notification['notification_type'] === 'expiration'): ?>
                  <i class="fas fa-clock text-orange-500 text-xl"></i>
                <?php else: ?>
                  <i class="fas <?= $notification['type'] === 'comment' ? 'fa-comment' : 'fa-reply' ?> text-blue-500 text-xl"></i>
                <?php endif; ?>
              </div>
              <div class="flex-1">
                <div class="font-medium mb-1">
                  <?= htmlspecialchars($notification['message']) ?>
                </div>
                <?php if ($notification['notification_type'] === 'comment'): ?>
                  <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    On paste: <a href="/?id=<?= $notification['paste_id'] ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                      "<?= htmlspecialchars($notification['paste_title']) ?>"
                    </a>
                  </div>
                <?php elseif ($notification['notification_type'] === 'expiration'): ?>
                  <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Paste: <a href="/?id=<?= $notification['paste_id'] ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                      "<?= htmlspecialchars($notification['paste_title']) ?>"
                    </a>
                    <span class="text-orange-600 dark:text-orange-400 font-medium ml-2">
                      Expires: <?= date('M j, Y g:i A', $notification['expires_at']) ?>
                    </span>
                  </div>
                <?php endif; ?>
                <?php if ($notification['related_content']): ?>
                  <div class="text-sm text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 p-3 rounded mb-2">
                    <i class="fas fa-quote-left text-gray-400 mr-1"></i>
                    <?= htmlspecialchars(substr($notification['related_content'], 0, 150)) ?><?= strlen($notification['related_content']) > 150 ? '...' : '' ?>
                  </div>
                <?php endif; ?>
                <div class="text-xs text-gray-500 flex items-center gap-4">
                  <span>
                    <i class="fas fa-clock mr-1"></i>
                    <?= date('M j, Y g:i A', $notification['created_at']) ?>
                  </span>
                  <?php if ($notification['username']): ?>
                    <span>
                      <i class="fas fa-user mr-1"></i>
                      by @<?= htmlspecialchars($notification['username']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="flex-shrink-0 flex flex-col gap-2">
                <?php if ($notification['notification_type'] === 'message'): ?>
                  <a href="threaded_messages.php?thread_id=<?= $notification['message_id'] ?>" 
                     class="px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600 transition-colors"
                     onclick="markAsRead(<?= $notification['id'] ?>, 'message')">
                    <i class="fas fa-eye mr-1"></i>View Message
                  </a>
                <?php elseif ($notification['notification_type'] === 'expiration'): ?>
                  <a href="/?id=<?= $notification['paste_id'] ?>" 
                     class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600 transition-colors"
                     onclick="markAsRead(<?= $notification['id'] ?>, 'expiration')">
                    <i class="fas fa-eye mr-1"></i>View Paste
                  </a>
                  <button onclick="renewPaste(<?= $notification['paste_id'] ?>)" 
                          class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors">
                    <i class="fas fa-redo mr-1"></i>Renew
                  </button>
                <?php else: ?>
                  <a href="/?id=<?= $notification['paste_id'] ?><?= $notification['target_comment_id'] ? '#comment-' . $notification['target_comment_id'] : '' ?>" 
                     class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors"
                     onclick="markAsRead(<?= $notification['id'] ?>, 'comment')">
                    <i class="fas fa-eye mr-1"></i>View
                  </a>
                <?php endif; ?>
                <?php if (!$notification['is_read']): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                    <input type="hidden" name="notification_type" value="<?= $notification['notification_type'] ?>">
                    <button type="submit" name="mark_read" class="px-3 py-1 bg-gray-500 text-white text-sm rounded hover:bg-gray-600 transition-colors w-full">
                      <i class="fas fa-check mr-1"></i>Mark Read
                    </button>
                  </form>
                <?php endif; ?>
                <button onclick="deleteNotification(<?= $notification['id'] ?>, '<?= $notification['notification_type'] ?>', this)" 
                        class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600 transition-colors w-full">
                  <i class="fas fa-trash mr-1"></i>Delete
                </button>
              </div>
            </div>
          <?php endforeach; ?>
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

    function markAsRead(notificationId, notificationType = 'comment') {
      // Mark notification as read when clicked
      fetch('notifications.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `mark_read=1&notification_id=${notificationId}&notification_type=${notificationType}`
      });
    }

    function renewPaste(pasteId) {
      if (!confirm('Do you want to extend this paste\'s expiration time?')) {
        return;
      }

      // Show renewal options
      const renewalTime = prompt('Choose renewal period:\n1 = 1 hour\n2 = 1 day\n3 = 1 week\n4 = 1 month\n5 = Never expire', '3');
      
      if (!renewalTime || renewalTime < 1 || renewalTime > 5) {
        return;
      }

      const renewalOptions = {
        '1': 3600,        // 1 hour
        '2': 86400,       // 1 day  
        '3': 604800,      // 1 week
        '4': 2592000,     // 1 month
        '5': 0            // Never expire
      };

      const expireTime = renewalOptions[renewalTime];
      
      fetch('renew_paste.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `paste_id=${pasteId}&expire_time=${expireTime}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Paste renewal successful!');
          window.location.reload();
        } else {
          alert('Failed to renew paste: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred while renewing paste');
      });
    }

    function deleteNotification(notificationId, notificationType, buttonElement) {
      if (!confirm('Are you sure you want to delete this notification? This cannot be undone.')) {
        return;
      }

      // Disable button during request
      buttonElement.disabled = true;
      buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Deleting...';

      fetch('notifications.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `delete_notification=1&notification_id=${notificationId}&notification_type=${notificationType}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Find and remove the notification element
          const notificationElement = buttonElement.closest('.flex.items-start.gap-4');
          if (notificationElement) {
            notificationElement.style.opacity = '0';
            notificationElement.style.transform = 'translateX(-100%)';
            setTimeout(() => {
              notificationElement.remove();

              // Check if there are any notifications left
              const remainingNotifications = document.querySelectorAll('.flex.items-start.gap-4').length;
              if (remainingNotifications === 0) {
                // Show "no notifications" message
                const container = document.querySelector('.space-y-4');
                if (container) {
                  container.innerHTML = `
                    <div class="text-center py-8">
                      <i class="fas fa-bell-slash text-4xl text-gray-400 mb-4"></i>
                      <p class="text-gray-500 text-lg mb-4">No notifications yet.</p>
                      <p class="text-gray-400">You'll receive notifications when someone comments on your pastes or replies to your comments.</p>
                    </div>
                  `;
                }
              }
            }, 300);
          }

          // Update notification count in header
          setTimeout(() => {
            window.location.reload();
          }, 500);
        } else {
          // Re-enable button on error
          buttonElement.disabled = false;
          buttonElement.innerHTML = '<i class="fas fa-trash mr-1"></i>Delete';
          alert('Failed to delete notification: ' + (data.message || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Re-enable button on error
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<i class="fas fa-trash mr-1"></i>Delete';
        alert('Network error occurred while deleting notification');
      });
    }
  </script>
  <script defer src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>