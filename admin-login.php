<?php
session_start();
$error = '';

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create admin users table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");

    // Check if admin user exists, if not create it
    $stmt = $db->prepare("SELECT 1 FROM admin_users WHERE username = ?");
    $stmt->execute(['nitestryker']);
    if (!$stmt->fetch()) {
        $hashed_password = password_hash('j$3208118', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admin_users (username, password) VALUES (?, ?)");
        $stmt->execute(['nitestryker', $hashed_password]);
    }

    // Handle login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($_POST['password'], $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: admindash.php');
            exit;
        } else {
            $error = "Invalid credentials";
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?><!DOCTYPE html>
<html class="dark">
<head>
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-lg shadow-lg w-96">
            <h1 class="text-2xl font-bold mb-6 text-gray-900 dark:text-white">Admin Login</h1>
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
