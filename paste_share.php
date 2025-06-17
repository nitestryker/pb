
<?php
session_start();
require_once 'database.php';

$db = Database::getInstance()->getConnection();

// Handle AJAX requests for sharing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'generate_share_link') {
        $paste_id = $_POST['paste_id'] ?? null;
        
        if (!$paste_id) {
            echo json_encode(['error' => 'Invalid paste ID']);
            exit;
        }
        
        // Get paste info
        $stmt = $db->prepare("SELECT id, title, is_public FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $paste = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$paste || !$paste['is_public']) {
            echo json_encode(['error' => 'Paste not found or not public']);
            exit;
        }
        
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $share_url = $base_url . '/?id=' . $paste_id;
        
        echo json_encode([
            'success' => true,
            'url' => $share_url,
            'title' => $paste['title'],
            'qr_code' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($share_url)
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'generate_embed_code') {
        $paste_id = $_POST['paste_id'] ?? null;
        $theme = $_POST['theme'] ?? 'light';
        $height = $_POST['height'] ?? '400';
        
        if (!$paste_id) {
            echo json_encode(['error' => 'Invalid paste ID']);
            exit;
        }
        
        $base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $embed_url = $base_url . '/embed.php?id=' . $paste_id . '&theme=' . $theme;
        
        $embed_code = '<iframe src="' . htmlspecialchars($embed_url) . '" width="100%" height="' . htmlspecialchars($height) . '" frameborder="0"></iframe>';
        
        echo json_encode([
            'success' => true,
            'embed_code' => $embed_code,
            'preview_url' => $embed_url
        ]);
        exit;
    }
}

// If not AJAX, redirect to home
header('Location: /');
exit;
?>
