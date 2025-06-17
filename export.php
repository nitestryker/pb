<?php
session_start();

// Check for maintenance mode
require_once 'maintenance_check.php';

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;

    if (!$user_id) {
        header('Location: /?page=login');
        exit;
    }

    // Handle export request
    if (isset($_GET['action']) && $_GET['action'] === 'export') {
        $format = $_GET['format'] ?? 'json';
        $selection = $_GET['selection'] ?? 'all';
        $paste_ids = $_GET['paste_ids'] ?? '';

        // Build query based on selection
        if ($selection === 'selected' && !empty($paste_ids)) {
            $ids = array_map('intval', explode(',', $paste_ids));
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $query = "SELECT p.*, u.username FROM pastes p 
                     LEFT JOIN users u ON p.user_id = u.id 
                     WHERE p.id IN ($placeholders) AND p.user_id = ? 
                     ORDER BY p.created_at DESC";
            $params = array_merge($ids, [$user_id]);
        } else {
            $query = "SELECT p.*, u.username FROM pastes p 
                     LEFT JOIN users u ON p.user_id = u.id 
                     WHERE p.user_id = ? 
                     ORDER BY p.created_at DESC";
            $params = [$user_id];
        }

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "pasteforge_export_{$timestamp}";

        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
                
                $export_data = [
                    'export_info' => [
                        'timestamp' => time(),
                        'date' => date('Y-m-d H:i:s'),
                        'user' => $username,
                        'count' => count($pastes),
                        'format_version' => '1.0'
                    ],
                    'pastes' => array_map(function($paste) {
                        return [
                            'id' => $paste['id'],
                            'title' => $paste['title'],
                            'content' => $paste['content'],
                            'language' => $paste['language'],
                            'tags' => $paste['tags'],
                            'created_at' => $paste['created_at'],
                            'views' => $paste['views'],
                            'is_public' => $paste['is_public'],
                            'created_date' => date('Y-m-d H:i:s', $paste['created_at'])
                        ];
                    }, $pastes)
                ];
                
                echo json_encode($export_data, JSON_PRETTY_PRINT);
                break;

            case 'csv':
                header('Content-Type: text/csv');
                header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
                
                $output = fopen('php://output', 'w');
                
                // Write header
                fputcsv($output, [
                    'ID', 'Title', 'Language', 'Tags', 'Views', 'Public', 
                    'Created Date', 'Content Preview', 'Content'
                ]);
                
                // Write data
                foreach ($pastes as $paste) {
                    fputcsv($output, [
                        $paste['id'],
                        $paste['title'],
                        $paste['language'],
                        $paste['tags'],
                        $paste['views'],
                        $paste['is_public'] ? 'Yes' : 'No',
                        date('Y-m-d H:i:s', $paste['created_at']),
                        substr($paste['content'], 0, 100) . (strlen($paste['content']) > 100 ? '...' : ''),
                        $paste['content']
                    ]);
                }
                
                fclose($output);
                break;

            case 'txt':
                header('Content-Type: text/plain');
                header("Content-Disposition: attachment; filename=\"{$filename}.txt\"");
                
                echo "PasteForge Export\n";
                echo "================\n";
                echo "Exported by: {$username}\n";
                echo "Date: " . date('Y-m-d H:i:s') . "\n";
                echo "Total pastes: " . count($pastes) . "\n\n";
                
                foreach ($pastes as $i => $paste) {
                    echo "Paste #" . ($i + 1) . "\n";
                    echo "----------\n";
                    echo "Title: " . $paste['title'] . "\n";
                    echo "Language: " . $paste['language'] . "\n";
                    echo "Created: " . date('Y-m-d H:i:s', $paste['created_at']) . "\n";
                    echo "Views: " . $paste['views'] . "\n";
                    echo "Tags: " . $paste['tags'] . "\n";
                    echo "Public: " . ($paste['is_public'] ? 'Yes' : 'No') . "\n";
                    echo "\nContent:\n";
                    echo $paste['content'] . "\n";
                    echo "\n" . str_repeat("=", 80) . "\n\n";
                }
                break;
        }
        exit;
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>
