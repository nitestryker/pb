<?php
session_start();

require_once 'maintenance_check.php';
require_once 'database.php';
require_once 'audit_logger.php';

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$user_id) {
    header('Location: /?page=login');
    exit;
}

$db = Database::getInstance()->getConnection();
$audit_logger = new AuditLogger();

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['target_user_id'])) {
        $target_user_id = $_POST['target_user_id'];

        if ($target_user_id === $user_id) {
            $error = "You cannot follow yourself.";
        } else {
            try {
                $db->beginTransaction();

                if ($_POST['action'] === 'follow') {
                    // Check if already following
                    $stmt = $db->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
                    $stmt->execute([$user_id, $target_user_id]);

                    if (!$stmt->fetch()) {
                        // Add follow relationship
                        $stmt = $db->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
                        $stmt->execute([$user_id, $target_user_id]);

                        // Update counts
                        $db->prepare("UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) WHERE id = ?")->execute([$user_id, $user_id]);
                        $db->prepare("UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) WHERE id = ?")->execute([$target_user_id, $target_user_id]);

                        $audit_logger->log('user_followed', 'social', $user_id, ['target_user_id' => $target_user_id]);
                        $success = "Successfully followed user.";
                    }
                } elseif ($_POST['action'] === 'unfollow') {
                    // Remove follow relationship
                    $stmt = $db->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
                    $stmt->execute([$user_id, $target_user_id]);

                    // Update counts
                    $db->prepare("UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) WHERE id = ?")->execute([$user_id, $user_id]);
                    $db->prepare("UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) WHERE id = ?")->execute([$target_user_id, $target_user_id]);

                    $audit_logger->log('user_unfollowed', 'social', $user_id, ['target_user_id' => $target_user_id]);
                    $success = "Successfully unfollowed user.";
                }

                $db->commit();
            } catch (Exception $e) {
                $db->rollback();
                $error = "An error occurred. Please try again.";
                error_log("Follow/unfollow error: " . $e->getMessage());
            }
        }
    }
}

// Get current tab
$tab = $_GET['tab'] ?? 'following';

// Get user's following list
$following = [];
if ($tab === 'following') {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.profile_image, u.tagline, 
               COUNT(p.id) as paste_count,
               uf.created_at as followed_at
        FROM user_follows uf 
        JOIN users u ON uf.following_id = u.id
        LEFT JOIN pastes p ON u.id = p.user_id AND p.is_public = 1 AND p.zero_knowledge = 0
        WHERE uf.follower_id = ?
        GROUP BY u.id, u.username, u.profile_image, u.tagline, uf.created_at
        ORDER BY uf.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $following = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's followers list
$followers = [];
if ($tab === 'followers') {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.profile_image, u.tagline,
               COUNT(p.id) as paste_count,
               uf.created_at as followed_at
        FROM user_follows uf 
        JOIN users u ON uf.follower_id = u.id
        LEFT JOIN pastes p ON u.id = p.user_id AND p.is_public = 1 AND p.zero_knowledge = 0
        WHERE uf.following_id = ?
        GROUP BY u.id, u.username, u.profile_image, u.tagline, uf.created_at
        ORDER BY uf.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get suggested users (users not currently following)
$suggested = [];
if ($tab === 'discover') {
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.profile_image, u.tagline,
               COUNT(p.id) as paste_count,
               u.followers_count
        FROM users u
        LEFT JOIN pastes p ON u.id = p.user_id AND p.is_public = 1 AND p.zero_knowledge = 0
        WHERE u.id != ? 
        AND u.id NOT IN (SELECT following_id FROM user_follows WHERE follower_id = ?)
        GROUP BY u.id, u.username, u.profile_image, u.tagline, u.followers_count
        ORDER BY u.followers_count DESC, COUNT(p.id) DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    $suggested = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user's counts
$stmt = $db->prepare("SELECT following_count, followers_count FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_counts = $stmt->fetch(PDO::FETCH_ASSOC);

$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Following - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
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
                                  <a href="/threaded_messages.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-envelope mr-2"></i> My Messages
                                  </a>

                                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                                  <!-- Tools Group -->
                                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tools</div>
                                  <a href="/project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-folder-tree mr-2"></i> Projects
                                  </a>
                                  <a href="/following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 bg-blue-100 dark:bg-blue-900">
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

    <div class="max-w-6xl mx-auto px-4 py-8 pt-20">
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <!-- Header -->
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-users mr-3 text-blue-500"></i>
                    Social Network
                </h1>
                <div class="mt-2 flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400">
                    <span><strong><?= number_format($user_counts['following_count']) ?></strong> Following</span>
                    <span><strong><?= number_format($user_counts['followers_count']) ?></strong> Followers</span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <a href="?tab=following" class="<?= $tab === 'following' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700' ?> py-4 px-1 text-sm font-medium">
                        <i class="fas fa-user-friends mr-2"></i>Following (<?= number_format($user_counts['following_count']) ?>)
                    </a>
                    <a href="?tab=followers" class="<?= $tab === 'followers' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700' ?> py-4 px-1 text-sm font-medium">
                        <i class="fas fa-users mr-2"></i>Followers (<?= number_format($user_counts['followers_count']) ?>)
                    </a>
                    <a href="?tab=discover" class="<?= $tab === 'discover' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700' ?> py-4 px-1 text-sm font-medium">
                        <i class="fas fa-search mr-2"></i>Discover
                    </a>
                </nav>
            </div>

            <!-- Content -->
            <div class="p-6">
                <?php if ($tab === 'following'): ?>
                    <!-- Following Tab -->
                    <?php if (empty($following)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-user-friends text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 text-lg mb-4">You're not following anyone yet.</p>
                            <a href="?tab=discover" class="text-blue-500 hover:text-blue-700">Discover users to follow</a>
                        </div>
                    <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($following as $user): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <img src="<?= htmlspecialchars($user['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($user['username'])).'?d=mp&s=48') ?>" 
                                             class="w-12 h-12 rounded-full" alt="Profile">
                                        <div class="flex-1">
                                            <a href="/?page=profile&username=<?= urlencode($user['username']) ?>" class="font-medium text-blue-500 hover:text-blue-700">
                                                @<?= htmlspecialchars($user['username']) ?>
                                            </a>
                                            <div class="text-sm text-gray-500">
                                                <?= number_format($user['paste_count']) ?> pastes
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($user['tagline']): ?>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            <?= htmlspecialchars($user['tagline']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs text-gray-500">
                                            Following since <?= date('M Y', $user['followed_at']) ?>
                                        </span>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="unfollow">
                                            <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" class="text-sm bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600">
                                                Unfollow
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'followers'): ?>
                    <!-- Followers Tab -->
                    <?php if (empty($followers)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 text-lg mb-4">No followers yet.</p>
                            <p class="text-gray-400">Share your profile to get followers!</p>
                        </div>
                    <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($followers as $user): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <img src="<?= htmlspecialchars($user['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($user['username'])).'?d=mp&s=48') ?>" 
                                             class="w-12 h-12 rounded-full" alt="Profile">
                                        <div class="flex-1">
                                            <a href="/?page=profile&username=<?= urlencode($user['username']) ?>" class="font-medium text-blue-500 hover:text-blue-700">
                                                @<?= htmlspecialchars($user['username']) ?>
                                            </a>
                                            <div class="text-sm text-gray-500">
                                                <?= number_format($user['paste_count']) ?> pastes
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($user['tagline']): ?>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            <?= htmlspecialchars($user['tagline']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-500">
                                        Followed you <?= date('M j, Y', $user['followed_at']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($tab === 'discover'): ?>
                    <!-- Discover Tab -->
                    <?php if (empty($suggested)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500 text-lg mb-4">No new users to discover right now.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($suggested as $user): ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-3 mb-3">
                                        <img src="<?= htmlspecialchars($user['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($user['username'])).'?d=mp&s=48') ?>" 
                                             class="w-12 h-12 rounded-full" alt="Profile">
                                        <div class="flex-1">
                                            <a href="/?page=profile&username=<?= urlencode($user['username']) ?>" class="font-medium text-blue-500 hover:text-blue-700">
                                                @<?= htmlspecialchars($user['username']) ?>
                                            </a>
                                            <div class="text-sm text-gray-500">
                                                <?= number_format($user['paste_count']) ?> pastes · <?= number_format($user['followers_count']) ?> followers
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($user['tagline']): ?>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                                            <?= htmlspecialchars($user['tagline']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="follow">
                                        <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                            <i class="fas fa-user-plus mr-2"></i>Follow
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
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

        // Show success/error messages with SweetAlert
        <?php if (isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= addslashes($success) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        <?php endif; ?>

        <?php if (isset($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($error) ?>'
        });
        <?php endif; ?>
    </script>
</body>
</html>