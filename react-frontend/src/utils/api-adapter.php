<?php
/**
 * API Adapter for the PHP backend
 * 
 * This file provides endpoints for the React frontend to interact with the PHP backend.
 * It should be placed in the root directory of the PHP project.
 */

// Set headers to allow cross-origin requests from the React app
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session for authentication
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Check if this is an authentication check
if (isset($_GET['check_auth'])) {
    if (isset($_SESSION['user_id'])) {
        // Get user data from database
        require_once 'database.php';
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, email, profile_image, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            // User not found in database, clear session
            session_destroy();
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Not authenticated'
        ]);
    }
    exit;
}

// Handle login request
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false,
            'message' => 'Username and password are required'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Login successful
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        // Return user data (excluding password)
        unset($user['password']);
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username or password'
        ]);
    }
    exit;
}

// Handle registration request
if (isset($_POST['register'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($username) || empty($password) || empty($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required'
        ]);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT 1 FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Username already taken'
        ]);
        exit;
    }
    
    // Check if email already exists
    if ($email) {
        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Email already in use'
            ]);
            exit;
        }
    }
    
    // Create new user
    $user_id = uniqid();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("INSERT INTO users (id, username, email, password, created_at) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$user_id, $username, $email, $hashed_password, time()]);
    
    if ($result) {
        // Login the new user
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user_id,
                'username' => $username,
                'email' => $email,
                'created_at' => time()
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create account'
        ]);
    }
    exit;
}

// Handle logout request
if (isset($_GET['logout'])) {
    session_destroy();
    echo json_encode([
        'success' => true
    ]);
    exit;
}

// Handle create paste request
if (isset($_POST['create_paste'])) {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $language = $_POST['language'] ?? 'plaintext';
    $expire_time = $_POST['expire_time'] ?? '0';
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] === '1';
    $password = $_POST['password'] ?? '';
    $tags = $_POST['tags'] ?? '';
    $burn_after_read = isset($_POST['burn_after_read']) && $_POST['burn_after_read'] === '1';
    $zero_knowledge = isset($_POST['zero_knowledge']) && $_POST['zero_knowledge'] === '1';
    
    if (empty($content)) {
        echo json_encode([
            'success' => false,
            'message' => 'Content is required'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Calculate expiration time
    $expiry = null;
    if ($expire_time > 0) {
        $expiry = time() + intval($expire_time);
    }
    
    // Insert paste
    $stmt = $db->prepare("
        INSERT INTO pastes (
            title, content, language, password, expire_time, created_at, 
            is_public, tags, user_id, burn_after_read, zero_knowledge, current_version
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $result = $stmt->execute([
        $title,
        $content,
        $language,
        $password ? password_hash($password, PASSWORD_DEFAULT) : null,
        $expiry,
        time(),
        $is_public ? 1 : 0,
        $tags,
        $_SESSION['user_id'] ?? null,
        $burn_after_read ? 1 : 0,
        $zero_knowledge ? 1 : 0
    ]);
    
    if ($result) {
        $paste_id = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'paste_id' => $paste_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create paste'
        ]);
    }
    exit;
}

// Handle get paste request
if (isset($_GET['id']) && !isset($_GET['comments']) && !isset($_GET['related'])) {
    $paste_id = $_GET['id'];
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get paste data
    $stmt = $db->prepare("
        SELECT p.*, u.username 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$paste) {
        echo json_encode([
            'success' => false,
            'message' => 'Paste not found'
        ]);
        exit;
    }
    
    // Check if paste is password protected
    if ($paste['password'] && !isset($_SESSION['paste_access'][$paste_id])) {
        echo json_encode([
            'success' => false,
            'message' => 'Password required',
            'requires_password' => true
        ]);
        exit;
    }
    
    // Check if paste is expired
    if ($paste['expire_time'] && time() > $paste['expire_time']) {
        echo json_encode([
            'success' => false,
            'message' => 'Paste has expired'
        ]);
        exit;
    }
    
    // Check if paste is private and user is not the owner
    if (!$paste['is_public'] && $paste['user_id'] !== ($_SESSION['user_id'] ?? null)) {
        echo json_encode([
            'success' => false,
            'message' => 'This paste is private'
        ]);
        exit;
    }
    
    // Increment view count
    $stmt = $db->prepare("UPDATE pastes SET views = views + 1 WHERE id = ?");
    $stmt->execute([$paste_id]);
    
    // Check if burn after read
    if ($paste['burn_after_read']) {
        $stmt = $db->prepare("DELETE FROM pastes WHERE id = ?");
        $stmt->execute([$paste_id]);
        $paste['burn_after_read_viewed'] = true;
    }
    
    echo json_encode([
        'success' => true,
        'paste' => $paste
    ]);
    exit;
}

// Handle get comments request
if (isset($_GET['id']) && isset($_GET['comments'])) {
    $paste_id = $_GET['id'];
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get comments
    $stmt = $db->prepare("
        SELECT c.*, u.username 
        FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.paste_id = ? 
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$paste_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    exit;
}

// Handle get related pastes request
if (isset($_GET['id']) && isset($_GET['related'])) {
    $paste_id = $_GET['id'];
    
    require_once 'database.php';
    require_once 'related_pastes_helper.php';
    
    $db = Database::getInstance()->getConnection();
    $related_helper = new RelatedPastesHelper($db);
    
    $related_pastes = $related_helper->getRelatedPastes($paste_id, 5);
    
    echo json_encode([
        'success' => true,
        'related_pastes' => $related_pastes
    ]);
    exit;
}

// Handle add comment request
if (isset($_POST['add_comment'])) {
    $paste_id = $_POST['paste_id'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($paste_id) || empty($content)) {
        echo json_encode([
            'success' => false,
            'message' => 'Paste ID and content are required'
        ]);
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to comment'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Insert comment
    $stmt = $db->prepare("
        INSERT INTO comments (paste_id, user_id, content, created_at) 
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([
        $paste_id,
        $_SESSION['user_id'],
        $content,
        time()
    ]);
    
    if ($result) {
        $comment_id = $db->lastInsertId();
        
        // Get the comment with username
        $stmt = $db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'comment' => $comment
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add comment'
        ]);
    }
    exit;
}

// Handle get recent pastes request
if (isset($_GET['recent'])) {
    $limit = $_GET['limit'] ?? 5;
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get recent public pastes
    $stmt = $db->prepare("
        SELECT p.*, u.username 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.is_public = 1 
        AND (p.expire_time IS NULL OR p.expire_time > ?) 
        ORDER BY p.created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([time(), $limit]);
    $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pastes' => $pastes
    ]);
    exit;
}

// Handle get archive pastes request
if (isset($_GET['page']) && $_GET['page'] === 'archive') {
    $page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $language = $_GET['language'] ?? '';
    $tag = $_GET['tag'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'date';
    $order = $_GET['order'] ?? 'desc';
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Build query
    $where = "WHERE p.is_public = 1 AND (p.expire_time IS NULL OR p.expire_time > ?)";
    $params = [time()];
    
    if ($language) {
        $where .= " AND p.language = ?";
        $params[] = $language;
    }
    
    if ($tag) {
        $where .= " AND p.tags LIKE ?";
        $params[] = "%$tag%";
    }
    
    if ($search) {
        $where .= " AND (p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // Determine sort order
    $order_by = "p.created_at";
    if ($sort === 'views') {
        $order_by = "p.views";
    }
    
    $order_dir = $order === 'asc' ? 'ASC' : 'DESC';
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes p $where");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Get pastes
    $stmt = $db->prepare("
        SELECT p.*, u.username 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        $where 
        ORDER BY $order_by $order_dir 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'pastes' => $pastes,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'items_per_page' => $limit
        ]
    ]);
    exit;
}

// Handle get user collections request
if (isset($_GET['page']) && $_GET['page'] === 'collections') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get user collections
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM collection_pastes WHERE collection_id = c.id) as paste_count 
        FROM collections c 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'collections' => $collections
    ]);
    exit;
}

// Handle create collection request
if (isset($_POST['create_collection'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $is_public = isset($_POST['is_public']) && $_POST['is_public'] === '1';
    
    if (empty($name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Collection name is required'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Insert collection
    $stmt = $db->prepare("
        INSERT INTO collections (name, description, user_id, is_public, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $now = time();
    $result = $stmt->execute([
        $name,
        $description,
        $_SESSION['user_id'],
        $is_public ? 1 : 0,
        $now,
        $now
    ]);
    
    if ($result) {
        $collection_id = $db->lastInsertId();
        
        // Get the collection
        $stmt = $db->prepare("SELECT * FROM collections WHERE id = ?");
        $stmt->execute([$collection_id]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add paste_count property
        $collection['paste_count'] = 0;
        
        echo json_encode([
            'success' => true,
            'collection' => $collection
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create collection'
        ]);
    }
    exit;
}

// Handle get user account request
if (isset($_GET['page']) && $_GET['page'] === 'account') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Remove sensitive data
    unset($user['password']);
    
    // Get user statistics
    $stats = [
        'totalPastes' => 0,
        'publicPastes' => 0,
        'totalViews' => 0,
        'collections' => 0,
        'following' => 0,
        'followers' => 0
    ];
    
    // Total pastes
    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['totalPastes'] = $stmt->fetchColumn();
    
    // Public pastes
    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND is_public = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['publicPastes'] = $stmt->fetchColumn();
    
    // Total views
    $stmt = $db->prepare("SELECT SUM(views) FROM pastes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['totalViews'] = $stmt->fetchColumn() ?: 0;
    
    // Collections
    $stmt = $db->prepare("SELECT COUNT(*) FROM collections WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['collections'] = $stmt->fetchColumn();
    
    // Following/followers (if table exists)
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['following'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['followers'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Table might not exist
    }
    
    // Get recent pastes
    $stmt = $db->prepare("
        SELECT * FROM pastes 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => $stats,
        'recent_pastes' => $recent_pastes
    ]);
    exit;
}

// Handle update user settings request
if (isset($_POST['update_settings'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }
    
    $setting_type = $_POST['setting_type'] ?? '';
    
    require_once 'database.php';
    $db = Database::getInstance()->getConnection();
    
    switch ($setting_type) {
        case 'password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'All password fields are required'
                ]);
                exit;
            }
            
            if ($new_password !== $confirm_password) {
                echo json_encode([
                    'success' => false,
                    'message' => 'New passwords do not match'
                ]);
                exit;
            }
            
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ]);
                exit;
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Password updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update password'
                ]);
            }
            break;
            
        case 'preferences':
            $theme_preference = $_POST['theme_preference'] ?? 'system';
            $email_notifications = isset($_POST['email_notifications']) && $_POST['email_notifications'] === '1';
            $default_paste_expiry = $_POST['default_paste_expiry'] ?? '604800';
            $default_paste_public = isset($_POST['default_paste_public']) && $_POST['default_paste_public'] === '1';
            $timezone = $_POST['timezone'] ?? 'UTC';
            
            // Add columns if they don't exist
            try {
                $db->exec("ALTER TABLE users ADD COLUMN theme_preference TEXT DEFAULT 'system'");
                $db->exec("ALTER TABLE users ADD COLUMN email_notifications INTEGER DEFAULT 1");
                $db->exec("ALTER TABLE users ADD COLUMN default_paste_expiry INTEGER DEFAULT 604800");
                $db->exec("ALTER TABLE users ADD COLUMN default_paste_public INTEGER DEFAULT 1");
                $db->exec("ALTER TABLE users ADD COLUMN timezone TEXT DEFAULT 'UTC'");
            } catch (PDOException $e) {
                // Columns might already exist
            }
            
            // Update preferences
            $stmt = $db->prepare("
                UPDATE users SET 
                theme_preference = ?, 
                email_notifications = ?, 
                default_paste_expiry = ?, 
                default_paste_public = ?, 
                timezone = ? 
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $theme_preference,
                $email_notifications ? 1 : 0,
                $default_paste_expiry,
                $default_paste_public ? 1 : 0,
                $timezone,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Preferences updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update preferences'
                ]);
            }
            break;
            
        case 'privacy':
            $profile_visibility = $_POST['profile_visibility'] ?? 'public';
            $show_paste_count = isset($_POST['show_paste_count']) && $_POST['show_paste_count'] === '1';
            $allow_messages = isset($_POST['allow_messages']) && $_POST['allow_messages'] === '1';
            
            // Add columns if they don't exist
            try {
                $db->exec("ALTER TABLE users ADD COLUMN profile_visibility TEXT DEFAULT 'public'");
                $db->exec("ALTER TABLE users ADD COLUMN show_paste_count INTEGER DEFAULT 1");
                $db->exec("ALTER TABLE users ADD COLUMN allow_messages INTEGER DEFAULT 1");
            } catch (PDOException $e) {
                // Columns might already exist
            }
            
            // Update privacy settings
            $stmt = $db->prepare("
                UPDATE users SET 
                profile_visibility = ?, 
                show_paste_count = ?, 
                allow_messages = ? 
                WHERE id = ?
            ");
            $result = $stmt->execute([
                $profile_visibility,
                $show_paste_count ? 1 : 0,
                $allow_messages ? 1 : 0,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Privacy settings updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update privacy settings'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid settings type'
            ]);
    }
    exit;
}

// If no specific API endpoint was matched, return an error
echo json_encode([
    'success' => false,
    'message' => 'Invalid API endpoint'
]);