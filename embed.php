
<?php
require_once 'database.php';

$db = Database::getInstance()->getConnection();

$paste_id = $_GET['id'] ?? null;
$theme = $_GET['theme'] ?? 'light';

if (!$paste_id) {
    http_response_code(404);
    echo 'Paste not found';
    exit;
}

// Get paste data
$stmt = $db->prepare("SELECT * FROM pastes WHERE id = ? AND is_public = 1");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    http_response_code(404);
    echo 'Paste not found or not public';
    exit;
}

// Check if paste is expired
if ($paste['expire_time'] && time() > $paste['expire_time']) {
    http_response_code(410);
    echo 'Paste has expired';
    exit;
}
?>
<!DOCTYPE html>
<html class="<?= $theme === 'dark' ? 'dark' : '' ?>">
<head>
    <title><?= htmlspecialchars($paste['title']) ?> - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" class="light-theme" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-okaidia.min.css" class="dark-theme" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <style>
        body { margin: 0; padding: 0; }
        pre { margin: 0; }
        code { word-break: break-word; }
    </style>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-lg font-semibold"><?= htmlspecialchars($paste['title']) ?></h1>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <?= htmlspecialchars($paste['language']) ?> • 
                    <?= date('M j, Y', $paste['created_at']) ?>
                </div>
            </div>
            <a href="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/?id=' . $paste_id ?>" 
               target="_blank" 
               class="text-blue-500 hover:text-blue-700 text-sm">
                View on PasteForge →
            </a>
        </div>
        
        <div class="bg-gray-100 dark:bg-gray-800 rounded overflow-x-auto">
            <pre class="p-4"><code class="language-<?= htmlspecialchars($paste['language']) ?>"><?= htmlspecialchars($paste['content']) ?></code></pre>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.Prism) {
                Prism.highlightAll();
            }
        });
    </script>
</body>
</html>
<?php
require_once 'database.php';

if (!isset($_GET['id'])) {
    http_response_code(404);
    exit('Paste not found');
}

$paste_id = $_GET['id'];
$db = Database::getInstance()->getConnection();

// Get paste data
$stmt = $db->prepare("SELECT * FROM pastes WHERE id = ? AND (expiry_date IS NULL OR expiry_date > strftime('%s', 'now'))");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paste) {
    http_response_code(404);
    exit('Paste not found');
}

// Set security headers for embedding
header('X-Frame-Options: SAMEORIGIN');
header('Content-Security-Policy: frame-ancestors *; default-src \'self\' \'unsafe-inline\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com;');
header('X-Content-Type-Options: nosniff');

$theme = $_GET['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paste['title']) ?> - PasteForge Embed</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/<?= $theme === 'dark' ? 'prism-okaidia' : 'prism' ?>.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: <?= $theme === 'dark' ? '#1a1a1a' : '#ffffff' ?>;
            color: <?= $theme === 'dark' ? '#e5e5e5' : '#333333' ?>;
            line-height: 1.6;
            overflow-x: auto;
        }
        
        .embed-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .embed-header {
            background: <?= $theme === 'dark' ? '#2d2d2d' : '#f8f9fa' ?>;
            border-bottom: 1px solid <?= $theme === 'dark' ? '#404040' : '#e9ecef' ?>;
            padding: 8px 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        
        .embed-title {
            font-weight: 600;
            color: <?= $theme === 'dark' ? '#ffffff' : '#495057' ?>;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            margin-right: 10px;
        }
        
        .embed-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            color: <?= $theme === 'dark' ? '#a0a0a0' : '#6c757d' ?>;
            font-size: 11px;
        }
        
        .language-badge {
            background: <?= $theme === 'dark' ? '#404040' : '#e9ecef' ?>;
            color: <?= $theme === 'dark' ? '#ffffff' : '#495057' ?>;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .embed-content {
            flex: 1;
            overflow: auto;
            background: <?= $theme === 'dark' ? '#1e1e1e' : '#ffffff' ?>;
        }
        
        pre {
            margin: 0 !important;
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: auto;
        }
        
        code {
            background: transparent !important;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
        }
        
        .line-numbers .line-numbers-rows {
            background: <?= $theme === 'dark' ? '#252525' : '#f8f9fa' ?>;
            border-right: 1px solid <?= $theme === 'dark' ? '#404040' : '#e9ecef' ?>;
        }
        
        .embed-footer {
            background: <?= $theme === 'dark' ? '#2d2d2d' : '#f8f9fa' ?>;
            border-top: 1px solid <?= $theme === 'dark' ? '#404040' : '#e9ecef' ?>;
            padding: 6px 12px;
            font-size: 10px;
            color: <?= $theme === 'dark' ? '#a0a0a0' : '#6c757d' ?>;
            text-align: center;
            flex-shrink: 0;
        }
        
        .embed-footer a {
            color: <?= $theme === 'dark' ? '#66b3ff' : '#007bff' ?>;
            text-decoration: none;
        }
        
        .embed-footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .embed-header {
                padding: 6px 8px;
            }
            
            .embed-meta {
                display: none;
            }
            
            pre {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="embed-container">
        <div class="embed-header">
            <div class="embed-title"><?= htmlspecialchars($paste['title']) ?></div>
            <div class="embed-meta">
                <span class="language-badge"><?= htmlspecialchars($paste['language']) ?></span>
                <span><?= number_format(strlen($paste['content'])) ?> chars</span>
                <span><?= number_format(substr_count($paste['content'], "\n") + 1) ?> lines</span>
            </div>
        </div>
        
        <div class="embed-content">
            <pre class="line-numbers"><code class="language-<?= htmlspecialchars($paste['language']) ?>"><?= htmlspecialchars($paste['content']) ?></code></pre>
        </div>
        
        <div class="embed-footer">
            <a href="/?id=<?= urlencode($paste_id) ?>" target="_blank" rel="noopener">View on PasteForge</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.js"></script>
    <script>
        // Initialize syntax highlighting
        Prism.highlightAll();
        
        // Handle responsive adjustments
        function adjustLayout() {
            const container = document.querySelector('.embed-container');
            if (window.innerWidth < 400) {
                container.style.fontSize = '11px';
            } else {
                container.style.fontSize = '';
            }
        }
        
        window.addEventListener('resize', adjustLayout);
        adjustLayout();
    </script>
</body>
</html>
