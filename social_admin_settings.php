
<?php
require_once 'admin-session.php';
check_admin_auth();
require_once 'database.php';
require_once 'social_media_integration.php';

$db = Database::getInstance()->getConnection();
$social = new SocialMediaIntegration();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_provider':
                $provider_id = $_POST['provider_id'];
                $client_id = $_POST['client_id'] ?? '';
                $client_secret = $_POST['client_secret'] ?? '';
                $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
                
                $stmt = $db->prepare("UPDATE social_login_providers SET client_id = ?, client_secret = ?, is_enabled = ? WHERE id = ?");
                $stmt->execute([$client_id, $client_secret, $is_enabled, $provider_id]);
                
                echo '<div class="mb-4 p-4 bg-green-100 text-green-700 rounded">Provider settings updated!</div>';
                break;
                
            case 'test_provider':
                $provider_name = $_POST['provider_name'];
                echo '<div class="mb-4 p-4 bg-blue-100 text-blue-700 rounded">Test functionality not implemented yet.</div>';
                break;
        }
    }
}

// Get all providers
$stmt = $db->query("SELECT * FROM social_login_providers ORDER BY name");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get social sharing stats
$stmt = $db->query("
    SELECT platform, COUNT(*) as total_shares, SUM(clicks) as total_clicks 
    FROM social_shares 
    GROUP BY platform 
    ORDER BY total_shares DESC
");
$share_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="p-6">
    <h2 class="text-2xl font-bold mb-6">Social Media Integration Settings</h2>
    
    <!-- Social Login Providers -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h3 class="text-xl font-semibold mb-4">Social Login Providers</h3>
        
        <div class="space-y-6">
            <?php foreach ($providers as $provider): ?>
                <div class="border border-gray-700 rounded-lg p-4">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_provider">
                        <input type="hidden" name="provider_id" value="<?= $provider['id'] ?>">
                        
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <i class="fab fa-<?= $provider['name'] ?> text-2xl"></i>
                                <h4 class="text-lg font-medium"><?= ucfirst($provider['name']) ?></h4>
                            </div>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" 
                                       name="is_enabled" 
                                       <?= $provider['is_enabled'] ? 'checked' : '' ?>
                                       class="rounded">
                                <span>Enabled</span>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Client ID</label>
                                <input type="text" 
                                       name="client_id" 
                                       value="<?= htmlspecialchars($provider['client_id'] ?? '') ?>"
                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Client Secret</label>
                                <input type="password" 
                                       name="client_secret" 
                                       value="<?= htmlspecialchars($provider['client_secret'] ?? '') ?>"
                                       class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded">
                            </div>
                        </div>
                        
                        <div class="bg-gray-700 rounded p-3 text-sm">
                            <strong>Callback URL:</strong> 
                            <code class="bg-gray-600 px-2 py-1 rounded">
                                <?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') ?>://<?= $_SERVER['HTTP_HOST'] ?>/social_login.php?action=callback&provider=<?= $provider['name'] ?>
                            </code>
                        </div>
                        
                        <div class="bg-blue-900/20 border border-blue-500 rounded p-3 text-sm">
                            <strong>Scopes:</strong> <?= $provider['scopes'] ?>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                Save Settings
                            </button>
                            <button type="button" 
                                    onclick="testProvider('<?= $provider['name'] ?>')"
                                    class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                Test Connection
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Social Sharing Statistics -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h3 class="text-xl font-semibold mb-4">Social Sharing Statistics</h3>
        
        <?php if (!empty($share_stats)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left py-2">Platform</th>
                            <th class="text-right py-2">Total Shares</th>
                            <th class="text-right py-2">Total Clicks</th>
                            <th class="text-right py-2">Avg. CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($share_stats as $stat): ?>
                            <tr class="border-b border-gray-700">
                                <td class="py-2">
                                    <div class="flex items-center space-x-2">
                                        <i class="fab fa-<?= $stat['platform'] ?>"></i>
                                        <span><?= ucfirst($stat['platform']) ?></span>
                                    </div>
                                </td>
                                <td class="text-right py-2"><?= number_format($stat['total_shares']) ?></td>
                                <td class="text-right py-2"><?= number_format($stat['total_clicks']) ?></td>
                                <td class="text-right py-2">
                                    <?= $stat['total_shares'] > 0 ? round(($stat['total_clicks'] / $stat['total_shares']) * 100, 1) : 0 ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400">No sharing data available yet.</p>
        <?php endif; ?>
    </div>
    
    <!-- Setup Instructions -->
    <div class="bg-gray-800 rounded-lg p-6">
        <h3 class="text-xl font-semibold mb-4">Setup Instructions</h3>
        
        <div class="space-y-4 text-sm">
            <div class="border-l-4 border-blue-500 pl-4">
                <h4 class="font-medium mb-2">Google OAuth Setup</h4>
                <ol class="list-decimal list-inside space-y-1 text-gray-300">
                    <li>Go to <a href="https://console.cloud.google.com" target="_blank" class="text-blue-400 hover:underline">Google Cloud Console</a></li>
                    <li>Create a new project or select existing one</li>
                    <li>Enable the Google+ API</li>
                    <li>Create OAuth 2.0 credentials</li>
                    <li>Add the callback URL to authorized redirect URIs</li>
                </ol>
            </div>
            
            <div class="border-l-4 border-purple-500 pl-4">
                <h4 class="font-medium mb-2">GitHub OAuth Setup</h4>
                <ol class="list-decimal list-inside space-y-1 text-gray-300">
                    <li>Go to <a href="https://github.com/settings/applications/new" target="_blank" class="text-blue-400 hover:underline">GitHub Developer Settings</a></li>
                    <li>Create a new OAuth App</li>
                    <li>Set the callback URL</li>
                    <li>Copy the Client ID and Client Secret</li>
                </ol>
            </div>
            
            <div class="border-l-4 border-indigo-500 pl-4">
                <h4 class="font-medium mb-2">Discord OAuth Setup</h4>
                <ol class="list-decimal list-inside space-y-1 text-gray-300">
                    <li>Go to <a href="https://discord.com/developers/applications" target="_blank" class="text-blue-400 hover:underline">Discord Developer Portal</a></li>
                    <li>Create a new application</li>
                    <li>Go to OAuth2 section</li>
                    <li>Add the callback URL to redirects</li>
                    <li>Copy the Client ID and Client Secret</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<script>
function testProvider(providerName) {
    Swal.fire({
        title: `Test ${providerName} Integration`,
        text: 'This will open a new window to test the OAuth flow.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Test Now'
    }).then((result) => {
        if (result.isConfirmed) {
            const testWindow = window.open(
                `/social_login.php?action=login&provider=${providerName}&test=1`,
                'oauth_test',
                'width=600,height=600'
            );
            
            // Monitor the test window
            const checkClosed = setInterval(() => {
                if (testWindow.closed) {
                    clearInterval(checkClosed);
                    Swal.fire({
                        title: 'Test Complete',
                        text: 'Check your browser console for any errors.',
                        icon: 'success'
                    });
                }
            }, 1000);
        }
    });
}
</script>
