<?php
session_start();
require_once 'database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$paste_id = $_POST['paste_id'] ?? '';
$from_version = $_POST['from_version'] ?? '';
$to_version = $_POST['to_version'] ?? '';

if (!$paste_id || $from_version === '' || $to_version === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Get paste data to verify access
    $stmt = $db->prepare("SELECT id, title, content, language, is_public, user_id, current_version FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paste) {
        echo json_encode(['success' => false, 'error' => 'Paste not found']);
        exit;
    }

    // Check if paste is public or user has access
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$paste['is_public'] && $paste['user_id'] !== $user_id) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit;
    }

    // Initialize variables
    $from_content = '';
    $from_title = '';
    $to_content = '';
    $to_title = '';
    $from_language = '';
    $to_language = '';

    // Get "from" version data
    if ($from_version === 'current') {
        $from_content = $paste['content'] ?? '';
        $from_title = $paste['title'] ?? '';
        $from_language = $paste['language'] ?? '';
    } else {
        $stmt = $db->prepare("SELECT title, content, language FROM paste_versions WHERE paste_id = ? AND version_number = ?");
        $stmt->execute([$paste_id, intval($from_version)]);
        $version_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($version_data) {
            $from_content = $version_data['content'] ?? '';
            $from_title = $version_data['title'] ?? '';
            $from_language = $version_data['language'] ?? '';
        } else {
            echo json_encode(['success' => false, 'error' => "From version $from_version not found"]);
            exit;
        }
    }

    // Get "to" version data
    if ($to_version === 'current') {
        $to_content = $paste['content'] ?? '';
        $to_title = $paste['title'] ?? '';
        $to_language = $paste['language'] ?? '';
    } else {
        $stmt = $db->prepare("SELECT title, content, language FROM paste_versions WHERE paste_id = ? AND version_number = ?");
        $stmt->execute([$paste_id, intval($to_version)]);
        $version_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($version_data) {
            $to_content = $version_data['content'] ?? '';
            $to_title = $version_data['title'] ?? '';
            $to_language = $version_data['language'] ?? '';
        } else {
            echo json_encode(['success' => false, 'error' => "To version $to_version not found"]);
            exit;
        }
    }

    // Generate diff
    $diff_html = generateDiffHtml($from_content, $to_content, $from_title, $to_title, $from_version, $to_version, $from_language, $to_language);
    $has_differences = ($from_content !== $to_content || $from_title !== $to_title || $from_language !== $to_language);

    echo json_encode([
        'success' => true,
        'has_differences' => $has_differences,
        'diff_html' => $diff_html
    ]);

} catch (Exception $e) {
    error_log("Version diff error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to generate diff: ' . $e->getMessage()]);
}

function generateDiffHtml($from_content, $to_content, $from_title, $to_title, $from_version, $to_version, $from_language = '', $to_language = '') {
    $html = '';

    try {
        // Sanitize inputs
        $from_title = $from_title ?? '';
        $to_title = $to_title ?? '';
        $from_content = $from_content ?? '';
        $to_content = $to_content ?? '';
        $from_language = $from_language ?? '';
        $to_language = $to_language ?? '';

        // Header with version info
        $html .= '<div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">';
        $html .= '<div class="text-sm text-blue-800 dark:text-blue-200">';
        $html .= 'Comparing ' . ($from_version === 'current' ? 'Current Version' : "Version $from_version");
        $html .= ' → ' . ($to_version === 'current' ? 'Current Version' : "Version $to_version");
        $html .= '</div>';
        $html .= '</div>';

        // Title diff if different
        if ($from_title !== $to_title) {
            $html .= '<div class="mb-6">';
            $html .= '<h5 class="font-medium mb-2 text-gray-900 dark:text-white">Title Changes:</h5>';
            $html .= '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-3 mb-2">';
            $html .= '<span class="text-red-600 dark:text-red-400 font-mono text-sm">- ' . htmlspecialchars($from_title) . '</span>';
            $html .= '</div>';
            $html .= '<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded p-3">';
            $html .= '<span class="text-green-600 dark:text-green-400 font-mono text-sm">+ ' . htmlspecialchars($to_title) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Language diff if different
        if ($from_language !== $to_language) {
            $html .= '<div class="mb-6">';
            $html .= '<h5 class="font-medium mb-2 text-gray-900 dark:text-white">Language Changes:</h5>';
            $html .= '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded p-3 mb-2">';
            $html .= '<span class="text-red-600 dark:text-red-400 font-mono text-sm">- ' . htmlspecialchars($from_language) . '</span>';
            $html .= '</div>';
            $html .= '<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded p-3">';
            $html .= '<span class="text-green-600 dark:text-green-400 font-mono text-sm">+ ' . htmlspecialchars($to_language) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
        }

        // Content diff
        $html .= '<div>';
        $html .= '<h5 class="font-medium mb-3 text-gray-900 dark:text-white">Content Changes:</h5>';

        if ($from_content === $to_content) {
            $html .= '<div class="text-gray-500 dark:text-gray-400 italic p-4 text-center bg-gray-50 dark:bg-gray-700 rounded">';
            $html .= '<i class="fas fa-equals mr-2"></i>No content changes';
            $html .= '</div>';
        } else {
            $html .= generateLineDiff($from_content, $to_content);
        }

        $html .= '</div>';

        return $html;

    } catch (Exception $e) {
        return '<div class="text-red-500 p-4">Error generating diff: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function generateLineDiff($from_content, $to_content) {
    try {
        // Split content into lines
        $from_lines = explode("\n", $from_content);
        $to_lines = explode("\n", $to_content);

        // Use a simple but reliable diff algorithm
        $diff = computeLineDiff($from_lines, $to_lines);

        // Generate HTML
        $html = '<div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 max-h-96 overflow-auto">';
        $html .= '<div class="space-y-1 font-mono text-sm">';

        if (empty($diff)) {
            $html .= '<div class="text-gray-500 italic text-center py-4">No changes detected</div>';
        } else {
            $line_number = 1;
            foreach ($diff as $item) {
                $line_class = '';
                $prefix = '';
                $bg_class = '';
                $icon = '';

                switch ($item['type']) {
                    case 'removed':
                        $line_class = 'text-red-600 dark:text-red-400';
                        $bg_class = 'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400';
                        $prefix = '-';
                        $icon = '<i class="fas fa-minus mr-2"></i>';
                        break;
                    case 'added':
                        $line_class = 'text-green-600 dark:text-green-400';
                        $bg_class = 'bg-green-50 dark:bg-green-900/20 border-l-4 border-green-400';
                        $prefix = '+';
                        $icon = '<i class="fas fa-plus mr-2"></i>';
                        break;
                    case 'unchanged':
                        $line_class = 'text-gray-600 dark:text-gray-400';
                        $bg_class = 'bg-gray-50 dark:bg-gray-700/50';
                        $prefix = ' ';
                        $icon = '';
                        break;
                }

                $escaped_line = htmlspecialchars($item['line']);

                $html .= '<div class="' . $bg_class . ' px-3 py-2 rounded ' . $line_class . '">';
                $html .= '<div class="flex items-start">';
                $html .= '<span class="inline-block w-12 text-xs text-gray-500 mr-2">' . $line_number . '</span>';
                $html .= '<span class="mr-2">' . $icon . '</span>';
                $html .= '<span class="flex-1 break-all">' . $prefix . ' ' . $escaped_line . '</span>';
                $html .= '</div>';
                $html .= '</div>';

                $line_number++;
            }
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;

    } catch (Exception $e) {
        return '<div class="text-red-500 p-4">Error generating line diff: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

function computeLineDiff($from_lines, $to_lines) {
    $diff = [];
    $from_count = count($from_lines);
    $to_count = count($to_lines);
    $from_index = 0;
    $to_index = 0;

    while ($from_index < $from_count || $to_index < $to_count) {
        if ($from_index >= $from_count) {
            // Only additions remain
            while ($to_index < $to_count) {
                $diff[] = ['type' => 'added', 'line' => $to_lines[$to_index]];
                $to_index++;
            }
        } elseif ($to_index >= $to_count) {
            // Only deletions remain
            while ($from_index < $from_count) {
                $diff[] = ['type' => 'removed', 'line' => $from_lines[$from_index]];
                $from_index++;
            }
        } elseif ($from_lines[$from_index] === $to_lines[$to_index]) {
            // Lines are the same
            $diff[] = ['type' => 'unchanged', 'line' => $from_lines[$from_index]];
            $from_index++;
            $to_index++;
        } else {
            // Lines are different - simple replacement for now
            $diff[] = ['type' => 'removed', 'line' => $from_lines[$from_index]];
            $diff[] = ['type' => 'added', 'line' => $to_lines[$to_index]];
            $from_index++;
            $to_index++;
        }
    }

    return $diff;
}
?>