<?php
session_start();
require_once 'database.php';
require_once 'maintenance_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /?page=login');
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Get user info
$stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $user['username'];

// Handle import/export actions
$action = $_GET['action'] ?? 'view';
$success_message = '';
$error_message = '';

if ($action === 'export' && isset($_GET['format'])) {
    $format = $_GET['format'];
    $selection = $_GET['selection'] ?? 'all';
    $paste_ids = $_GET['paste_ids'] ?? '';
    
    // Redirect to export.php with parameters
    header("Location: export.php?action=export&format=$format&selection=$selection&paste_ids=$paste_ids");
    exit;
}

// Get user's pastes for export selection
$stmt = $db->prepare("SELECT id, title, language, created_at, views FROM pastes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$user_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get theme from cookie
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Import & Export - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                    <div class="flex space-x-4">
                        <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
                        <a href="/?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">Archive</a>
                        <a href="/?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
                        <a href="project_manager.php" class="hover:bg-blue-700 px-3 py-2 rounded">Projects</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="toggleTheme()" class="p-2 rounded hover:bg-blue-700">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="relative group">
                        <button class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                            <?php
                            $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $profile_image = $stmt->fetch()['profile_image'] ?? null;
                            ?>
                            <img src="<?= $profile_image ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=32' ?>" 
                                 class="w-8 h-8 rounded-full" alt="Profile">
                            <span><?= htmlspecialchars($username) ?></span>
                            <i class="fas fa-chevron-down ml-1"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 hidden group-hover:block z-10">
                            <a href="/?page=account" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-user mr-2"></i>Account
                            </a>
                            <a href="/?page=settings" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <a href="/?page=collections" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-folder mr-2"></i>Collections
                            </a>
                            <a href="project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-folder-tree mr-2"></i>Projects
                            </a>
                            <a href="following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-users mr-2"></i>Following
                            </a>
                            <a href="import-export.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700">
                                <i class="fas fa-exchange-alt mr-2"></i>Import/Export
                            </a>
                            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                            <a href="/?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($success_message): ?>
            <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
            <!-- Header -->
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h1 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-exchange-alt mr-3 text-blue-500"></i>
                    Import & Export
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Transfer your pastes between PasteForge and other platforms
                </p>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-8 px-6" aria-label="Tabs">
                    <a href="#import" onclick="showTab('import'); return false;" id="import-tab" class="tab-button border-b-2 border-blue-500 text-blue-600 dark:text-blue-400 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-file-import mr-2"></i>Import
                    </a>
                    <a href="#export" onclick="showTab('export'); return false;" id="export-tab" class="tab-button border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-4 px-1 text-sm font-medium">
                        <i class="fas fa-file-export mr-2"></i>Export
                    </a>
                </nav>
            </div>

            <!-- Import Tab -->
            <div id="import-tab-content" class="p-6">
                <h2 class="text-lg font-semibold mb-4">Import Pastes</h2>
                
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

                <a href="import.php" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors">
                    <i class="fas fa-file-import mr-2"></i>
                    Go to Import Page
                </a>

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

            <!-- Export Tab -->
            <div id="export-tab-content" class="p-6 hidden">
                <h2 class="text-lg font-semibold mb-4">Export Pastes</h2>
                
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-2">
                        <i class="fas fa-info-circle"></i> Export Options
                    </h3>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        Export your pastes in various formats for backup or transfer to other platforms.
                        Choose which pastes to export and your preferred format.
                    </p>
                </div>

                <form id="exportForm" action="import-export.php" method="GET" class="space-y-6">
                    <input type="hidden" name="action" value="export">
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Export Format</label>
                        <div class="grid grid-cols-3 gap-4">
                            <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                <input type="radio" name="format" value="json" checked class="mr-2">
                                <div>
                                    <div class="font-medium">JSON</div>
                                    <div class="text-xs text-gray-500">Structured data format</div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                <input type="radio" name="format" value="csv" class="mr-2">
                                <div>
                                    <div class="font-medium">CSV</div>
                                    <div class="text-xs text-gray-500">Spreadsheet compatible</div>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                <input type="radio" name="format" value="txt" class="mr-2">
                                <div>
                                    <div class="font-medium">TXT</div>
                                    <div class="text-xs text-gray-500">Plain text format</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-2">Pastes to Export</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="selection" value="all" checked class="mr-2" onchange="togglePasteSelection()">
                                <span>All my pastes</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="selection" value="selected" class="mr-2" onchange="togglePasteSelection()">
                                <span>Selected pastes only</span>
                            </label>
                        </div>
                    </div>

                    <div id="pasteSelectionContainer" class="hidden">
                        <label class="block text-sm font-medium mb-2">Select Pastes</label>
                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <input type="checkbox" id="selectAllPastes" class="mr-2" onchange="toggleAllPastes()">
                                            Select
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Title
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Language
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Date
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php foreach ($user_pastes as $paste): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="paste_checkbox" value="<?= $paste['id'] ?>" class="paste-checkbox" onchange="updateSelectedPastes()">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?= htmlspecialchars($paste['title'] ?: 'Untitled') ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                    <?= htmlspecialchars($paste['language']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <?= date('M j, Y', $paste['created_at']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <input type="hidden" name="paste_ids" id="selectedPasteIds" value="">
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" class="bg-blue-500 text-white py-2 px-6 rounded-lg hover:bg-blue-600">
                            <i class="fas fa-file-export mr-2"></i>
                            Export Pastes
                        </button>
                        <button type="reset" class="bg-gray-500 text-white py-2 px-6 rounded-lg hover:bg-gray-600">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
            html.classList.remove('dark', 'light');
            html.classList.add(newTheme);
            document.cookie = `theme=${newTheme};path=/`;
        }

        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('[id$="-tab-content"]').forEach(tab => {
                tab.classList.add('hidden');
            });

            // Remove active styling from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById(`${tabName}-tab-content`).classList.remove('hidden');

            // Add active styling to selected tab button
            const activeButton = document.getElementById(`${tabName}-tab`);
            activeButton.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
            activeButton.classList.remove('border-transparent', 'text-gray-500');
        }

        function togglePasteSelection() {
            const selectionContainer = document.getElementById('pasteSelectionContainer');
            const selectionType = document.querySelector('input[name="selection"]:checked').value;
            
            if (selectionType === 'selected') {
                selectionContainer.classList.remove('hidden');
            } else {
                selectionContainer.classList.add('hidden');
            }
        }

        function toggleAllPastes() {
            const selectAll = document.getElementById('selectAllPastes');
            const checkboxes = document.querySelectorAll('.paste-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedPastes();
        }

        function updateSelectedPastes() {
            const checkboxes = document.querySelectorAll('.paste-checkbox:checked');
            const selectedIds = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('selectedPasteIds').value = selectedIds.join(',');
        }

        // Initialize the form
        document.addEventListener('DOMContentLoaded', function() {
            togglePasteSelection();
            
            // Handle form submission
            document.getElementById('exportForm').addEventListener('submit', function(e) {
                const selectionType = document.querySelector('input[name="selection"]:checked').value;
                
                if (selectionType === 'selected') {
                    const selectedIds = document.getElementById('selectedPasteIds').value;
                    
                    if (!selectedIds) {
                        e.preventDefault();
                        alert('Please select at least one paste to export');
                    }
                }
            });
        });
    </script>
</body>
</html>