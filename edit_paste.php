<?php
session_start();

// Check for maintenance mode
require_once 'maintenance_check.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /');
    exit;
}

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get user info
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? null;

    if (!$user_id || !isset($_GET['id'])) {
        header('Location: /');
        exit;
    }

    $paste_id = $_GET['id'];

    // Get paste data
    $stmt = $db->prepare("SELECT * FROM pastes WHERE id = ? AND user_id = ?");
    $stmt->execute([$paste_id, $user_id]);
    $paste = $stmt->fetch();

    if (!$paste) {
        header('Location: /');
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Save current version to history
        $stmt = $db->prepare("INSERT INTO paste_versions (paste_id, version_number, title, content, language, created_at, created_by, change_message) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $paste_id,
            $paste['current_version'],
            $paste['title'],
            $paste['content'],
            $paste['language'],
            $paste['created_at'],
            $user_id,
            $_POST['change_message'] ?? 'Version update'
        ]);

        // Update paste with new data
        $stmt = $db->prepare("
            UPDATE pastes SET 
            title = ?, content = ?, language = ?, tags = ?, 
            current_version = current_version + 1, last_modified = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $_POST['title'],
            $_POST['content'],
            $_POST['language'],
            $_POST['tags'] ?? '',
            time(),
            $paste_id,
            $user_id
        ]);

        header('Location: /?id=' . $paste_id);
        exit;
    }

    $theme = $_COOKIE['theme'] ?? 'light';
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Edit Paste - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            <a href="?id=<?= $paste_id ?>" class="text-blue-500 hover:text-blue-700">
                <i class="fas fa-arrow-left"></i> Back to Paste
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Edit Paste</h2>
            
            <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <i class="fas fa-info-circle"></i>
                    This will create version <?= $paste['current_version'] + 1 ?> of your paste. 
                    The current version will be saved to history.
                </p>
            </div>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium mb-2">Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($paste['title']) ?>" 
                           required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Content</label>
                    <textarea name="content" required rows="20" 
                              class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 font-mono"><?= htmlspecialchars($paste['content']) ?></textarea>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Language</label>
                        <select name="language" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            <option value="plaintext" <?= $paste['language'] === 'plaintext' ? 'selected' : '' ?>>Plain Text</option>
                            <option value="javascript" <?= $paste['language'] === 'javascript' ? 'selected' : '' ?>>JavaScript</option>
                            <option value="python" <?= $paste['language'] === 'python' ? 'selected' : '' ?>>Python</option>
                            <option value="php" <?= $paste['language'] === 'php' ? 'selected' : '' ?>>PHP</option>
                            <option value="java" <?= $paste['language'] === 'java' ? 'selected' : '' ?>>Java</option>
                            <option value="cpp" <?= $paste['language'] === 'cpp' ? 'selected' : '' ?>>C++</option>
                            <option value="c" <?= $paste['language'] === 'c' ? 'selected' : '' ?>>C</option>
                            <option value="csharp" <?= $paste['language'] === 'csharp' ? 'selected' : '' ?>>C#</option>
                            <option value="html" <?= $paste['language'] === 'html' ? 'selected' : '' ?>>HTML</option>
                            <option value="css" <?= $paste['language'] === 'css' ? 'selected' : '' ?>>CSS</option>
                            <option value="sql" <?= $paste['language'] === 'sql' ? 'selected' : '' ?>>SQL</option>
                            <option value="json" <?= $paste['language'] === 'json' ? 'selected' : '' ?>>JSON</option>
                            <option value="xml" <?= $paste['language'] === 'xml' ? 'selected' : '' ?>>XML</option>
                            <option value="bash" <?= $paste['language'] === 'bash' ? 'selected' : '' ?>>Bash</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Tags</label>
                        <input type="text" name="tags" value="<?= htmlspecialchars($paste['tags']) ?>" 
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                               placeholder="comma, separated, tags">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">Change Message</label>
                    <input type="text" name="change_message" 
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700"
                           placeholder="Describe what you changed in this version">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="?id=<?= $paste_id ?>" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
