
<?php
// Only start session if not already started (for standalone access)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only require these if not already included
if (!class_exists('Database')) {
    require_once 'database.php';
}

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;
$theme = $_COOKIE['theme'] ?? 'dark';

// Check if this is being included from index.php
$is_included = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?page=pricing') !== false;

if (!$is_included): ?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
    <title>Upgrade - PasteForge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white">
    <!-- Navigation Bar -->
    <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-6">
                    <a href="/" class="flex items-center space-x-3">
                        <i class="fas fa-paste text-2xl"></i>
                        <span class="text-xl font-bold">PasteForge</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-home mr-2"></i>Home
                    </a>
                    <a href="/?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">
                        <i class="fas fa-archive mr-2"></i>Archive
                    </a>
                    <?php if ($user_id): ?>
                        <a href="/?page=account" class="hover:bg-blue-700 px-3 py-2 rounded">
                            <i class="fas fa-crown mr-2"></i>Account
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-8 px-4">
<?php endif; ?>

<!-- Page Header -->
<div class="text-center mb-12">
    <h1 class="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
        <i class="fas fa-rocket mr-3 text-blue-500"></i>
        Upgrade Your PasteForge Experience
    </h1>
    <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
        Choose the perfect plan to enhance your code sharing and collaboration experience
    </p>
</div>

<!-- Pricing Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
    <!-- Free Plan -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Free</h3>
            <div class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
                $0
                <span class="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Basic features for casual users</p>
        </div>

        <ul class="space-y-3 mb-8">
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Create & share pastes</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Syntax highlighting</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Public pastes</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Self-destruct option</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Basic syntax highlighting</span>
            </li>
            <li class="flex items-center text-sm text-gray-400">
                <i class="fas fa-times text-red-500 mr-3"></i>
                <span>Analytics</span>
            </li>
        </ul>

        <button class="w-full py-2 px-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
            Current Plan
        </button>
    </div>

    <!-- Starter AI Plan -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Starter AI</h3>
            <div class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
                $5
                <span class="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Essential AI features for knowledge workers</p>
        </div>

        <ul class="space-y-3 mb-8">
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Everything in Free</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>AI Generated Tags</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>AI Tag Suggestions (3/hr)</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>AI Search Multibot</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Private pastes</span>
            </li>
            <li class="flex items-center text-sm text-gray-400">
                <i class="fas fa-times text-red-500 mr-3"></i>
                <span>Advanced AI features</span>
            </li>
        </ul>

        <button class="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i class="fas fa-rocket mr-2"></i>
            Select Plan
        </button>
    </div>

    <!-- Pro AI Plan -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border-2 border-blue-500 p-6 relative">
        <!-- Popular Badge -->
        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
            <span class="bg-blue-500 text-white px-4 py-1 rounded-full text-sm font-medium">Popular</span>
        </div>

        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Pro AI</h3>
            <div class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
                $10
                <span class="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Complete features for power users</p>
        </div>

        <ul class="space-y-3 mb-8">
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Full AI Code Refactoring</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Advanced AI models</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Unlimited AI queries</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Scheduled Publishing</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Private Collections</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Priority Support</span>
            </li>
        </ul>

        <button class="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i class="fas fa-crown mr-2"></i>
            Select Plan
        </button>
    </div>

    <!-- Dev Team Plan -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
        <div class="text-center mb-6">
            <h3 class="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Dev Team</h3>
            <div class="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
                $25
                <span class="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400">Collaborative tools for development teams</p>
        </div>

        <ul class="space-y-3 mb-8">
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Use Collaborative Tools</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Team chat sharing (5 users)</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>Advanced Analytics</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>SSO & ACLs</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>API Webhooks</span>
                <span class="ml-2 text-xs bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded">coming soon</span>
            </li>
            <li class="flex items-center text-sm">
                <i class="fas fa-check text-green-500 mr-3"></i>
                <span>All Pro AI features</span>
            </li>
        </ul>

        <button class="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i class="fas fa-users mr-2"></i>
            Select Plan
        </button>
    </div>
</div>

<!-- Information Banner -->
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-8">
    <div class="flex items-center justify-center">
        <i class="fas fa-info-circle text-blue-500 mr-3"></i>
        <span class="text-blue-800 dark:text-blue-200 text-sm">
            This is a development environment web interface preview. Pro, gradebook, you would be redirected to a payment processor.
        </span>
    </div>
</div>

<!-- Features Comparison -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-8">
    <h2 class="text-2xl font-bold text-center mb-8 text-gray-900 dark:text-white">
        Feature Comparison
    </h2>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-4 px-4 font-medium text-gray-900 dark:text-white">Feature</th>
                    <th class="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Free</th>
                    <th class="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Starter AI</th>
                    <th class="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Pro AI</th>
                    <th class="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Dev Team</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="py-4 px-4">Public Pastes</td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="py-4 px-4">Private Pastes</td>
                    <td class="text-center py-4 px-4"><span class="text-gray-400">Limited</span></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="py-4 px-4">AI Code Analysis</td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><span class="text-blue-600">Basic</span></td>
                    <td class="text-center py-4 px-4"><span class="text-green-600">Advanced</span></td>
                    <td class="text-center py-4 px-4"><span class="text-green-600">Advanced</span></td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="py-4 px-4">Team Collaboration</td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><span class="text-blue-600">Basic</span></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                </tr>
                <tr class="border-b border-gray-100 dark:border-gray-700">
                    <td class="py-4 px-4">API Access</td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><span class="text-blue-600">Limited</span></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                </tr>
                <tr>
                    <td class="py-4 px-4">Priority Support</td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-times text-red-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                    <td class="text-center py-4 px-4"><i class="fas fa-check text-green-500"></i></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- FAQ Section -->
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
    <h2 class="text-2xl font-bold text-center mb-8 text-gray-900 dark:text-white">
        Frequently Asked Questions
    </h2>

    <div class="grid md:grid-cols-2 gap-8">
        <div>
            <h3 class="font-semibold mb-2 text-gray-900 dark:text-white">Can I change my plan at any time?</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">
                Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.
            </p>
        </div>

        <div>
            <h3 class="font-semibold mb-2 text-gray-900 dark:text-white">What payment methods do you accept?</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">
                We accept all major credit cards and PayPal for subscription payments.
            </p>
        </div>

        <div>
            <h3 class="font-semibold mb-2 text-gray-900 dark:text-white">Is there a free trial for paid plans?</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">
                Yes, we offer a 7-day free trial for all paid plans. No credit card required.
            </p>
        </div>

        <div>
            <h3 class="font-semibold mb-2 text-gray-900 dark:text-white">Can I cancel my subscription?</h3>
            <p class="text-gray-600 dark:text-gray-400 text-sm">
                Absolutely. You can cancel your subscription at any time from your account settings.
            </p>
        </div>
    </div>
</div>

<!-- Call to Action -->
<div class="text-center mt-12">
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-8 text-white">
        <h2 class="text-3xl font-bold mb-4">Ready to Get Started?</h2>
        <p class="text-lg mb-6 opacity-90">
            Join thousands of developers who trust PasteForge for their code sharing needs
        </p>
        <?php if (!$user_id): ?>
            <div class="space-x-4">
                <a href="/?page=signup" class="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    <i class="fas fa-user-plus mr-2"></i>
                    Start Free Trial
                </a>
                <a href="/?page=login" class="inline-block border border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </a>
            </div>
        <?php else: ?>
            <a href="/?page=account" class="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Account
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$is_included): ?>
    </div>

    <script>
        // Add some interactive functionality
        document.addEventListener('DOMContentLoaded', function() {
            const planButtons = document.querySelectorAll('button:contains("Select Plan")');
            
            planButtons.forEach(button => {
                button.addEventListener('click', function() {
                    alert('This would redirect to payment processing in a production environment.');
                });
            });
        });
    </script>
</body>
</html>
<?php endif; ?>
