<?php
// Adding social media integration to the user settings page.
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

// Handle settings updates
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                // Verify current password
                $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!password_verify($current_password, $user['password'])) {
                    $error_message = 'Current password is incorrect.';
                } elseif ($new_password !== $confirm_password) {
                    $error_message = 'New passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $error_message = 'New password must be at least 6 characters long.';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $success_message = 'Password updated successfully!';
                }
                break;

            case 'update_preferences':
                // Add user preferences columns if they don't exist
                try {
                    $db->exec("ALTER TABLE users ADD COLUMN theme_preference TEXT DEFAULT 'system'");
                    $db->exec("ALTER TABLE users ADD COLUMN email_notifications INTEGER DEFAULT 1");
                    $db->exec("ALTER TABLE users ADD COLUMN default_paste_expiry INTEGER DEFAULT 604800");
                    $db->exec("ALTER TABLE users ADD COLUMN default_paste_public INTEGER DEFAULT 1");
                    $db->exec("ALTER TABLE users ADD COLUMN timezone TEXT DEFAULT 'UTC'");
                } catch (PDOException $e) {
                    // Columns might already exist
                }

                $theme_preference = $_POST['theme_preference'];
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $default_paste_expiry = (int)$_POST['default_paste_expiry'];
                $default_paste_public = isset($_POST['default_paste_public']) ? 1 : 0;
                $timezone = $_POST['timezone'];

                $stmt = $db->prepare("UPDATE users SET theme_preference = ?, email_notifications = ?, default_paste_expiry = ?, default_paste_public = ?, timezone = ? WHERE id = ?");
                $stmt->execute([$theme_preference, $email_notifications, $default_paste_expiry, $default_paste_public, $timezone, $user_id]);
                $success_message = 'Preferences updated successfully!';
                break;

            case 'update_privacy':
                try {
                    $db->exec("ALTER TABLE users ADD COLUMN profile_visibility TEXT DEFAULT 'public'");
                    $db->exec("ALTER TABLE users ADD COLUMN show_paste_count INTEGER DEFAULT 1");
                    $db->exec("ALTER TABLE users ADD COLUMN allow_messages INTEGER DEFAULT 1");
                } catch (PDOException $e) {
                    // Columns might already exist
                }

                $profile_visibility = $_POST['profile_visibility'];
                $show_paste_count = isset($_POST['show_paste_count']) ? 1 : 0;
                $allow_messages = isset($_POST['allow_messages']) ? 1 : 0;

                $stmt = $db->prepare("UPDATE users SET profile_visibility = ?, show_paste_count = ?, allow_messages = ? WHERE id = ?");
                $stmt->execute([$profile_visibility, $show_paste_count, $allow_messages, $user_id]);
                $success_message = 'Privacy settings updated successfully!';
                break;
        }
    }
}

// Get current user settings
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Set defaults for new columns if they don't exist
$user_settings['theme_preference'] = $user_settings['theme_preference'] ?? 'system';
$user_settings['email_notifications'] = $user_settings['email_notifications'] ?? 1;
$user_settings['default_paste_expiry'] = $user_settings['default_paste_expiry'] ?? 604800;
$user_settings['default_paste_public'] = $user_settings['default_paste_public'] ?? 1;
$user_settings['timezone'] = $user_settings['timezone'] ?? 'UTC';
$user_settings['profile_visibility'] = $user_settings['profile_visibility'] ?? 'public';
$user_settings['show_paste_count'] = $user_settings['show_paste_count'] ?? 1;
$user_settings['allow_messages'] = $user_settings['allow_messages'] ?? 1;

$theme = $_COOKIE['theme'] ?? 'dark';

// Check if this is being included from index.php
$is_included = isset($settings_user_id) || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?page=settings') !== false);

if (!$is_included): ?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Settings - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <a href="/?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-folder mr-2"></i>Collections
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto py-8 px-4">
<?php else: ?>
    <div class="max-w-4xl mx-auto py-8 px-4">
<?php endif; ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <!-- Header -->
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-cogs mr-3 text-blue-500"></i>
                    Account Settings
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Manage your account preferences and security settings</p>
            </div>

            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="m-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="m-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <button onclick="showTab('security')" id="tab-security" class="tab-button border-b-2 border-blue-500 text-blue-600 dark:text-blue-400 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-shield-alt mr-2"></i>Security
                    </button>
                    <button onclick="showTab('preferences')" id="tab-preferences" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-sliders-h mr-2"></i>Preferences
                    </button>
                    <button onclick="showTab('privacy')" id="tab-privacy" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-user-shield mr-2"></i>Privacy
                    </button>
                </nav>
            </div>

            <!-- Security Tab -->
            <div id="security-tab" class="tab-content p-6">
                <h2 class="text-lg font-semibold mb-4">Security Settings</h2>

                <!-- Change Password -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                    <h3 class="text-md font-medium mb-4 flex items-center">
                        <i class="fas fa-key mr-2 text-yellow-500"></i>
                        Change Password
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">

                        <div>
                            <label class="block text-sm font-medium mb-2">Current Password</label>
                            <input type="password" name="current_password" required 
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">New Password</label>
                            <input type="password" name="new_password" required minlength="6"
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500">
                            <p class="text-sm text-gray-500 mt-1">Minimum 6 characters</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6"
                                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-save mr-2"></i>Update Password
                        </button>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                    <h3 class="text-md font-medium mb-4 flex items-center">
                        <i class="fas fa-info-circle mr-2 text-blue-500"></i>
                        Account Information
                    </h3>
                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Username:</span>
                            <span class="ml-2"><?= htmlspecialchars($user_settings['username']) ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Email:</span>
                            <span class="ml-2"><?= htmlspecialchars($user_settings['email'] ?? 'Not set') ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">Member Since:</span>
                            <span class="ml-2"><?= date('F j, Y', $user_settings['created_at']) ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600 dark:text-gray-400">User ID:</span>
                            <span class="ml-2 font-mono text-xs"><?= htmlspecialchars($user_settings['id']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div id="preferences-tab" class="tab-content p-6 hidden">
                <h2 class="text-lg font-semibold mb-4">User Preferences</h2>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_preferences">

                    <!-- Theme Settings -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-medium mb-4 flex items-center">
                            <i class="fas fa-palette mr-2 text-purple-500"></i>
                            Theme & Display
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Preferred Theme</label>
                                <select name="theme_preference" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option value="system" <?= $user_settings['theme_preference'] === 'system' ? 'selected' : '' ?>>System Default</option>
                                    <option value="light" <?= $user_settings['theme_preference'] === 'light' ? 'selected' : '' ?>>Light Mode</option>
                                    <option value="dark" <?= $user_settings['theme_preference'] === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                </select>
                                <p class="text-sm text-gray-500 mt-1">Choose your preferred color scheme</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Timezone</label>
                                <select name="timezone" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option value="UTC" <?= $user_settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York" <?= $user_settings['timezone'] === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?= $user_settings['timezone'] === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                    <option value="America/Denver" <?= $user_settings['timezone'] === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?= $user_settings['timezone'] === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                    <option value="Europe/London" <?= $user_settings['timezone'] === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                    <option value="Europe/Paris" <?= $user_settings['timezone'] === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                    <option value="Asia/Tokyo" <?= $user_settings['timezone'] === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Paste Defaults -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-medium mb-4 flex items-center">
                            <i class="fas fa-code mr-2 text-green-500"></i>
                            Default Paste Settings
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Default Paste Expiry</label>
                                <select name="default_paste_expiry" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option value="0" <?= $user_settings['default_paste_expiry'] == 0 ? 'selected' : '' ?>>Never</option>
                                    <option value="600" <?= $user_settings['default_paste_expiry'] == 600 ? 'selected' : '' ?>>10 minutes</option>
                                    <option value="3600" <?= $user_settings['default_paste_expiry'] == 3600 ? 'selected' : '' ?>>1 hour</option>
                                    <option value="86400" <?= $user_settings['default_paste_expiry'] == 86400 ? 'selected' : '' ?>>1 day</option>
                                    <option value="604800" <?= $user_settings['default_paste_expiry'] == 604800 ? 'selected' : '' ?>>1 week</option>
                                </select>
                            </div>

                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="default_paste_public" <?= $user_settings['default_paste_public'] ? 'checked' : '' ?> class="rounded">
                                    <span>Make pastes public by default</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-medium mb-4 flex items-center">
                            <i class="fas fa-bell mr-2 text-orange-500"></i>
                            Notifications
                        </h3>

                        <div>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="email_notifications" <?= $user_settings['email_notifications'] ? 'checked' : '' ?> class="rounded">
                                <span>Receive email notifications for comments and messages</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Preferences
                    </button>
                </form>
            </div>

            <!-- Privacy Tab -->
            <div id="privacy-tab" class="tab-content p-6 hidden">
                <h2 class="text-lg font-semibold mb-4">Privacy Settings</h2>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update_privacy">

                    <!-- Profile Privacy -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                        <h3 class="text-md font-medium mb-4 flex items-center">
                            <i class="fas fa-user-shield mr-2 text-indigo-500"></i>
                            Profile Privacy
                        </h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Profile Visibility</label>
                                <select name="profile_visibility" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-600">
                                    <option value="public" <?= $user_settings['profile_visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                                    <option value="limited" <?= $user_settings['profile_visibility'] === 'limited' ? 'selected' : '' ?>>Limited (Hide some details)</option>
                                    <option value="private" <?= $user_settings['profile_visibility'] === 'private' ? 'selected' : '' ?>>Private (Username only)</option>
                                </select>
                                <p class="text-sm text-gray-500 mt-1">Control who can see your profile information</p>
                            </div>

                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="show_paste_count" <?= $user_settings['show_paste_count'] ? 'checked' : '' ?> class="rounded">
                                    <span>Show paste count on profile</span>
                                </label>
                            </div>

                            <div>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="allow_messages" <?= $user_settings['allow_messages'] ? 'checked' : '' ?> class="rounded">
                                    <span>Allow other users to send me messages</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <i class="fas fa-save mr-2"></i>Save Privacy Settings
                    </button>
                </form>
            </div>
        </div>

            <!-- Social Accounts Section -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
                <h3 class="text-xl font-semibold mb-4">
                    <i class="fas fa-users mr-2"></i>Connected Social Accounts
                </h3>

                <?php
                require_once 'social_media_integration.php';
                $social = new SocialMediaIntegration();
                $settings_user_id = $user_id; // Ensure $settings_user_id is defined for included file
                $connected_accounts = $social->getUserSocialAccounts($settings_user_id);
                $available_providers = $social->getEnabledProviders();
                ?>

                <div class="space-y-4">
                    <?php if (!empty($connected_accounts)): ?>
                        <div>
                            <h4 class="font-medium mb-3">Connected Accounts</h4>
                            <div class="space-y-3">
                                <?php foreach ($connected_accounts as $account): ?>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <i class="fab fa-<?= $account['platform'] ?> text-2xl"></i>
                                            <div>
                                                <div class="font-medium"><?= ucfirst($account['platform']) ?></div>
                                                <?php if ($account['platform_username']): ?>
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        @<?= htmlspecialchars($account['platform_username']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm <?= $account['is_active'] ? 'text-green-600' : 'text-gray-500' ?>">
                                                <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="unlink_social">
                                                <input type="hidden" name="platform" value="<?= $account['platform'] ?>">
                                                <button type="submit" 
                                                        onclick="return confirm('Are you sure you want to unlink this account?')"
                                                        class="text-red-500 hover:text-red-700">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($available_providers)): ?>
                        <div>
                            <h4 class="font-medium mb-3">Connect New Account</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php 
                                $connected_platforms = array_column($connected_accounts, 'platform');
                                foreach ($available_providers as $provider): 
                                    if (!in_array($provider['name'], $connected_platforms)):
                                ?>
                                    <a href="social_login.php?action=login&provider=<?= $provider['name'] ?>" 
                                       class="flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <i class="fab fa-<?= $provider['name'] ?> mr-3 text-lg"></i>
                                        Connect <?= ucfirst($provider['name']) ?>
                                    </a>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($connected_accounts) && empty($available_providers)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">No social login providers are currently configured.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <h5 class="font-medium text-blue-800 dark:text-blue-200 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>About Social Accounts
                    </h5>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                        <li>• Connected accounts can be used to log in to PasteForge</li>
                        <li>• You can share your pastes directly to connected platforms</li>
                        <li>• Account linking is secure and can be revoked at any time</li>
                        <li>• We only access basic profile information</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php if (!$is_included): ?>
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });

            // Remove active styling from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.remove('hidden');

            // Add active styling to selected tab button
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Apply theme preference immediately when changed
        document.querySelector('select[name="theme_preference"]').addEventListener('change', function() {
            const theme = this.value;
            const html = document.documentElement;

            if (theme === 'system') {
                // Use system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    html.classList.add('dark');
                    html.classList.remove('light');
                } else {
                    html.classList.add('light');
                    html.classList.remove('dark');
                }
            } else {
                html.classList.remove('dark', 'light');
                html.classList.add(theme);
            }

            // Update cookie
            document.cookie = `theme=${theme === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : theme};path=/`;
        });

        // Show success message with SweetAlert if redirected from form submission
        <?php if ($success_message): ?>
        Swal.fire({
            icon: 'success',
            title: 'Settings Updated!',
            text: '<?= addslashes($success_message) ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        <?php endif; ?>

        <?php if ($error_message): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($error_message) ?>'
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php else: ?>
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });

            // Remove active styling from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.remove('hidden');

            // Add active styling to selected tab button
            const activeButton = document.getElementById('tab-' + tabName);
            activeButton.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        // Apply theme preference immediately when changed
        const themeSelect = document.querySelector('select[name="theme_preference"]');
        if (themeSelect) {
            themeSelect.addEventListener('change', function() {
                const theme = this.value;
                const html = document.documentElement;

                if (theme === 'system') {
                    // Use system preference
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        html.classList.add('dark');
                        html.classList.remove('light');
                    } else {
                        html.classList.add('light');
                        html.classList.remove('dark');
                    }
                } else {
                    html.classList.remove('dark', 'light');
                    html.classList.add(theme);
                }

                // Update cookie
                document.cookie = `theme=${theme === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : theme};path=/`;
            });
        }

        // Show success message with SweetAlert if redirected from form submission
        <?php if ($success_message): ?>
        Swal.fire({
            icon: 'success',
            title: 'Settings Updated!',
            text: '<?= addslashes($success_message) ?>',
            toast: true,
            position: 'top-end',            showConfirmButton: false,
            timer: 3000
        });
        <?php endif; ?>

        <?php if ($error_message): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($error_message) ?>'
        });
        <?php endif; ?>
    </script>
<?php endif; ?>