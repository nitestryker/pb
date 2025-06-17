<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();
?>
<!DOCTYPE html>
<html class="dark">
<head>
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gray-800 min-h-screen p-4">
            <div class="text-xl font-bold mb-8">Admin</div>
            <nav class="space-y-2">
                <a href="#dashboard" class="block px-4 py-2 bg-gray-700 rounded" data-tab="dashboard">Dashboard</a>
                <a href="#flagged" class="block px-4 py-2 hover:bg-gray-700 rounded" data-tab="flagged">Flagged Pastes</a>
                <a href="#users" class="block px-4 py-2 hover:bg-gray-700 rounded" data-tab="users">Users</a>
                <a href="#audit" class="block px-4 py-2 hover:bg-gray-700 rounded" data-tab="audit">Audit Logs</a>
                <a href="#settings" class="block px-4 py-2 hover:bg-gray-700 rounded" data-tab="settings">Settings</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-8" id="mainContent">
            <!-- Tab content will be loaded here -->
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.getElementById('mainContent');
    const navLinks = document.querySelectorAll('nav a');

    async function loadTab(tabName) {
        try {
            const response = await fetch(`admin-${tabName}.php`);
            const content = await response.text();
            mainContent.innerHTML = content;

            // Execute any scripts in the loaded content
            const scripts = mainContent.querySelectorAll('script');
            scripts.forEach(script => {
                if (script.src) {
                    // External script
                    const newScript = document.createElement('script');
                    newScript.src = script.src;
                    document.head.appendChild(newScript);
                } else if (script.textContent) {
                    // Inline script - execute in global scope
                    try {
                        // Use indirect eval to execute in global scope
                        (1, eval)(script.textContent);
                    } catch (e) {
                        console.error('Error executing script:', e);
                    }
                }
            });

            // Special handling for dashboard chart initialization
            if (tabName === 'dashboard' && window.initTrendChart) {
                setTimeout(function() {
                    window.initTrendChart();
                }, 300);
            }

            // Update active tab
            navLinks.forEach(link => {
                link.classList.toggle('bg-gray-700', link.dataset.tab === tabName);
                link.classList.toggle('hover:bg-gray-700', link.dataset.tab !== tabName);
            });
        } catch (error) {
            console.error('Error loading tab:', error);
        }
    }

    // Handle navigation
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.dataset.tab;
            loadTab(tabName);
        });
    });

    // Load dashboard by default
    loadTab('dashboard');
});
</script>
</body>
</html>