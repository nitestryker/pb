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

    $import_results = null;
    $error_message = null;

    // Handle import request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
        $file = $_FILES['import_file'];
        $import_mode = $_POST['import_mode'] ?? 'add';
        $title_prefix = $_POST['title_prefix'] ?? '';
        $default_language = $_POST['default_language'] ?? '';
        $default_tags = $_POST['default_tags'] ?? '';
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $file_content = file_get_contents($file['tmp_name']);
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            $imported_pastes = [];
            
            try {
                switch ($file_extension) {
                    case 'json':
                        $data = json_decode($file_content, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('Invalid JSON format');
                        }
                        
                        // Check if it's a PasteForge export
                        if (isset($data['pastes'])) {
                            $imported_pastes = $data['pastes'];
                        } else {
                            // Try to parse as simple array of pastes
                            $imported_pastes = $data;
                        }
                        break;
                        
                    case 'csv':
                        $lines = str_getcsv($file_content, "\n");
                        $headers = str_getcsv(array_shift($lines));
                        
                        foreach ($lines as $line) {
                            if (empty(trim($line))) continue;
                            
                            $values = str_getcsv($line);
                            $paste = array_combine($headers, $values);
                            
                            // Map CSV columns to paste structure
                            $imported_pastes[] = [
                                'title' => $paste['Title'] ?? 'Imported Paste',
                                'content' => $paste['Content'] ?? '',
                                'language' => $paste['Language'] ?? 'plaintext',
                                'tags' => $paste['Tags'] ?? '',
                                'is_public' => ($paste['Public'] ?? 'Yes') === 'Yes' ? 1 : 0
                            ];
                        }
                        break;
                        
                    case 'txt':
                        // Simple text import - create single paste
                        $txt_title = $title_prefix ? $title_prefix . basename($file['name'], '.txt') : 'Imported Text - ' . date('Y-m-d H:i:s');
                        $imported_pastes[] = [
                            'title' => $txt_title,
                            'content' => $file_content,
                            'language' => 'plaintext',
                            'tags' => 'imported',
                            'is_public' => 1
                        ];
                        break;
                        
                    default:
                        throw new Exception('Unsupported file format. Please use JSON, CSV, or TXT files.');
                }
                
                // Import pastes to database
                $successful_imports = 0;
                $failed_imports = 0;
                $skipped_imports = 0;
                
                foreach ($imported_pastes as $paste_data) {
                    try {
                        // Validate required fields
                        if (empty($paste_data['title']) || empty($paste_data['content'])) {
                            $failed_imports++;
                            continue;
                        }
                        
                        // Check for duplicates if mode is 'skip_duplicates'
                        if ($import_mode === 'skip_duplicates') {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND title = ? AND content = ?");
                            $stmt->execute([$user_id, $paste_data['title'], $paste_data['content']]);
                            if ($stmt->fetchColumn() > 0) {
                                $skipped_imports++;
                                continue;
                            }
                        }
                        
                        // Apply custom settings
                        $final_title = $title_prefix . $paste_data['title'];
                        $final_language = $default_language ?: ($paste_data['language'] ?? 'plaintext');
                        $final_tags = $paste_data['tags'] ?? '';
                        if ($default_tags) {
                            $final_tags = $final_tags ? $final_tags . ', ' . $default_tags : $default_tags;
                        }
                        
                        // Insert paste
                        $stmt = $db->prepare("
                            INSERT INTO pastes (title, content, language, tags, is_public, user_id, created_at, views, current_version) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)
                        ");
                        
                        $stmt->execute([
                            $final_title,
                            $paste_data['content'],
                            $final_language,
                            $final_tags,
                            $paste_data['is_public'] ?? 1,
                            $user_id,
                            time()
                        ]);
                        
                        $successful_imports++;
                        
                    } catch (Exception $e) {
                        $failed_imports++;
                    }
                }
                
                $import_results = [
                    'successful' => $successful_imports,
                    'failed' => $failed_imports,
                    'skipped' => $skipped_imports,
                    'total' => count($imported_pastes)
                ];
                
            } catch (Exception $e) {
                $error_message = $e->getMessage();
            }
        } else {
            $error_message = 'File upload failed. Please try again.';
        }
    }

} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Import Pastes - PasteForge</title>
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
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="mb-6">
            <a href="/?page=import-export" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Import/Export
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">
                <i class="fas fa-file-import mr-2"></i>Import Pastes
            </h2>

            <?php if ($import_results): ?>
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <h3 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-2">
                        <i class="fas fa-check-circle"></i> Import Completed
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600"><?= $import_results['successful'] ?></div>
                            <div class="text-gray-600 dark:text-gray-400">Successful</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-red-600"><?= $import_results['failed'] ?></div>
                            <div class="text-gray-600 dark:text-gray-400">Failed</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600"><?= $import_results['skipped'] ?></div>
                            <div class="text-gray-600 dark:text-gray-400">Skipped</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600"><?= $import_results['total'] ?></div>
                            <div class="text-gray-600 dark:text-gray-400">Total</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-2">
                        <i class="fas fa-exclamation-triangle"></i> Import Error
                    </h3>
                    <p class="text-red-700 dark:text-red-300"><?= htmlspecialchars($error_message) ?></p>
                </div>
            <?php endif; ?>

            <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
                    <i class="fas fa-info-circle"></i> Supported Formats
                </h3>
                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                    <li><strong>JSON:</strong> PasteForge export format or custom JSON with title, content, language fields</li>
                    <li><strong>CSV:</strong> Spreadsheet format with Title, Content, Language, Tags, Public columns</li>
                    <li><strong>TXT:</strong> Plain text file (creates single paste)</li>
                </ul>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Select Import File</label>
                    <input type="file" name="import_file" required accept=".json,.csv,.txt" 
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    <p class="mt-1 text-sm text-gray-500">Supported formats: JSON, CSV, TXT</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Import Mode</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="import_mode" value="add" checked class="mr-2">
                            <span>Add all pastes (allow duplicates)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="import_mode" value="skip_duplicates" class="mr-2">
                            <span>Skip duplicate pastes (based on title and content)</span>
                        </label>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Title Prefix (Optional)</label>
                        <input type="text" name="title_prefix" placeholder="e.g. Imported - " 
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <p class="mt-1 text-sm text-gray-500">Will be added to the beginning of each paste title</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Default Language</label>
                        <select name="default_language" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="">Use file's language (if specified)</option>
                            <option value="plaintext">Plain Text</option>
                            <option value="javascript">JavaScript</option>
                            <option value="python">Python</option>
                            <option value="php">PHP</option>
                            <option value="html">HTML</option>
                            <option value="css">CSS</option>
                            <option value="sql">SQL</option>
                            <option value="json">JSON</option>
                            <option value="xml">XML</option>
                            <option value="markdown">Markdown</option>
                            <option value="bash">Bash</option>
                            <option value="java">Java</option>
                            <option value="cpp">C++</option>
                            <option value="c">C</option>
                            <option value="csharp">C#</option>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Override language for imported pastes</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Default Tags (Optional)</label>
                    <input type="text" name="default_tags" placeholder="imported, backup, archive" 
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                    <p class="mt-1 text-sm text-gray-500">Comma-separated tags to add to all imported pastes</p>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-file-import mr-2"></i>Import Pastes
                    </button>
                    <a href="/?page=import-export" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600">
                        Cancel
                    </a>
                </div>
            </form>

            <div class="mt-8 border-t dark:border-gray-700 pt-6">
                <h3 class="text-lg font-semibold mb-4">Import Examples</h3>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="font-medium">JSON Format Example:</h4>
                        <pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm overflow-x-auto"><code>{
  "pastes": [
    {
      "title": "Hello World",
      "content": "console.log('Hello, World!');",
      "language": "javascript",
      "tags": "example,javascript",
      "is_public": 1
    }
  ]
}</code></pre>
                    </div>

                    <div>
                        <h4 class="font-medium">CSV Format Example:</h4>
                        <pre class="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm overflow-x-auto"><code>Title,Content,Language,Tags,Public
"Hello World","console.log('Hello, World!');","javascript","example,javascript","Yes"
"Python Script","print('Hello Python')","python","example,python","No"</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
