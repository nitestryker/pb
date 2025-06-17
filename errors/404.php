
<?php
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>404 - Page Not Found | PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="max-w-lg mx-auto text-center">
            <div class="mb-8">
                <div class="relative mb-6">
                    <i class="fas fa-search text-8xl text-gray-300 dark:text-gray-600"></i>
                    <i class="fas fa-times-circle text-3xl text-red-500 absolute -top-2 -right-2"></i>
                </div>
                <h1 class="text-6xl font-bold mb-2 text-gray-800 dark:text-white">404</h1>
                <h2 class="text-2xl font-semibold mb-4 text-gray-700 dark:text-gray-300">Page Not Found</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8 leading-relaxed">
                    The paste or page you're looking for might have been moved, deleted, or expired. 
                    Don't worry, let's get you back on track!
                </p>
            </div>
            
            <div class="space-y-4">
                <a href="/" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-8 rounded-lg transition-all transform hover:scale-105">
                    <i class="fas fa-home mr-2"></i>Return Home
                </a>
                
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="?page=archive" class="text-blue-500 hover:text-blue-700 font-medium">
                        <i class="fas fa-archive mr-2"></i>Browse Archive
                    </a>
                    <button onclick="history.back()" class="text-blue-500 hover:text-blue-700 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Go Back
                    </button>
                </div>
            </div>
            
            <div class="mt-12 p-6 bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <a href="/" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-plus text-green-500 text-xl mb-2"></i>
                        <div class="font-medium">Create New Paste</div>
                        <div class="text-sm text-gray-500">Start fresh with a new paste</div>
                    </a>
                    <a href="?page=archive" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fas fa-search text-blue-500 text-xl mb-2"></i>
                        <div class="font-medium">Search Pastes</div>
                        <div class="text-sm text-gray-500">Find what you're looking for</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
