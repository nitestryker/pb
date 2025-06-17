
<?php
session_start();

require_once 'maintenance_check.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

if (!$user_id) {
    header('Location: /?page=login');
    exit;
}

$db = Database::getInstance()->getConnection();

function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    return floor($diff / 86400) . ' days ago';
}

// Get pastes from followed users
$stmt = $db->prepare("
    SELECT p.*, u.username, u.profile_image,
           (SELECT COUNT(*) FROM user_pastes WHERE paste_id = p.id AND is_favorite = 1) as favorite_count,
           (SELECT COUNT(*) FROM comments WHERE paste_id = p.id) as comment_count,
           EXISTS(SELECT 1 FROM user_pastes WHERE user_id = ? AND paste_id = p.id AND is_favorite = 1) as is_favorite
    FROM pastes p
    JOIN users u ON p.user_id = u.id
    JOIN user_follows uf ON uf.following_id = p.user_id
    WHERE uf.follower_id = ? 
    AND p.is_public = 1 
    AND (p.expire_time IS NULL OR p.expire_time > ?)
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_id, $user_id, time()]);
$feed_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get following count
$stmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
$stmt->execute([$user_id]);
$following_count = $stmt->fetchColumn();

$theme = $_COOKIE['theme'] ?? 'dark';
?>

<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Following Feed - PasteForge</title>
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
    <!-- Navigation Bar -->
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
                        <a href="/?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
                        <a href="following_feed.php" class="bg-blue-700 px-3 py-2 rounded">Feed</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-home mr-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6 flex items-center">
                <i class="fas fa-stream mr-3 text-blue-500"></i>
                Following Feed
                <span class="ml-2 text-sm font-normal text-gray-500">(<?= $following_count ?> following)</span>
            </h1>

            <?php if (empty($feed_pastes)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-rss text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500 text-lg mb-4">Your feed is empty.</p>
                    <?php if ($following_count === 0): ?>
                        <p class="text-gray-400 mb-4">You're not following anyone yet.</p>
                        <a href="following.php?tab=discover" class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-search mr-2"></i>Discover Users to Follow
                        </a>
                    <?php else: ?>
                        <p class="text-gray-400">The users you follow haven't posted any public pastes recently.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($feed_pastes as $paste): ?>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <!-- Header -->
                            <div class="flex items-center gap-3 mb-4">
                                <img src="<?= htmlspecialchars($paste['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($paste['username'])).'?d=mp&s=40') ?>" 
                                     class="w-10 h-10 rounded-full" alt="Profile">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <a href="/?page=profile&username=<?= urlencode($paste['username']) ?>" class="font-medium text-blue-500 hover:text-blue-700">
                                            @<?= htmlspecialchars($paste['username']) ?>
                                        </a>
                                        <span class="text-gray-500">·</span>
                                        <span class="text-sm text-gray-500"><?= human_time_diff($paste['created_at']) ?></span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($paste['language']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Paste Title and Content Preview -->
                            <div class="mb-4">
                                <h3 class="text-lg font-medium mb-2">
                                    <a href="/?id=<?= $paste['id'] ?>" class="text-blue-500 hover:text-blue-700">
                                        <?= htmlspecialchars($paste['title']) ?>
                                    </a>
                                </h3>
                                <div class="bg-gray-100 dark:bg-gray-800 rounded p-3 font-mono text-sm overflow-hidden">
                                    <?= htmlspecialchars(substr($paste['content'], 0, 200)) ?><?= strlen($paste['content']) > 200 ? '...' : '' ?>
                                </div>
                            </div>

                            <!-- Tags -->
                            <?php if (!empty($paste['tags'])): ?>
                                <div class="mb-4">
                                    <?php foreach (explode(',', $paste['tags']) as $tag): ?>
                                        <span class="inline-block bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs px-2 py-1 rounded mr-2">
                                            <?= htmlspecialchars(trim($tag)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="flex items-center gap-6 text-sm text-gray-500">
                                <a href="/?id=<?= $paste['id'] ?>" class="hover:text-blue-500">
                                    <i class="fas fa-eye mr-1"></i><?= number_format($paste['views']) ?> views
                                </a>
                                <button onclick="toggleLike(<?= $paste['id'] ?>)" class="hover:text-yellow-500 like-btn">
                                    <i class="fas fa-star <?= $paste['is_favorite'] ? 'text-yellow-400' : '' ?> mr-1"></i>
                                    <span id="like-count-<?= $paste['id'] ?>"><?= number_format($paste['favorite_count']) ?></span>
                                </button>
                                <a href="/?id=<?= $paste['id'] ?>#comments" class="hover:text-green-500">
                                    <i class="fas fa-comment mr-1"></i><?= number_format($paste['comment_count']) ?> comments
                                </a>
                                <a href="/?id=<?= $paste['id'] ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                                    View Paste →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Load More -->
                <div class="text-center mt-8">
                    <button onclick="loadMorePastes()" class="bg-gray-500 text-white px-6 py-2 rounded hover:bg-gray-600">
                        Load More
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        async function toggleLike(pasteId) {
            try {
                const response = await fetch('/', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_favorite=1&paste_id=${pasteId}`
                });

                if (response.ok) {
                    const btn = document.querySelector(`button[onclick="toggleLike(${pasteId})"]`);
                    const star = btn.querySelector('.fa-star');
                    const countSpan = document.getElementById(`like-count-${pasteId}`);
                    const currentCount = parseInt(countSpan.textContent.replace(/,/g, ''));

                    if (star.classList.contains('text-yellow-400')) {
                        star.classList.remove('text-yellow-400');
                        countSpan.textContent = (currentCount - 1).toLocaleString();
                    } else {
                        star.classList.add('text-yellow-400');
                        countSpan.textContent = (currentCount + 1).toLocaleString();
                    }
                }
            } catch (error) {
                console.error('Error toggling like:', error);
            }
        }

        function loadMorePastes() {
            // Implement pagination if needed
            alert('Load more functionality would be implemented here');
        }

        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
            html.classList.remove('dark', 'light');
            html.classList.add(newTheme);
            document.cookie = `theme=${newTheme};path=/`;
        }
    </script>
</body>
</html>
