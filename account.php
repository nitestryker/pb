
<?php
// Only start session if not already started (for standalone access)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only require these if not already included
if (!class_exists('Database')) {
    require_once 'database.php';
}
if (!class_exists('SiteSettings')) {
    require_once 'settings_helper.php';
}

if (!isset($_SESSION['user_id'])) {
    if (headers_sent()) {
        echo '<script>window.location.href = "/?page=login";</script>';
        exit;
    } else {
        header('Location: /?page=login');
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get user information
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">User not found</div>';
    exit;
}

// Get paste statistics
$stmt = $db->prepare("SELECT COUNT(*) as total_pastes FROM pastes WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_pastes = $stmt->fetch()['total_pastes'];

$stmt = $db->prepare("SELECT COUNT(*) as public_pastes FROM pastes WHERE user_id = ? AND is_public = 1 AND zero_knowledge = 0");
$stmt->execute([$user_id]);
$public_pastes = $stmt->fetch()['public_pastes'];

$stmt = $db->prepare("SELECT SUM(views) as total_views FROM pastes WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_views = $stmt->fetch()['total_views'] ?? 0;

// Get recent activity (last 5 pastes)
$stmt = $db->prepare("SELECT title, created_at, views, language FROM pastes WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get collections count
$stmt = $db->prepare("SELECT COUNT(*) as total_collections FROM collections WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_collections = $stmt->fetch()['total_collections'] ?? 0;

// Get following statistics
$stmt = $db->prepare("SELECT COUNT(*) as following_count FROM user_follows WHERE follower_id = ?");
$stmt->execute([$user_id]);
$following_count = $stmt->fetch()['following_count'] ?? 0;

$stmt = $db->prepare("SELECT COUNT(*) as followers_count FROM user_follows WHERE following_id = ?");
$stmt->execute([$user_id]);
$followers_count = $stmt->fetch()['followers_count'] ?? 0;

// Calculate account age
$account_age_days = floor((time() - $user['created_at']) / 86400);

// Get most used language
$stmt = $db->prepare("SELECT language, COUNT(*) as count FROM pastes WHERE user_id = ? AND language IS NOT NULL GROUP BY language ORDER BY count DESC LIMIT 1");
$stmt->execute([$user_id]);
$top_language = $stmt->fetch();

$theme = $_COOKIE['theme'] ?? 'dark';

// Check if this is being included from index.php
$is_included = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?page=account') !== false;

if (!$is_included): ?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Account Overview - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">
    <!-- Navigation Bar -->
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-home mr-2"></i>Home
                    </a>
                    <a href="/?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-archive mr-2"></i>Archive
                    </a>
                    <a href="/?page=settings" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-cogs mr-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4 min-h-screen">
<?php endif; ?>

<!-- Page Header -->
<div class="mb-8 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <h1 class="text-3xl font-bold flex items-center text-gray-900 dark:text-white">
        <i class="fas fa-crown mr-3 text-yellow-500"></i>
        Account Overview
    </h1>
    <p class="text-gray-600 dark:text-gray-400 mt-2">View your account information and usage statistics</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column - Profile & Quick Stats -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Profile Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <div class="text-center">
                <div class="relative inline-block">
                    <img src="<?= $user['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($user['email'] ?? $user['username'])).'?d=mp&s=128' ?>" 
                         class="w-24 h-24 rounded-full mx-auto mb-4" alt="Profile">
                    <div class="absolute bottom-0 right-0 w-6 h-6 bg-green-500 rounded-full border-2 border-white dark:border-gray-800"></div>
                </div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($user['username']) ?></h2>
                <p class="text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user['email'] ?? 'No email set') ?></p>
                <div class="mt-4 inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <i class="fas fa-check-circle mr-1"></i>
                    Active Account
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Quick Stats</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Total Pastes</span>
                    <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($total_pastes) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Total Views</span>
                    <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($total_views) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Collections</span>
                    <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($total_collections) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Following</span>
                    <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($following_count) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">Followers</span>
                    <span class="font-semibold text-gray-900 dark:text-white"><?= number_format($followers_count) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Detailed Information -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Account Overview -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                    <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                    Account Overview
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Status</label>
                            <div class="mt-1 flex items-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <i class="fas fa-check-circle mr-1"></i>
                                    Member Since: <?= date('M j, Y', $user['created_at']) ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Type</label>
                            <div class="mt-1">
                                <span class="text-blue-600 dark:text-blue-400 font-medium">Free Account</span>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Age</label>
                            <div class="mt-1">
                                <span class="font-medium text-gray-900 dark:text-white"><?= $account_age_days ?> days</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">User ID</label>
                            <div class="mt-1">
                                <span class="font-mono text-sm text-gray-800 dark:text-gray-200"><?= htmlspecialchars($user['id']) ?></span>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Website</label>
                            <div class="mt-1">
                                <?php if ($user['website']): ?>
                                    <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        <?= htmlspecialchars($user['website']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">Not set</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Most Used Language</label>
                            <div class="mt-1">
                                <span class="font-medium text-gray-900 dark:text-white"><?= $top_language ? htmlspecialchars($top_language['language']) : 'None' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Usage -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                    <i class="fas fa-chart-bar mr-2 text-green-500"></i>
                    Feature Usage
                </h3>
            </div>
            <div class="p-6">
                <div class="space-y-6">
                    <!-- All Features -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">All Features</span>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Available</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>

                    <!-- Free AI Tools -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Free AI Tools</span>
                            <span class="text-sm text-green-600 dark:text-green-400">Available</span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <!-- Premium Features -->
                <div class="mt-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                    <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">
                        <i class="fas fa-star mr-2"></i>Upgrade to Premium
                    </h4>
                    <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
                        Unlock advanced features and enhance your experience
                    </p>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1 mb-4">
                        <li><i class="fas fa-check mr-2"></i>Unlimited private pastes</li>
                        <li><i class="fas fa-check mr-2"></i>Advanced analytics</li>
                        <li><i class="fas fa-check mr-2"></i>Custom themes and branding</li>
                        <li><i class="fas fa-check mr-2"></i>Priority support</li>
                    </ul>
                    <a href="/?page=pricing" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium inline-flex items-center">
                        <i class="fas fa-crown mr-2"></i>Upgrade Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-xl font-semibold flex items-center text-gray-900 dark:text-white">
                    <i class="fas fa-clock mr-2 text-purple-500"></i>
                    Recent Activity
                </h3>
            </div>
            <div class="p-6">
                <?php if (!empty($recent_pastes)): ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_pastes as $paste): ?>
                            <div class="flex items-center space-x-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                        <i class="fas fa-code text-blue-600 dark:text-blue-400"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        <?= htmlspecialchars($paste['title']) ?>
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <?= $paste['language'] ? htmlspecialchars($paste['language']) : 'Plain Text' ?> • 
                                        <?= number_format($paste['views']) ?> views • 
                                        <?= date('M j, Y', $paste['created_at']) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-6 text-center">
                        <a href="/?page=archive" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-archive mr-2"></i>
                            View All Pastes
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-file-alt text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">No recent activity to show</p>
                        <a href="/" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>
                            Create Your First Paste
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$is_included): ?>
    </div>
</body>
</html>
<?php endif; ?>
