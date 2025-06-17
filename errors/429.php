
<?php
$theme = $_COOKIE['theme'] ?? 'dark';
$reset_time = $_GET['reset_time'] ?? null;
$wait_time = $reset_time ? max(0, $reset_time - time()) : 0;
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>429 - Too Many Requests | PasteForge</title>
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
        <div class="max-w-md mx-auto text-center">
            <div class="mb-8">
                <i class="fas fa-hourglass-half text-6xl text-yellow-500 mb-4 animate-pulse"></i>
                <h1 class="text-4xl font-bold mb-2">429</h1>
                <h2 class="text-xl text-gray-600 dark:text-gray-400 mb-4">Too Many Requests</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    You've made too many requests in a short period. This helps us keep PasteForge fast and secure for everyone.
                </p>
            </div>
            
            <?php if ($wait_time > 0): ?>
            <div class="mb-8 p-4 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg">
                <div class="text-yellow-800 dark:text-yellow-200">
                    <i class="fas fa-clock mr-2"></i>
                    Please wait <span id="countdown" class="font-bold"><?= $wait_time ?></span> seconds before trying again.
                </div>
            </div>
            
            <script>
                let timeLeft = <?= $wait_time ?>;
                const countdown = document.getElementById('countdown');
                
                const timer = setInterval(() => {
                    timeLeft--;
                    countdown.textContent = timeLeft;
                    
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        location.reload();
                    }
                }, 1000);
            </script>
            <?php endif; ?>
            
            <div class="space-y-4">
                <a href="/" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-home mr-2"></i>Go Home
                </a>
                
                <div class="text-center">
                    <button onclick="history.back()" class="text-blue-500 hover:text-blue-700 underline">
                        <i class="fas fa-arrow-left mr-1"></i>Go Back
                    </button>
                </div>
            </div>
            
            <div class="mt-8 p-4 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                <h3 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">Need Help?</h3>
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p>If you believe this is an error, please contact support.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
