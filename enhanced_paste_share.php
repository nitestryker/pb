
<?php
require_once 'database.php';
require_once 'social_media_integration.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $db = Database::getInstance()->getConnection();
    $social = new SocialMediaIntegration();
    
    if ($_POST['action'] === 'generate_enhanced_share_link') {
        $paste_id = $_POST['paste_id'] ?? null;
        $platform = $_POST['platform'] ?? 'general';
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!$paste_id) {
            echo json_encode(['error' => 'Invalid paste ID']);
            exit;
        }
        
        // Get paste info
        $stmt = $db->prepare("SELECT id, title, content, language, is_public, user_id FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $paste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paste || !$paste['is_public']) {
            echo json_encode(['error' => 'Paste not found or not public']);
            exit;
        }
        
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $share_url = $base_url . '/?id=' . $paste_id;
        
        // Track the share
        $share_id = $social->trackSocialShare($paste_id, $platform, $user_id, $share_url);
        
        // Generate platform-specific content
        $share_content = generatePlatformContent($paste, $platform, $share_url);
        
        echo json_encode([
            'success' => true,
            'url' => $share_url,
            'title' => $paste['title'],
            'platform_content' => $share_content,
            'share_id' => $share_id,
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($share_url),
            'metadata' => [
                'language' => $paste['language'],
                'lines' => substr_count($paste['content'], "\n") + 1,
                'characters' => strlen($paste['content'])
            ]
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'share_to_platform') {
        $paste_id = $_POST['paste_id'] ?? null;
        $platform = $_POST['platform'] ?? null;
        $user_id = $_SESSION['user_id'] ?? null;
        $custom_message = $_POST['custom_message'] ?? '';
        
        if (!$paste_id || !$platform) {
            echo json_encode(['error' => 'Missing required parameters']);
            exit;
        }
        
        // Get paste info
        $stmt = $db->prepare("SELECT * FROM pastes WHERE id = ? AND is_public = 1 AND zero_knowledge = 0");
        $stmt->execute([$paste_id]);
        $paste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paste) {
            echo json_encode(['error' => 'Paste not found']);
            exit;
        }
        
        try {
            $result = shareToSpecificPlatform($paste, $platform, $custom_message, $user_id);
            
            // Track the share
            $social->trackSocialShare($paste_id, $platform, $user_id, $result['shared_url'] ?? null);
            
            echo json_encode([
                'success' => true,
                'platform' => $platform,
                'shared_url' => $result['shared_url'] ?? null,
                'message' => 'Successfully shared to ' . ucfirst($platform)
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'error' => 'Failed to share to ' . $platform . ': ' . $e->getMessage()
            ]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_share_analytics') {
        $paste_id = $_POST['paste_id'] ?? null;
        $user_id = $_SESSION['user_id'] ?? null;
        
        if (!$paste_id) {
            echo json_encode(['error' => 'Invalid paste ID']);
            exit;
        }
        
        // Check if user owns the paste
        $stmt = $db->prepare("SELECT user_id FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $paste = $stmt->fetch();
        
        if (!$paste || ($paste['user_id'] !== $user_id && $user_id)) {
            echo json_encode(['error' => 'Not authorized to view analytics']);
            exit;
        }
        
        // Get share analytics
        $stmt = $db->prepare("
            SELECT platform, COUNT(*) as share_count, SUM(clicks) as total_clicks, MAX(created_at) as last_shared
            FROM social_shares 
            WHERE paste_id = ? 
            GROUP BY platform
            ORDER BY share_count DESC
        ");
        $stmt->execute([$paste_id]);
        $analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'analytics' => $analytics
        ]);
        exit;
    }
}

function generatePlatformContent($paste, $platform, $share_url) {
    $title = $paste['title'];
    $language = $paste['language'];
    $preview = substr($paste['content'], 0, 100) . (strlen($paste['content']) > 100 ? '...' : '');
    
    switch ($platform) {
        case 'twitter':
            $text = "Check out this {$language} code snippet: \"{$title}\"";
            if (strlen($text . ' ' . $share_url) > 280) {
                $max_title_length = 280 - strlen("Check out this {$language} code snippet: \"\"") - strlen($share_url) - 3;
                $title = substr($title, 0, $max_title_length) . '...';
                $text = "Check out this {$language} code snippet: \"{$title}\"";
            }
            return [
                'text' => $text,
                'url' => "https://twitter.com/intent/tweet?text=" . urlencode($text) . "&url=" . urlencode($share_url),
                'hashtags' => ['code', strtolower($language), 'programming', 'pasteforge']
            ];
            
        case 'facebook':
            return [
                'text' => "Sharing a useful {$language} code snippet: {$title}",
                'url' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($share_url),
                'quote' => $preview
            ];
            
        case 'linkedin':
            return [
                'text' => "Sharing a {$language} code snippet that might be helpful: {$title}",
                'url' => "https://www.linkedin.com/sharing/share-offsite/?url=" . urlencode($share_url),
                'summary' => $preview
            ];
            
        case 'reddit':
            return [
                'title' => "[{$language}] {$title}",
                'url' => "https://reddit.com/submit?url=" . urlencode($share_url) . "&title=" . urlencode("[{$language}] {$title}"),
                'text' => $preview
            ];
            
        case 'discord':
            return [
                'text' => "Found this useful {$language} code: **{$title}**\n```{$language}\n" . substr($paste['content'], 0, 200) . "\n```\nFull code: {$share_url}",
                'embeds' => [
                    'title' => $title,
                    'description' => $preview,
                    'url' => $share_url,
                    'color' => 0x0099ff
                ]
            ];
            
        case 'slack':
            return [
                'text' => "Sharing a {$language} code snippet: *{$title}*",
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "```{$language}\n{$preview}\n```"
                        ]
                    ],
                    [
                        'type' => 'actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => ['type' => 'plain_text', 'text' => 'View Full Code'],
                                'url' => $share_url
                            ]
                        ]
                    ]
                ]
            ];
            
        default:
            return [
                'text' => "Check out this {$language} code: {$title}",
                'url' => $share_url,
                'preview' => $preview
            ];
    }
}

function shareToSpecificPlatform($paste, $platform, $custom_message, $user_id) {
    // This would integrate with actual social media APIs
    // For now, we'll return mock responses
    
    $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $share_url = $base_url . '/?id=' . $paste['id'];
    
    switch ($platform) {
        case 'twitter':
            // Would use Twitter API v2
            return [
                'shared_url' => 'https://twitter.com/user/status/123456789',
                'platform_id' => '123456789'
            ];
            
        case 'discord':
            // Would use Discord webhook or bot
            return [
                'shared_url' => 'https://discord.com/channels/123/456/789',
                'platform_id' => '789'
            ];
            
        case 'slack':
            // Would use Slack webhook
            return [
                'shared_url' => 'https://workspace.slack.com/archives/C123/p1234567890',
                'platform_id' => 'p1234567890'
            ];
            
        default:
            throw new Exception('Platform not supported for direct sharing');
    }
}

// Handle GET requests for share tracking
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['track_click'])) {
    $share_id = $_GET['share_id'] ?? null;
    
    if ($share_id) {
        $social = new SocialMediaIntegration();
        $social->incrementShareClick($share_id);
    }
    
    // Redirect to the actual paste
    $paste_id = $_GET['paste_id'] ?? null;
    if ($paste_id) {
        header('Location: /?id=' . $paste_id);
    } else {
        header('Location: /');
    }
    exit;
}
?>
