
<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

function detectLanguageFromContent($content, $filename = '') {
    // Language detection patterns
    $patterns = [
        'python' => ['/^#!/.*python/m', '/import\s+\w+/', '/def\s+\w+\s*\(/', '/class\s+\w+/', '/print\s*\(/'],
        'javascript' => ['/^#!/.*node/m', '/function\s+\w+/', '/const\s+\w+\s*=/', '/let\s+\w+\s*=/', '/var\s+\w+\s*=/', '/console\.log/'],
        'php' => ['/^<\?php/', '/\$\w+/', '/function\s+\w+/', '/class\s+\w+/', '/echo\s+/'],
        'html' => ['/<!DOCTYPE html>/i', '/<html[^>]*>/i', '/<head[^>]*>/i', '/<body[^>]*>/i'],
        'css' => ['/\{[^}]*\}/', '/\w+\s*:\s*[^;]+;/', '/@media/', '/@import/'],
        'json' => ['/^\s*\{/', '/^\s*\[/', '/"[^"]*"\s*:\s*/', '/"[^"]*"\s*,/'],
        'xml' => ['/^<\?xml/', '/<[^>]+>[^<]*<\/[^>]+>/', '/<[^>]+\/>/'],
        'java' => ['/public\s+class\s+\w+/', '/import\s+java\./', '/public\s+static\s+void\s+main/', '/System\.out\.print/'],
        'cpp' => ['/^#include\s*</', '/using\s+namespace\s+std/', '/int\s+main\s*\(/', '/std::/'],
        'c' => ['/^#include\s*</', '/int\s+main\s*\(/', '/printf\s*\(/', '/malloc\s*\(/'],
        'sql' => ['/SELECT\s+/i', '/FROM\s+/i, /WHERE\s+/i', '/INSERT\s+INTO/i', '/UPDATE\s+/i', '/DELETE\s+FROM/i'],
        'markdown' => ['/^#\s+/', '/\*\*[^*]+\*\*/', '/\[[^\]]+\]\([^)]+\)/', '/```/']
    ];

    // First try filename extension
    if ($filename) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $extensionMap = [
            'js' => 'javascript',
            'py' => 'python',
            'php' => 'php',
            'html' => 'html',
            'htm' => 'html',
            'css' => 'css',
            'java' => 'java',
            'cpp' => 'cpp',
            'cxx' => 'cpp',
            'c' => 'c',
            'sql' => 'sql',
            'json' => 'json',
            'xml' => 'xml',
            'md' => 'markdown',
            'txt' => 'plaintext'
        ];
        
        if (isset($extensionMap[$extension])) {
            return $extensionMap[$extension];
        }
    }

    // Then try content patterns
    foreach ($patterns as $language => $regexes) {
        $matches = 0;
        foreach ($regexes as $regex) {
            if (preg_match($regex, $content)) {
                $matches++;
            }
        }
        if ($matches >= 2) { // Require at least 2 pattern matches
            return $language;
        }
    }

    return 'plaintext';
}

function sanitizeContent($content) {
    // Remove any potentially harmful content
    $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
    $content = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
    return $content;
}

try {
    switch ($input['action']) {
        case 'import_url':
            $url = filter_var($input['url'], FILTER_VALIDATE_URL);
            if (!$url) {
                throw new Exception('Invalid URL provided');
            }

            // Create context with headers
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PasteForge Import Bot 1.0',
                        'Accept: text/plain, text/html, application/json, */*'
                    ],
                    'timeout' => 30
                ]
            ]);

            $content = @file_get_contents($url, false, $context);
            
            if ($content === false) {
                throw new Exception('Failed to fetch content from URL');
            }

            // Check content size (max 1MB)
            if (strlen($content) > 1024 * 1024) {
                throw new Exception('Content too large (max 1MB)');
            }

            $content = sanitizeContent($content);
            
            // Try to extract title from HTML
            $title = '';
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
                $title = trim(html_entity_decode($matches[1]));
            }
            
            if (!$title) {
                $title = basename(parse_url($url, PHP_URL_PATH)) ?: 'Imported Content';
            }

            $language = detectLanguageFromContent($content, $title);

            echo json_encode([
                'success' => true,
                'content' => $content,
                'title' => $title,
                'language' => $language
            ]);
            break;

        case 'import_gist':
            $gistId = $input['gist_id'];
            $gistUrl = $input['gist_url'];
            
            if (!preg_match('/^[a-f0-9]+$/', $gistId)) {
                throw new Exception('Invalid Gist ID');
            }

            $apiUrl = "https://api.github.com/gists/$gistId";
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: PasteForge Import Bot 1.0',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'timeout' => 30
                ]
            ]);

            $response = @file_get_contents($apiUrl, false, $context);
            
            if ($response === false) {
                throw new Exception('Failed to fetch Gist from GitHub API');
            }

            $gistData = json_decode($response, true);
            
            if (!$gistData || !isset($gistData['files'])) {
                throw new Exception('Invalid Gist data received');
            }

            // Get the first file from the gist
            $files = $gistData['files'];
            $firstFile = reset($files);
            
            if (!$firstFile || !isset($firstFile['content'])) {
                throw new Exception('No content found in Gist');
            }

            $content = $firstFile['content'];
            $filename = $firstFile['filename'] ?? 'gist-file';
            $title = $gistData['description'] ?: $filename;
            
            // Check content size
            if (strlen($content) > 1024 * 1024) {
                throw new Exception('Gist content too large (max 1MB)');
            }

            $language = detectLanguageFromContent($content, $filename);
            
            // Override with GitHub's language detection if available
            if (isset($firstFile['language']) && $firstFile['language']) {
                $githubLang = strtolower($firstFile['language']);
                $langMap = [
                    'javascript' => 'javascript',
                    'python' => 'python',
                    'php' => 'php',
                    'html' => 'html',
                    'css' => 'css',
                    'java' => 'java',
                    'c++' => 'cpp',
                    'c' => 'c',
                    'sql' => 'sql',
                    'json' => 'json',
                    'xml' => 'xml',
                    'markdown' => 'markdown'
                ];
                
                if (isset($langMap[$githubLang])) {
                    $language = $langMap[$githubLang];
                }
            }

            echo json_encode([
                'success' => true,
                'content' => $content,
                'title' => $title,
                'language' => $language
            ]);
            break;

        default:
            throw new Exception('Unknown action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
