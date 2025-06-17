
<?php
// Simple maintenance page - no database access needed
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Site Under Maintenance - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl mx-auto px-4 text-center">
        <!-- Animated Maintenance Icon -->
        <div class="mb-8">
            <div class="relative inline-block">
                <div class="gradient-bg w-32 h-32 rounded-full flex items-center justify-center mx-auto mb-6 shadow-2xl">
                    <i class="fas fa-tools text-white text-5xl pulse-animation"></i>
                </div>
                <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                    <i class="fas fa-exclamation text-white text-sm"></i>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-10 border border-gray-200 dark:border-gray-700">
            <h1 class="text-4xl font-bold text-gray-800 dark:text-white mb-4">
                🔧 Under Maintenance
            </h1>
            
            <p class="text-xl text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                We're currently performing scheduled maintenance to improve your experience.
            </p>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-8">
                <div class="flex items-center justify-center mb-3">
                    <i class="fas fa-clock text-blue-500 text-2xl mr-3"></i>
                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">
                        Expected Duration
                    </h3>
                </div>
                <p class="text-blue-700 dark:text-blue-300 text-lg">
                    We'll be back online shortly. Please check back in a few minutes.
                </p>
            </div>

            <!-- Features Being Improved -->
            <div class="grid md:grid-cols-3 gap-4 mb-8">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <i class="fas fa-database text-green-500 text-2xl mb-2"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-1">Database</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Optimizing performance</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <i class="fas fa-shield-alt text-blue-500 text-2xl mb-2"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-1">Security</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Enhancing protection</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <i class="fas fa-rocket text-purple-500 text-2xl mb-2"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white mb-1">Features</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Adding improvements</p>
                </div>
            </div>

            <!-- Call to Action -->
            <div class="space-y-4">
                <button onclick="location.reload()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition-colors duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Refresh Page
                </button>
                
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    You can safely refresh this page to check if we're back online
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center">
            <p class="text-gray-500 dark:text-gray-400 text-sm">
                Thank you for your patience while we make PasteForge even better!
            </p>
            <div class="mt-4 flex justify-center space-x-4">
                <div class="w-2 h-2 bg-blue-500 rounded-full pulse-animation"></div>
                <div class="w-2 h-2 bg-blue-500 rounded-full pulse-animation" style="animation-delay: 0.2s;"></div>
                <div class="w-2 h-2 bg-blue-500 rounded-full pulse-animation" style="animation-delay: 0.4s;"></div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds to check if maintenance is over
        setTimeout(() => {
            location.reload();
        }, 30000);

        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to feature cards
            const cards = document.querySelectorAll('.bg-gray-50, .dark\\:bg-gray-700');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>
