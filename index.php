<?php
// Incorporating paste versioning and history features, including UI adjustments for displaying versions and updating edit functionalities.
session_start();

// Check for maintenance mode first
require_once 'maintenance_check.php';

// Include security and logging systems
require_once 'audit_logger.php';
require_once 'rate_limiter.php';
require_once 'error_handler.php';
require_once 'settings_helper.php';

// Initialize security systems
$audit_logger = new AuditLogger();
$rate_limiter = new RateLimiter();
$error_handler = new ErrorHandler($audit_logger);

// Initialize database connection early for AJAX requests
require_once 'database.php';
$db = Database::getInstance()->getConnection();

// Handle logout
if (isset($_GET['logout'])) {
    $audit_logger->log('user_logout', 'auth', $_SESSION['user_id'] ?? null);
    session_destroy();
    header('Location: /');
    exit;
}

  // Handle AJAX requests
  if (isset($_GET['action'])) {
    if ($_GET['action'] === 'load_children') {
      $parent_id = $_GET['parent_id'] ?? '';
      if ($parent_id) {
        $stmt = $db->prepare(
          "SELECT p.*, u.username,
          (SELECT COUNT(*) FROM comments WHERE paste_id = p.id) as comment_count,
          COALESCE(p.fork_count, 0) as fork_count
          FROM pastes p
          LEFT JOIN users u ON p.user_id = u.id
          WHERE p.parent_paste_id = ? AND p.is_public = 1 AND p.zero_knowledge = 0
          AND (p.expire_time IS NULL OR p.expire_time > ?)
          ORDER BY p.created_at ASC"
        );
        $stmt->execute([$parent_id, time()]);
        $children = $stmt->fetchAll();

        header('Content-Type: application/json');
        echo json_encode($children);
        exit;
      }
    }
    else if ($_GET['action'] === 'get_discussion_threads') {
      $paste_id = $_GET['paste_id'] ?? '';
      if ($paste_id) {
        $stmt = $db->prepare("
          SELECT dt.*, u.username, u.profile_image,
                 (SELECT COUNT(*) FROM paste_discussion_posts WHERE thread_id = dt.id AND is_deleted = 0) as reply_count
          FROM paste_discussion_threads dt
          LEFT JOIN users u ON dt.user_id = u.id
          WHERE dt.paste_id = ?
          ORDER BY dt.created_at DESC
        ");
        $stmt->execute([$paste_id]);
        $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($threads);
        exit;
      }
    }
    else if ($_GET['action'] === 'get_discussion_posts') {
      $thread_id = $_GET['thread_id'] ?? '';
      if ($thread_id) {
        $stmt = $db->prepare("
          SELECT dp.*, u.username, u.profile_image
          FROM paste_discussion_posts dp
          LEFT JOIN users u ON dp.user_id = u.id
          WHERE dp.thread_id = ? AND dp.is_deleted = 0
          ORDER BY dp.created_at ASC
        ");
        $stmt->execute([$thread_id]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($posts);
        exit;
      }
    }
    else if ($_GET['action'] === 'search_users') {
      $term = $_GET['term'] ?? '';
      $stmt = $db->prepare("SELECT username FROM users WHERE username LIKE ? LIMIT 5");
      $stmt->execute(["%$term%"]);
      $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
      header('Content-Type: application/json');
      echo json_encode($usernames);
      exit;
    }
    else if ($_GET['action'] === 'validate_user') {
      $username = $_GET['username'] ?? '';
      $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
      $stmt->execute([$username]);
      $exists = $stmt->fetchColumn() > 0;
      header('Content-Type: application/json');
      echo json_encode(['exists' => $exists]);
      exit;
    }
    else if ($_GET['action'] === 'get_templates') {
      $category = $_GET['category'] ?? 'all';
      $where = "WHERE is_public = 1";
      if ($user_id) {
        $where .= " OR created_by = '$user_id'";
      }
      if ($category !== 'all') {
        $where .= " AND category = " . $db->quote($category);
      }

      $stmt = $db->query("SELECT * FROM paste_templates $where ORDER BY usage_count DESC, name ASC");
      $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
      header('Content-Type: application/json');
      echo json_encode($templates);
      exit;
    }
    else if ($_GET['action'] === 'get_template') {
      $template_id = $_GET['id'] ?? '';
      $stmt = $db->prepare("SELECT * FROM paste_templates WHERE id = ? AND (is_public = 1 OR created_by = ?)");
      $stmt->execute([$template_id, $user_id]);
      $template = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($template) {
        // Increment usage count
        $db->prepare("UPDATE paste_templates SET usage_count = usage_count + 1 WHERE id = ?")->execute([$template_id]);
      }

      header('Content-Type: application/json');
      echo json_encode($template ?: ['error' => 'Template not found']);
      exit;
    }
    else if ($_GET['action'] === 'get_template_categories') {
      $stmt = $db->query("SELECT DISTINCT category FROM paste_templates WHERE is_public = 1 OR created_by = '$user_id' ORDER BY category");
      $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
      header('Content-Type: application/json');
      echo json_encode($categories);
      exit;
    }
  }

function human_time_diff($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    
    $days = floor($diff / 86400);
    if ($days < 30) return $days . ' days ago';
    
    $months = floor($days / 30);
    if ($months < 12) return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    
    $years = floor($months / 12);
    return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
}

// Build pagination URLs with current filter parameters
function buildPaginationUrl($page) {
    $params = $_GET;
    $params['p'] = $page;
    return '?' . http_build_query($params);
}



try {

  // Add user table
  $db->exec("CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT NOT NULL,
    email TEXT,
    profile_image TEXT DEFAULT NULL,
    website TEXT DEFAULT NULL,
    created_at INTEGER DEFAULT (strftime('%s', 'now'))
  )");

  // Ensure messages table has the correct threaded messaging schema
  // Check if table exists with correct structure
  $stmt = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='messages'");
  $existing_table = $stmt->fetch();

  if (!$existing_table || strpos($existing_table['sql'], 'reply_to_message_id') === false) {
    // Drop and recreate with correct schema
    $db->exec("DROP TABLE IF EXISTS messages");
    $db->exec("CREATE TABLE messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      sender_id TEXT NOT NULL,
      subject TEXT NOT NULL,
      content TEXT NOT NULL,
      reply_to_message_id INTEGER,
      thread_id INTEGER,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      sender_keep INTEGER DEFAULT 1,
      FOREIGN KEY(sender_id) REFERENCES users(id),
      FOREIGN KEY(reply_to_message_id) REFERENCES messages(id),
      FOREIGN KEY(thread_id) REFERENCES messages(id)
    )");
  }

  // Drop and recreate if columns are missing
  $stmt = $db->query("PRAGMA table_info(users)");
  $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
  if (!in_array('password', $columns)) {
    $db->exec("DROP TABLE IF EXISTS users");
    $db->exec("CREATE TABLE users (
      id TEXT PRIMARY KEY,
      username TEXT UNIQUE,
      password TEXT NOT NULL,
      email TEXT,
      profile_image TEXT DEFAULT NULL,
      created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");
  }

  // Add profile_image and website columns if they don't exist
  $columns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
  if (!in_array('profile_image', $columns)) {
    $db->exec("ALTER TABLE users ADD COLUMN profile_image TEXT DEFAULT NULL");
  }
  if (!in_array('website', $columns)) {
    $db->exec("ALTER TABLE users ADD COLUMN website TEXT DEFAULT NULL");
  }
  if (!in_array('email', $columns)) {
    $db->exec("ALTER TABLE users ADD COLUMN email TEXT DEFAULT NULL");
  }

  // Create site_settings table if not exists and ensure defaults
  $db->exec("CREATE TABLE IF NOT EXISTS site_settings (
    id INTEGER PRIMARY KEY,
    site_name TEXT DEFAULT 'PasteForge',
    max_paste_size INTEGER DEFAULT 500000,
    default_expiry INTEGER DEFAULT 604800,
    registration_enabled INTEGER DEFAULT 1,
    email_verification_required INTEGER DEFAULT 0,
    allowed_email_domains TEXT DEFAULT '*',
    ai_moderation_enabled INTEGER DEFAULT 0,
    shadowban_enabled INTEGER DEFAULT 1,
    auto_blur_threshold INTEGER DEFAULT 5,
    auto_delete_threshold INTEGER DEFAULT 10,
    theme_default TEXT DEFAULT 'dark',
    site_logo TEXT DEFAULT NULL,
    daily_paste_limit_free INTEGER DEFAULT 10,
    daily_paste_limit_premium INTEGER DEFAULT 50,
    encryption_enabled INTEGER DEFAULT 1,
    maintenance_mode INTEGER DEFAULT 0
  )");

  // Insert default settings if not exist
  $db->exec("INSERT OR IGNORE INTO site_settings (id) VALUES (1)");

  // Add user_pastes table for favorites
  $db->exec("CREATE TABLE IF NOT EXISTS user_pastes (
    user_id TEXT,
    paste_id INTEGER,
    is_favorite BOOLEAN DEFAULT 0,
    created_at INTEGER,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(paste_id) REFERENCES pastes(id)
  )");

  // Add collections table
  $db->exec("CREATE TABLE IF NOT EXISTS collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    user_id TEXT NOT NULL,
    is_public BOOLEAN DEFAULT 1,
    created_at INTEGER DEFAULT (strftime('%s', 'now')),
    updated_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY(user_id) REFERENCES users(id)
  )");

  // Add collection_pastes table for many-to-many relationship
  $db->exec("CREATE TABLE IF NOT EXISTS collection_pastes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    collection_id INTEGER NOT NULL,
    paste_id INTEGER NOT NULL,
    added_at INTEGER DEFAULT (strftime('%s', 'now')),
    FOREIGN KEY(collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    FOREIGN KEY(paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
    UNIQUE(collection_id, paste_id)
  )");

  // Add comments table
  $db->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    paste_id INTEGER,
    user_id TEXT,
    content TEXT,
    created_at INTEGER,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(paste_id) REFERENCES pastes(id)
  )");

  // Add columns to pastes table if they don't exist
  $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);

  if (!in_array('tags', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN tags TEXT DEFAULT ''");
  }
  if (!in_array('views', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN views INTEGER DEFAULT 0");
  }
  if (!in_array('user_id', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN user_id TEXT");
  }

  $db->exec("CREATE TABLE IF NOT EXISTS pastes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    content TEXT,
    language TEXT,
    password TEXT,
    expire_time INTEGER,
    is_public BOOLEAN DEFAULT 1,
    tags TEXT DEFAULT '',
    views INTEGER DEFAULT 0,
    user_id TEXT,
    burn_after_read BOOLEAN DEFAULT 0,
    zero_knowledge INTEGER DEFAULT 0
  )");

  // Add table for tracking unique views
  $db->exec("CREATE TABLE IF NOT EXISTS paste_views (
    paste_id INTEGER,
    ip_address TEXT,
    created_at INTEGER,
    PRIMARY KEY (paste_id, ip_address),
    FOREIGN KEY(paste_id) REFERENCES pastes(id)
  )");

  // Add burn_after_read column if it doesn't exist
  $columns = $db->query("PRAGMA table_info(pastes)")->fetchAll(PDO::FETCH_COLUMN, 1);
  if (!in_array('burn_after_read', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN burn_after_read BOOLEAN DEFAULT 0");
  }
  if (!in_array('current_version', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN current_version INTEGER DEFAULT 1");
  }
  if (!in_array('last_modified', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN last_modified INTEGER");
  }
  if (!in_array('collection_id', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN collection_id INTEGER DEFAULT NULL");
  }
  if (!in_array('zero_knowledge', $columns)) {
    $db->exec("ALTER TABLE pastes ADD COLUMN zero_knowledge INTEGER DEFAULT 0");
  }


  // Start session for user management
  if (!isset($_SESSION)) {
    session_start();
  }

  // Handle follow/unfollow actions
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['follow', 'unfollow'])) {
    if (!$user_id) {
      header('Location: /?page=login');
      exit;
    }

    $target_user_id = $_POST['target_user_id'] ?? '';
    $username_param = $_GET['username'] ?? '';

    if ($target_user_id && $target_user_id !== $user_id) {
      try {
        $database = Database::getInstance();
        $database->beginTransaction();

        if ($_POST['action'] === 'follow') {
          // Check if already following
          $stmt = $db->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
          $stmt->execute([$user_id, $target_user_id]);

          if (!$stmt->fetch()) {
            // Add follow relationship
            $stmt = $db->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $target_user_id]);

            // Update counts
            $db->prepare("UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) WHERE id = ?")->execute([$user_id, $user_id]);
            $db->prepare("UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) WHERE id = ?")->execute([$target_user_id, $target_user_id]);

            $audit_logger->log('user_followed', 'social', $user_id, ['target_user_id' => $target_user_id]);
          }
        } elseif ($_POST['action'] === 'unfollow') {
          // Remove follow relationship
          $stmt = $db->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
          $stmt->execute([$user_id, $target_user_id]);

          // Update counts
          $db->prepare("UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE follower_id = ?) WHERE id = ?")->execute([$user_id, $user_id]);
          $db->prepare("UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE following_id = ?) WHERE id = ?")->execute([$target_user_id, $target_user_id]);

          $audit_logger->log('user_unfollowed', 'social', $user_id, ['target_user_id' => $target_user_id]);
        }

        $database->commit();

        // Redirect back to profile
        if ($username_param) {
          header('Location: /?page=profile&username=' . urlencode($username_param));
          exit;
        }
      } catch (Exception $e) {
        $database->rollback();
        error_log("Follow/unfollow error: " . $e->getMessage());
      }
    }
  }

  // Handle login
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
      $username = trim($_POST['username'] ?? '');
      $password = $_POST['password'] ?? '';

      if (empty($username) || empty($password)) {
        header('Location: /?page=login&error=1');
        exit;
      }

      // Check rate limit for login attempts (only for failed attempts tracking)
      $login_limit = $rate_limiter->checkLimit('login_attempts');
      if (!$login_limit['allowed']) {
        $audit_logger->logSecurityEvent('login_rate_limit_exceeded', [
          'username' => $username,
          'remaining_time' => $login_limit['reset_time'] - time()
        ], 'medium');
        ErrorHandler::show429($login_limit['reset_time']);
      }

      // Optimize user lookup with prepared statement
      $stmt = $db->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
      $stmt->execute([$username]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($user && password_verify($password, $user['password'])) {
        // Successful login - set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Log success asynchronously (don't block the login)
        $audit_logger->log('user_login_success', 'auth', $user['id'], ['username' => $user['username']]);

        // Redirect immediately
        header('Location: /');
        exit;
      } else {
        // Failed login - count the attempt and log
        $rate_limiter->hit('login_attempts');

        $audit_logger->logSecurityEvent('login_failed', [
          'username' => $username,
          'reason' => $user ? 'invalid_password' : 'user_not_found'
        ], 'medium');

        header('Location: /?page=login&error=1');
        exit;
      }
    } 
    // Handle registration
    else if ($_POST['action'] === 'register') {
      // Check if registration is enabled
      if (!SiteSettings::get('registration_enabled', 1)) {
        $audit_logger->logSecurityEvent('registration_attempted_when_disabled', [
          'username' => $_POST['username'] ?? 'unknown',
          'email' => $_POST['email'] ?? 'unknown'
        ], 'medium');
        header('Location: /?page=signup&error=registration_disabled');
        exit;
      }

      // Check rate limit for registration
      $reg_limit = $rate_limiter->checkLimit('registration');
      if (!$reg_limit['allowed']) {
        $audit_logger->logSecurityEvent('registration_rate_limit_exceeded', [
          'username' => $_POST['username'] ?? 'unknown'
        ], 'low');
        ErrorHandler::show429($reg_limit['reset_time']);
      }

      // Validate email domain if specified
      $email = trim($_POST['email'] ?? '');
      $allowed_domains = SiteSettings::get('allowed_email_domains', '*');

      if ($email && $allowed_domains !== '*') {
        $email_domain = strtolower(substr(strrchr($email, '@'), 1));
        $allowed_list = array_map('trim', array_map('strtolower', explode(',', $allowed_domains)));

        if (!in_array($email_domain, $allowed_list)) {
          $audit_logger->logSecurityEvent('registration_invalid_domain', [
            'username' => $_POST['username'] ?? 'unknown',
            'email_domain' => $email_domain,
            'allowed_domains' => $allowed_domains
          ], 'low');
          header('Location: /?page=signup&error=invalid_domain');
          exit;
        }
      }

      $stmt = $db->prepare("SELECT 1 FROM users WHERE username = ?");
      $stmt->execute([$_POST['username']]);
      if (!$stmt->fetch()) {
        // Check if email is already taken
        if ($email) {
          $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
          $stmt->execute([$email]);
          if ($stmt->fetch()) {
            $audit_logger->logSecurityEvent('registration_failed', [
              'username' => $_POST['username'],
              'reason' => 'email_exists'
            ], 'low');
            header('Location: /?page=signup&error=email_exists');
            exit;
          }
        }

        $stmt = $db->prepare("INSERT INTO users (id, username, password, email, created_at) VALUES (?, ?, ?, ?, ?)");
        $user_id = uniqid();
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt->execute([$user_id, $_POST['username'], $hashed_password, $email, time()]);

        // If email verification is required, don't log them in yet
        if (SiteSettings::get('email_verification_required', 0) && $email) {
          $audit_logger->log('user_registration_pending_verification', 'auth', $user_id, [
            'username' => $_POST['username'],
            'email' => $email
          ]);
          header('Location: /?page=signup&success=verification_required');
          exit;
        } else {
          $_SESSION['user_id'] = $user_id;
          $_SESSION['username'] = $_POST['username'];

          $audit_logger->log('user_registration', 'auth', $user_id, [
            'username' => $_POST['username']
          ]);

          header('Location: /');
          exit;
        }
      } else {
        $audit_logger->logSecurityEvent('registration_failed', [
          'username' => $_POST['username'],
          'reason' => 'username_exists'
        ], 'low');
        header('Location: /?page=signup&error=username_exists');
        exit;
      }
    }
  }

  // Get user info from session
  $user_id = $_SESSION['user_id'] ?? null;
  $username = $_SESSION['username'] ?? null;

  // Create a fake example paste for AI Summary showcase (only if it doesn't exist)
  $stmt = $db->prepare("SELECT id FROM pastes WHERE title = 'AI Summary Demo - Python Game Engine' LIMIT 1");
  $stmt->execute();
  $demo_paste = $stmt->fetch();

  if (!$demo_paste) {
    $demo_content = '#!/usr/bin/env python3
"""
Simple 2D Game Engine Demo
A demonstration of object-oriented programming in Python
"""

import pygame
import sys
import math
from typing import Tuple, List

class GameObject:
    """Base class for all game objects"""

    def __init__(self, x: float, y: float, width: int, height: int):
        self.x = x
        self.y = y
        self.width = width
        self.height = height
        self.velocity_x = 0.0
        self.velocity_y = 0.0
        self.active = True

    def update(self, delta_time: float) -> None:
        """Update object position and state"""
        if not self.active:
            return

        self.x += self.velocity_x * delta_time
        self.y += self.velocity_y * delta_time

    def render(self, screen: pygame.Surface) -> None:
        """Render the object to screen"""
        if self.active:
            rect = pygame.Rect(self.x, self.y, self.width, self.height)
            pygame.draw.rect(screen, (255, 255, 255), rect)

class Player(GameObject):
    """Player character class"""

    def __init__(self, x: float, y: float):
        super().__init__(x, y, 32, 32)
        self.speed = 200.0
        self.health = 100
        self.score = 0

    def handle_input(self, keys: pygame.key.ScancodeWrapper) -> None:
        """Handle player input"""
        self.velocity_x = 0
        self.velocity_y = 0

        if keys[pygame.K_LEFT] or keys[pygame.K_a]:
            self.velocity_x = -self.speed
        if keys[pygame.K_RIGHT] or keys[pygame.K_d]:
            self.velocity_x = self.speed
        if keys[pygame.K_UP] or keys[pygame.K_w]:
            self.velocity_y = -self.speed
        if keys[pygame.K_DOWN] or keys[pygame.K_s]:
            self.velocity_y = self.speed

    def render(self, screen: pygame.Surface) -> None:
        """Render player with distinct color"""
        if self.active:
            rect = pygame.Rect(self.x, self.y, self.width, self.height)
            pygame.draw.rect(screen, (0, 128, 255), rect)

class GameEngine:
    """Main game engine class"""

    def __init__(self, width: int = 800, height: int = 600):
        pygame.init()
        self.width = width
        self.height = height
        self.screen = pygame.display.set_mode((width, height))
        pygame.display.set_caption("2D Game Engine Demo")

        self.clock = pygame.time.Clock()
        self.running = True
        self.fps = 60

        # Game objects
        self.player = Player(width // 2, height // 2)
        self.game_objects: List[GameObject] = [self.player]

    def handle_events(self) -> None:
        """Process game events"""
        for event in pygame.event.get():
            if event.type == pygame.QUIT:
                self.running = False
            elif event.type == pygame.KEYDOWN:
                if event.key == pygame.K_ESCAPE:
                    self.running = False

    def update(self, delta_time: float) -> None:
        """Update all game objects"""
        keys = pygame.key.get_pressed()
        self.player.handle_input(keys)

        for obj in self.game_objects:
            obj.update(delta_time)

        # Keep player within screen bounds
        self.player.x = max(0, min(self.player.x, self.width - self.player.width))
        self.player.y = max(0, min(self.player.y, self.height - self.player.height))

    def render(self) -> None:
        """Render the game scene"""
        self.screen.fill((20, 20, 40))  # Dark blue background

        for obj in self.game_objects:
            obj.render(self.screen)

        # Draw UI
        font = pygame.font.Font(None, 36)
        score_text = font.render(f"Score: {self.player.score}", True, (255, 255, 255))
        self.screen.blit(score_text, (10, 10))

        pygame.display.flip()

    def run(self) -> None:
        """Main game loop"""
        while self.running:
            delta_time = self.clock.tick(self.fps) / 1000.0

            self.handle_events()
            self.update(delta_time)
            self.render()

        pygame.quit()
        sys.exit()

def main():
    """Entry point"""
    try:
        game = GameEngine(800, 600)
        game.run()
    except Exception as e:
        print(f"Game crashed: {e}")
        pygame.quit()
        sys.exit(1)

if __name__ == "__main__":
    main()';

    // Also create the demo user if it doesn't exist
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (id, username, created_at) VALUES (?, ?, ?)");
    $demo_time = time();
    $stmt->execute(['demo_user_123', 'GameDevDemoUser', $demo_time]);

    $stmt = $db->prepare("INSERT INTO pastes (title, content, language, is_public, user_id, created_at, views, tags, current_version, last_modified, expire_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $far_future = time() + (50 * 365 * 24 * 60 * 60); // 50 years from now - never expires
    $stmt->execute([
      'AI Summary Demo - Python Game Engine',
      $demo_content,
      'python',
      1, // public
      'demo_user_123', // fake user ID
      $demo_time,
      42, // fake view count
      'python, game-development, pygame, oop, demo',
      1,
      $demo_time,
      $far_future
    ]);

    $demo_paste_id = $db->lastInsertId();
    echo "<!-- Demo paste created with ID: $demo_paste_id - Access at: /?id=$demo_paste_id -->";
  } else {
    echo "<!-- Demo paste exists with ID: {$demo_paste['id']} - Access at: /?id={$demo_paste['id']} -->";
  }

  // Handle paste creation
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle fork creation
    if (isset($_POST['action']) && $_POST['action'] === 'fork_paste') {
      if (!$user_id) {
        header('Location: /?page=login');
        exit;
      }

      $original_paste_id = $_POST['paste_id'] ?? $_POST['original_paste_id'] ?? '';
      $is_fork = !empty($original_paste_id) || isset($_POST['is_fork']);

      if ($original_paste_id) {
        // Check rate limit for fork creation (treat as paste creation)
        $fork_limit = $rate_limiter->checkLimit('paste_creation', $user_id ?: $rate_limiter->getClientIP());
        if (!$fork_limit['allowed']) {
          $audit_logger->logSecurityEvent('fork_creation_rate_limit_exceeded', [
            'user_id' => $user_id,
            'original_paste_id' => $original_paste_id,
            'remaining_time' => $fork_limit['reset_time'] - time()
          ], 'low');
          ErrorHandler::show429($fork_limit['reset_time']);
        }

        // Get original paste
        $stmt = $db->prepare("SELECT * FROM pastes WHERE id = ?");
        $stmt->execute([$original_paste_id]);
        $original_paste = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($original_paste) {
          // Check if user already forked this paste
          $stmt = $db->prepare("SELECT 1 FROM paste_forks WHERE original_paste_id = ? AND forked_by_user_id = ?");
          $stmt->execute([$original_paste_id, $user_id]);

          if ($stmt->fetch()) {
            // User already forked this paste
            header('Location: ?id=' . $original_paste_id . '&error=already_forked');
            exit;
          }

          // Check if user is trying to fork their own paste
          if ($original_paste['user_id'] === $user_id) {
            header('Location: ?id=' . $original_paste_id . '&error=own_paste');
            exit;
          }

          // Use transaction for fork creation
          $database = Database::getInstance();
          $database->beginTransaction();

          try {
            // Create new paste (fork)
            $stmt = $db->prepare("INSERT INTO pastes (title, content, language, password, expire_time, is_public, tags, user_id, created_at, burn_after_read, current_version, last_modified, original_paste_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            // Remove password protection and burn_after_read from forks
            $fork_title = 'Fork of ' . $original_paste['title'];
            $current_time = time();

            $stmt->execute([
              $fork_title,
              $original_paste['content'],
              $original_paste['language'],
              null, // Remove password protection
              $original_paste['expire_time'],
              $original_paste['is_public'],
              $original_paste['tags'],
              $user_id,
              $current_time,
              0, // Remove burn_after_read
              1,
              $current_time,
              $original_paste_id
            ]);

            $forked_paste_id = $db->lastInsertId();

            // Record the fork relationship
            $stmt = $db->prepare("INSERT INTO paste_forks (original_paste_id, forked_paste_id, forked_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$original_paste_id, $forked_paste_id, $user_id]);

            // Update fork count on original paste
            $stmt = $db->prepare("UPDATE pastes SET fork_count = fork_count + 1 WHERE id = ?");
            $stmt->execute([$original_paste_id]);

            $audit_logger->log('paste_forked', 'paste', $forked_paste_id, [
              'original_paste_id' => $original_paste_id,
              'fork_title' => $fork_title
            ]);

            $database->commit();

            // Redirect to the new fork
            header('Location: ?id=' . $forked_paste_id . '&forked=1');
            exit;

          } catch (Exception $e) {
            $database->rollback();
            error_log("Error creating fork: " . $e->getMessage());
            header('Location: ?id=' . $original_paste_id . '&error=fork_failed');
            exit;
          }
        } else {
          header('Location: /?error=paste_not_found');
          exit;
        }
      } else {
        header('Location: /?error=invalid_request');
        exit;
      }
    }

    // Load site settings for validation
    // Handle template creation
    if (isset($_POST['action']) && $_POST['action'] === 'save_template') {
      if ($user_id) {
        $stmt = $db->prepare("INSERT INTO paste_templates (name, description, content, language, category, created_by, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['template_name'],
          $_POST['template_description'] ?? '',
          $_POST['template_content'],
          $_POST['template_language'],
          $_POST['template_category'] ?? 'general',
          $user_id,
          isset($_POST['is_public']) ? 1 : 0
        ]);
        header('Location: ?page=templates&created=1');
        exit;
      }
    }

    // Handle template deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_template') {
      if ($user_id) {
        $stmt = $db->prepare("DELETE FROM paste_templates WHERE id = ? AND created_by = ?");
        $stmt->execute([$_POST['template_id'], $user_id]);
        header('Location: ?page=templates&deleted=1');
        exit;
      }
    }

    // Handle collection creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_collection') {
      if ($user_id) {
        $stmt = $db->prepare("INSERT INTO collections (name, description, user_id, is_public) VALUES (?, ?, ?, ?)");
        $stmt->execute([
          $_POST['collection_name'],
          $_POST['collection_description'] ?? '',
          $user_id,
          isset($_POST['is_public']) ? 1 : 0
        ]);
        header('Location: ?page=collections');
        exit;
      }
    }

    // Handle collection editing
    if (isset($_POST['action']) && $_POST['action'] === 'edit_collection') {
      if ($user_id) {
        $stmt = $db->prepare("UPDATE collections SET name = ?, description = ?, is_public = ?, updated_at = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([
          $_POST['collection_name'],
          $_POST['collection_description'] ?? '',
          isset($_POST['is_public']) ? 1 : 0,
          time(),
          $_POST['collection_id'],
          $user_id
        ]);
        header('Location: ?page=collection&collection_id=' . $_POST['collection_id']);
        exit;
      }
    }

    // Handle collection deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_collection') {
      if ($user_id) {
        $stmt = $db->prepare("DELETE FROM collections WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['collection_id'], $user_id]);
        header('Location: ?page=collections');
        exit;
      }
    }

    // Handle adding paste to collection
    if (isset($_POST['action']) && $_POST['action'] === 'add_to_collection') {
      if ($user_id) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO collection_pastes (collection_id, paste_id) VALUES (?, ?)");
        $stmt->execute([$_POST['collection_id'], $_POST['paste_id']]);
        header('Location: ?id=' . $_POST['paste_id']);
        exit;
      }
    }

    // Handle removing paste from collection
    if (isset($_POST['action']) && $_POST['action'] === 'remove_from_collection') {
      if ($user_id) {
        $stmt = $db->prepare("DELETE FROM collection_pastes WHERE collection_id = ? AND paste_id = ?");
        $stmt->execute([$_POST['collection_id'], $_POST['paste_id']]);
        header('Location: ?id=' . $_POST['paste_id']);
        exit;
      }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
      // Check rate limit for comment creation
      $comment_limit = $rate_limiter->checkLimit('comment_creation', $user_id ?: $rate_limiter->getClientIP());
      if (!$comment_limit['allowed']) {
        $audit_logger->logSecurityEvent('comment_creation_rate_limit_exceeded', [
          'user_id' => $user_id,
          'paste_id' => $_POST['paste_id'],
          'remaining_time' => $comment_limit['reset_time'] - time()
        ], 'low');
        ErrorHandler::show429($comment_limit['reset_time']);
      }

      $stmt = $db->prepare("INSERT INTO comments (paste_id, user_id, content, created_at) VALUES (?, ?, ?, ?)");
      $stmt->execute([$_POST['paste_id'], $user_id ?: null, $_POST['comment'], time()]);

      $comment_id = $db->lastInsertId();

      $audit_logger->log('comment_created', 'comment', $comment_id, [
        'paste_id' => $_POST['paste_id'],
        'content_length' => strlen($_POST['comment'])
      ]);

      // Create notification for paste owner
      if ($user_id) {
        $stmt = $db->prepare("SELECT user_id FROM pastes WHERE id = ?");
        $stmt->execute([$_POST['paste_id']]);
        $paste_owner = $stmt->fetch();

        if ($paste_owner && $paste_owner['user_id'] && $paste_owner['user_id'] !== $user_id) {
          $stmt = $db->prepare("INSERT INTO comment_notifications (user_id, paste_id, comment_id, type, message) VALUES (?, ?, ?, 'comment', ?)");
          $stmt->execute([
            $paste_owner['user_id'],
            $_POST['paste_id'],
            $comment_id,
            'New comment on your paste'
          ]);
        }
      }

      header('Location: ?id=' . $_POST['paste_id']);
      exit;
    }

    // Handle comment replies
    if (isset($_POST['action']) && $_POST['action'] === 'add_reply') {
      $stmt = $db->prepare("INSERT INTO comment_replies (parent_comment_id, paste_id, user_id, content, created_at) VALUES (?, ?, ?, ?, ?)");
      $stmt->execute([$_POST['parent_comment_id'], $_POST['paste_id'], $user_id ?: null, $_POST['reply_content'], time()]);

      // Update reply count
      $stmt = $db->prepare("UPDATE comments SET reply_count = (SELECT COUNT(*) FROM comment_replies WHERE parent_comment_id = ?) WHERE id = ?");
      $stmt->execute([$_POST['parent_comment_id'], $_POST['parent_comment_id']]);

      // Create notification for comment author
      if ($user_id) {
        $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$_POST['parent_comment_id']]);
        $comment_author = $stmt->fetch();

        if ($comment_author && $comment_author['user_id'] && $comment_author['user_id'] !== $user_id) {
          $stmt = $db->prepare("INSERT INTO comment_notifications (user_id, paste_id, reply_id, type, message) VALUES (?, ?, ?, 'reply', ?)");
          $stmt->execute([
            $comment_author['user_id'],
            $_POST['paste_id'],
            $db->lastInsertId(),
            'New reply to your comment'
          ]);
        }
      }

      header('Location: ?id=' . $_POST['paste_id'] . '#comment-' . $_POST['parent_comment_id']);
      exit;
    }

    // Handle comment reports
    if (isset($_POST['action']) && $_POST['action'] === 'report_comment') {
      $stmt = $db->prepare("INSERT INTO comment_reports (comment_id, reply_id, reporter_user_id, reason, description, created_at) VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([
        $_POST['comment_id'] ?: null,
        $_POST['reply_id'] ?: null,
        $user_id,
        $_POST['reason'],
        $_POST['description'] ?: null,
        time()
      ]);

      // Flag the comment/reply
      if ($_POST['comment_id']) {
        $stmt = $db->prepare("UPDATE comments SET is_flagged = 1 WHERE id = ?");
        $stmt->execute([$_POST['comment_id']]);
      }

      header('Location: ?id=' . $_POST['paste_id'] . '&reported=1');
      exit;
    }

    // Handle discussion thread creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_discussion_thread') {
      if (!$user_id) {
        header('Location: ?page=login');
        exit;
      }

      $paste_id = $_POST['paste_id'];
      $title = trim($_POST['thread_title']);
      $category = $_POST['thread_category'];
      $content = trim($_POST['first_post_content']);

      if (!empty($title) && !empty($content) && in_array($category, ['Q&A', 'Tip', 'Idea', 'Bug', 'General'])) {
        try {
          $database = Database::getInstance();
          $database->beginTransaction();

          // Create thread
          $stmt = $db->prepare("INSERT INTO paste_discussion_threads (paste_id, user_id, title, category) VALUES (?, ?, ?, ?)");
          $stmt->execute([$paste_id, $user_id, $title, $category]);
          $thread_id = $db->lastInsertId();

          // Create first post
          $stmt = $db->prepare("INSERT INTO paste_discussion_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
          $stmt->execute([$thread_id, $user_id, $content]);

          $database->commit();

          $audit_logger->log('discussion_thread_created', 'discussion', $thread_id, [
            'paste_id' => $paste_id,
            'title' => $title,
            'category' => $category
          ]);

          header('Location: ?id=' . $paste_id . '&thread_created=1');
          exit;
        } catch (Exception $e) {
          $database->rollback();
          error_log("Error creating discussion thread: " . $e->getMessage());
          header('Location: ?id=' . $paste_id . '&error=thread_creation_failed');
          exit;
        }
      }
    }

    // Handle discussion post creation
    if (isset($_POST['action']) && $_POST['action'] === 'add_discussion_post') {
      if (!$user_id) {
        header('Location: ?page=login');
        exit;
      }

      $thread_id = $_POST['thread_id'];
      $content = trim($_POST['post_content']);
      $paste_id = $_POST['paste_id'];

      if (!empty($content)) {
        $stmt = $db->prepare("INSERT INTO paste_discussion_posts (thread_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$thread_id, $user_id, $content]);

        $post_id = $db->lastInsertId();

        $audit_logger->log('discussion_post_created', 'discussion', $post_id, [
          'thread_id' => $thread_id,
          'paste_id' => $paste_id
        ]);
      }

      header('Location: ?id=' . $paste_id . '&view_thread=' . $thread_id);
      exit;
    }

    // Handle discussion post deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_discussion_post') {
      if (!$user_id) {
        header('Location: ?page=login');
        exit;
      }

      $post_id = $_POST['post_id'];
      $paste_id = $_POST['paste_id'];
      $thread_id = $_POST['thread_id'];

      // Check if user owns the post or is admin
      $stmt = $db->prepare("SELECT user_id FROM paste_discussion_posts WHERE id = ?");
      $stmt->execute([$post_id]);
      $post = $stmt->fetch();

      if ($post && ($post['user_id'] === $user_id)) {
        $stmt = $db->prepare("UPDATE paste_discussion_posts SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$post_id]);

        $audit_logger->log('discussion_post_deleted', 'discussion', $post_id, [
          'thread_id' => $thread_id,
          'paste_id' => $paste_id
        ]);
      }

      header('Location: ?id=' . $paste_id . '&view_thread=' . $thread_id);
      exit;
    }

    // Handle comment deletion (moderation)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_comment' && $user_id) {
      $comment_id = $_POST['comment_id'];
      $reply_id = $_POST['reply_id'] ?? null;

      if ($reply_id) {
        // Check if user owns the reply or is admin
        $stmt = $db->prepare("SELECT user_id FROM comment_replies WHERE id = ?");
        $stmt->execute([$reply_id]);
        $reply = $stmt->fetch();

        if ($reply && ($reply['user_id'] === $user_id)) {
          $stmt = $db->prepare("UPDATE comment_replies SET is_deleted = 1 WHERE id = ?");
          $stmt->execute([$reply_id]);

          // Update reply count
          $stmt = $db->prepare("UPDATE comments SET reply_count = (SELECT COUNT(*) FROM comment_replies WHERE parent_comment_id = ? AND is_deleted = 0) WHERE id = ?");
          $stmt->execute([$_POST['parent_comment_id'], $_POST['parent_comment_id']]);
        }
      } else {
        // Check if user owns the comment or is admin
        $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();

        if ($comment && ($comment['user_id'] === $user_id)) {
          $stmt = $db->prepare("UPDATE comments SET is_deleted = 1 WHERE id = ?");
          $stmt->execute([$comment_id]);
        }
      }

      header('Location: ?id=' . $_POST['paste_id']);
      exit;
    }
    if (isset($_POST['content'])) {
      // Get site settings for validation
      $max_paste_size = SiteSettings::get('max_paste_size');
      $daily_limit_free = SiteSettings::get('daily_paste_limit_free');
      $daily_limit_premium = SiteSettings::get('daily_paste_limit_premium');

      // Validate paste size if limit is set
      if ($max_paste_size && $max_paste_size > 0) {
        $content_size = strlen($_POST['content']);
        if ($content_size > $max_paste_size) {
          $audit_logger->logSecurityEvent('paste_size_exceeded', [
            'user_id' => $user_id,
            'content_size' => $content_size,
            'max_allowed' => $max_paste_size
          ], 'low');

          header('Content-Type: application/json');
          echo json_encode([
            'error' => true,
            'message' => 'Paste content exceeds maximum allowed size of ' . number_format($max_paste_size) . ' bytes. Your paste is ' . number_format($content_size) . ' bytes.'
          ]);
          exit;
        }
      }

      // Check daily paste limit if limits are set
      if ($user_id && ($daily_limit_free > 0 || $daily_limit_premium > 0)) {
        // Check if user is premium (for now assume all users are free)
        $is_premium = false; // TODO: Add premium user detection
        $daily_limit = $is_premium ? $daily_limit_premium : $daily_limit_free;

        if ($daily_limit > 0) {
          // Count pastes created today
          $today_start = strtotime('today');
          $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND created_at >= ?");
          $stmt->execute([$user_id, $today_start]);
          $today_count = $stmt->fetchColumn();

          if ($today_count >= $daily_limit) {
            $audit_logger->logSecurityEvent('daily_paste_limit_exceeded', [
              'user_id' => $user_id,
              'daily_count' => $today_count,
              'daily_limit' => $daily_limit,
              'is_premium' => $is_premium
            ], 'low');

            header('Content-Type: application/json');
            echo json_encode([
              'error' => true,
              'message' => 'Daily paste limit reached. You can create ' . $daily_limit . ' pastes per day. ' . 
                          ($is_premium ? '' : 'Consider upgrading to premium for higher limits.')
            ]);
            exit;
          }
        }
      }

      // Check rate limit for paste creation
      $paste_limit = $rate_limiter->checkLimit('paste_creation', $user_id ?: $rate_limiter->getClientIP());
      if (!$paste_limit['allowed']) {
        $audit_logger->logSecurityEvent('paste_creation_rate_limit_exceeded', [
          'user_id' => $user_id,
          'remaining_time' => $paste_limit['reset_time'] - time()
        ], 'low');
        ErrorHandler::show429($paste_limit['reset_time']);
      }

      // Use transaction for paste creation
      $database = Database::getInstance();
      $database->beginTransaction();

      try {
        $stmt = $db->prepare(
          "INSERT INTO pastes (title, content, language, password, expire_time, is_public, tags, user_id, created_at, burn_after_read, zero_knowledge, current_version, last_modified, collection_id, original_paste_id, parent_paste_id)
           VALUES (:title, :content, :language, :password, :expire_time, :is_public, :tags, :user_id, :created_at, :burn_after_read, :zero_knowledge, 1, :created_at, :collection_id, :original_paste_id, :parent_paste_id)"
        );

      $expire_time = null;
      if ($_POST['expire'] !== 'never') {
        $expire_time = time() + (int)$_POST['expire'];
      } else {
        // Apply default expiry if set and no expiry was specified
        $default_expiry = SiteSettings::get('default_expiry');
        if ($default_expiry && $default_expiry > 0) {
          $expire_time = time() + $default_expiry;
        }
      }

      $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
      $tags = !empty($_POST['tags']) ? $_POST['tags'] : '';

      // Handle fork title prefix for anonymous users
      $title = $_POST['title'];
      if ($is_fork && !empty($original_paste_id) && !$user_id) {
        // For anonymous forks, add "Fork of" prefix if not already present
        if (strpos($title, 'Fork of') !== 0) {
          $title = 'Fork of ' . $title;
        }
      }

      $stmt->execute([
        ':title' => $title,
        ':content' => $_POST['content'],
        ':language' => $_POST['language'],
        ':password' => $password,
        ':expire_time' => $expire_time,
        ':is_public' => isset($_POST['is_public']) ? 1 : 0,
        ':tags' => $tags,
        ':user_id' => $user_id,
        ':created_at' => time(),
        ':burn_after_read' => isset($_POST['burn_after_read']) ? 1 : 0,
        ':zero_knowledge' => isset($_POST['zero_knowledge']) ? 1 : 0,
        ':collection_id' => !empty($_POST['collection_id']) ? $_POST['collection_id'] : null,
        ':original_paste_id' => !empty($original_paste_id) ? $original_paste_id : null,
        ':parent_paste_id' => !empty($_POST['parent_paste_id']) ? $_POST['parent_paste_id'] : null
      ]);

      $paste_id = $db->lastInsertId();

        // Handle fork relationship tracking
        if (!empty($original_paste_id)) {
          if ($user_id) {
            // For logged-in users: create fork relationship record
            $stmt = $db->prepare("INSERT INTO paste_forks (original_paste_id, forked_paste_id, forked_by_user_id) VALUES (?, ?, ?)");
            $stmt->execute([$original_paste_id, $paste_id, $user_id]);
          }

          // Update fork count on original paste for both logged-in and anonymous users
          $stmt = $db->prepare("UPDATE pastes SET fork_count = fork_count + 1 WHERE id = ?");
          $stmt->execute([$original_paste_id]);
        }

        $audit_logger->log('paste_created', 'paste', $paste_id, [
          'title' => $title,
          'language' => $_POST['language'],
          'is_public' => isset($_POST['is_public']) ? 1 : 0,
          'has_password' => !empty($password),
          'burn_after_read' => isset($_POST['burn_after_read']) ? 1 : 0,
          'is_fork' => !empty($original_paste_id),
          'original_paste_id' => $original_paste_id
        ]);

        // Clear related pastes cache for similar pastes
        require_once 'related_pastes_helper.php';
        $related_helper = new RelatedPastesHelper($db);
        $related_helper->clearCache($paste_id);

        $database->commit();

        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
          header('Content-Type: application/json');
          echo json_encode(['success' => true, 'paste_id' => $paste_id]);
          exit;
        }

        header('Location: ?id=' . $paste_id);
        exit;
      } catch (Exception $e) {
        $database->rollback();
        error_log("Error creating paste: " . $e->getMessage());

        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
          header('Content-Type: application/json');
          echo json_encode(['error' => true, 'message' => 'Database error occurred while creating paste.']);
          exit;
        }

        throw $e;
      }
    }
  }

  // Delete paste
  if (isset($_POST['delete_paste']) && $user_id) {
    $paste_id = $_POST['paste_id'];
    $stmt = $db->prepare("DELETE FROM pastes WHERE id = ? AND user_id = ?");
    $stmt->execute([$paste_id, $user_id]);
    header('Location: /');
    exit;
  }

  // Edit paste
  if (isset($_POST['edit_paste']) && $user_id) {
    $stmt = $db->prepare(
      "UPDATE pastes SET 
       title = :title,
       content = :content,
       language = :language,
       password = :password,
       expire_time = :expire_time,
       is_public = :is_public,
       tags = :tags,
       zero_knowledge = :zero_knowledge
       WHERE id = :id AND user_id = :user_id"
    );

    $expire_time = null;
    if ($_POST['expire'] !== 'never') {
      $expire_time = time() + (int)$_POST['expire'];
    }

    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $tags = !empty($_POST['tags']) ? $_POST['tags'] : '';

    $stmt->execute([
      ':title' => $_POST['title'],
      ':content' => $_POST['content'],
      ':language' => $_POST['language'],
      ':password' => $password,
      ':expire_time' => $expire_time,
      ':is_public' => isset($_POST['is_public']) ? 1 : 0,
      ':tags' => $tags,
      ':zero_knowledge' => isset($_POST['zero_knowledge']) ? 1 : 0,
      ':id' => $_POST['id'],
      ':user_id' => $user_id
    ]);

    // Clear related pastes cache since this paste was modified
    require_once 'related_pastes_helper.php';
    $related_helper = new RelatedPastesHelper($db);
    $related_helper->clearCache($_POST['id']);

    header('Location: ?id=' . $_POST['id']);
    exit;
  }

  // Toggle favorite
  if (isset($_POST['toggle_favorite']) && $user_id) {
    $paste_id = $_POST['paste_id'];
    $stmt = $db->prepare("SELECT 1 FROM user_pastes WHERE user_id = ? AND paste_id = ?");
    $stmt->execute([$user_id, $paste_id]);

    if ($stmt->fetch()) {
      $stmt = $db->prepare("DELETE FROM user_pastes WHERE user_id = ? AND paste_id = ?");
    } else {
      $stmt = $db->prepare("INSERT INTO user_pastes (user_id, paste_id, is_favorite, created_at) VALUES (?, ?, 1, ?)");
    }
    $stmt->execute([$user_id, $paste_id, time()]);
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
  }

  // View paste
  $paste = null;
  if (isset($_GET['id'])) {
    // Check if password was submitted
    if (isset($_POST['paste_password'])) {
      $stmt = $db->prepare("SELECT password FROM pastes WHERE id = ?");
      $stmt->execute([$_GET['id']]);
      $stored_hash = $stmt->fetch(PDO::FETCH_COLUMN);

      if (!password_verify($_POST['paste_password'], $stored_hash)) {
        $password_error = "Incorrect password";
      } else {
        // Store paste ID in session when password is correct
        if (!isset($_SESSION['verified_pastes'])) {
          $_SESSION['verified_pastes'] = [];
        }
        $_SESSION['verified_pastes'][] = $_GET['id'];
      }
    }

    if (isset($_GET['raw']) || isset($_GET['download'])) {
      // First check if paste is password protected 
      $stmt = $db->prepare("SELECT password, flags FROM pastes WHERE id = ?");
      $stmt->execute([$_GET['id']]);
      $protection = $stmt->fetch();

      // Check if paste is flagged above auto-blur threshold
      $auto_blur_threshold = SiteSettings::get('auto_blur_threshold', 3);
      if ($protection && $protection['flags'] >= $auto_blur_threshold) {
        header('HTTP/1.1 403 Forbidden');
        echo "This paste is under review due to multiple reports and cannot be accessed directly.";
        exit;
      }

      if ($protection && $protection['password']) {
        // Check if paste is verified in session
        if (!isset($_SESSION['verified_pastes']) || !in_array($_GET['id'], $_SESSION['verified_pastes'])) {
          header('HTTP/1.1 403 Forbidden');
          echo "This paste is password protected";
          exit;
        }
      }

      // If we get here, paste is either not protected or password was correct
      $stmt = $db->prepare("SELECT content, title FROM pastes WHERE id = ?");
      $stmt->execute([$_GET['id']]);
      $paste = $stmt->fetch();

      if (isset($_GET['download'])) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9]+/', '_', $paste['title']) . '.txt"');
      } else {
        header('Content-Type: text/plain');
      }
      echo $paste['content'];
      exit;
    }

    $stmt = $db->prepare("SELECT p.*, u.username, u.profile_image,
                         EXISTS(SELECT 1 FROM user_pastes WHERE user_id = ? AND paste_id = p.id) as is_favorite,
                         (SELECT COUNT(*) FROM user_pastes WHERE paste_id = p.id AND is_favorite = 1) as favorite_count,
                         COALESCE(p.flags, 0) as flag_count,
                         COALESCE(p.fork_count, 0) as fork_count,
                         p.original_paste_id,
                         p.parent_paste_id,
                         CASE WHEN p.original_paste_id IS NOT NULL THEN 1 ELSE 0 END as is_fork
                         FROM pastes p 
                         LEFT JOIN users u ON p.user_id = u.id 
                         WHERE p.id = ?");
    $stmt->execute([$user_id, $_GET['id']]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get auto-blur and auto-delete thresholds
    $auto_blur_threshold = SiteSettings::get('auto_blur_threshold', 3);
    $auto_delete_threshold = SiteSettings::get('auto_delete_threshold', 10);

    // Check if paste should be auto-deleted
    if ($paste && $paste['flag_count'] >= $auto_delete_threshold) {
      $paste = null; // Treat as if paste doesn't exist
    }

    if ($paste) {
      if ($paste['expire_time'] && time() > $paste['expire_time']) {
        $paste = null;
      } else if ($paste['burn_after_read']) {
        // Delete the paste after it's read
        $stmt = $db->prepare("DELETE FROM pastes WHERE id = ?");
        $stmt->execute([$_GET['id']]);
      } else {
        // Record unique view
        $stmt = $db->prepare("INSERT OR IGNORE INTO paste_views (paste_id, ip_address, created_at) VALUES (?, ?, ?)");
        if ($stmt->execute([$_GET['id'], $_SERVER['REMOTE_ADDR'], time()])) {
          // Only increment views if this was a new unique view
          if ($stmt->rowCount() > 0) {
            $db->prepare("UPDATE pastes SET views = (SELECT COUNT(DISTINCT ip_address) FROM paste_views WHERE paste_id = ?) WHERE id = ?")->execute([$_GET['id'], $_GET['id']]);
          }
        }
      }
    }
  }

  // Get user's pastes for dashboard
  $user_pastes = [];
  $favorite_pastes = [];
  if ($user_id) {
    $stmt = $db->prepare("SELECT * FROM pastes WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $user_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT p.* FROM pastes p JOIN user_pastes up ON p.id = up.paste_id WHERE up.user_id = ? AND up.is_favorite = 1");
    $stmt->execute([$user_id]);
    $favorite_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  // List public pastes with search and filtering
  $where = ["p.is_public = 1", "p.zero_knowledge = 0", "(p.expire_time IS NULL OR p.expire_time > " . time() . ")"];
  if (isset($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where[] = "(p.title LIKE ? OR p.content LIKE ? OR p.tags LIKE ?)";
  }
  if (isset($_GET['language'])) {
    $where[] = "p.language = ?";
  }

  $sql = "SELECT p.*, u.username FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE " . implode(" AND ", $where) . " ORDER BY p.created_at DESC LIMIT 5";
  $stmt = $db->prepare($sql);

  $params = [];
  if (isset($_GET['search'])) {
    $params = array_merge($params, [$search, $search, $search]);
  }
  if (isset($_GET['language'])) {
    $params[] = $_GET['language'];
  }

  $stmt->execute($params);
  $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);


  // Handle latest pastes request
  if (isset($_GET['action']) && $_GET['action'] === 'latest_pastes') {
    $stmt = $db->prepare("SELECT p.*, u.username FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE p.is_public = 1 AND p.zero_knowledge = 0 AND (p.expire_time IS NULL OR p.expire_time > ?) ORDER BY p.created_at DESC LIMIT 5");
    $stmt->execute([time()]);
    $latestPastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($latestPastes as $p) {
        echo '<a href="?id=' . $p['id'] . '" class="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700">';
        echo '<div class="flex items-start gap-3">';
        echo '<i class="fas fa-code text-blue-500 mt-1"></i>';
        echo '<div class="min-w-0">';
        echo '<div class="font-medium truncate">"' . htmlspecialchars($p['title']) . '" by ' . ($p['username'] ? '@'.htmlspecialchars($p['username']) : 'Anonymous') . '</div>';
        echo '<div class="text-sm text-gray-600 dark:text-gray-400">';
        echo $p['views'] . ' views · ' . human_time_diff($p['created_at']);
        if ($p['expire_time']) {
            echo '<div class="text-xs mt-1" data-expires="' . $p['expire_time'] . '">';
            echo '<i class="fas fa-clock"></i> <span class="countdown-timer">Calculating...</span>';
            echo '</div>';
        }
        echo '</div></div></div></a>';
    }
    exit;
  }


} catch (PDOException $ex) {
  echo $ex->getMessage();
  exit;
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html class="<?= $theme ?>">
<head>
  <title>PasteForge</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism.min.css" class="light-theme" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-okaidia.min.css" class="dark-theme" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.css" rel="stylesheet" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-core.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/autoloader/prism-autoloader.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/plugins/line-numbers/prism-line-numbers.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/brands.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script defer src="https://unpkg.com/@alpinejs/persist@3.x.x/dist/cdn.min.js"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    /* Code display styling */
    pre {
      max-width: 100%;
    }
    code {
      word-break: break-word;
    }

    /* Countdown styling */
    .countdown-timer {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-family: monospace;
      font-weight: bold;
    }

    .countdown-urgent {
      color: #ef4444;
      animation: pulse 1s infinite;
    }

    .countdown-warning {
      color: #f59e0b;
    }

    .countdown-normal {
      color: #6b7280;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.7; }
    }

    /* Discussion-specific styles */
    .discussion-thread:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .discussion-post {
      transition: all 0.2s ease-in-out;
    }

    .discussion-post:hover {
      background-color: rgba(147, 51, 234, 0.05);
    }

    .dark .discussion-post:hover {
      background-color: rgba(147, 51, 234, 0.1);
    }

  </style>
  <script>
    // Countdown timer initialization
    function initializeCountdownTimers() {
      document.querySelectorAll('[data-expires]').forEach(element => {
        const expiresAt = parseInt(element.getAttribute('data-expires'));
        const timerElement = element.querySelector('.countdown-timer');

        if (timerElement && expiresAt) {
          updateCountdown(timerElement, expiresAt);

          // Update every minute
          setInterval(() => {
            updateCountdown(timerElement, expiresAt);
          }, 60000);
        }
      });
    }

    function updateCountdown(element, expiresAt) {
      const now = Math.floor(Date.now() / 1000);
      const timeLeft = expiresAt - now;

      if (timeLeft <= 0) {
        element.textContent = 'Expired';
        element.className = 'countdown-timer countdown-urgent';
        return;
      }

      const days = Math.floor(timeLeft / 86400);
      const hours = Math.floor((timeLeft % 86400) / 3600);
      const minutes = Math.floor((timeLeft % 3600) / 60);

      let timeString = '';
      let className = 'countdown-timer ';

      if (days > 0) {
        timeString = `${days}d ${hours}h`;
        className += 'countdown-normal';
      } else if (hours > 0) {
        timeString = `${hours}h ${minutes}m`;
        className += timeLeft < 3600 ? 'countdown-warning' : 'countdown-normal';
      } else {
        timeString = `${minutes}m`;
        className += timeLeft < 600 ? 'countdown-urgent' : 'countdown-warning';
      }

      element.textContent = timeString;
      element.className = className;
    }

    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#0097FB'
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-out',
            'slide-in': 'slideIn 0.3s ease-out'
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0' },
              '100%': { opacity: '1' }
            },
            slideIn: {
              '0%': { transform: 'translateX(-100%)' },
              '100%': { transform: 'translateX(0)' }
            }
          }
        }
      }
    }
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');

      if (sidebarToggleBtn && sidebar) {
        const updateLayout = (isOpen) => {
          sidebar.style.transform = isOpen ? 'translateX(0)' : 'translateX(-100%)';
          mainContent.style.marginLeft = isOpen ? '16rem' : '0';
          const icon = sidebarToggleBtn.querySelector('i');
          icon.className = isOpen ? 'fas fa-chevron-left' : 'fas fa-chevron-right';
        };

        // Initialize sidebar state
        let isOpen = window.innerWidth >= 1024; // lg breakpoint
        updateLayout(isOpen);

        sidebarToggleBtn.addEventListener('click', () => {
          isOpen = !isOpen;
          updateLayout(isOpen);
        });

        // Handle window resize
        window.addEventListener('resize', () => {
          if (window.innerWidth >= 1024) {
            isOpen = true;
            updateLayout(true);
          } else {
            isOpen = false;
            updateLayout(false);
          }
        });
      }

      // Initialize countdown timers
      initializeCountdownTimers();

      // Form animations
      gsap.from('.paste-form-element', {
        y: 20,
        opacity: 0,
        duration: 0.5,
        ease: 'power2.out',
        stagger: 0.1
      });
    });
  </script>
  <script>

    function toggleTheme() {
      const html = document.documentElement;
      const newTheme = html.classList.contains('dark') ? 'light' : 'dark';
      html.classList.remove('dark', 'light');
      html.classList.add(newTheme);
      document.cookie = `theme=${newTheme};path=/`;
    }

    // Live updates with improved error handling
    function fetchLatestPastes() {
      const sidebarContainer = document.getElementById('latest-pastes');
      if (!sidebarContainer) {
        // Sidebar container doesn't exist on this page, skip update
        return;
      }

      // Check if we're viewing a specific paste - don't update sidebar during paste viewing
      const pasteId = new URLSearchParams(window.location.search).get('id');
      if (pasteId) {
        // Skip updates when viewing a paste to prevent interference
        return;
      }

      // Check if we're on a page that should have sidebar updates
      const currentPage = new URLSearchParams(window.location.search).get('page');
      if (currentPage && ['login', 'signup', 'profile', 'settings'].includes(currentPage)) {
        // Skip updates on pages that don't need live sidebar
        return;
      }

      fetch('?action=latest_pastes')
        .then(response => {
          if (!response.ok) {
            // Don't throw error for non-critical sidebar updates
            return null;
          }
          return response.text();
        })
        .then(html => {
          if (html && html.trim() && sidebarContainer) {
            sidebarContainer.innerHTML = html;

            // Reinitialize countdown timers for the new content
            if (typeof initializeCountdownTimers === 'function') {
              setTimeout(initializeCountdownTimers, 100);
            }
          }
        })
        .catch(error => {
          // Silently handle errors for non-critical sidebar updates
          // Only log in development/debug mode
          if (window.location.hostname === 'localhost' || window.location.hostname.includes('replit')) {
            console.debug('Sidebar update skipped:', error.message);
          }
        });
    }

    // Only start interval updates if sidebar exists and page is appropriate
    document.addEventListener('DOMContentLoaded', function() {
      const sidebarContainer = document.getElementById('latest-pastes');
      const currentPage = new URLSearchParams(window.location.search).get('page');
      const pasteId = new URLSearchParams(window.location.search).get('id');

      // Don't start live updates when viewing a paste or on specific pages
      if (sidebarContainer && !pasteId && (!currentPage || !['login', 'signup', 'settings'].includes(currentPage))) {
        // Initial update
        fetchLatestPastes();

        // Update every 45 seconds (reduced frequency to be less aggressive)
        setInterval(fetchLatestPastes, 45000);
      }
    });
  </script>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
  <!-- Modern Navigation Bar -->
  <nav class="bg-blue-600 dark:bg-blue-800 text-white shadow-lg fixed w-full z-10">
    <div class="max-w-7xl mx-auto px-4">
      <div class="flex justify-between h-16">
        <div class="flex items-center space-x-6">
          <a href="/" class="flex items-center space-x-3">
            <i class="fas fa-paste text-2xl"></i>
            <span class="text-xl font-bold">PasteForge</span>
          </a>
          <div class="flex space-x-4">
            <a href="/" class="hover:bg-blue-700 px-3 py-2 rounded">Home</a>
            <a href="?page=archive" class="hover:bg-blue-700 px-3 py-2 rounded">Archive</a>
            <?php if ($user_id): ?>
              <a href="?page=collections" class="hover:bg-blue-700 px-3 py-2 rounded">Collections</a>
            <?php else: ?>
              <a href="?page=about" class="hover:bg-blue-700 px-3 py-2 rounded">About</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <?php if ($user_id): ?>
            <!-- Notification Bell -->
            <a href="notifications.php" class="relative p-2 rounded hover:bg-blue-700 transition-colors">
              <i class="fas fa-bell text-lg"></i>
              <?php
              // Get unread notification count for navigation
              $stmt = $db->prepare("SELECT COUNT(*) FROM comment_notifications WHERE user_id = ? AND is_read = 0");
              $stmt->execute([$user_id]);
              $nav_unread_notifications = $stmt->fetchColumn();
              if ($nav_unread_notifications > 0):
              ?>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center min-w-[20px] animate-pulse">
                  <?= $nav_unread_notifications > 99 ? '99+' : $nav_unread_notifications ?>
                </span>
              <?php endif; ?>
            </a>
          <?php endif; ?>
          <button onclick="toggleTheme()" class="p-2 rounded hover:bg-blue-700">
            <i class="fas fa-moon"></i>
          </button>
          <?php if (!$user_id): ?>
            <div class="flex items-center space-x-2">
              <a href="?page=login" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
              </a>
              <a href="?page=signup" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                <i class="fas fa-user-plus"></i>
                <span>Sign Up</span>
              </a>
            </div>
          <?php else: ?>
            <div class="relative" x-data="{ open: false }">
              <button @click="open = !open" class="flex items-center space-x-2 hover:bg-blue-700 px-3 py-2 rounded">
                <?php
                $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_avatar = $stmt->fetch()['profile_image'];
              ?>
              <img src="<?= $user_avatar ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=32' ?>" 
                   class="w-8 h-8 rounded-full" alt="Profile">
                <span><?= htmlspecialchars($username) ?></span>
                <i class="fas fa-chevron-down ml-1"></i>
              </button>
              <div x-show="open" 
                   @click.away="open = false"
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="transform opacity-0 scale-95"
                   x-transition:enter-end="transform opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-75"
                   x-transition:leave-start="transform opacity-100 scale-100"
                   x-transition:leave-end="transform opacity-0 scale-95"
                   class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                <div class="py-1">
                  <!-- Account Group -->
                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Account</div>
                  <a href="?page=edit-profile" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user-edit mr-2"></i> Edit Profile
                  </a>
                  <a href="?page=profile&username=<?= urlencode($username) ?>" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-user mr-2"></i> View Profile
                  </a>
                  <a href="?page=account" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-crown mr-2"></i> Account
                  </a>
                  <a href="?page=settings" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-cog mr-2"></i> Edit Settings
                  </a>

                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                  <!-- Messages Group -->
                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Messages</div>
                  <a href="threaded_messages.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-envelope mr-2"></i> My Messages
                  </a>

                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                  <!-- Tools Group -->
                  <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tools</div>
                  <a href="project_manager.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-folder-tree mr-2"></i> Projects
                  </a>
                  <a href="following.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-users mr-2"></i> Following
                  </a>
                  <a href="?page=import-export" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-exchange-alt mr-2"></i> Import/Export
                  </a>

                  <hr class="my-1 border-gray-200 dark:border-gray-700">

                  <!-- Logout -->
                  <a href="?logout=1" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content with Sidebar -->
  <div class="min-h-screen pt-16 flex relative">
    <!-- Modern Sidebar -->
    <div id="sidebar" class="fixed left-0 top-16 h-[calc(100vh-4rem)] bg-white dark:bg-gray-800 w-64 transform transition-transform duration-300 ease-in-out shadow-lg z-50">
      <!-- Sidebar Toggle Button (positioned absolutely) -->
      <button id="sidebarToggleBtn" class="absolute -right-8 top-1/2 transform -translate-y-1/2 bg-white dark:bg-gray-800 p-2 rounded-r shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none z-50">
        <i class="fas fa-chevron-right"></i>
      </button>
      <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h2 class="text-xl font-semibold flex items-center">
          <span class="mr-2">🔥</span> Recent Pastes
        </h2>
      </div>

      <div id="latest-pastes" class="divide-y divide-gray-200 dark:divide-gray-700">
        <?php foreach ($pastes as $p): ?>
          <a href="?id=<?= $p['id'] ?>" class="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="flex items-start gap-3">
              <i class="fas fa-code text-blue-500 mt-1 flex-shrink-0"></i>
              <div class="min-w-0 flex-1">
                <div class="font-medium truncate text-gray-900 dark:text-white">
                  "<?= htmlspecialchars($p['title']) ?>" by <?= $p['username'] ? '@'.htmlspecialchars($p['username']) : 'Anonymous' ?>
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  <?= $p['views'] ?> views · <?= human_time_diff($p['created_at']) ?>
                  <?php if ($p['expire_time']): ?>
                    <div class="text-xs mt-1" data-expires="<?= $p['expire_time'] ?>">
                      <i class="fas fa-clock"></i> <span class="countdown-timer">Calculating...</span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 transition-all duration-300" id="mainContent">
      <div class="max-w-4xl mx-auto px-4 py-8">
        <?php if ($paste): ?>
          <?php if ($paste['password'] && !isset($password_error) && !isset($_POST['paste_password'])): ?>
            <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
              <h3 class="text-xl font-semibold mb-4">Password Protected Paste</h3>
              <?php if (isset($password_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                  <?= $password_error ?>
                </div>
              <?php endif; ?>
              <form method="POST" class="space-y-4">
                <div>
                  <label class="block text-sm font-medium mb-2">Enter Password:</label>
                  <input type="password" name="paste_password" required 
                         class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                  View Paste
                </button>
              </form>
            </div>
          <?php else: ?>
            <!-- Modern Paste View with Tabs -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
              <!-- Paste Header -->
              <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <?php 
                $is_flagged_for_blur = $paste['flag_count'] >= $auto_blur_threshold;
                ?>

                <?php if ($is_flagged_for_blur): ?>
                  <div class="mb-4 p-4 bg-yellow-100 dark:bg-yellow-900 border-l-4 border-yellow-500 rounded">
                    <div class="flex items-center">
                      <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mr-2"></i>
                      <div>
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200">Content Under Review</h4>
                        <p class="text-yellow-700 dark:text-yellow-300 text-sm">
                          This paste has been flagged by the community and is currently under review by our moderation team. 
                          Content has been hidden for safety.
                        </p>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Parent Paste indicator (for paste chains) -->
                <?php if (!empty($paste['parent_paste_id'])): ?>
                  <?php
                  $parent_id = $paste['parent_paste_id'];
                  $stmt = $db->prepare("SELECT p.title, u.username FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
                  $stmt->execute([$parent_id]);
                  $parent_paste = $stmt->fetch(PDO::FETCH_ASSOC);
                  ?>
                  <?php if ($parent_paste): ?>
                    <div class="mb-4 p-4 bg-blue-100 dark:bg-blue-900/30 border-l-4 border-blue-500 rounded-r shadow-sm">
                      <div class="flex items-center">
                        <i class="fas fa-link text-blue-600 dark:text-blue-400 mr-3 text-lg"></i>
                        <div class="flex-1">
                          <div class="text-blue-800 dark:text-blue-200 font-medium">
                            🔗 This paste is part of a chain, following 
                            <a href="?id=<?= $parent_id ?>" class="font-semibold hover:underline text-blue-700 dark:text-blue-300">
                              "<?= htmlspecialchars($parent_paste['title']) ?>"
                            </a>
                          </div>
                          <?php if ($parent_paste['username']): ?>
                            <div class="text-blue-700 dark:text-blue-300 text-sm mt-1">
                              Original by <a href="?page=profile&username=<?= urlencode($parent_paste['username']) ?>" class="font-medium hover:underline">
                                @<?= htmlspecialchars($parent_paste['username']) ?>
                              </a>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- Forked from indicator -->
                <?php if (!empty($paste['original_paste_id']) || !empty($paste['is_fork'])): ?>
                  <?php
                  $original_id = $paste['original_paste_id'];
                  if ($original_id) {
                    $stmt = $db->prepare("SELECT p.title, u.username FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
                    $stmt->execute([$original_id]);
                    $original_paste = $stmt->fetch(PDO::FETCH_ASSOC);
                  } else {
                    $original_paste = null;
                  }
                  ?>
                  <?php if ($original_paste): ?>
                    <div class="mb-4 p-4 bg-purple-100 dark:bg-purple-900/30 border-l-4 border-purple-500 rounded-r shadow-sm">
                      <div class="flex items-center">
                        <i class="fas fa-code-branch text-purple-600 dark:text-purple-400 mr-3 text-lg"></i>
                        <div class="flex-1">
                          <div class="text-purple-800 dark:text-purple-200 font-medium">
                            🍴 This is a fork of 
                            <a href="?id=<?= $original_id ?>" class="font-semibold hover:underline text-purple-700 dark:text-purple-300">
                              "<?= htmlspecialchars($original_paste['title']) ?>"
                            </a>
                          </div>
                          <?php if ($original_paste['username']): ?>
                            <div class="text-purple-700 dark:text-purple-300 text-sm mt-1">
                              Original by <a href="?page=profile&username=<?= urlencode($original_paste['username']) ?>" class="font-medium hover:underline">
                                @<?= htmlspecialchars($original_paste['username']) ?>
                              </a>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php elseif (!empty($paste['is_fork']) || !empty($paste['original_paste_id'])): ?>
                    <div class="mb-4 p-4 bg-purple-100 dark:bg-purple-900/30 border-l-4 border-purple-500 rounded-r shadow-sm">
                      <div class="flex items-center">
                        <i class="fas fa-code-branch text-purple-600 dark:text-purple-400 mr-3 text-lg"></i>
                        <div class="flex-1">
                          <div class="text-purple-800 dark:text-purple-200 font-medium">
                            🍴 This is a forked paste
                          </div>
                          <div class="text-purple-700 dark:text-purple-300 text-sm mt-1">
                            Original paste may no longer be available
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- Paste Title and Info -->
                <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                  <div class="flex-1">
                    <h1 class="text-3xl font-bold mb-2 flex items-center gap-2">
                      <?php if (!empty($paste['original_paste_id']) || !empty($paste['is_fork'])): ?>
                        <span class="inline-flex items-center px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 rounded text-sm font-medium">
                          <i class="fas fa-code-branch mr-1"></i>Fork
                        </span>
                      <?php endif; ?>
                      <?= htmlspecialchars($paste['title']) ?>
                    </h1>

                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400 mb-3">
                      <div class="flex items-center gap-2">
                        <img src="<?= $paste['username'] ? ($paste['profile_image'] ?? 'https://www.gravatar.com/avatar/default?s=20') : 'https://www.gravatar.com/avatar/anonymous?d=mp&s=20' ?>" 
                             class="w-5 h-5 rounded-full" alt="User avatar">
                        <span>
                          <?php if ($paste['username']): ?>
                            <a href="?page=profile&username=<?= urlencode($paste['username']) ?>" class="hover:text-blue-500">
                              @<?= htmlspecialchars($paste['username']) ?>
                            </a>
                          <?php else: ?>
                            Anonymous
                          <?php endif; ?>
                        </span>
                      </div>
                      <span>·</span>
                      <span><?= date('M j, Y g:i A', $paste['created_at']) ?></span>
                      <span>·</span>
                      <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                        <?= htmlspecialchars($paste['language']) ?>
                      </span>
                      <?php if ($paste['expire_time']): ?>
                        <span>·</span>
                        <span data-expires="<?= $paste['expire_time'] ?>" class="text-orange-500">
                          <i class="fas fa-clock"></i> 
                          <span class="countdown-timer">Calculating...</span>
                        </span>
                      <?php endif; ?>
                      <span>·</span>
                      <span class="flex items-center gap-1">
                        <?php if ($user_id): ?>
                          <button onclick="toggleLike(<?= $paste['id'] ?>)" 
                                  class="flex items-center gap-1 hover:text-red-500 transition-colors">
                            <i class="fas fa-heart <?= $paste['is_favorite'] ? 'text-red-500' : 'text-gray-400' ?>"></i>
                            <span id="like-count-<?= $paste['id'] ?>"><?= number_format($paste['favorite_count'] ?? 0) ?></span>
                          </button>
                        <?php else: ?>
                          <a href="?page=login" class="flex items-center gap-1 hover:text-red-500 transition-colors">
                            <i class="fas fa-heart text-gray-400"></i>
                            <span><?= number_format($paste['favorite_count'] ?? 0) ?></span>
                          </a>
                        <?php endif; ?>
                      </span>
                    </div>

                    <!-- Tags -->
                    <?php if (!empty($paste['tags'])): ?>
                      <div class="mb-3">
                        <?php foreach (explode(',', $paste['tags']) as $tag): ?>
                          <a href="?page=archive&search=<?= urlencode(trim($tag)) ?>" class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs px-2 py-1 rounded mr-2 mb-1 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            #<?= htmlspecialchars(trim($tag)) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Actions -->
                  <div class="flex flex-col items-end gap-2">
                    <?php
                    // Get comment count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE paste_id = ? AND is_deleted = 0");
                    $stmt->execute([$paste['id']]);
                    $comment_count = $stmt->fetchColumn();

                    // Get version count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM paste_versions WHERE paste_id = ?");
                    $stmt->execute([$paste['id']]);
                    $version_count = $stmt->fetchColumn() + 1; // +1 for current version

                    // Get fork count
                    $fork_count = $paste['fork_count'] ?? 0;

                    // Get related pastes count
                    require_once 'related_pastes_helper.php';
                    $related_helper = new RelatedPastesHelper($db);
                    $related_pastes = $related_helper->getRelatedPastes($paste['id'], 5);
                    $related_count = count($related_pastes);

                    // Get chain count (children)
                    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE parent_paste_id = ? AND is_public = 1 AND zero_knowledge = 0");
                    $stmt->execute([$paste['id']]);
                    $chain_count = $stmt->fetchColumn();
                    ?>

                    <!-- Action Buttons Row 1 -->
                    <div class="flex gap-1">
                      <?php if (!$is_flagged_for_blur): ?>
                        <button onclick="copyText(document.querySelector('pre code')?.textContent)" class="p-2 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" title="Copy code">
                          <i class="fas fa-copy text-sm"></i>
                        </button>
                        <a href="?id=<?= $paste['id'] ?>&raw=1" target="_blank" class="p-2 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 inline-flex transition-colors zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" title="View raw">
                          <i class="fas fa-code text-sm"></i>
                        </a>
                        <?php if ($paste['zero_knowledge']): ?>
                          <a href="#" onclick="downloadPaste('<?= preg_replace('/[^a-zA-Z0-9]+/', '_', $paste['title']) ?>'); return false;" class="p-2 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 inline-flex transition-colors zk-restricted hidden" title="Download">
                            <i class="fas fa-download text-sm"></i>
                          </a>
                        <?php else: ?>
                          <a href="?id=<?= $paste['id'] ?>&download=1" class="p-2 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 inline-flex transition-colors" title="Download">
                            <i class="fas fa-download text-sm"></i>
                          </a>
                        <?php endif; ?>
                        <button onclick="clonePaste()" class="p-2 rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" title="Clone">
                          <i class="fas fa-clone text-sm"></i>
                        </button>
                      <?php endif; ?>

                      <button onclick="window.location.href='/?parent_id=<?= $paste['id'] ?>'" class="p-2 rounded bg-green-500 text-white hover:bg-green-600 transition-colors zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" title="Add to Chain">
                        <i class="fas fa-link text-sm"></i>
                      </button>

                      <?php if (!$user_id || ($user_id && $paste['user_id'] !== $user_id)): ?>
                        <?php
                        $already_forked = false;
                        if ($user_id) {
                          $stmt = $db->prepare("SELECT 1 FROM paste_forks WHERE original_paste_id = ? AND forked_by_user_id = ?");
                          $stmt->execute([$paste['id'], $user_id]);
                          $already_forked = $stmt->fetch();
                        }
                        ?>
                        <?php if (!$already_forked): ?>
                          <button onclick="forkPaste(<?= $paste['id'] ?>)" class="p-2 rounded bg-purple-500 text-white hover:bg-purple-600 transition-colors zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" title="Fork">
                            <i class="fas fa-code-branch text-sm"></i>
                          </button>
                        <?php endif; ?>
                      <?php endif; ?>

                      <?php if ($user_id && $paste['user_id'] === $user_id): ?>
                        <button onclick="editPaste(<?= $paste['id'] ?>)" class="p-2 rounded bg-yellow-500 text-white hover:bg-yellow-600 transition-colors" title="Edit">
                          <i class="fas fa-edit text-sm"></i>
                        </button>
                      <?php endif; ?>

                      <button onclick="reportPaste(<?= $paste['id'] ?>)" class="p-2 rounded bg-red-500 text-white hover:bg-red-600 transition-colors" title="Report this paste">
                        <i class="fas fa-flag text-sm"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Navigation Tabs -->
              <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex space-x-0" role="tablist">
                  <button onclick="switchPasteTab('overview')" id="paste-tab-overview" class="paste-tab active-paste-tab px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 transition-all duration-200" role="tab">
                    <i class="fas fa-eye mr-2"></i>Overview
                  </button>
                  <?php if ($version_count > 1): ?>
                    <button onclick="switchPasteTab('versions')" id="paste-tab-versions" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-history mr-2"></i>Versions <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $version_count ?></span>
                    </button>
                  <?php endif; ?>
                  <?php if ($related_count > 0): ?>
                    <button onclick="switchPasteTab('related')" id="paste-tab-related" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-lightbulb mr-2"></i>Related <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $related_count ?></span>
                    </button>
                  <?php endif; ?>
                  <?php if ($chain_count > 0): ?>
                    <button onclick="switchPasteTab('chain')" id="paste-tab-chain" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-link mr-2"></i>Chain <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $chain_count ?></span>
                    </button>
                  <?php endif; ?>
                  <?php if ($fork_count > 0): ?>
                    <button onclick="switchPasteTab('forks')" id="paste-tab-forks" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-code-branch mr-2"></i>Forks <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $fork_count ?></span>
                    </button>
                  <?php endif; ?>
                  <button onclick="switchPasteTab('comments')" id="paste-tab-comments" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200 zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" role="tab">
                    <i class="fas fa-comment mr-2"></i>Comments <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $comment_count ?></span>
                  </button>
                  <?php
                  // Get discussion thread count
                  $stmt = $db->prepare("SELECT COUNT(*) FROM paste_discussion_threads WHERE paste_id = ?");
                  $stmt->execute([$paste['id']]);
                  $discussion_count = $stmt->fetchColumn();
                  ?>
                  <button onclick="switchPasteTab('discussions')" id="paste-tab-discussions" class="paste-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200 zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" role="tab">
                    <i class="fas fa-comments mr-2"></i>Discussions <span class="ml-1 px-2 py-0.5 bg-gray-200 dark:bg-gray-600 rounded-full text-xs"><?= $discussion_count ?></span>
                  </button>
                </nav>
              </div>

              <!-- Tab Content -->
              <div class="p-6">
                <!-- Overview Tab -->
                <div id="paste-content-overview" class="tab-content" role="tabpanel">
                  <div class="space-y-6">
                    <!-- AI Summary Section (Only show on demo paste) -->
                    <?php if ($paste['title'] === 'AI Summary Demo - Python Game Engine'): ?>
                    <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-6">
                      <h3 class="text-lg font-medium mb-3 text-blue-800 dark:text-blue-200">
                        <i class="fas fa-robot mr-2 text-blue-600 dark:text-blue-400"></i>AI Summary
                      </h3>
                      <div class="text-blue-700 dark:text-blue-300">
                        <p class="text-sm leading-relaxed">
                          "This Python script defines a simple 2D game engine using object-oriented programming principles. It demonstrates the use of classes and inheritance with a base GameObject class, a specialized Player class, and a main GameEngine class that handles the game loop. The code includes proper event handling, input processing, and basic collision detection for keeping the player within screen boundaries. The implementation showcases modern Python practices including type hints, proper encapsulation, and clean separation of concerns between rendering, updating, and input handling."
                        </p>
                        <div class="mt-2 pt-2 border-t border-blue-200 dark:border-blue-600">
                          <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">
                            <i class="fas fa-info-circle mr-1"></i>AI-generated summary • Coming soon feature preview
                          </span>
                        </div>
                      </div>
                    </div>
                    <?php endif; ?>

                    <!-- Paste Statistics at a Glance -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                      <h3 class="text-lg font-medium mb-3 text-gray-900 dark:text-white">
                        <i class="fas fa-chart-bar mr-2 text-blue-500"></i>Statistics
                      </h3>
                      <div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-sm">
                        <div class="text-center">
                          <div class="text-2xl font-bold text-blue-600 dark:text-blue-400"><?= number_format($paste['views']) ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Views</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-green-600 dark:text-green-400"><?= number_format($paste['favorite_count'] ?? 0) ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Likes</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-purple-600 dark:text-purple-400"><?= number_format(strlen($paste['content'])) ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Characters</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-orange-600 dark:text-orange-400"><?= number_format(substr_count($paste['content'], "\n") + 1) ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Lines</div>
                        </div>
                        <div class="text-center">
                          <?php
                          $content_bytes = strlen($paste['content']);
                          $size_display = '';
                          $size_class = 'text-indigo-600 dark:text-indigo-400';

                          if ($content_bytes < 1024) {
                            $size_display = $content_bytes . ' B';
                          } elseif ($content_bytes < 1048576) {
                            $size_display = round($content_bytes / 1024, 1) . ' KB';
                          } else {
                            $size_display = round($content_bytes / 1048576, 2) . ' MB';
                          }
                          ?>
                          <div class="text-2xl font-bold <?= $size_class ?>"><?= $size_display ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Size</div>
                        </div>
                        <div class="text-center">
                          <div class="text-2xl font-bold text-pink-600 dark:text-pink-400"><?= number_format($comment_count) ?></div>
                          <div class="text-gray-600 dark:text-gray-400">Comments</div>
                        </div>
                      </div>
                    </div>

                    <!-- Code Content -->
                    <div>
                      <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                          <i class="fas fa-code mr-2 text-green-500"></i>Code Content
                        </h3>
                        <div class="flex gap-2">
                          <?php if (!$is_flagged_for_blur): ?>
                            <button onclick="copyText(document.querySelector('pre code').textContent)" class="text-sm px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                              <i class="fas fa-copy mr-1"></i>Copy
                            </button>
                            <button onclick="printRawCode()" class="text-sm px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                              <i class="fas fa-print mr-1"></i>Print
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>

                      <div class="relative overflow-x-auto">
                        <?php if ($is_flagged_for_blur): ?>
                          <!-- Server-side protected content -->
                          <div class="bg-gray-100 dark:bg-gray-800 p-8 rounded-lg text-center">
                            <div class="bg-yellow-500 text-black px-6 py-4 rounded-lg font-semibold inline-block">
                              <i class="fas fa-eye-slash mr-2"></i>
                              Content Hidden - Under Review
                              <div class="text-sm font-normal mt-2">
                                This content has been flagged by the community and is under moderation review.
                                <br>Content will be restored once reviewed by administrators.
                              </div>
                            </div>
                            <div class="mt-4 text-gray-600 dark:text-gray-400">
                              <p class="text-sm">If you believe this is an error, please contact support.</p>
                            </div>
                          </div>
                        <?php elseif ($paste['zero_knowledge']): ?>
                          <div id="zkBanner" class="bg-yellow-100 dark:bg-yellow-900 p-4 rounded mb-2 text-center text-sm">
                            This is a Zero-Knowledge Paste. Only users with the full link can decrypt it.
                            <div id="zkPrivateLink" class="mt-2 break-words"></div>
                          </div>
                          <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                            <pre id="zkContent" class="p-4 whitespace-pre-wrap break-words" style="font-family: monospace;"></pre>
                          </div>
                        <?php else: ?>
                          <!-- Normal content display -->
                          <div class="bg-gray-100 dark:bg-gray-800 rounded-lg overflow-hidden">
                            <pre class="line-numbers p-4 whitespace-pre-wrap break-words text-gray-900 dark:text-gray-100" style="font-family: monospace; font-size: 0.875rem;"><code class="language-<?= htmlspecialchars($paste['language']) ?> dark:text-gray-100"><?= htmlspecialchars($paste['content']) ?></code></pre>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div></old_str>
            <!-- Versions Tab -->
                <?php if ($version_count > 1): ?>
                <div id="paste-content-versions" class="tab-content hidden" role="tabpanel">
                  <?php
                  // Check if we're viewing a specific version
                  $viewing_version = isset($_GET['version']) ? (int)$_GET['version'] : null;
                  $is_viewing_old_version = false;

                  if ($viewing_version) {
                    // Fetch specific version
                    $stmt = $db->prepare("SELECT pv.*, u.username FROM paste_versions pv 
                                         LEFT JOIN users u ON pv.created_by = u.id 
                                         WHERE pv.paste_id = ? AND pv.version_number = ?");
                    $stmt->execute([$paste['id'], $viewing_version]);
                    $version_data = $stmt->fetch();

                    if ($version_data) {
                      // Replace paste data with version data for display
                      $paste['title'] = $version_data['title'];
                      $paste['content'] = $version_data['content'];
                      $paste['language'] = $version_data['language'];
                      $paste['created_at'] = $version_data['created_at'];
                      $paste['is_version'] = true;
                      $paste['version_number'] = $version_data['version_number'];
                      $is_viewing_old_version = true;
                    }
                  }

                  // Fetch version history
                  $stmt = $db->prepare("SELECT pv.*, u.username FROM paste_versions pv 
                                       LEFT JOIN users u ON pv.created_by = u.id 
                                       WHERE pv.paste_id = ? 
                                       ORDER BY pv.version_number DESC");
                  $stmt->execute([$paste['id']]);
                  $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                  // Count total versions (including current)
                  $total_versions = count($versions) + 1;
                  ?>

                  <div class="space-y-4">
                    <div class="flex justify-between items-center">
                      <h3 class="text-lg font-medium">
                        <i class="fas fa-history mr-2 text-purple-500"></i>Version History
                      </h3>
                      <?php if ($total_versions > 1): ?>
                        <button onclick="openDiffModal()" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 text-sm">
                          <i class="fas fa-code-branch mr-2"></i>Compare Versions
                        </button>
                      <?php endif; ?>
                    </div>

                    <?php if ($is_viewing_old_version): ?>
                      <div class="p-4 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg">
                        <p class="text-yellow-800 dark:text-yellow-200">
                          <i class="fas fa-info-circle mr-2"></i>
                          You are viewing version <?= $viewing_version ?> of this paste.
                          <a href="?id=<?= $paste['id'] ?>" class="ml-2 text-blue-600 hover:text-blue-800 underline">
                            View Current Version (v<?= $paste['current_version'] ?>)
                          </a>
                        </p>
                      </div>
                    <?php endif; ?>

                    <div class="space-y-3">
                      <!-- Current Version -->
                      <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg <?= !$is_viewing_old_version ? 'border-2 border-blue-500' : '' ?>">
                        <div class="flex items-center gap-3">
                          <div class="flex items-center gap-2">
                            <span class="font-medium">Version <?= $paste['current_version'] ?></span>
                            <?php if (!$is_viewing_old_version): ?>
                              <span class="bg-green-500 text-white text-xs px-2 py-1 rounded">Current</span>
                            <?php endif; ?>
                          </div>
                          <span class="text-gray-600 dark:text-gray-400">
                            by <?= $paste['username'] ? htmlspecialchars($paste['username']) : 'Anonymous' ?>
                          </span>
                        </div>
                        <div class="flex items-center gap-4">
                          <span class="text-sm text-gray-500">
                            <?= date('M j, Y g:i A', $paste['last_modified'] ?? $paste['created_at']) ?>
                          </span>
                          <?php if ($is_viewing_old_version): ?>
                            <a href="?id=<?= $paste['id'] ?>" 
                               class="text-blue-500 hover:text-blue-700 text-sm">
                              View
                            </a>
                          <?php else: ?>
                            <span class="text-blue-500 text-sm">Viewing</span>
                          <?php endif; ?>
                        </div>
                      </div>

                      <!-- Previous Versions -->
                      <?php foreach ($versions as $version): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg <?= $viewing_version == $version['version_number'] ? 'border-2 border-blue-500' : '' ?>">
                          <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2">
                              <span class="font-medium">Version <?= $version['version_number'] ?></span>
                              <?php if ($viewing_version == $version['version_number']): ?>
                                <span class="bg-blue-500 text-white text-xs px-2 py-1 rounded">Viewing</span>
                              <?php endif; ?>
                            </div>
                            <span class="text-gray-600 dark:text-gray-400">
                              by <?= $version['username'] ? htmlspecialchars($version['username']) : 'Anonymous' ?>
                            </span>
                            <?php if (!empty($version['change_message'])): ?>
                              <span class="text-sm text-gray-500">
                                - <?= htmlspecialchars($version['change_message']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                          <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-500">
                              <?= date('M j, Y g:i A', $version['created_at']) ?>
                            </span>
                            <?php if ($viewing_version != $version['version_number']): ?>
                              <a href="?id=<?= $paste['id'] ?>&version=<?= $version['version_number'] ?>" 
                                 class="text-blue-500 hover:text-blue-700 text-sm">
                                View
                              </a>
                            <?php else: ?>
                              <span class="text-blue-500 text-sm">Viewing</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Related Tab -->
                <?php if ($related_count > 0): ?>
                <div id="paste-content-related" class="tab-content hidden" role="tabpanel">
                  <div class="space-y-4">
                    <h3 class="text-lg font-medium">
                      <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>Related Pastes
                    </h3>

                    <div class="space-y-3">
                      <?php foreach ($related_pastes as $related): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                          <div class="flex items-start justify-between">
                            <div class="flex-1">
                              <h4 class="font-medium mb-1">
                                <a href="?id=<?= $related['id'] ?>" class="text-blue-500 hover:text-blue-700">
                                  <?= htmlspecialchars($related['title']) ?>
                                </a>
                              </h4>
                              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                                  <?= htmlspecialchars($related['language']) ?>
                                </span>
                                <?php if ($related['username']): ?>
                                  <span>by @<?= htmlspecialchars($related['username']) ?></span>
                                <?php else: ?>
                                  <span>by Anonymous</span>
                                <?php endif; ?>
                                <span>•</span>
                                <span><?= human_time_diff($related['created_at']) ?></span>
                                <span>•</span>
                                <span><?= number_format($related['views']) ?> views</span>
                              </div>
                            </div>
                            <div class="ml-4">
                              <a href="?id=<?= $related['id'] ?>" 
                                 class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                View
                              </a>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Chain Tab -->
                <?php if ($chain_count > 0): ?>
                <div id="paste-content-chain" class="tab-content hidden" role="tabpanel">
                  <?php
                  // Get child pastes in this chain
                  $stmt = $db->prepare("
                    SELECT p.*, u.username, u.profile_image
                    FROM pastes p
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE p.parent_paste_id = ? AND p.is_public = 1 AND p.zero_knowledge = 0
                    ORDER BY p.created_at ASC
                    LIMIT 10
                  ");
                  $stmt->execute([$paste['id']]);
                  $child_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  ?>

                  <div class="space-y-4">
                    <h3 class="text-lg font-medium">
                      <i class="fas fa-link mr-2 text-blue-500"></i>
                      Chain Continuations (<?= count($child_pastes) ?><?= count($child_pastes) >= 10 ? '+' : '' ?>)
                    </h3>

                    <div class="space-y-4">
                      <?php foreach ($child_pastes as $child): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                          <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                              <h4 class="font-medium mb-1">
                                <a href="?id=<?= $child['id'] ?>" class="text-blue-500 hover:text-blue-700">
                                  <?= htmlspecialchars($child['title']) ?>
                                </a>
                              </h4>
                              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <img src="<?= $child['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($child['username'] ?? 'anonymous')).'?d=mp&s=20' ?>" 
                                     class="w-5 h-5 rounded-full" alt="Author">
                                <span>
                                  <?php if ($child['username']): ?>
                                    <a href="?page=profile&username=<?= urlencode($child['username']) ?>" class="hover:text-blue-500">
                                      @<?= htmlspecialchars($child['username']) ?>
                                    </a>
                                  <?php else: ?>
                                    Anonymous
                                  <?php endif; ?>
                                </span>
                                <span>•</span>
                                <span><?= human_time_diff($child['created_at']) ?></span>
                              </div>
                            </div>
                            <div class="flex gap-2">
                              <a href="?id=<?= $child['id'] ?>" 
                                 class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                View
                              </a>
                              <a href="/?parent_id=<?= $child['id'] ?>" 
                                 class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">
                                Continue Chain
                              </a>
                            </div>
                          </div>

                          <div class="flex items-center justify-between text-sm text-gray-500">
                            <span><i class="fas fa-eye mr-1"></i><?= number_format($child['views']) ?> views</span>
                            <span><i class="fas fa-star mr-1"></i><?= number_format($child['favorite_count'] ?? 0) ?> likes</span>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Forks Tab -->
                <?php if ($fork_count > 0): ?>
                <div id="paste-content-forks" class="tab-content hidden" role="tabpanel">
                  <?php
                  // Get forks of this paste
                  $stmt = $db->prepare("
                    SELECT p.*, u.username, u.profile_image, pf.created_at as forked_at
                    FROM paste_forks pf
                    JOIN pastes p ON pf.forked_paste_id = p.id
                    LEFT JOIN users u ON p.user_id = u.id
                    WHERE pf.original_paste_id = ?
                    ORDER BY pf.created_at DESC
                    LIMIT 10
                  ");
                  $stmt->execute([$paste['id']]);
                  $forks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                  ?>

                  <div class="space-y-4">
                    <h3 class="text-lg font-medium">
                      <i class="fas fa-code-branch mr-2 text-purple-500"></i>
                      Forks (<?= count($forks) ?><?= count($forks) >= 10 ? '+' : '' ?>)
                    </h3>

                    <div class="grid md:grid-cols-2 gap-4">
                      <?php foreach ($forks as $fork): ?>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                          <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                              <h4 class="font-medium mb-1">
                                <a href="?id=<?= $fork['id'] ?>" class="text-blue-500 hover:text-blue-700">
                                  <?= htmlspecialchars($fork['title']) ?>
                                </a>
                              </h4>
                              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <img src="<?= $fork['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($fork['username'] ?? 'anonymous')).'?d=mp&s=20' ?>" 
                                     class="w-5 h-5 rounded-full" alt="Author">
                                <span>
                                  <?php if ($fork['username']): ?>
                                    <a href="?page=profile&username=<?= urlencode($fork['username']) ?>" class="hover:text-blue-500">
                                      @<?= htmlspecialchars($fork['username']) ?>
                                    </a>
                                  <?php else: ?>
                                    Anonymous
                                  <?php endif; ?>
                                </span>
                                <span>•</span>
                                <span><?= human_time_diff($fork['forked_at']) ?></span>
                              </div>
                            </div>
                            <div class="flex gap-2">
                              <a href="?id=<?= $fork['id'] ?>" 
                                 class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                View
                              </a>
                              <?php if ($user_id && $fork['user_id'] !== $user_id): ?>
                                <button onclick="forkPaste(<?= $fork['id'] ?>)" 
                                        class="px-3 py-1 bg-purple-500 text-white rounded text-sm hover:bg-purple-600">
                                  Fork
                                </button>
                              <?php endif; ?>
                            </div>
                          </div>

                          <div class="flex items-center justify-between text-sm text-gray-500">
                            <span><i class="fas fa-eye mr-1"></i><?= number_format($fork['views']) ?> views</span>
                            <span><i class="fas fa-star mr-1"></i><?= number_format($fork['favorite_count'] ?? 0) ?> likes</span>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Discussions Tab -->
                <div id="paste-content-discussions" class="tab-content hidden zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" role="tabpanel">
                  <div class="space-y-6">
                    <div class="flex justify-between items-center">
                      <h3 class="text-lg font-medium">
                        <i class="fas fa-comments mr-2 text-purple-500"></i>Discussions (<?= $discussion_count ?>)
                      </h3>
                      <?php if ($user_id): ?>
                        <button onclick="toggleDiscussionForm()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 text-sm">
                          <i class="fas fa-plus mr-1"></i>Start Discussion
                        </button>
                      <?php endif; ?>
                    </div>

                    <!-- Create Discussion Form -->
                    <?php if ($user_id): ?>
                    <div id="discussionForm" class="<?= isset($_GET['view_thread']) ? 'hidden' : '' ?> bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hidden">
                      <h4 class="text-lg font-medium mb-4">Start a New Discussion</h4>
                      <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_discussion_thread">
                        <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">

                        <div>
                          <label class="block text-sm font-medium mb-2">Discussion Title</label>
                          <input type="text" name="thread_title" required 
                                 class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" 
                                 placeholder="What would you like to discuss?">
                        </div>

                        <div>
                          <label class="block text-sm font-medium mb-2">Category</label>
                          <select name="thread_category" required class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">Select a category...</option>
                            <option value="Q&A">Q&A - Questions & Answers</option>
                            <option value="Tip">Tip - Helpful tips and tricks</option>
                            <option value="Idea">Idea - Suggestions and ideas</option>
                            <option value="Bug">Bug - Bug reports and issues</option>
                            <option value="General">General - General discussion</option>
                          </select>
                        </div>

                        <div>
                          <label class="block text-sm font-medium mb-2">Your Message</label>
                          <textarea name="first_post_content" required rows="4" 
                                    class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" 
                                    placeholder="Start the discussion..."></textarea>
                        </div>

                        <div class="flex gap-2">
                          <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                            <i class="fas fa-plus mr-1"></i>Create Discussion
                          </button>
                          <button type="button" onclick="toggleDiscussionForm()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                            Cancel
                          </button>
                        </div>
                      </form>
                    </div>
                    <?php endif; ?>

                    <!-- Thread View -->
                    <?php if (isset($_GET['view_thread'])): ?>
                      <?php
                      $thread_id = $_GET['view_thread'];

                      // Get thread details
                      $stmt = $db->prepare("
                        SELECT dt.*, u.username, u.profile_image
                        FROM paste_discussion_threads dt
                        LEFT JOIN users u ON dt.user_id = u.id
                        WHERE dt.id = ? AND dt.paste_id = ?
                      ");
                      $stmt->execute([$thread_id, $paste['id']]);
                      $thread = $stmt->fetch(PDO::FETCH_ASSOC);

                      if ($thread):
                        // Get posts
                        $stmt = $db->prepare("
                          SELECT dp.*, u.username, u.profile_image
                          FROM paste_discussion_posts dp
                          LEFT JOIN users u ON dp.user_id = u.id
                          WHERE dp.thread_id = ? AND dp.is_deleted = 0
                          ORDER BY dp.created_at ASC
                        ");
                        $stmt->execute([$thread_id]);
                        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                      ?>
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                          <!-- Thread Header -->
                          <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                            <div class="flex items-center justify-between mb-3">
                              <div class="flex items-center gap-2">
                                <button onclick="backToDiscussions()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                                  <i class="fas fa-arrow-left mr-2"></i>Back to Discussions
                                </button>
                              </div>
                              <?php
                              $category_colors = [
                                'Q&A' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                'Tip' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                'Idea' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                'Bug' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'General' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                              ];
                              ?>
                              <span class="px-3 py-1 rounded-full text-sm font-medium <?= $category_colors[$thread['category']] ?? $category_colors['General'] ?>">
                                <?= htmlspecialchars($thread['category']) ?>
                              </span>
                            </div>

                            <h4 class="text-xl font-semibold mb-2"><?= htmlspecialchars($thread['title']) ?></h4>
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                              <img src="<?= $thread['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($thread['username'] ?? 'anonymous')).'?d=mp&s=24' ?>" 
                                   class="w-6 h-6 rounded-full" alt="Author">
                              <span>Started by 
                                <?php if ($thread['username']): ?>
                                  <a href="?page=profile&username=<?= urlencode($thread['username']) ?>" class="hover:text-purple-500">
                                    @<?= htmlspecialchars($thread['username']) ?>
                                  </a>
                                <?php else: ?>
                                  Anonymous
                                <?php endif; ?>
                              </span>
                              <span>•</span>
                              <span><?= date('M j, Y g:i A', $thread['created_at']) ?></span>
                            </div>
                          </div>

                          <!-- Posts -->
                          <div class="space-y-6">
                            <?php foreach ($posts as $index => $post): ?>
                              <div class="flex items-start gap-4 <?= $index === 0 ? 'bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4' : '' ?>">
                                <img src="<?= $post['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($post['username'] ?? 'anonymous')).'?d=mp&s=40' ?>" 
                                     class="w-10 h-10 rounded-full" alt="Author">

                                <div class="flex-1">
                                  <div class="flex items-center gap-2 mb-2">
                                    <div class="font-medium">
                                      <?php if ($post['username']): ?>
                                        <a href="?page=profile&username=<?= urlencode($post['username']) ?>" class="hover:text-purple-500">
                                          @<?= htmlspecialchars($post['username']) ?>
                                        </a>
                                      <?php else: ?>
                                        Anonymous
                                      <?php endif; ?>
                                    </div>
                                    <?php if ($index === 0): ?>
                                      <span class="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 text-xs px-2 py-1 rounded">OP</span>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">
                                      <?= date('M j, Y g:i A', $post['created_at']) ?>
                                    </div>
                                    <?php if ($user_id && ($post['user_id'] === $user_id)): ?>
                                      <button onclick="deleteDiscussionPost(<?= $post['id'] ?>, <?= $thread_id ?>, <?= $paste['id'] ?>)" 
                                              class="text-red-500 hover:text-red-700 text-xs ml-auto">
                                        <i class="fas fa-trash"></i>
                                      </button>
                                    <?php endif; ?>
                                  </div>

                                  <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>

                          <!-- Reply Form -->
                          <?php if ($user_id): ?>
                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                              <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_discussion_post">
                                <input type="hidden" name="thread_id" value="<?= $thread_id ?>">
                                <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">

                                <div>
                                  <label class="block text-sm font-medium mb-2">Your Reply</label>
                                  <textarea name="post_content" required rows="3" 
                                            class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" 
                                            placeholder="Write your reply..."></textarea>
                                </div>

                                <button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                                  <i class="fas fa-reply mr-1"></i>Reply
                                </button>
                              </form>
                            </div>
                          <?php else: ?>
                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 text-center">
                              <a href="?page=login" class="text-purple-500 hover:text-purple-700">
                                Login to reply to this discussion
                              </a>
                            </div>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-8">
                          <p class="text-gray-500">Thread not found.</p>
                          <button onclick="backToDiscussions()" class="mt-2 text-purple-500 hover:text-purple-700">
                            Back to Discussions
                          </button>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <!-- Discussions List -->
                      <div id="discussionsList">
                        <?php
                        // Get discussion threads
                        $stmt = $db->prepare("
                          SELECT dt.*, u.username, u.profile_image,
                                 (SELECT COUNT(*) FROM paste_discussion_posts WHERE thread_id = dt.id AND is_deleted = 0) as reply_count
                          FROM paste_discussion_threads dt
                          LEFT JOIN users u ON dt.user_id = u.id
                          WHERE dt.paste_id = ?
                          ORDER BY dt.created_at DESC
                        ");
                        $stmt->execute([$paste['id']]);
                        $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (empty($discussions)): ?>
                          <div class="text-center py-12">
                            <i class="fas fa-comments text-4xl text-gray-400 mb-4"></i>
                            <h4 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No discussions yet</h4>
                            <p class="text-gray-500 mb-4">Be the first to start a discussion about this paste!</p>
                            <?php if ($user_id): ?>
                              <button onclick="toggleDiscussionForm()" class="bg-purple-500 text-white px-6 py-3 rounded hover:bg-purple-600">
                                <i class="fas fa-plus mr-2"></i>Start Discussion
                              </button>
                            <?php else: ?>
                              <a href="?page=login" class="text-purple-500 hover:text-purple-700">
                                Login to start a discussion
                              </a>
                            <?php endif; ?>
                          </div>
                        <?php else: ?>
                          <div class="space-y-4">
                            <?php foreach ($discussions as $discussion): ?>
                              <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                                   onclick="viewThread(<?= $discussion['id'] ?>)">
                                <div class="flex items-start justify-between">
                                  <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                      <h4 class="text-lg font-medium hover:text-purple-500 transition-colors">
                                        <?= htmlspecialchars($discussion['title']) ?>
                                      </h4>
                                      <?php
                                      $category_colors = [
                                        'Q&A' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                        'Tip' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                        'Idea' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                        'Bug' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                        'General' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                      ];
                                      ?>
                                      <span class="px-2 py-1 rounded-full text-xs font-medium <?= $category_colors[$discussion['category']] ?? $category_colors['General'] ?>">
                                        <?= htmlspecialchars($discussion['category']) ?>
                                      </span>
                                    </div>

                                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                      <div class="flex items-center gap-2">
                                        <img src="<?= $discussion['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($discussion['username'] ?? 'anonymous')).'?d=mp&s=20' ?>" 
                                             class="w-5 h-5 rounded-full" alt="Author">
                                        <span>
                                          <?php if ($discussion['username']): ?>
                                            <a href="?page=profile&username=<?= urlencode($discussion['username']) ?>" class="hover:text-purple-500" onclick="event.stopPropagation()">
                                              @<?= htmlspecialchars($discussion['username']) ?>
                                            </a>
                                          <?php else: ?>
                                            Anonymous
                                          <?php endif; ?>
                                        </span>
                                      </div>
                                      <span>•</span>
                                      <span><?= human_time_diff($discussion['created_at']) ?></span>
                                      <span>•</span>
                                      <span class="flex items-center gap-1">
                                        <i class="fas fa-reply text-xs"></i>
                                        <?= $discussion['reply_count'] - 1 ?> replies
                                      </span>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Comments Tab -->
                <div id="paste-content-comments" class="tab-content hidden zk-restricted<?= $paste['zero_knowledge'] ? ' hidden' : '' ?>" role="tabpanel">
                  <div class="space-y-6">
                    <h3 class="text-lg font-medium">
                      <i class="fas fa-comment mr-2 text-green-500"></i>Comments (<?= $comment_count ?>)
                    </h3>

                    <?php
                    // Fetch comments with reply counts
                    $stmt = $db->prepare("SELECT c.*, u.username, u.profile_image,
                                                (SELECT COUNT(*) FROM comment_replies cr WHERE cr.parent_comment_id = c.id AND cr.is_deleted = 0) as reply_count
                                        FROM comments c 
                                        LEFT JOIN users u ON c.user_id = u.id 
                                        WHERE c.paste_id = ? AND c.is_deleted = 0 
                                        ORDER BY c.created_at DESC");
                    $stmt->execute([$paste['id']]);
                    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Function to get replies for a comment
                    function getCommentReplies($db, $comment_id) {
                      $stmt = $db->prepare("SELECT cr.*, u.username, u.profile_image 
                                           FROM comment_replies cr 
                                           LEFT JOIN users u ON cr.user_id = u.id 
                                           WHERE cr.parent_comment_id = ? AND cr.is_deleted = 0 
                                           ORDER BY cr.created_at ASC");
                      $stmt->execute([$comment_id]);
                      return $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>

                    <!-- Comments List -->
                    <?php if ($comments): ?>
                      <div class="space-y-6">
                        <?php foreach ($comments as $comment): ?>
                        <div id="comment-<?= $comment['id'] ?>" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 <?= $comment['is_flagged'] ? 'border-l-4 border-red-500' : '' ?>">
                          <!-- Main Comment -->
                          <div class="flex items-start gap-3 mb-3">
                            <img src="<?= htmlspecialchars($comment['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($comment['username'] ?? 'anonymous')).'?d=mp&s=32') ?>" 
                                 class="w-8 h-8 rounded-full" alt="Commenter avatar">
                            <div class="flex-1">
                              <div class="flex items-center gap-2 mb-1">
                                <div class="font-medium">
                                  <?php if ($comment['username']): ?>
                                    <a href="?page=profile&username=<?= urlencode($comment['username']) ?>" class="hover:text-blue-500">
                                      @<?= htmlspecialchars($comment['username']) ?>
                                    </a>
                                  <?php else: ?>
                                    Anonymous
                                  <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                  <?= date('F jS Y g:i A', $comment['created_at']) ?>
                                </div>
                                <?php if ($comment['is_flagged']): ?>
                                  <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded">Flagged</span>
                                <?php endif; ?>
                              </div>
                              <div class="text-gray-700 dark:text-gray-300 mb-3">
                                <?= nl2br(htmlspecialchars($comment['content'])) ?>
                              </div>
                              <!-- Comment Actions -->
                              <div class="flex items-center gap-4 text-sm">
                                <button onclick="toggleReplyForm(<?= $comment['id'] ?>)" class="text-blue-500 hover:text-blue-700">
                                  <i class="fas fa-reply mr-1"></i>Reply
                                </button>
                                <?php if ($comment['reply_count'] > 0): ?>
                                  <button onclick="toggleReplies(<?= $comment['id'] ?>)" class="text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-comments mr-1"></i><?= $comment['reply_count'] ?> replies
                                  </button>
                                <?php endif; ?>
                                <?php if ($user_id && ($comment['user_id'] === $user_id)): ?>
                                  <button onclick="deleteComment(<?= $comment['id'] ?>, null, <?= $paste['id'] ?>)" class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                  </button>
                                <?php endif; ?>
                                <?php if ($user_id && $comment['user_id'] !== $user_id): ?>
                                  <button onclick="reportComment(<?= $comment['id'] ?>, null, <?= $paste['id'] ?>)" class="text-orange-500 hover:text-orange-700">
                                    <i class="fas fa-flag mr-1"></i>Report
                                  </button>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>

                          <!-- Reply Form -->
                          <div id="reply-form-<?= $comment['id'] ?>" class="hidden mt-4 ml-11">
                            <form method="POST" class="space-y-3">
                              <input type="hidden" name="action" value="add_reply">
                              <input type="hidden" name="parent_comment_id" value="<?= $comment['id'] ?>">
                              <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">
                              <textarea name="reply_content" rows="2" 
                                        class="w-full px-3 py-2 rounded border bg-white dark:bg-gray-600 text-sm" 
                                        placeholder="Write a reply..." required></textarea>
                              <div class="flex gap-2">
                                <button type="submit" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                                  Post Reply
                                </button>
                                <button type="button" onclick="toggleReplyForm(<?= $comment['id'] ?>)" class="px-3 py-1 bg-gray-300 text-gray-700 rounded text-sm hover:bg-gray-400">
                                  Cancel
                                </button>
                              </div>
                            </form>
                          </div>

                          <!-- Replies -->
                          <?php 
                          $replies = getCommentReplies($db, $comment['id']);
                          if (!empty($replies)): 
                          ?>
                          <div id="replies-<?= $comment['id'] ?>" class="mt-4 ml-11 space-y-3 border-l-2 border-gray-200 dark:border-gray-600 pl-4">
                            <?php foreach ($replies as $reply): ?>
                            <div class="bg-white dark:bg-gray-600 rounded p-3">
                              <div class="flex items-start gap-2">
                                <img src="<?= htmlspecialchars($reply['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($reply['username'] ?? 'anonymous')).'?d=mp&s=24') ?>" 
                                     class="w-6 h-6 rounded-full" alt="Reply avatar">
                                <div class="flex-1">
                                  <div class="flex items-center gap-2 mb-1">
                                    <div class="font-medium text-sm">
                                      <?php if ($reply['username']): ?>
                                        <a href="?page=profile&username=<?= urlencode($reply['username']) ?>" class="hover:text-blue-500">
                                          @<?= htmlspecialchars($reply['username']) ?>
                                        </a>
                                      <?php else: ?>
                                        Anonymous
                                      <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                      <?= date('M j, g:i A', $reply['created_at']) ?>
                                    </div>
                                  </div>
                                  <div class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                    <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                  </div>
                                  <div class="flex items-center gap-3 text-xs">
                                    <?php if ($user_id && ($reply['user_id'] === $user_id)): ?>
                                      <button onclick="deleteComment(<?= $comment['id'] ?>, <?= $reply['id'] ?>, <?= $paste['id'] ?>)" class="text-red-500 hover:text-red-700">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                      </button>
                                    <?php endif; ?>
                                    <?php if ($user_id && $reply['user_id'] !== $user_id): ?>
                                      <button onclick="reportComment(null, <?= $reply['id'] ?>, <?= $paste['id'] ?>)" class="text-orange-500 hover:text-orange-700">
                                        <i class="fas fa-flag mr-1"></i>Report
                                      </button>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <?php endforeach; ?>
                          </div>
                          <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p class="text-gray-500 dark:text-gray-400">No comments yet.</p>
                    <?php endif; ?>

                    <!-- Add Comment Form -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                      <h4 class="text-lg font-semibold mb-4">Add a Comment</h4>
                      <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">
                        <textarea 
                          name="comment" 
                          rows="3" 
                          class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                          placeholder="Write your comment..."
                          required
                        ></textarea>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                          Post Comment <?= $user_id ? '' : 'as Anonymous' ?>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Tab Switching Script -->
            <script>
              function switchPasteTab(tabName) {
                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(content => {
                  content.classList.add('hidden');
                });

                // Remove active state from all tabs
                document.querySelectorAll('.paste-tab').forEach(tab => {
                  tab.classList.remove('active-paste-tab', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
                  tab.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                });

                // Show selected tab content
                document.getElementById('paste-content-' + tabName).classList.remove('hidden');

                // Add active state to selected tab
                const activeTab = document.getElementById('paste-tab-' + tabName);
                activeTab.classList.add('active-paste-tab', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
                activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');

                // Re-initialize Prism syntax highlighting for the visible content
                if (window.Prism) {
                  setTimeout(() => {
                    const visibleContent = document.querySelector('.tab-content:not(.hidden)');
                    if (visibleContent) {
                      visibleContent.querySelectorAll('pre code').forEach((block) => {
                        // Ensure line numbers are applied
                        const pre = block.parentElement;
                        if (pre && pre.tagName === 'PRE') {
                          pre.classList.add('line-numbers');
                        }
                        Prism.highlightElement(block);
                      });
                    }
                  }, 100);
                }
              }

              // Initialize on page load
              document.addEventListener('DOMContentLoaded', function() {
                // Add smooth transition animations
                const style = document.createElement('style');
                style.textContent = `
                  .tab-content {
                    opacity: 1;
                    transition: opacity 0.2s ease-in-out;
                  }
                  .tab-content.hidden {
                    opacity: 0;
                  }
                  .paste-tab {
                    position: relative;
                    overflow: hidden;
                    transition: all 0.2s ease-in-out;
                  }
                  .paste-tab:hover {
                    background-color: rgba(59, 130, 246, 0.05);
                  }
                  .dark .paste-tab:hover {
                    background-color: rgba(59, 130, 246, 0.1);
                  }
                `;
                document.head.appendChild(style);

                // Auto-switch to discussions tab if viewing a thread
                <?php if (isset($_GET['view_thread'])): ?>
                switchPasteTab('discussions');
                <?php endif; ?>
              });

              // Comment functions
              function toggleReplyForm(commentId) {
                const form = document.getElementById(`reply-form-${commentId}`);
                form.classList.toggle('hidden');
                if (!form.classList.contains('hidden')) {
                  form.querySelector('textarea').focus();
                }
              }

              function toggleReplies(commentId) {
                const replies = document.getElementById(`replies-${commentId}`);
                replies.classList.toggle('hidden');
              }

              function deleteComment(commentId, replyId, pasteId) {
                if (confirm('Are you sure you want to delete this comment?')) {
                  const form = document.createElement('form');
                  form.method = 'POST';
                  form.innerHTML = `
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="comment_id" value="${commentId}">
                    ${replyId ? `<input type="hidden" name="reply_id" value="${replyId}">` : ''}
                    <input type="hidden" name="paste_id" value="${pasteId}">
                    ${replyId ? `<input type="hidden" name="parent_comment_id" value="${commentId}">` : ''}
                  `;
                  document.body.appendChild(form);
                  form.submit();
                }
              }

              function reportComment(commentId, replyId, pasteId) {
                // Implement comment reporting functionality
                alert('Comment reporting functionality would be implemented here');
              }

              // Discussion functions
              function toggleDiscussionForm() {
                const form = document.getElementById('discussionForm');
                if (form) {
                  form.classList.toggle('hidden');
                  if (!form.classList.contains('hidden')) {
                    form.querySelector('input[name="thread_title"]').focus();
                  }
                }
              }

              function viewThread(threadId) {
                window.location.href = `?id=<?= $paste['id'] ?>&view_thread=${threadId}`;
              }

              function backToDiscussions() {
                window.location.href = `?id=<?= $paste['id'] ?>`;
              }

              function deleteDiscussionPost(postId, threadId, pasteId) {
                if (confirm('Are you sure you want to delete this post?')) {
                  const form = document.createElement('form');
                  form.method = 'POST';
                  form.innerHTML = `
                    <input type="hidden" name="action" value="delete_discussion_post">
                    <input type="hidden" name="post_id" value="${postId}">
                    <input type="hidden" name="thread_id" value="${threadId}">
                    <input type="hidden" name="paste_id" value="${pasteId}">
                  `;
                  document.body.appendChild(form);
                  form.submit();
                }
              }
            </script>

            <style>
              pre code {
                word-wrap: break-word;
                white-space: pre-wrap;
                max-width: 100%;
                display: block;
              }
              .line-numbers {
                overflow-x: auto;
                max-width: 100%;
                position: relative;
                padding-left: 3.8em !important;
                counter-reset: linenumber;
              }
              .line-numbers .line-numbers-rows {
                position: absolute;
                pointer-events: none;
                top: 0;
                font-size: 100%;
                left: -3.8em;
                width: 3em;
                letter-spacing: -1px;
                border-right: 1px solid #999;
                user-select: none;
              }
              .line-numbers .line-numbers-rows > span {
                pointer-events: none;
                display: block;
                counter-increment: linenumber;
              }
              .line-numbers .line-numbers-rows > span:before {
                content: counter(linenumber);
                color: #999;
                display: block;
                padding-right: 0.8em;
                text-align: right;
              }
            </style></old_str>


            <script>

              // Copy text function with notification
              function printRawCode() {
                <?php if ($is_flagged_for_blur): ?>
                  Swal.fire({
                    icon: 'warning',
                    title: 'Content Protected',
                    text: 'This paste is under moderation review and cannot be printed.'
                  });
                  return;
                <?php endif; ?>

                const codeElement = document.querySelector('pre code');
                if (!codeElement) {
                  Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Content not available for printing.'
                  });
                  return;
                }

                const codeContent = codeElement.textContent;
                const printContent = document.body.innerHTML;
                document.body.innerHTML = `
                  <pre style="padding: 20px; white-space: pre-wrap; font-family: monospace;">${codeContent}</pre>`;
                window.print();
                document.body.innerHTML = printContent;
              }

      function forkPaste(pasteId) {
        <?php if ($paste['zero_knowledge']): ?>
          if (!window.pasteDecrypted) {
            Swal.fire({
              icon: 'warning',
              title: 'Action Disabled',
              text: 'This action is disabled for encrypted pastes. Please provide the decryption key first.'
            });
            return;
          }
        <?php endif; ?>
        <?php if (!$user_id): ?>
          // For anonymous users: store fork data and redirect to create form
          Swal.fire({
            title: 'Fork this paste?',
            text: "This will create a copy of the paste that you can edit.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-code-branch mr-2"></i>Fork Paste',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              // Get paste data for forking
              fetch(`?id=${pasteId}&raw=1`)
                .then(response => response.text())
                .then(content => {
                  // Store fork metadata in sessionStorage
                  sessionStorage.setItem('forkContent', content);
                  sessionStorage.setItem('forkPasteId', pasteId);
                  sessionStorage.setItem('forkLanguage', '<?= htmlspecialchars($paste['language']) ?>');
                  sessionStorage.setItem('forkTitle', '<?= htmlspecialchars($paste['title']) ?>');

                  // Redirect to home page for editing
                  window.location.href = '/?fork=1';
                })
                .catch(error => {
                  console.error('Error fetching paste content:', error);
                  Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to fork paste. Please try again.'
                  });
                });
            }
          });
        <?php else: ?>
          // For logged-in users: use existing fork functionality
          Swal.fire({
            title: 'Fork this paste?',
            text: "This will create a copy of the paste under your account.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#8b5cf6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-code-branch mr-2"></i>Fork Paste',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              const form = document.createElement('form');
              form.method = 'POST';
              form.innerHTML = `
                <input type="hidden" name="action" value="fork_paste">
                <input type="hidden" name="paste_id" value="${pasteId}">
              `;
              document.body.appendChild(form);
              form.submit();
            }
          });
        <?php endif; ?>
      }

      function clonePaste() {
                <?php if ($paste['zero_knowledge']): ?>
                  if (!window.pasteDecrypted) {
                    Swal.fire({
                      icon: 'warning',
                      title: 'Action Disabled',
                      text: 'This action is disabled for encrypted pastes. Please provide the decryption key first.'
                    });
                    return;
                  }
                <?php endif; ?>
                <?php if ($is_flagged_for_blur): ?>
                  Swal.fire({
                    icon: 'warning',
                    title: 'Content Protected',
                    text: 'This paste is under moderation review and cannot be cloned.'
                  });
                  return;
                <?php endif; ?>

                const codeElement = document.querySelector('pre code');
                if (!codeElement) {
                  Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Content not available for cloning.'
                  });
                  return;
                }

                const content = codeElement.textContent;
                const language = '<?= htmlspecialchars($paste['language']) ?>';

                // Store in sessionStorage
                sessionStorage.setItem('clonedContent', content);
                sessionStorage.setItem('clonedLanguage', language);

                // Redirect to home page
                window.location.href = '/?clone=1';
              }

              async function copyText(text) {
                <?php if ($is_flagged_for_blur): ?>
                  Swal.fire({
                    icon: 'warning',
                    title: 'Content Protected',
                    text: 'This paste is under moderation review and cannot be copied.'
                  });
                  return;
                <?php endif; ?>

                try {
                  await navigator.clipboard.writeText(text);
                  Swal.fire({
                    icon: 'success',
                    title: 'Copied!',
                    text: 'Text copied to clipboard',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                  });
                } catch (err) {
                  Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to copy text',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                  });
                }
              }

              function downloadPaste(filename) {
                if (!window.pasteDecrypted || !window.decryptedText) {
                  Swal.fire({
                    icon: 'warning',
                    title: 'Action Disabled',
                    text: 'This action is disabled for encrypted pastes. Please provide the decryption key first.'
                  });
                  return;
                }
                const blob = new Blob([window.decryptedText], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename || 'paste.txt';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
              }

              // Report paste function
              function reportPaste(pasteId) {
                // Load the flag modal content
                fetch(`flag_paste.php?paste_id=${pasteId}`)
                  .then(response => response.text())
                  .then(html => {
                    // Create modal backdrop
                    const modalBackdrop = document.createElement('div');
                    modalBackdrop.id = 'flagModal';
                    modalBackdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
                    modalBackdrop.innerHTML = html;

                    document.body.appendChild(modalBackdrop);

                    // Close modal when clicking backdrop
                    modalBackdrop.addEventListener('click', function(e) {
                      if (e.target === modalBackdrop) {
                        closeFlagModal();
                      }
                    });
                  })
                  .catch(error => {
                    console.error('Error loading flag modal:', error);
                    Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: 'Failed to load reporting form'
                    });
                  });
              }

              function closeFlagModal() {
                const modal = document.getElementById('flagModal');
                if (modal) {
                  modal.remove();
                }
              }

              function toggleOtherField(flagType) {
                const otherField = document.getElementById('otherReasonField');
                if (otherField) {
                  if (flagType === 'other') {
                    otherField.classList.remove('hidden');
                    otherField.querySelector('input').required = true;
                  } else {
                    otherField.classList.add('hidden');
                    otherField.querySelector('input').required = false;
                  }
                }
              }

              function submitFlag(event) {
                event.preventDefault();

                const formData = new FormData(event.target);

                fetch('flag_paste.php', {
                  method: 'POST',
                  body: formData
                })
                .then(response => response.json())
                .then(data => {
                  if (data.success) {
                    Swal.fire({
                      icon: 'success',
                      title: 'Report Submitted',
                      text: data.message,
                      toast: true,
                      position: 'top-end',
                      showConfirmButton: false,
                      timer: 3000
                    });
                    closeFlagModal();
                  } else {
                    Swal.fire({
                      icon: 'error',
                      title: 'Error',
                      text: data.message
                    });
                  }
                })
                .catch(error => {
                  console.error('Error submitting flag:', error);
                  Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to submit report'
                  });
                });
              }

              // Initialize Prism syntax highlighting
              document.addEventListener('DOMContentLoaded', () => {
                if (document.querySelector('pre code')) {
                  // Force re-highlighting with line numbers
                  document.querySelectorAll('pre code').forEach((block) => {
                    // Get the language from the class
                    const language = block.className.match(/language-(\w+)/)?.[1] || 'plaintext';
                    // Set the class explicitly
                    block.className = `language-${language}`;
                    // Add line-numbers class to parent pre
                    const pre = block.parentElement;
                    if (pre && pre.tagName === 'PRE') {
                      pre.classList.add('line-numbers');
                    }
                    Prism.highlightElement(block);
                  });
                }
              });

              async function toggleLike(pasteId) {
                try {
                  const response = await fetch('', {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_favorite=1&paste_id=${pasteId}`
                  });

                  if (response.ok) {
                    const btn = document.querySelector(`button[onclick="toggleLike(${pasteId})"]`);
                    const heart = btn.querySelector('.fa-heart');
                    const countSpan = document.getElementById(`like-count-${pasteId}`);
                    const currentCount = parseInt(countSpan.textContent.replace(/,/g, ''));

                    if (heart.classList.contains('text-red-500')) {
                      heart.classList.remove('text-red-500');
                      heart.classList.add('text-gray-400');
                      countSpan.textContent = (currentCount - 1).toLocaleString();
                    } else {
                      heart.classList.remove('text-gray-400');
                      heart.classList.add('text-red-500');
                      countSpan.textContent = (currentCount + 1).toLocaleString();
                    }
                  }
                } catch (error) {
                  console.error('Error toggling like:', error);
                }
              }
            </script>
            </div>

            <!-- Collections Modal -->
            <?php if ($user_id && $paste['user_id'] === $user_id): ?>
            <?php
            // Get user's collections
            $stmt = $db->prepare("SELECT * FROM collections WHERE user_id = ? ORDER BY name");
            $stmt->execute([$user_id]);
            $user_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get collections this paste is in
            $stmt = $db->prepare("SELECT c.* FROM collections c 
                                 JOIN collection_pastes cp ON c.id = cp.collection_id 
                                 WHERE cp.paste_id = ? AND c.user_id = ?");
            $stmt->execute([$paste['id'], $user_id]);
            $paste_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <!-- Collection Management Modal -->
            <div id="collectionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
              <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-md w-full max-h-[80vh] overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
                  <h3 class="text-lg font-semibold">Manage Collections</h3>
                  <button onclick="closeCollectionModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                  </button>
                </div>

                <div class="p-6 space-y-6">
                  <?php if (!empty($paste_collections)): ?>
                    <div>
                      <h4 class="font-medium mb-3 text-gray-900 dark:text-white">This paste is in:</h4>
                      <div class="space-y-2">
                        <?php foreach ($paste_collections as $collection): ?>
                          <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 p-3 rounded">
                            <span class="text-gray-900 dark:text-white"><?= htmlspecialchars($collection['name']) ?></span>
                            <form method="POST" class="inline" onsubmit="return confirmRemoveFromCollection('<?= htmlspecialchars($collection['name']) ?>')">
                              <input type="hidden" name="action" value="remove_from_collection">
                              <input type="hidden" name="collection_id" value="<?= $collection['id'] ?>">
                              <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">
                              <button type="submit" class="text-red-500 hover:text-red-700 p-1" title="Remove from collection">
                                <i class="fas fa-times"></i>
                              </button>
                            </form>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($user_collections)): ?>
                    <?php 
                    $paste_collection_ids = array_column($paste_collections, 'id');
                    $available_collections = array_filter($user_collections, function($collection) use ($paste_collection_ids) {
                      return !in_array($collection['id'], $paste_collection_ids);
                    });
                    ?>

                    <?php if (!empty($available_collections)): ?>
                      <div>
                        <h4 class="font-medium mb-3 text-gray-900 dark:text-white">Add to collection:</h4>
                        <form method="POST" class="space-y-4">
                          <input type="hidden" name="action" value="add_to_collection">
                          <input type="hidden" name="paste_id" value="<?= $paste['id'] ?>">
                          <select name="collection_id" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <?php foreach ($available_collections as $collection): ?>
                              <option value="<?= $collection['id'] ?>"><?= htmlspecialchars($collection['name']) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="w-full bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-folder-plus mr-2"></i>Add to Collection
                          </button>
                        </form>
                      </div>
                    <?php else: ?>
                      <div class="text-center py-4">
                        <p class="text-gray-600 dark:text-gray-400 mb-3">This paste is already in all your collections!</p>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-center py-4">
                      <p class="text-gray-600 dark:text-gray-400 mb-3">You haven't created any collections yet.</p>
                      <a href="?page=collections" class="inline-flex items-center text-blue-500 hover:text-blue-700">
                        <i class="fas fa-plus mr-2"></i>Create your first collection
                      </a>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>



            <!-- Embed Modal -->
            <div id="embedModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
              <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
                  <h3 class="text-xl font-semibold">
                    <i class="fas fa-code mr-2"></i>Embed "<?= htmlspecialchars($paste['title']) ?>"
                  </h3>
                  <button onclick="closeEmbedModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fas fa-times text-xl"></i>
                  </button>
                </div>

                <div class="p-6 space-y-6">
                  <!-- Live Preview -->
                  <div>
                    <label class="block text-sm font-medium mb-3">Live Preview</label>
                    <div class="border rounded-lg overflow-hidden bg-gray-50 dark:bg-gray-900">
                      <iframe id="embedPreview" 
                              src="embed.php?id=<?= $paste['id'] ?>&theme=<?= $theme ?>" 
                              width="100%" 
                              height="400" 
                              frameborder="0"
                              class="w-full"></iframe>
                    </div>
                  </div>

                  <!-- Customization Options -->
                  <div class="grid md:grid-cols-3 gap-4">
                    <div>
                      <label class="block text-sm font-medium mb-2">Theme</label>
                      <select id="embedTheme" onchange="updateEmbedPreview()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Dark</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-2">Width</label>
                      <input type="text" id="embedWidth" value="100%" onchange="updateEmbedCode()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-2">Height</label>
                      <input type="number" id="embedHeight" value="400" min="200" max="800" onchange="updateEmbedPreview(); updateEmbedCode()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    </div>
                  </div>

                  <!-- Embed Code -->
                  <div>
                    <div class="flex justify-between items-center mb-2">
                      <label class="block text-sm font-medium">Embed Code</label>
                      <button onclick="copyEmbedCode()" class="text-blue-500 hover:text-blue-700 text-sm">
                        <i class="fas fa-copy mr-1"></i>Copy Code
                      </button>
                    </div>
                    <textarea id="embedCodeTextarea" 
                              readonly 
                              rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-sm font-mono"></textarea>
                  </div>

                  <!-- Paste Information -->
                  <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h4 class="font-medium mb-2">Paste Information</h4>
                    <div class="grid md:grid-cols-3 gap-4 text-sm">
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Language:</span>
                        <span class="ml-1 font-medium"><?= htmlspecialchars($paste['language']) ?></span>
                      </div>
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Lines:</span>
                        <span class="ml-1 font-medium"><?= number_format(substr_count($paste['content'], "\n") + 1) ?></span>
                      </div>
                      <div>
                        <span class="text-gray-600 dark:text-gray-400">Characters:</span>
                        <span class="ml-1 font-medium"><?= number_format(strlen($paste['content'])) ?></span>
                      </div>
                    </div>
                  </div>

                  <!-- Usage Tips -->
                  <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">
                      <i class="fas fa-lightbulb mr-1"></i>Embedding Tips
                    </h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                      <li>• Embedded pastes are always public and read-only</li>
                      <li>• The embed will automatically update if the original paste is modified</li>
                      <li>• Responsive design adapts to different screen sizes</li>
                      <li>• Content is served with proper security headers</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div>
        <?php else: ?>
          <?php if(isset($_GET['page']) && $_GET['page'] === 'dashboard' && $user_id): ?>
            <div class="mb-8">
              <h2 class="text-xl font-semibold mb-4">Dashboard</h2>
              <div class="grid md:grid-cols-2 gap-4">
                <div>
                  <h3 class="text-lg font-medium mb-2">Your Pastes</h3>
                  <?php foreach ($user_pastes as $p): ?>
                    <a href="?id=<?= $p['id'] ?>" class="block p-4 bg-gray-100 dark:bg-gray-800 rounded mb-2 hover:bg-gray-200 dark:hover:bg-gray-700">
                      <?= htmlspecialchars($p['title']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
                <div>
                  <h3 class="text-lg font-medium mb-2">Favorite Pastes</h3>
                  <?php foreach ($favorite_pastes as $p): ?>
                    <a href="?id=<?= $p['id'] ?>" class="block p-4 bg-gray-100 dark:bg-gray-800 rounded mb-2 hover:bg-gray-200 dark:hover:bg-gray-700">
                      <?= htmlspecialchars($p['title']) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'login'): ?>
            <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 animate-fade-in">
              <h2 class="text-2xl font-bold mb-6">Login to PasteForge</h2>

              <!-- Error Messages -->
              <?php if (isset($_GET['error'])): ?>
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                  <?php if ($_GET['error'] === 'social_login_failed'): ?>
                    Social login failed. <?= isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Please try again.' ?>
                  <?php elseif ($_GET['error'] === 'already_forked'): ?>
                    You have already forked this paste.
                  <?php elseif ($_GET['error'] === 'own_paste'): ?>
                    You cannot fork your own paste.
                  <?php elseif ($_GET['error'] === 'fork_failed'): ?>
                    Failed to create fork. Please try again.
                  <?php elseif ($_GET['error'] === 'paste_not_found'): ?>
                    The paste you're trying to fork was not found.
                  <?php else: ?>
                    Invalid username or password.
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Success Messages -->
              <?php if (isset($_GET['forked'])): ?>
                <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
                  <i class="fas fa-code-branch mr-2"></i>Successfully forked! This is your copy of the original paste.
                </div>
              <?php endif; ?>

              <!-- Social Login Options -->
              <?php
              require_once 'social_media_integration.php';
              $social = new SocialMediaIntegration();
              $providers = $social->getEnabledProviders();
              ?>

              <?php if (!empty($providers)): ?>
                <div class="mb-6">
                  <p class="text-center text-gray-600 dark:text-gray-400 mb-4">Sign in with your social account</p>
                  <div class="space-y-3">
                    <?php foreach ($providers as $provider): ?>
                      <a href="social_login.php?action=login&provider=<?= $provider['name'] ?>" 
                         class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <i class="fab fa-<?= $provider['name'] ?> mr-3 text-lg"></i>
                        Continue with <?= ucfirst($provider['name']) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>

                  <div class="my-6 flex items-center">
                    <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                    <span class="px-4 text-sm text-gray-500 dark:text-gray-400">or</span>
                    <div class="flex-1 border-t border-gray-300 dark:border-gray-600"></div>
                  </div>
                </div>
              <?php endif; ?>

              <p class="mb-6">Login with your username and password.</p>
              <form method="POST" class="space-y-4" onsubmit="return validateLoginForm(event)">
                <input type="hidden" name="action" value="login">
                <div>
                  <label class="block text-sm font-medium mb-2">Username</label>
                  <input type="text" name="username" class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                <div>
                  <label class="block text-sm font-medium mb-2">Password</label>
                  <input type="password" name="password" class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600">
                  Login
                </button>
              </form>

              <div class="mt-6 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Don't have an account? 
                  <a href="?page=signup" class="text-blue-500 hover:text-blue-700">Sign up here</a>
                </p>
              </div>

              <script>
                function validateLoginForm(event) {
                  event.preventDefault();
                  const username = event.target.username.value.trim();
                  const password = event.target.password.value.trim();

                  if (!username || !password) {
                    Swal.fire({
                      icon: 'error',
                      title: 'Validation Error',
                      text: 'Please fill in all fields'
                    });
                    return false;
                  }

                  event.target.submit();
                  return true;
                }
              </script>
            </div>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'profile' && isset($_GET['username'])): ?>
            <?php
              $profile_username = $_GET['username'];
              $stmt = $db->prepare("SELECT id, username, created_at, tagline, website FROM users WHERE username = ?");
              $stmt->execute([$profile_username]);
              $profile_user = $stmt->fetch();

              if ($profile_user) {
                // Get total pastes and views
                // Get paste stats including favorites
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as paste_count, 
                        COALESCE(SUM(views), 0) as total_views,
                        (SELECT COUNT(*) 
                         FROM user_pastes 
                         WHERE is_favorite = 1 
                         AND paste_id IN (SELECT id FROM pastes WHERE user_id = ?)
                        ) as total_favorites
                    FROM pastes WHERE user_id = ?");
                $stmt->execute([$profile_user['id'], $profile_user['id']]);
                $stats = $stmt->fetch();

                // Get follower counts
                $stmt = $db->prepare("SELECT followers_count, following_count FROM users WHERE id = ?");
                $stmt->execute([$profile_user['id']]);
                $follow_counts = $stmt->fetch();

                // Check if current user is following this profile
                $is_following = false;
                if ($user_id && $user_id !== $profile_user['id']) {
                  $stmt = $db->prepare("SELECT 1 FROM user_follows WHERE follower_id = ? AND following_id = ?");
                  $stmt->execute([$user_id, $profile_user['id']]);
                  $is_following = (bool)$stmt->fetch();
                }

                // Get user's pastes
                $stmt = $db->prepare("SELECT id, title, views, created_at FROM pastes WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$profile_user['id']]);
                $user_pastes = $stmt->fetchAll();
            ?>
              <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                <!-- Profile Header -->
                <div class="p-8 border-b border-gray-200 dark:border-gray-700">
                  <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                    <?php
                      $stmt = $db->prepare("SELECT profile_image FROM users WHERE id = ?");
                      $stmt->execute([$profile_user['id']]);
                      $profile_avatar = $stmt->fetch()['profile_image'];
                    ?>
                    <img src="<?= $profile_avatar ?? 'https://www.gravatar.com/avatar/' . md5(strtolower($profile_user['username'])) . '?d=mp&s=128' ?>" 
                         alt="Profile" 
                         class="w-24 h-24 rounded-full border-4 border-blue-500 shadow-lg">

                    <div class="flex-1">
                      <h1 class="text-3xl md:text-4xl font-bold mb-2 text-gray-900 dark:text-white">@<?= htmlspecialchars($profile_user['username']) ?></h1>
                      <p class="text-gray-600 dark:text-gray-400 text-lg mb-3"><?= htmlspecialchars($profile_user['tagline'] ?? 'Just a dev sharing random code') ?></p>

                      <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                          <i class="far fa-calendar-alt"></i>
                          <span>Member since <?= human_time_diff($profile_user['created_at']) ?></span>
                        </div>
                        <?php if (!empty($profile_user['website'])): ?>
                          <a href="<?= htmlspecialchars($profile_user['website']) ?>" target="_blank" class="flex items-center gap-2 text-blue-500 hover:text-blue-600 transition-colors">
                            <i class="fas fa-globe"></i>
                            <span>Website</span>
                          </a>
                        <?php endif; ?>
                      </div>
                    </div>

                    <!-- Follow Button -->
                    <?php if ($user_id && $user_id !== $profile_user['id']): ?>
                      <div>
                        <form method="POST" id="followForm">
                          <input type="hidden" name="action" value="<?= $is_following ? 'unfollow' : 'follow' ?>">
                          <input type="hidden" name="target_user_id" value="<?= $profile_user['id'] ?>">
                          <button type="submit" class="<?= $is_following ? 'bg-gray-500 hover:bg-gray-600' : 'bg-blue-500 hover:bg-blue-600' ?> text-white px-6 py-3 rounded-lg font-medium transition-colors shadow-lg">
                            <i class="fas <?= $is_following ? 'fa-user-minus' : 'fa-user-plus' ?> mr-2"></i>
                            <?= $is_following ? 'Unfollow' : 'Follow' ?>
                          </button>
                        </form>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                  <nav class="flex space-x-0" role="tablist">
                    <button onclick="switchTab('overview')" id="tab-overview" class="profile-tab active-tab px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 transition-all duration-200" role="tab">
                      <i class="fas fa-chart-line mr-2"></i>Overview
                    </button>
                    <button onclick="switchTab('achievements')" id="tab-achievements" class="profile-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-trophy mr-2"></i>Achievements
                    </button>
                    <button onclick="switchTab('collections')" id="tab-collections" class="profile-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-folder mr-2"></i>Collections
                    </button>
                    <button onclick="switchTab('pastes')" id="tab-pastes" class="profile-tab px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 transition-all duration-200" role="tab">
                      <i class="fas fa-file-alt mr-2"></i>Recent Pastes
                    </button>
                  </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-8">
                  <!-- Overview Tab -->
                  <div id="content-overview" class="tab-content" role="tabpanel">
                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-6 mb-8">
                      <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-xl p-6 border border-blue-200 dark:border-blue-700">
                        <div class="flex items-center justify-between">
                          <div>
                            <p class="text-blue-600 dark:text-blue-400 text-sm font-medium">Total Pastes</p>
                            <p class="text-3xl font-bold text-blue-900 dark:text-blue-100"><?= number_format($stats['paste_count']) ?></p>
                          </div>
                          <i class="fas fa-file-code text-2xl text-blue-500 opacity-60"></i>
                        </div>
                      </div>

                      <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 rounded-xl p-6 border border-green-200 dark:border-green-700">
                        <div class="flex items-center justify-between">
                          <div>
                            <p class="text-green-600 dark:text-green-400 text-sm font-medium">Total Views</p>
                            <p class="text-3xl font-bold text-green-900 dark:text-green-100"><?= number_format($stats['total_views']) ?></p>
                          </div>
                          <i class="fas fa-eye text-2xl text-green-500 opacity-60"></i>
                        </div>
                      </div>

                      <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/30 dark:to-yellow-800/30 rounded-xl p-6 border border-yellow-200 dark:border-yellow-700">
                        <div class="flex items-center justify-between">
                          <div>
                            <p class="text-yellow-600 dark:text-yellow-400 text-sm font-medium">Total Likes</p>
                            <p class="text-3xl font-bold text-yellow-900 dark:text-yellow-100"><?= number_format($stats['total_favorites']) ?></p>
                          </div>
                          <i class="fas fa-star text-2xl text-yellow-500 opacity-60"></i>
                        </div>
                      </div>

                      <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 rounded-xl p-6 border border-purple-200 dark:border-purple-700">
                        <div class="flex items-center justify-between">
                          <div>
                            <p class="text-purple-600 dark:text-purple-400 text-sm font-medium">Followers</p>
                            <p class="text-3xl font-bold text-purple-900 dark:text-purple-100"><?= number_format($follow_counts['followers_count'] ?? 0) ?></p>
                          </div>
                          <i class="fas fa-users text-2xl text-purple-500 opacity-60"></i>
                        </div>
                      </div>

                      <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 dark:from-indigo-900/30 dark:to-indigo-800/30 rounded-xl p-6 border border-indigo-200 dark:border-indigo-700">
                        <div class="flex items-center justify-between">
                          <div>
                            <p class="text-indigo-600 dark:text-indigo-400 text-sm font-medium">Following</p>
                            <p class="text-3xl font-bold text-indigo-900 dark:text-indigo-100"><?= number_format($follow_counts['following_count'] ?? 0) ?></p>
                          </div>
                          <i class="fas fa-user-friends text-2xl text-indigo-500 opacity-60"></i>
                        </div>
                      </div>
                    </div>

                    <!-- Quick Summary -->
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-6">
                      <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Profile Summary</h3>
                      <div class="grid md:grid-cols-2 gap-6 text-sm">
                        <div>
                          <p class="text-gray-600 dark:text-gray-400 mb-2">
                            <span class="font-medium">Account Status:</span> Active Member
                          </p>
                          <p class="text-gray-600 dark:text-gray-400 mb-2">
                            <span class="font-medium">Join Date:</span> <?= date('F j, Y', $profile_user['created_at']) ?>
                          </p>
                          <p class="text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Activity:</span> <?= $stats['paste_count'] > 0 ? 'Regular contributor' : 'New member' ?>
                          </p>
                        </div>
                        <div>
                          <p class="text-gray-600 dark:text-gray-400 mb-2">
                            <span class="font-medium">Total Engagement:</span> <?= number_format($stats['total_views'] + $stats['total_favorites']) ?> interactions
                          </p>
                          <p class="text-gray-600 dark:text-gray-400 mb-2">
                            <span class="font-medium">Average Views:</span> <?= $stats['paste_count'] > 0 ? number_format($stats['total_views'] / $stats['paste_count']) : '0' ?> per paste
                          </p>
                          <p class="text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Social Reach:</span> <?= number_format($follow_counts['followers_count'] ?? 0) ?> followers
                          </p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Achievements Tab -->
                  <div id="content-achievements" class="tab-content hidden" role="tabpanel">
                    <?php
                    require_once 'user_profile_achievements.php';
                    displayUserAchievements($profile_user['id'], true);
                    ?>
                  </div>

                  <!-- Collections Tab -->
                  <div id="content-collections" class="tab-content hidden" role="tabpanel">
                    <?php
                    // Get user's public collections
                    $stmt = $db->prepare("SELECT c.*, COUNT(cp.paste_id) as paste_count FROM collections c 
                                         LEFT JOIN collection_pastes cp ON c.id = cp.collection_id 
                                         WHERE c.user_id = ? AND c.is_public = 1 
                                         GROUP BY c.id 
                                         ORDER BY c.updated_at DESC");
                    $stmt->execute([$profile_user['id']]);
                    $user_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (!empty($user_collections)): ?>
                      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($user_collections as $collection): ?>
                          <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors shadow-sm">
                            <div class="flex items-start justify-between mb-4">
                              <div class="flex-1">
                                <h3 class="font-semibold text-lg mb-2">
                                  <a href="?page=collection&collection_id=<?= $collection['id'] ?>" class="text-blue-500 hover:text-blue-700 transition-colors">
                                    <?= htmlspecialchars($collection['name']) ?>
                                  </a>
                                </h3>
                                <?php if ($collection['description']): ?>
                                  <p class="text-gray-600 dark:text-gray-400 text-sm mb-3 line-clamp-3">
                                    <?= htmlspecialchars($collection['description']) ?>
                                  </p>
                                <?php endif; ?>
                              </div>
                              <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs font-medium">
                                Public
                              </span>
                            </div>

                            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                              <div class="flex items-center gap-1">
                                <i class="fas fa-file-alt"></i>
                                <span><?= $collection['paste_count'] ?> pastes</span>
                              </div>
                              <span>Updated <?= human_time_diff($collection['updated_at']) ?></span>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <div class="text-center py-12">
                        <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No public collections</h3>
                        <p class="text-gray-500">This user hasn't created any public collections yet.</p>
                      </div>
                    <?php endif; ?>
                  </div>

                  <!-- Recent Pastes Tab -->
                  <div id="content-pastes" class="tab-content hidden" role="tabpanel">
                    <?php if (!empty($user_pastes)): ?>
                      <div class="bg-gray-50 dark:bg-gray-700 rounded-lg overflow-hidden">
                        <table class="w-full">
                          <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-800">
                              <th class="text-left p-4 text-gray-900 dark:text-white font-semibold">Title</th>
                              <th class="text-center p-4 text-gray-900 dark:text-white font-semibold">Language</th>
                              <th class="text-right p-4 text-gray-900 dark:text-white font-semibold">Views</th>
                              <th class="text-right p-4 text-gray-900 dark:text-white font-semibold">Created</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($user_pastes as $paste): ?>
                              <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <td class="p-4">
                                  <a href="?id=<?= $paste['id'] ?>" class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 font-medium transition-colors">
                                    <?= htmlspecialchars($paste['title']) ?>
                                  </a>
                                </td>
                                <td class="p-4 text-center">
                                  <span class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-xs font-medium">
                                    <?= htmlspecialchars($paste['language'] ?? 'text') ?>
                                  </span>
                                </td>
                                <td class="p-4 text-right text-gray-900 dark:text-white font-medium"><?= number_format($paste['views']) ?></td>
                                <td class="p-4 text-right text-gray-500 dark:text-gray-400"><?= date('M j, Y', $paste['created_at']) ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    <?php else: ?>
                      <div class="text-center py-12">
                        <i class="fas fa-file-alt text-4xl text-gray-400 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No pastes yet</h3>
                        <p class="text-gray-500">This user hasn't created any pastes yet.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Profile Tab Switching Script -->
              <script>
                function switchTab(tabName) {
                  // Hide all tab contents
                  document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                  });

                  // Remove active state from all tabs
                  document.querySelectorAll('.profile-tab').forEach(tab => {
                    tab.classList.remove('active-tab', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
                    tab.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                  });

                  // Show selected tab content
                  document.getElementById('content-' + tabName).classList.remove('hidden');

                  // Add active state to selected tab
                  const activeTab = document.getElementById('tab-' + tabName);
                  activeTab.classList.add('active-tab', 'border-blue-500', 'text-blue-600', 'dark:text-blue-400', 'bg-blue-50', 'dark:bg-blue-900/20');
                  activeTab.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                }

                // Add smooth transition animations
                document.addEventListener('DOMContentLoaded', function() {
                  // Add fade-in animation to tab content
                  const style = document.createElement('style');
                  style.textContent = `
                    .tab-content {
                      opacity: 1;
                      transition: opacity 0.2s ease-in-out;
                    }
                    .tab-content.hidden {
                      opacity: 0;
                    }
                    .profile-tab {
                      position: relative;
                      overflow: hidden;
                    }
                    .profile-tab:hover {
                      background-color: rgba(59, 130, 246, 0.05);
                    }
                    .dark .profile-tab:hover {
                      background-color: rgba(59, 130, 246, 0.1);
                    }
                  `;
                  document.head.appendChild(style);
                });
              </script>
            <?php } else { ?>
              <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                User not found
              </div>
            <?php } ?>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'account'): ?>
            <?php 
            if (!$user_id) {
              header('Location: /?page=login');
              exit;
            }
            
            // Include the account page
            include 'account.php';
            ?>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'pricing'): ?>
            <?php include 'pricing.php'; ?>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'settings'): ?>
            <?php 
            if (!$user_id) {
              header('Location: /?page=login');
              exit;
            }

            // Handle social account management
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
              if ($_POST['action'] === 'unlink_social') {
                require_once 'social_media_integration.php';
                $social = new SocialMediaIntegration();
                $platform = $_POST['platform'] ?? '';

                if ($platform && $social->unlinkSocialAccount($user_id, $platform)) {
                  echo '<div class="mb-4 p-4 bg-green-100 text-green-700 rounded">Social account unlinked successfully!</div>';
                } else {
                  echo '<div class="mb-4 p-4 bg-red-100 text-red-700 rounded">Failed to unlink social account.</div>';
                }
              }
            }

            // Set flag for user-settings.php to know it's included
            $settings_user_id = $user_id;
            $settings_username = $username;
            include 'user-settings.php';
            ?>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'edit-profile'): ?>
            <?php if (!$user_id) header('Location: /'); ?>

            <div class="max-w-2xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 animate-fade-in">
              <h2 class="text-2xl font-bold mb-6">Edit Profile</h2>

              <?php
              // Handle profile updates
              if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                if ($_POST['action'] === 'update_profile') {
                  $website = filter_var($_POST['website'], FILTER_SANITIZE_URL);
                  $tagline = htmlspecialchars(substr(trim($_POST['tagline']), 0, 100), ENT_QUOTES, 'UTF-8');

                  // Handle avatar upload
                  $avatar_path = null;
                  if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['avatar']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed)) {
                      $avatar_path = 'avatars/' . $user_id . '.' . $ext;
                      if (!file_exists('avatars')) {
                        mkdir('avatars', 0777, true);
                      }
                      move_uploaded_file($_FILES['avatar']['tmp_name'], $avatar_path);
                    }
                  }

                  // Update profile in database
                  if ($avatar_path) {
                    $stmt = $db->prepare("UPDATE users SET website = ?, tagline = ?, profile_image = ? WHERE id = ?");
                    $stmt->execute([$website, $tagline, $avatar_path, $user_id]);
                  } else {
                    $stmt = $db->prepare("UPDATE users SET website = ?, tagline = ? WHERE id = ?");
                    $stmt->execute([$website, $tagline, $user_id]);
                  }

                  echo '<div class="mb-4 p-4 bg-green-100 text-green-700 rounded">Profile updated successfully!</div>';

                  // Refresh user data to show updated values
                  $stmt = $db->prepare("SELECT website, profile_image, tagline FROM users WHERE id = ?");
                  $stmt->execute([$user_id]);
                  $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                }
              }

              // Get current user data if not already set from form submission
              if (!isset($user_data)) {
                $stmt = $db->prepare("SELECT website, profile_image, tagline FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
              }
              ?>

              <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="update_profile">

                <div>
                  <label class="block text-sm font-medium mb-2">Profile Picture</label>
                  <div class="flex items-center space-x-4">
                    <img src="<?= $user_data['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($username)).'?d=mp&s=128' ?>" 
                         alt="Current avatar" 
                         class="w-24 h-24 rounded-full">
                    <div class="flex-1">
                      <input type="file" 
                             name="avatar" 
                             accept="image/*"
                             class="w-full px-3 py-2 border rounded-lg dark:bg-gray-700">
                      <p class="mt-1 text-sm text-gray-500">Accepted formats: JPG, PNG, GIF</p>
                    </div>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-2">Tagline</label>
                  <input type="text" 
                         name="tagline" 
                         maxlength="100"
                         value="<?= htmlspecialchars($user_data['tagline'] ?? 'Just a dev sharing random code') ?>"
                         class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700"
                         placeholder="A short description about yourself">
                  <p class="mt-1 text-sm text-gray-500">Maximum 100 characters</p>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-2">Website</label>
                  <input type="url" 
                         name="website" 
                         value="<?= htmlspecialchars($user_data['website'] ?? '') ?>"
                         class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700"
                         placeholder="https://example.com">
                </div>

                <button type="submit" class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600">
                  Save Changes
                </button>
              </form>
            </div>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'collections'): ?>
            <?php if (!$user_id) header('Location: /'); ?>

            <?php
            // Handle edit mode
            $editing_collection = null;
            if (isset($_GET['edit'])) {
              $stmt = $db->prepare("SELECT * FROM collections WHERE id = ? AND user_id = ?");
              $stmt->execute([$_GET['edit'], $user_id]);
              $editing_collection = $stmt->fetch();
            }

            // Get user's collections with more details
            $stmt = $db->prepare("SELECT c.*, COUNT(cp.paste_id) as paste_count,
                                         (SELECT COUNT(*) FROM collection_pastes cp2 
                                          JOIN pastes p ON cp2.paste_id = p.id 
                                          WHERE cp2.collection_id = c.id AND p.views > 0) as viewed_pastes
                                 FROM collections c 
                                 LEFT JOIN collection_pastes cp ON c.id = cp.collection_id 
                                 WHERE c.user_id = ? 
                                 GROUP BY c.id 
                                 ORDER BY c.updated_at DESC");
            $stmt->execute([$user_id]);
            $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div class="flex justify-between items-center mb-6">
                <div>
                  <h2 class="text-2xl font-bold">My Collections</h2>
                  <p class="text-gray-600 dark:text-gray-400">Organize your pastes into collections</p>
                </div>
                <button onclick="toggleCreateForm()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                  <i class="fas fa-plus"></i> Create Collection
                </button>
              </div>

              <!-- Create/Edit Collection Form -->
              <div id="createCollectionForm" class="<?= $editing_collection ? '' : 'hidden' ?> mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-4">
                  <?= $editing_collection ? 'Edit Collection' : 'Create New Collection' ?>
                </h3>
                <form method="POST" class="space-y-4">
                  <input type="hidden" name="action" value="<?= $editing_collection ? 'edit_collection' : 'create_collection' ?>">
                  <?php if ($editing_collection): ?>
                    <input type="hidden" name="collection_id" value="<?= $editing_collection['id'] ?>">
                  <?php endif; ?>

                  <div>
                    <label class="block text-sm font-medium mb-2">Collection Name:</label>
                    <input type="text" name="collection_name" required 
                           value="<?= $editing_collection ? htmlspecialchars($editing_collection['name']) : '' ?>"
                           class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600">
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-2">Description:</label>
                    <textarea name="collection_description" rows="3" 
                              class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600"><?= $editing_collection ? htmlspecialchars($editing_collection['description']) : '' ?></textarea>
                  </div>

                  <div>
                    <label class="flex items-center space-x-2">
                      <input type="checkbox" name="is_public" 
                             <?= !$editing_collection || $editing_collection['is_public'] ? 'checked' : '' ?> 
                             class="rounded">
                      <span>Public collection (others can view)</span>
                    </label>
                  </div>

                  <div class="flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                      <i class="fas fa-save mr-1"></i>
                      <?= $editing_collection ? 'Update Collection' : 'Create Collection' ?>
                    </button>
                    <?php if ($editing_collection): ?>
                      <a href="?page=collections" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        <i class="fas fa-times mr-1"></i>Cancel
                      </a>
                    <?php endif; ?>
                  </div>
                </form>
              </div>

              <!-- Collections Grid -->
              <?php if (!empty($collections)): ?>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                  <?php foreach ($collections as $collection): ?>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                      <!-- Collection Header -->
                      <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                          <h3 class="font-semibold text-lg mb-1"><?= htmlspecialchars($collection['name']) ?></h3>
                          <div class="flex items-center gap-2 text-sm">
                            <span class="px-2 py-1 rounded text-xs <?= $collection['is_public'] ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-200' ?>">
                              <?= $collection['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                          </div>
                        </div>
                        <div class="flex gap-1">
                          <a href="?page=collections&edit=<?= $collection['id'] ?>" 
                             class="text-blue-500 hover:text-blue-700 p-1" title="Edit">
                            <i class="fas fa-edit text-sm"></i>
                          </a>
                          <button onclick="deleteCollection(<?= $collection['id'] ?>)" 
                                  class="text-red-500 hover:text-red-700 p-1" title="Delete">
                            <i class="fas fa-trash text-sm"></i>
                          </button>
                        </div>
                      </div>

                      <!-- Collection Description -->
                      <?php if ($collection['description']): ?>
                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-2">
                          <?= htmlspecialchars($collection['description']) ?>
                        </p>
                      <?php endif; ?>

                      <!-- Collection Stats -->
                      <div class="space-y-2 mb-4">
                        <div class="flex items-center justify-between text-sm">
                          <span class="text-gray-600 dark:text-gray-400">
                            <i class="fas fa-file-alt mr-1"></i>Total Pastes
                          </span>
                          <span class="font-medium"><?= number_format($collection['paste_count']) ?></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                          <span class="text-gray-600 dark:text-gray-400">
                            <i class="fas fa-calendar mr-1"></i>Updated
                          </span>
                          <span class="font-medium"><?= human_time_diff($collection['updated_at']) ?></span>
                        </div>
                      </div>

                      <!-- Action Button -->
                      <div class="flex gap-2">
                        <a href="?page=collection&collection_id=<?= $collection['id'] ?>" 
                           class="flex-1 text-center bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600 text-sm">
                          <i class="fas fa-folder-open mr-1"></i>View Collection
                        </a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="text-center py-12">
                  <i class="fas fa-folder-open text-4xl text-gray-400 mb-4"></i>
                  <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">No collections yet</h3>
                  <p class="text-gray-500 mb-4">Collections help you organize your pastes into folders for better management.</p>
                  <button onclick="toggleCreateForm()" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">
                    <i class="fas fa-plus mr-2"></i>Create your first collection
                  </button>
                </div>
              <?php endif; ?>
            </div>

            <script>
              function toggleCreateForm() {
                const form = document.getElementById('createCollectionForm');
                form.classList.toggle('hidden');
                if (!form.classList.contains('hidden')) {
                  form.querySelector('input[name="collection_name"]').focus();
                }
              }

              function deleteCollection(collectionId) {
                Swal.fire({
                  title: 'Delete Collection?',
                  text: "This will permanently delete the collection. Pastes in the collection will not be deleted.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#d33',
                  cancelButtonColor: '#3085d6',
                  confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                  if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                      <input type="hidden" name="action" value="delete_collection">
                      <input type="hidden" name="collection_id" value="${collectionId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                  }
                });
              }
            </script>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'collection' && isset($_GET['collection_id'])): ?>
            <?php
            $collection_id = $_GET['collection_id'];
            $stmt = $db->prepare("SELECT * FROM collections WHERE id = ? AND (user_id = ? OR is_public = 1)");
            $stmt->execute([$collection_id, $user_id]);
            $collection = $stmt->fetch();

            if (!$collection) {
              echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">Collection not found</div>';
            } else {
              // Get pastes in this collection with complete information
              $stmt = $db->prepare("
                SELECT p.*, u.username, u.profile_image, cp.added_at,
                       (SELECT COUNT(*) FROM comments WHERE paste_id = p.id) as comment_count,
                       (SELECT COUNT(*) FROM user_pastes WHERE paste_id = p.id AND is_favorite = 1) as favorite_count
                FROM pastes p 
                LEFT JOIN users u ON p.user_id = u.id 
                JOIN collection_pastes cp ON p.id = cp.paste_id 
                WHERE cp.collection_id = ? 
                ORDER BY cp.added_at DESC
              ");
              $stmt->execute([$collection_id]);
              $collection_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

              // Get collection owner info
              $stmt = $db->prepare("SELECT u.username, u.profile_image FROM users u WHERE u.id = ?");
              $stmt->execute([$collection['user_id']]);
              $collection_owner = $stmt->fetch();
            ?>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <!-- Collection Header -->
              <div class="flex justify-between items-start mb-6">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <i class="fas fa-folder text-2xl text-blue-500"></i>
                    <h2 class="text-2xl font-bold"><?= htmlspecialchars($collection['name']) ?></h2>
                    <?php if (!$collection['is_public']): ?>
                      <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded">Private</span>
                    <?php endif; ?>
                  </div>

                  <?php if ($collection['description']): ?>
                    <p class="text-gray-600 dark:text-gray-400 mb-3"><?= htmlspecialchars($collection['description']) ?></p>
                  <?php endif; ?>

                  <div class="flex items-center gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                      <img src="<?= $collection_owner['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($collection_owner['username'])).'?d=mp&s=24' ?>" 
                           class="w-6 h-6 rounded-full" alt="Owner">
                      <span>by <a href="?page=profile&username=<?= urlencode($collection_owner['username']) ?>" class="hover:text-blue-500">@<?= htmlspecialchars($collection_owner['username']) ?></a></span>
                    </div>
                    <span>•</span>
                    <span><?= count($collection_pastes) ?> pastes</span>
                    <span>•</span>
                    <span>Created <?= human_time_diff($collection['created_at']) ?></span>
                    <?php if ($collection['updated_at'] != $collection['created_at']): ?>
                      <span>•</span>
                      <span>Updated <?= human_time_diff($collection['updated_at']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <?php if ($user_id && $collection['user_id'] === $user_id): ?>
                  <div class="flex gap-2">
                    <button onclick="editCollection(<?= $collection['id'] ?>)" class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                      <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                    <button onclick="deleteCollection(<?= $collection['id'] ?>)" class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                      <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Pastes List -->
              <?php if (empty($collection_pastes)): ?>
                <div class="text-center py-12">
                  <i class="fas fa-file-alt text-4xl text-gray-400 mb-4"></i>
                  <h3 class="text-lg font-medium text-gray-600 dark:text-gray-400 mb-2">This collection is empty</h3>
                  <p class="text-gray-500">No pastes have been added to this collection yet.</p>
                  <?php if ($user_id && $collection['user_id'] === $user_id): ?>
                    <div class="mt-4">
                      <a href="/" class="text-blue-500 hover:text-blue-700">Create a new paste</a> and add it to this collection.
                    </div>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="space-y-4">
                  <?php foreach ($collection_pastes as $paste): ?>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                      <!-- Paste Header -->
                      <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                          <h3 class="text-lg font-medium mb-2">
                            <a href="?id=<?= $paste['id'] ?>" class="text-blue-500 hover:text-blue-700">
                              <?= htmlspecialchars($paste['title']) ?>
                            </a>
                          </h3>

                          <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400 mb-3">
                            <div class="flex items-center gap-2">
                              <img src="<?= $paste['profile_image'] ?? 'https://www.gravatar.com/avatar/'.md5(strtolower($paste['username'] ?? 'anonymous')).'?d=mp&s=20' ?>" 
                                   class="w-5 h-5 rounded-full" alt="Author">
                              <span>
                                <?php if ($paste['username']): ?>
                                  <a href="?page=profile&username=<?= urlencode($paste['username']) ?>" class="hover:text-blue-500">
                                    @<?= htmlspecialchars($paste['username']) ?>
                                  </a>
                                <?php else: ?>
                                  Anonymous
                                <?php endif; ?>
                              </span>
                            </div>
                            <span>•</span>
                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                              <?= htmlspecialchars($paste['language']) ?>
                            </span>
                            <span>•</span>
                            <span>Created <?= human_time_diff($paste['created_at']) ?></span>
                            <span>•</span>
                            <span>Added to collection <?= human_time_diff($paste['added_at']) ?></span>
                          </div>

                          <!-- Paste Tags -->
                          <?php if (!empty($paste['tags'])): ?>
                            <div class="mb-3">
                              <?php foreach (explode(',', $paste['tags']) as $tag): ?>
                                <span class="inline-block bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs px-2 py-1 rounded mr-2 mb-1">
                                  <?= htmlspecialchars(trim($tag)) ?>
                                </span>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>

                          <!-- Content Preview -->
                          <?php if (strlen($paste['content']) > 0): ?>
                            <div class="bg-white dark:bg-gray-800 rounded p-3 mb-3 font-mono text-sm">
                              <code class="language-<?= htmlspecialchars($paste['language']) ?>">
                                <?= htmlspecialchars(substr($paste['content'], 0, 200)) ?><?= strlen($paste['content']) > 200 ? '...' : '' ?>
                              </code>
                            </div>
                          <?php endif; ?>
                        </div>

                        <?php if ($user_id && $collection['user_id'] === $user_id): ?>
                          <div class="ml-4">
                            <button onclick="removeFromCollection(<?= $collection['id'] ?>, <?= $paste['id'] ?>)" 
                                    class="text-red-500 hover:text-red-700 p-2" 
                                    title="Remove from collection">
                              <i class="fas fa-times"></i>
                            </button>
                          </div>
                        <?php endif; ?>
                      </div>

                      <!-- Paste Stats -->
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-6 text-sm text-gray-500">
                          <span><i class="fas fa-eye mr-1"></i><?= number_format($paste['views']) ?> views</span>
                          <span><i class="fas fa-star mr-1"></i><?= number_format($paste['favorite_count']) ?> likes</span>
                          <span><i class="fas fa-comment mr-1"></i><?= number_format($paste['comment_count']) ?> comments</span>
                          <?php if ($paste['expire_time']): ?>
                            <span class="text-orange-500">
                              <i class="fas fa-clock mr-1"></i>Expires <?= date('M j, Y', $paste['expire_time']) ?>
                            </span>
                          <?php endif; ?>
                        </div>

                        <div class="flex gap-2">
                          <a href="?id=<?= $paste['id'] ?>" 
                             class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                            <i class="fas fa-eye mr-1"></i>View
                          </a>
                          <a href="?id=<?= $paste['id'] ?>&raw=1" 
                             target="_blank"
                             class="px-3 py-1 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                            <i class="fas fa-code mr-1"></i>Raw
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <script>
              function removeFromCollection(collectionId, pasteId) {
                Swal.fire({
                  title: 'Remove from Collection?',
                  text: "This will remove the paste from this collection but won't delete the paste itself.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#d33',
                  cancelButtonColor: '#3085d6',
                  confirmButtonText: 'Yes, remove it!'
                }).then((result) => {
                  if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                      <input type="hidden" name="action" value="remove_from_collection">
                      <input type="hidden" name="collection_id" value="${collectionId}">
                      <input type="hidden" name="paste_id" value="${pasteId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                  }
                });
              }

              function editCollection(collectionId) {
                // Redirect to edit collection page
                window.location.href = `?page=collections&edit=${collectionId}`;
              }

              function deleteCollection(collectionId) {
                Swal.fire({
                  title: 'Delete Collection?',
                  text: "This will permanently delete the collection. Pastes in the collection will not be deleted.",
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#d33',
                  cancelButtonColor: '#3085d6',
                  confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                  if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                      <input type="hidden" name="action" value="delete_collection">
                      <input type="hidden" name="collection_id" value="${collectionId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                  }
                });
              }
            </script>

            <?php } ?>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'templates'): ?>
            <?php
            // Get template categories
            $stmt = $db->query("SELECT DISTINCT category FROM paste_templates WHERE is_public = 1" . ($user_id ? " OR created_by = '$user_id'" : "") . " ORDER BY category");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Get templates
            $category_filter = $_GET['category'] ?? 'all';
            $where = "WHERE is_public = 1";
            if ($user_id) {
              $where .= " OR created_by = '$user_id'";
            }
            if ($category_filter !== 'all') {
              $where .= " AND category = " . $db->quote($category_filter);
            }

            $stmt = $db->query("SELECT *, (created_by IS NOT NULL) as is_custom FROM paste_templates $where ORDER BY is_custom ASC, usage_count DESC, name ASC");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Code Templates</h2>
                <?php if ($user_id): ?>
                  <button onclick="toggleCreateTemplateForm()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    <i class="fas fa-plus"></i> Create Template
                  </button>
                <?php endif; ?>
              </div>

              <!-- Create Template Form -->
              <?php if ($user_id): ?>
              <div id="createTemplateForm" class="hidden mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <form method="POST" class="space-y-4">
                  <input type="hidden" name="action" value="save_template">
                  <div class="grid md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium mb-2">Template Name:</label>
                      <input type="text" name="template_name" required 
                             class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600" 
                             placeholder="My Custom Template">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-2">Category:</label>
                      <input type="text" name="template_category" 
                             class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600" 
                             placeholder="general" value="general">
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Description:</label>
                    <input type="text" name="template_description" 
                           class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600" 
                           placeholder="Brief description of the template">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Language:</label>
                    <select name="template_language" class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600">
                      <option value="plaintext">Plain Text</option>
                      <option value="javascript">JavaScript</option>
                      <option value="python">Python</option>
                      <option value="php">PHP</option>
                      <option value="html">HTML</option>
                      <option value="css">CSS</option>
                      <option value="sql">SQL</option>
                      <option value="java">Java</option>
                      <option value="cpp">C++</option>
                      <option value="csharp">C#</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Template Content:</label>
                    <textarea name="template_content" required rows="10" 
                              class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600 font-mono" 
                              placeholder="Enter your template code here..."></textarea>
                  </div>
                  <div>
                    <label class="flex items-center space-x-2">
                      <input type="checkbox" name="is_public" checked class="rounded">
                      <span>Make template public (others can use it)</span>
                    </label>
                  </div>
                  <div class="flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                      Save Template
                    </button>
                    <button type="button" onclick="toggleCreateTemplateForm()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
              <?php endif; ?>

              <!-- Category Filter -->
              <div class="mb-6">
                <div class="flex flex-wrap gap-2">
                  <a href="?page=templates" class="px-3 py-1 rounded <?= $category_filter === 'all' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">
                    All
                  </a>
                  <?php foreach ($categories as $category): ?>
                    <a href="?page=templates&category=<?= urlencode($category) ?>" 
                       class="px-3 py-1 rounded <?= $category_filter === $category ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">
                      <?= ucfirst($category) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Templates Grid -->
              <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($templates as $template): ?>
                  <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                      <div class="flex-1">
                        <h3 class="font-semibold text-lg"><?= htmlspecialchars($template['name']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                          <?= htmlspecialchars($template['description']) ?>
                        </p>
                      </div>
                      <?php if ($template['is_custom'] && $template['created_by'] === $user_id): ?>
                        <form method="POST" class="inline ml-2">
                          <input type="hidden" name="action" value="delete_template">
                          <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                          <button type="submit" onclick="return confirm('Delete this template?')" 
                                  class="text-red-500 hover:text-red-700" title="Delete Template">
                            <i class="fas fa-trash text-sm"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                      <div class="flex items-center gap-2">
                        <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded text-xs">
                          <?= htmlspecialchars($template['language']) ?>
                        </span>
                        <span class="px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded text-xs">
                          <?= htmlspecialchars($template['category']) ?>
                        </span>
                        <?php if ($template['is_custom']): ?>
                          <span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded text-xs">
                            Custom
                          </span>
                        <?php endif; ?>
                      </div>
                      <span class="text-gray-500"><?= $template['usage_count'] ?> uses</span>
                    </div>

                    <div class="mt-3 flex gap-2">
                      <button onclick="useTemplate(<?= $template['id'] ?>)" 
                              class="flex-1 bg-blue-500 text-white py-2 px-3 rounded text-sm hover:bg-blue-600">
                        <i class="fas fa-code"></i> Use Template
                      </button>
                      <button onclick="previewTemplate(<?= $template['id'] ?>)" 
                              class="bg-gray-500 text-white py-2 px-3 rounded text-sm hover:bg-gray-600">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <?php if (empty($templates)): ?>
                <div class="text-center py-8">
                  <i class="fas fa-code text-4xl text-gray-400 mb-4"></i>
                  <p class="text-gray-500">No templates found in this category.</p>
                  <?php if ($user_id): ?>
                    <button onclick="toggleCreateTemplateForm()" class="mt-4 text-blue-500 hover:text-blue-700">
                      Create your first template
                    </button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Template Preview Modal -->
            <div id="templatePreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
              <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-4xl w-full max-h-[80vh] overflow-hidden">
                <div class="flex justify-between items-center p-4 border-b dark:border-gray-700">
                  <h3 id="previewTitle" class="text-lg font-semibold">Template Preview</h3>
                  <button onclick="closeTemplatePreview()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                <div class="p-4 overflow-auto max-h-[60vh]">
                  <pre id="previewContent" class="bg-gray-100 dark:bg-gray-700 p-4 rounded overflow-x-auto"><code></code></pre>
                </div>
                <div class="p-4 border-t dark:border-gray-700 flex gap-2">
                  <button id="usePreviewTemplate" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Use This Template
                  </button>
                  <button onclick="closeTemplatePreview()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Close
                  </button>
                </div>
              </div>
            </div>

            <script>
              function toggleCreateTemplateForm() {
                const form = document.getElementById('createTemplateForm');
                form.classList.toggle('hidden');
              }

              function useTemplate(templateId) {
                fetch(`?action=get_template&id=${templateId}`)
                  .then(response => response.json())
                  .then(template => {
                    if (template && !template.error) {
                      // Store template data in sessionStorage
                      sessionStorage.setItem('templateContent', template.content);
                      sessionStorage.setItem('templateLanguage', template.language);
                      sessionStorage.setItem('templateName', template.name);

                      // Redirect to home page
                      window.location.href = '/?template=1';
                    } else {
                      alert('Error loading template');
                    }
                  })
                  .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading template');
                  });
              }

              function previewTemplate(templateId) {
                fetch(`?action=get_template&id=${templateId}`)
                  .then(response => response.json())
                  .then(template => {
                    if (template && !template.error) {
                      document.getElementById('previewTitle').textContent = template.name;
                      document.getElementById('previewContent').innerHTML = `<code class="language-${template.language}">${template.content}</code>`;
                      document.getElementById('usePreviewTemplate').onclick = () => {
                        closeTemplatePreview();
                        useTemplate(templateId);
                      };
                      document.getElementById('templatePreviewModal').classList.remove('hidden');

                      // Highlight syntax if Prism is available
                      if (window.Prism) {
                        Prism.highlightAll();
                      }
                    } else {
                      alert('Error loading template');
                    }
                  })
                  .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading template');
                  });
              }

              function closeTemplatePreview() {
                document.getElementById('templatePreviewModal').classList.add('hidden');
              }

              // Close modal when clicking outside
              document.getElementById('templatePreviewModal').addEventListener('click', function(e) {
                if (e.target === this) {
                  closeTemplatePreview();
                }
              });
            </script>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'import-export'): ?>
            <?php if (!$user_id) header('Location: /'); ?>

            <?php
            // Get user's pastes for export selection
            $stmt = $db->prepare("SELECT id, title, created_at, views, language, tags FROM pastes WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $user_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <h2 class="text-2xl font-bold mb-6">
                <i class="fas fa-exchange-alt mr-2"></i>Import/Export Pastes
              </h2>

              <div class="grid md:grid-cols-2 gap-8">
                <!-- Export Section -->
                <div class="space-y-6">
                  <div class="border-b dark:border-gray-700 pb-4">
                    <h3 class="text-xl font-semibold mb-2 text-green-600 dark:text-green-400">
                      <i class="fas fa-file-export mr-2"></i>Export Pastes
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400">Download your pastes in various formats</p>
                  </div>

                  <div>
                    <h4 class="font-medium mb-3">Select Pastes to Export:</h4>
                    <div class="max-h-64 overflow-y-auto border dark:border-gray-600 rounded-lg p-3">
                      <div class="mb-3">
                        <label class="flex items-center">
                          <input type="checkbox" id="selectAll" class="mr-2" onchange="toggleAllPastes(this)">
                          <span class="font-medium">Select All (<?= count($user_pastes) ?> pastes)</span>
                        </label>
                      </div>
                      <div class="space-y-2">
                        <?php foreach ($user_pastes as $paste): ?>
                          <label class="flex items-start gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                            <input type="checkbox" name="paste_selection" value="<?= $paste['id'] ?>" class="mt-1">
                            <div class="flex-1 min-w-0">
                              <div class="font-medium truncate"><?= htmlspecialchars($paste['title']) ?></div>
                              <div class="text-sm text-gray-500">
                                <?= $paste['language'] ?> · <?= date('M j, Y', $paste['created_at']) ?> · <?= $paste['views'] ?> views
                              </div>
                            </div>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>

                  <div>
                    <h4 class="font-medium mb-3">Export Format:</h4>
                    <div class="space-y-2">
                      <label class="flex items-center">
                        <input type="radio" name="export_format" value="json" checked class="mr-2">
                        <span>JSON (recommended for re-importing)</span>
                      </label>
                      <label class="flex items-center">
                        <input type="radio" name="export_format" value="csv" class="mr-2">
                        <span>CSV (for spreadsheets)</span>
                      </label>
                      <label class="flex items-center">
                        <input type="radio" name="export_format" value="txt" class="mr-2">
                        <span>Plain Text (readable format)</span>
                      </label>
                    </div>
                  </div>

                  <div class="flex gap-2">
                    <button onclick="exportSelected()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600">
                      <i class="fas fa-download mr-2"></i>Export Selected
                    </button>
                    <button onclick="exportAll()" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                      <i class="fas fa-download mr-2"></i>Export All
                    </button>
                  </div>
                </div>

                <!-- Import Section -->
                <div class="space-y-6">
                  <div class="border-b dark:border-gray-700 pb-4">
                    <h3 class="text-xl font-semibold mb-2 text-blue-600 dark:text-blue-400">
                      <i class="fas fa-file-import mr-2"></i>Import Pastes
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400">Upload and import pastes from files</p>
                  </div>

                  <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                    <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Supported Formats:</h4>
                    <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                      <li><strong>JSON:</strong> PasteForge export files or custom JSON</li>
                      <li><strong>CSV:</strong> Spreadsheet files with paste data</li>
                      <li><strong>TXT:</strong> Plain text files</li>
                    </ul>
                  </div>

                  <div class="space-y-4">
                    <a href="import.php" class="block w-full bg-blue-500 text-white text-center py-3 px-4 rounded-lg hover:bg-blue-600 transition-colors">
                      <i class="fas fa-file-import mr-2"></i>Start Import Process
                    </a>

                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                      Click above to go to the import page where you can upload files and configure import settings
                    </div>
                  </div>

                  <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg">
                    <h4 class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                      <i class="fas fa-exclamation-triangle mr-2"></i>Import Tips:
                    </h4>
                    <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                      <li>• Large files may take longer to process</li>
                      <li>• Duplicate detection is based on title and content</li>
                      <li>• Invalid entries will be skipped with a report</li>
                      <li>• You can set title, language, and other metadata during import</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <script>
              function toggleAllPastes(checkbox) {
                const checkboxes = document.querySelectorAll('input[name="paste_selection"]');
                checkboxes.forEach(cb => cb.checked = checkbox.checked);
              }

              function getSelectedPastes() {
                const checkboxes = document.querySelectorAll('input[name="paste_selection"]:checked');
                return Array.from(checkboxes).map(cb => cb.value);
              }

              function getSelectedFormat() {
                const format = document.querySelector('input[name="export_format"]:checked');
                return format ? format.value : 'json';
              }

              function exportSelected() {
                const selected = getSelectedPastes();
                if (selected.length === 0) {
                  Swal.fire({
                    icon: 'warning',
                    title: 'No Selection',
                    text: 'Please select at least one paste to export'
                  });
                  return;
                }

                const format = getSelectedFormat();
                const url = `export.php?action=export&format=${format}&selection=selected&paste_ids=${selected.join(',')}`;
                window.location.href = url;
              }

              function exportAll() {
                const format = getSelectedFormat();
                const url = `export.php?action=export&format=${format}&selection=all`;
                window.location.href = url;
              }

              // Update select all checkbox based on individual selections
              document.addEventListener('change', function(e) {
                if (e.target.name === 'paste_selection') {
                  const checkboxes = document.querySelectorAll('input[name="paste_selection"]');
                  const checked = document.querySelectorAll('input[name="paste_selection"]:checked');
                  const selectAll = document.getElementById('selectAll');

                  selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
                  selectAll.checked = checked.length === checkboxes.length;
                }
              });
            </script>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'about'): ?>
            <!-- About Page -->
            <div class="max-w-6xl mx-auto">
              <!-- Hero Section -->
              <div class="text-center py-16 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-2xl mb-12">
                <div class="max-w-4xl mx-auto px-6">
                  <i class="fas fa-paste text-6xl text-blue-600 dark:text-blue-400 mb-6"></i>
                  <h1 class="text-5xl font-bold text-gray-900 dark:text-white mb-6">
                    Welcome to <span class="text-blue-600 dark:text-blue-400">PasteForge</span>
                  </h1>
                  <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 leading-relaxed">
                    The ultimate platform for sharing, organizing, and collaborating on code snippets. 
                    Built for developers, by developers, with powerful features that make code sharing effortless.
                  </p>
                  <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="?page=signup" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-lg text-lg font-semibold transition-all duration-200 transform hover:scale-105 shadow-lg">
                      <i class="fas fa-user-plus mr-2"></i>Get Started Free
                    </a>
                    <a href="?page=login" class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-blue-600 dark:text-blue-400 px-8 py-4 rounded-lg text-lg font-semibold transition-all duration-200 border-2 border-blue-600 dark:border-blue-400">
                      <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </a>
                  </div>
                </div>
              </div>

              <!-- Features Grid -->
              <div class="mb-16">
                <div class="text-center mb-12">
                  <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">Powerful Features</h2>
                  <p class="text-xl text-gray-600 dark:text-gray-300">Everything you need to share and manage your code</p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                  <!-- Smart Code Sharing -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-code text-2xl text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Smart Code Sharing</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Share code snippets with syntax highlighting for 200+ programming languages. Public or private, with optional password protection.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Syntax highlighting with Prism.js</li>
                      <li>✓ Line numbers and copy functionality</li>
                      <li>✓ Raw text and download options</li>
                    </ul>
                  </div>

                  <!-- AI-Powered Analysis -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-robot text-2xl text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">AI Code Summaries</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Get intelligent explanations of your code with our AI-powered analysis system that understands context and functionality.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Automated code explanations</li>
                      <li>✓ Language-aware analysis</li>
                      <li>✓ Quality-assured summaries</li>
                    </ul>
                  </div>

                  <!-- Smart Discovery -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-lightbulb text-2xl text-green-600 dark:text-green-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Smart Discovery</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Discover related pastes and similar code through our intelligent recommendation engine based on language and content.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Related paste suggestions</li>
                      <li>✓ Cross-user discovery</li>
                      <li>✓ Tag-based grouping</li>
                    </ul>
                  </div>

                  <!-- Organization Tools -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-orange-100 dark:bg-orange-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-folder-tree text-2xl text-orange-600 dark:text-orange-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Organization Tools</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Organize your code with collections, projects, and tagging systems. Keep everything structured and easy to find.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Custom collections</li>
                      <li>✓ Project management</li>
                      <li>✓ Advanced tagging</li>
                    </ul>
                  </div>

                  <!-- Collaboration -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-pink-100 dark:bg-pink-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-users text-2xl text-pink-600 dark:text-pink-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Collaboration</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Connect with other developers through following, discussions, and collaborative projects. Build your coding community.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Follow system</li>
                      <li>✓ Paste discussions</li>
                      <li>✓ Comments & feedback</li>
                    </ul>
                  </div>

                  <!-- Version Control -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg hover:shadow-xl transition-all duration-300 hover:transform hover:-translate-y-2">
                    <div class="w-16 h-16 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center mb-6">
                      <i class="fas fa-code-branch text-2xl text-indigo-600 dark:text-indigo-400"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Version Control</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">Track changes with versioning, create forks, and build paste chains. Never lose track of your code evolution.</p>
                    <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1">
                      <li>✓ Paste versioning</li>
                      <li>✓ Forking system</li>
                      <li>✓ Chain building</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Code Examples Section -->
              <div class="mb-16">
                <div class="text-center mb-12">
                  <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">See It In Action</h2>
                  <p class="text-xl text-gray-600 dark:text-gray-300">Real examples of what you can do with PasteForge</p>
                </div>

                <div class="grid lg:grid-cols-2 gap-8">
                  <!-- Example 1 -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-100 dark:bg-gray-700 px-6 py-4 border-b dark:border-gray-600">
                      <div class="flex items-center gap-3">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="ml-4 text-sm font-medium text-gray-600 dark:text-gray-300">React Component Example</span>
                      </div>
                    </div>
                    <div class="p-6">
                      <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto"><code>function UserCard({ user }) {
  return (
    &lt;div className="card"&gt;
      &lt;img src={user.avatar} alt={user.name} /&gt;
      &lt;h3&gt;{user.name}&lt;/h3&gt;
      &lt;p&gt;{user.bio}&lt;/p&gt;
    &lt;/div&gt;
  );
}</code></pre>
                      <div class="mt-4 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <span><i class="fas fa-eye mr-1"></i>1,234 views</span>
                        <span><i class="fas fa-heart mr-1"></i>89 likes</span>
                        <span><i class="fas fa-comment mr-1"></i>12 comments</span>
                      </div>
                    </div>
                  </div>

                  <!-- Example 2 -->
                  <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gray-100 dark:bg-gray-700 px-6 py-4 border-b dark:border-gray-600">
                      <div class="flex items-center gap-3">
                        <span class="w-3 h-3 bg-red-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                        <span class="w-3 h-3 bg-green-500 rounded-full"></span>
                        <span class="ml-4 text-sm font-medium text-gray-600 dark:text-gray-300">Python Data Analysis</span>
                      </div>
                    </div>
                    <div class="p-6">
                      <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-sm overflow-x-auto"><code>import pandas as pd
import matplotlib.pyplot as plt

# Load and analyze data
df = pd.read_csv('data.csv')
result = df.groupby('category').mean()
result.plot(kind='bar')
plt.show()</code></pre>
                      <div class="mt-4 flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                        <span><i class="fas fa-eye mr-1"></i>892 views</span>
                        <span><i class="fas fa-heart mr-1"></i>67 likes</span>
                        <span><i class="fas fa-comment mr-1"></i>8 comments</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Statistics Section -->
              <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-12 text-white text-center mb-16">
                <h2 class="text-3xl font-bold mb-8">Join Our Growing Community</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                  <div>
                    <div class="text-4xl font-bold mb-2">10K+</div>
                    <div class="text-blue-100">Code Pastes</div>
                  </div>
                  <div>
                    <div class="text-4xl font-bold mb-2">2K+</div>
                    <div class="text-blue-100">Active Users</div>
                  </div>
                  <div>
                    <div class="text-4xl font-bold mb-2">50+</div>
                    <div class="text-blue-100">Languages</div>
                  </div>
                  <div>
                    <div class="text-4xl font-bold mb-2">99.9%</div>
                    <div class="text-blue-100">Uptime</div>
                  </div>
                </div>
              </div>

              <!-- Call to Action -->
              <div class="text-center py-16">
                <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-6">Ready to Start Sharing?</h2>
                <p class="text-xl text-gray-600 dark:text-gray-300 mb-8 max-w-2xl mx-auto">
                  Join thousands of developers who trust PasteForge for their code sharing needs. 
                  Create your free account and start organizing your code today.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                  <a href="?page=signup" class="bg-blue-600 hover:bg-blue-700 text-white px-10 py-4 rounded-lg text-lg font-semibold transition-all duration-200 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-rocket mr-2"></i>Start Free Today
                  </a>
                  <a href="?page=archive" class="bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 px-10 py-4 rounded-lg text-lg font-semibold transition-all duration-200">
                    <i class="fas fa-search mr-2"></i>Browse Public Pastes
                  </a>
                </div>
              </div>
            </div>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'messages'): ?>
            <?php if (!$user_id) header('Location: /'); ?>

            <?php
            // Messages table is already created with threaded messaging support

            // Handle sending message
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
              $recipient_username = $_POST['recipient'];
              $subject = htmlspecialchars($_POST['subject']);
              $content = htmlspecialchars($_POST['content']);

              // Get recipient ID
              $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
              $stmt->execute([$recipient_username]);
              $recipient = $stmt->fetch();

              if ($recipient) {
                $stmt = $db->prepare("INSERT INTO messages (sender_id, recipient_id, subject, content, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $recipient['id'], $subject, $content, time()]);
                echo 'div class="mb-4 bg-green-100 text-green-700 p-3 rounded">Message sent successfully!</div>';
              }
            }

            // Get messages
            $tab = $_GET['tab'] ?? 'inbox';
            if ($tab === 'inbox') {
              $stmt = $db->prepare("
                SELECT messages.*, users.username as sender_username 
                FROM messages
                JOIN users ON messages.sender_id = users.id 
                WHERE messages.recipient_id = ? 
                ORDER BY messages.created_at DESC
              ");
              $stmt->execute([$user_id]);
            } else {
              $stmt = $db->prepare("
                SELECT messages.*, users.username as recipient_username 
                FROM messages
                JOIN users ON messages.recipient_id = users.id 
                WHERE messages.sender_id = ? 
                ORDER BY messages.created_at DESC
              ");
              $stmt->execute([$user_id]);
            }
            $messages = $stmt->fetchAll();
            ?>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold">Messages</h2>
                <div class="flex gap-2">
                  <a href="?page=messages&tab=inbox" class="px-4 py-2 rounded <?= $tab === 'inbox' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">
                    Inbox
                  </a>
                  <a href="?page=messages&tab=sent" class="px-4 py-2 rounded <?= $tab === 'sent' ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700' ?>">
                    Sent
                  </a>
                  <button onclick="toggleComposeForm()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    <i class="fas fa-pen"></i> Compose
                  </button>
                </div>
              </div>

              <!-- Compose Form -->
              <div id="composeForm" class="hidden mb-6 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <form method="POST" class="space-y-4">
                  <input type="hidden" name="action" value="send_message">
                  <div>
                    <label class="block text-sm font-medium mb-2">To:</label>
                    <input type="text" name="recipient" id="recipient" required class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Subject:</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600">
                  </div>
                  <div>
                    <label class="block text-sm font-medium mb-2">Message:</label>
                    <textarea name="content" required rows="4" class="w-full px-4 py-2 rounded-lg border dark:bg-gray-600"></textarea>
                  </div>
                  <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Send Message
                  </button>
                </form>
              </div>

              <!-- Messages List -->
              <div class="space-y-4">
                <?php if (empty($messages)): ?>
                  <p class="text-gray-500 dark:text-gray-400">No messages found.</p>
                <?php else: ?>
                  <?php foreach ($messages as $message): ?>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600">
                      <div class="flex justify-between items-start">
                        <div>
                          <h3 class="font-medium"><?= htmlspecialchars($message['subject']) ?></h3>
                          <p class="text-sm text-gray-600 dark:text-gray-400">
                            <?php if ($tab === 'inbox'): ?>
                              From: <?= htmlspecialchars($message['sender_username']) ?>
                            <?php else: ?>
                              To: <?= htmlspecialchars($message['recipient_username']) ?>
                            <?php endif; ?>
                          </p>
                        </div>
                        <span class="text-sm text-gray-500"><?= date('M j, Y g:i A', $message['created_at']) ?></span>
                      </div>
                      <p class="mt-2"><?= nl2br(htmlspecialchars($message['content'])) ?></p>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <script>
              function toggleComposeForm() {
                const form = document.getElementById('composeForm');
                form.classList.toggle('hidden');
              }

              // Initialize autocomplete
              $(document).ready(function() {
                $("#recipient").autocomplete({
                  source: "?action=search_users",
                  minLength: 2,
                  select: function(event, ui) {
                    // Don't need to validate on select since it came from search
                    return true;
                  }
                }).autocomplete("instance")._renderItem = function(ul, item) {
                  return $("<li>")
                    .append("<div class='p-2'><i class='fas fa-user mr-2'></i>" + item.label + "</div>")
                    .appendTo(ul);
                };

                // Add validation on blur
                $("#recipient").on('blur', function() {
                  const username = $(this).val().trim();
                  if (username) {
                    $.ajax({
                      url: "?action=validate_user",
                      method: "GET", 
                      data: { username: username },
                      dataType: "json",
                      success: function(response) {
                        if (!response.exists) {
                          Swal.fire({
                            icon: 'error',
                            title: 'Invalid User',
                            text: 'This username does not exist'
                          });
                          $("#recipient").val('');
                        }
                      },
                      error: function() {
                        console.log("Error validating username");
                      }
                    });
                  }
                });
              });
            </script>

            <style>
              .ui-autocomplete {
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                max-height: 200px;
                overflow-y: auto;
                overflow-x: hidden;
              }
              .dark .ui-autocomplete {
                background: #374151;
                border-color: #4B5563;
                color: white;
              }
              .ui-menu-item {
                padding: 4px;
                cursor: pointer;
              }
              .ui-menu-item:hover {
                background: #f3f4f6;
              }
              .dark .ui-menu-item:hover {
                background: #4B5563;
              }
            </style>

          <?php elseif (isset($_GET['page']) && $_GET['page'] === 'signup'): ?>
            <?php
            // Check if registration is enabled
            $registration_enabled = SiteSettings::get('registration_enabled', 1);
            $email_verification_required = SiteSettings::get('email_verification_required', 0);
            $allowed_domains = SiteSettings::get('allowed_email_domains', '*');
            ?>

            <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 animate-fade-in">
              <h2 class="text-2xl font-bold mb-6">Sign Up for PasteForge</h2>

              <?php if (!$registration_enabled): ?>
                <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                  <h3 class="font-semibold mb-2">Registration Disabled</h3>
                  <p>New user registration is currently disabled. Please contact an administrator if you need access.</p>
                </div>
                <div class="text-center">
                  <a href="/" class="text-blue-500 hover:text-blue-700">← Back to Home</a>
                </div>
              <?php else: ?>
                <!-- Error Messages -->
                <?php if (isset($_GET['error'])): ?>
                  <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
                    <?php if ($_GET['error'] === 'registration_disabled'): ?>
                      Registration is currently disabled.
                    <?php elseif ($_GET['error'] === 'invalid_domain'): ?>
                      Your email domain is not allowed. <?= $allowed_domains !== '*' ? 'Allowed domains: ' . htmlspecialchars($allowed_domains) : '' ?>
                    <?php elseif ($_GET['error'] === 'username_exists'): ?>
                      Username already exists. Please choose a different username.
                    <?php elseif ($_GET['error'] === 'email_exists'): ?>
                      An account with this email already exists.
                    <?php else: ?>
                      Registration failed. Please try again.
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Success Messages -->
                <?php if (isset($_GET['success'])): ?>
                  <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
                    <?php if ($_GET['success'] === 'verification_required'): ?>
                      <h3 class="font-semibold mb-2">Registration Successful!</h3>
                      <p>Your account has been created. You'll need to verify your email address before you can log in.</p>
                    <?php endif; ?>
                  </div>
                  <?php if ($_GET['success'] === 'verification_required'): ?>
                    <div class="text-center">
                      <a href="?page=login" class="text-blue-500 hover:text-blue-700">← Go to Login</a>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="mb-6">Create an account to get started.</p>
                  <?php if ($email_verification_required): ?>
                    <div class="mb-4 p-3 bg-blue-100 dark:bg-blue-900 border border-blue-400 text-blue-700 dark:text-blue-200 rounded text-sm">
                      <i class="fas fa-info-circle mr-1"></i>
                      Email verification is required for new accounts.
                    </div>
                  <?php endif; ?>
                  <?php if ($allowed_domains !== '*'): ?>
                    <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 text-yellow-700 dark:text-yellow-200 rounded text-sm">
                      <i class="fas fa-envelope mr-1"></i>
                      Only these email domains are allowed: <?= htmlspecialchars($allowed_domains) ?>
                    </div>
                  <?php endif; ?>

                  <form method="POST" class="space-y-4" onsubmit="return validateSignupForm(event)">
                    <input type="hidden" name="action" value="register">
                    <div>
                      <label class="block text-sm font-medium mb-2">Username <span class="text-red-500">*</span></label>
                      <input type="text" name="username" required class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-2">Email <?= $email_verification_required ? '<span class="text-red-500">*</span>' : '(optional)' ?></label>
                      <input type="email" name="email" <?= $email_verification_required ? 'required' : '' ?> class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>
                    <div>
                      <label class="block text-sm font-medium mb-2">Password <span class="text-red-500">*</span></label>
                      <input type="password" name="password" required class="w-full px-4 py-2 rounded-lg border bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600">
                      Sign Up
                    </button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
              <script>
                function validateSignupForm(event) {
                  event.preventDefault();
                  const username = event.target.username.value.trim();
                  const email = event.target.email.value.trim();
                  const password = event.target.password.value.trim();

                  if (!username || !email || !password) {
                    Swal.fire({
                      icon: 'error',
                      title: 'Validation Error',
                      text: 'Please fill in all fields'
                    });
                    return false;
                  }

                  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                  if (!emailRegex.test(email)) {
                    Swal.fire({
                      icon: 'error',
                      title: 'Invalid Email',
                      text: 'Please enter a valid email address'
                    });
                    return false;
                  }

                  if (password.length < 6) {
                    Swal.fire({
                      icon: 'error',
                      title: 'Password Too Short',
                      text: 'Password must be at least 6 characters long'
                    });
                    return false;
                  }

                  event.target.submit();
                  return true;
                }
              </script>
            </div>

          <?php endif; ?>

          <?php if (!isset($_GET['page']) || ($_GET['page'] !== 'login' && $_GET['page'] !== 'signup')): ?>
          <?php if (isset($_GET['page']) && $_GET['page'] === 'archive'): ?>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
              <h2 class="text-2xl font-bold mb-6">
                <i class="fas fa-archive mr-2"></i>Archive
              </h2>
<?php
  $items_per_page = 5;
  $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM pastes WHERE is_public = 1 AND zero_knowledge = 0 AND (expire_time IS NULL OR expire_time > ?) AND parent_paste_id IS NULL");
  $count_stmt->execute([time()]);
  $total_count = $count_stmt->fetch()["total"];
  $current_page = isset($_GET["p"]) ? max(1, intval($_GET["p"])) : 1;
  $offset = ($current_page - 1) * $items_per_page;
  $total_pages = ceil($total_count / $items_per_page);
  $stmt = $db->prepare("SELECT p.*, u.username, (SELECT COUNT(*) FROM comments WHERE paste_id = p.id) as comment_count, COALESCE(p.fork_count, 0) as fork_count, CASE WHEN p.original_paste_id IS NOT NULL THEN 1 ELSE 0 END as is_fork, p.original_paste_id, p.parent_paste_id, (SELECT COUNT(*) FROM pastes WHERE parent_paste_id = p.id AND is_public = 1 AND zero_knowledge = 0) as child_count FROM pastes p LEFT JOIN users u ON p.user_id = u.id WHERE p.is_public = 1 AND p.zero_knowledge = 0 AND (p.expire_time IS NULL OR p.expire_time > ?) AND p.parent_paste_id IS NULL ORDER BY p.created_at DESC LIMIT ? OFFSET ?");
  $stmt->execute([time(), $items_per_page, $offset]);
  $archive_pastes = $stmt->fetchAll();
?>
                <div class="overflow-x-auto">
                <table class="w-full table-auto">
                  <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700">
                      <th class="px-4 py-2 text-left">Title</th>
                      <th class="px-4 py-2 text-left">Author</th>
                      <th class="px-4 py-2 text-left">Language</th>
                      <th class="px-4 py-2 text-left">Posted</th>
                      <th class="px-4 py-2 text-left">Views</th>
                      <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($archive_pastes)): ?>
                      <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                          <i class="fas fa-archive text-4xl mb-4"></i>
                          <p class="text-lg">No pastes found in the archive.</p>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($archive_pastes as $paste):
                        $expires = $paste['expire_time'] ? date('Y-m-d H:i', $paste['expire_time']) : 'Never';
                        $has_children = $paste['child_count'] > 0;
                      ?>
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-3">
                          <div class="flex items-center gap-2">
                            <?php if ($paste['is_fork']): ?>
                              <i class="fas fa-code-branch text-purple-500" title="This is a fork"></i>
                            <?php endif; ?>
                            <div>
                              <a href="?id=<?= $paste['id'] ?>" class="text-blue-500 hover:text-blue-700 font-medium">
                                <?= htmlspecialchars($paste['title']) ?>
                              </a>

                              <!-- Tags display -->
                              <?php if (!empty($paste['tags'])): ?>
                                <div class="mt-1">
                                  <?php foreach (array_slice(explode(',', $paste['tags']), 0, 3) as $tag): ?>
                                    <span class="inline-block bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs px-2 py-1 rounded mr-1">
                                      <?= htmlspecialchars(trim($tag)) ?>
                                    </span>
                                  <?php endforeach; ?>
                                </div>
                              <?php endif; ?>

                              <?php if ($has_children): ?>
                                <button onclick="toggleChildren(<?= $paste['id'] ?>)" 
                                        class="mt-1 px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                  <span id="toggle-text-<?= $paste['id'] ?>">Show Children (<?= $paste['child_count'] ?>)</span>
                                </button>
                              <?php endif; ?>
                            </div>
                          </div>
                        </td>

                        <td class="px-4 py-3">
                          <div class="flex items-center gap-2">
                            <?php if ($paste['username']): ?>
                              <a href="?page=profile&username=<?= urlencode($paste['username']) ?>" class="text-blue-500 hover:text-blue-700">
                                @<?= htmlspecialchars($paste['username']) ?>
                              </a>
                            <?php else: ?>
                              <span class="text-gray-500">Anonymous</span>
                            <?php endif; ?>
                          </div>
                        </td>

                        <td class="px-4 py-3">
                          <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-sm">
                            <?= htmlspecialchars($paste['language']) ?>
                          </span>
                        </td>

                        <td class="px-4 py-3">
                          <div class="text-sm">
                            <?= human_time_diff($paste['created_at']) ?>
                            <?php if ($paste['expire_time']): ?>
                              <div class="text-xs text-orange-500 mt-1" data-expires="<?= $paste['expire_time'] ?>">
                                <i class="fas fa-clock"></i> <span class="countdown-timer">Calculating...</span>
                              </div>
                            <?php endif; ?>
                          </div>
                        </td>

                        <td class="px-4 py-3">
                          <div class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                            <i class="fas fa-eye"></i>
                            <span><?= number_format($paste['views']) ?></span>
                            <?php if ($paste['comment_count'] > 0): ?>
                              <span class="ml-2">
                                <i class="fas fa-comment"></i>
                                <?= number_format($paste['comment_count']) ?>
                              </span>
                            <?php endif; ?>
                            <?php if ($paste['fork_count'] > 0): ?>
                              <span class="ml-2">
                                <i class="fas fa-code-branch"></i>
                                <?= number_format($paste['fork_count']) ?>
                              </span>
                            <?php endif; ?>
                          </div>
                        </td>

                        <td class="px-4 py-3">
                          <div class="flex gap-1">
                            <a href="?id=<?= $paste['id'] ?>" 
                               class="px-2 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600 transition-colors">
                              View
                            </a>
                            <a href="?id=<?= $paste['id'] ?>&raw=1" 
                               target="_blank"
                               class="px-2 py-1 bg-gray-500 text-white rounded text-xs hover:bg-gray-600 transition-colors">
                              Raw
                            </a>
                          </div>
                        </td>
                      </tr>

                      <!-- Hidden row for children -->
                      <?php if ($has_children): ?>
                      <tr id="children-<?= $paste['id'] ?>" class="hidden">
                        <td colspan="6" class="px-0 py-0">
                          <div class="bg-gray-50 dark:bg-gray-800 border-l-4 border-blue-500">
                            <div id="children-content-<?= $paste['id'] ?>" class="p-4">
                              <!-- Children will be loaded here via AJAX -->
                            </div>
                          </div>
                        </td>
                      </tr>
                      <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
                  <!-- Results info -->
                  <div class="text-sm text-gray-600 dark:text-gray-400">
                    Showing <?= number_format(($current_page - 1) * $items_per_page + 1) ?> - 
                    <?= number_format(min($current_page * $items_per_page, $total_count)) ?> 
                    of <?= number_format($total_count) ?> results
                  </div>

                  <!-- Pagination -->
                  <div class="flex space-x-2">
                    <?php if ($current_page > 1): ?>
                      <a href="<?= buildPaginationUrl($current_page - 1) ?>" 
                         class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                        <i class="fas fa-chevron-left mr-1"></i>Previous
                      </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                      <a href="<?= buildPaginationUrl($i) ?>" 
                         class="px-4 py-2 rounded <?= $i === $current_page ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                        <?= $i ?>
                      </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                      <a href="<?= buildPaginationUrl($current_page + 1) ?>" 
                         class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded hover:bg-gray-200 dark:hover:bg-gray-600">
                        Next<i class="fas fa-chevron-right ml-1"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>



              <script>
                let loadedChildren = new Set();


                function toggleChildren(parentId) {
                  const childrenRow = document.getElementById(`children-${parentId}`);
                  const toggleText = document.getElementById(`toggle-text-${parentId}`);

                  if (childrenRow.classList.contains('hidden')) {
                    // Show children
                    childrenRow.classList.remove('hidden');

                    // Load children if not already loaded
                    if (!loadedChildren.has(parentId)) {
                      loadChildren(parentId);
                      loadedChildren.add(parentId);
                    }

                    toggleText.textContent = toggleText.textContent.replace('Show', 'Hide');
                  } else {
                    // Hide children
                    childrenRow.classList.add('hidden');
                    toggleText.textContent = toggleText.textContent.replace('Hide', 'Show');
                  }
                }

                function loadChildren(parentId) {
                  const contentDiv = document.getElementById(`children-content-${parentId}`);
                  contentDiv.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading children...</div>';

                  fetch(`?page=archive&action=load_children&parent_id=${parentId}`)
                    .then(response => response.json())
                    .then(children => {
                      if (children.length === 0) {
                        contentDiv.innerHTML = '<div class="text-center py-4 text-gray-500">No children found</div>';
                        return;
                      }

                      let html = '<div class="space-y-2">';
                      children.forEach(child => {
                        const timeAgo = formatTimeAgo(child.created_at);
                        html += `
                          <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded border-l-4 border-blue-300">
                            <div class="flex items-center gap-3">
                              <span class="text-blue-500">↳</span>
                              <span class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs px-2 py-1 rounded">🔗</span>
                              <a href="?id=${child.id}" class="text-blue-500 hover:text-blue-700 font-medium">
                                ${escapeHtml(child.title)}
                              </a>
                              <span class="text-xs text-gray-500">
                                by ${child.username ? '@' + escapeHtml(child.username) : 'Anonymous'}
                              </span>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-500">
                              <span>${timeAgo}</span>
                              <span class="px-2 py-1 bg-gray-100 dark:bg-gray-600 rounded text-xs">
                                ${escapeHtml(child.language)}
                              </span>
                            </div>
                          </div>
                        `;
                      });
                      html += '</div>';
                      contentDiv.innerHTML = html;
                    })
                    .catch(error => {
                      console.error('Error loading children:', error);
                      contentDiv.innerHTML = '<div class="text-center py-4 text-red-500">Error loading children</div>';
                    });
                }

                function formatTimeAgo(timestamp) {
                  const now = Math.floor(Date.now() / 1000);
                  const diff = now - timestamp;

                  if (diff < 60) return 'just now';
                  if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
                  if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
                  return Math.floor(diff / 86400) + ' days ago';
                }

                function escapeHtml(text) {
                  const div = document.createElement('div');
                  div.textContent = text;
                  return div.innerHTML;
                }

                // Initialize countdown timers for any items on the page
                function initializeCountdownTimers() {
                  document.querySelectorAll('[data-expires]').forEach(element => {
                    const expireTime = parseInt(element.dataset.expires);
                    const timerSpan = element.querySelector('.countdown-timer');

                    if (timerSpan && expireTime) {
                      updateCountdown(timerSpan, expireTime);
                      setInterval(() => updateCountdown(timerSpan, expireTime), 1000);
                    }
                  });
                }

                function updateCountdown(element, expireTime) {
                  const now = Math.floor(Date.now() / 1000);
                  const timeLeft = expireTime - now;

                  if (timeLeft <= 0) {
                    element.textContent = 'Expired';
                    element.className = 'countdown-timer countdown-urgent';
                    return;
                  }

                  const days = Math.floor(timeLeft / 86400);
                  const hours = Math.floor((timeLeft % 86400) / 3600);
                  const minutes = Math.floor((timeLeft % 3600) / 60);

                  let display = '';
                  if (days > 0) display = `${days}d ${hours}h`;
                  else if (hours > 0) display = `${hours}h ${minutes}m`;
                  else display = `${minutes}m`;

                  element.textContent = `Expires in ${display}`;

                  if (timeLeft < 3600) {
                    element.className = 'countdown-timer countdown-urgent';
                  } else if (timeLeft < 86400) {
                    element.className = 'countdown-timer countdown-warning';
                  } else {
                    element.className = 'countdown-timer countdown-normal';
                  }
                }

                // Initialize on page load
                document.addEventListener('DOMContentLoaded', function() {
                  initializeCountdownTimers();
                });
              </script>
            </div>
          <?php elseif (!isset($_GET['page']) || $_GET['page'] === 'home'): ?>
          <form method="POST" id="createPasteForm" class="box paste-box bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 animate-fade-in" onsubmit="return handlePasteSubmit(event)">
            <script>
              // Paste validation settings from PHP
              const maxPasteSize = <?= SiteSettings::get('max_paste_size', 0) ?>;
              const dailyLimitFree = <?= SiteSettings::get('daily_paste_limit_free', 0) ?>;
              const userPastesToday = <?php 
                if ($user_id) {
                  $today_start = strtotime('today');
                  $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND created_at >= ?");
                  $stmt->execute([$user_id, $today_start]);
                  echo $stmt->fetchColumn();
                } else {
                  echo '0';
                }
              ?>;

              function validatePasteForm(event) {
                const content = document.querySelector('textarea[name="content"]').value;

                // Check paste size limit
                if (maxPasteSize > 0 && content.length > maxPasteSize) {
                  event.preventDefault();
                  Swal.fire({
                    icon: 'error',
                    title: 'Content Too Large',
                    text: `Your paste is ${content.length.toLocaleString()} bytes, but the maximum allowed size is ${maxPasteSize.toLocaleString()} bytes.`,
                    footer: 'Please reduce the content size and try again.'
                  });
                  return false;
                }

                // Check daily limit for logged-in users
                <?php if ($user_id): ?>
                if (dailyLimitFree > 0 && userPastesToday >= dailyLimitFree) {
                  event.preventDefault();
                  Swal.fire({
                    icon: 'warning',
                    title: 'Daily Limit Reached',
                    text: `You have reached your daily limit of ${dailyLimitFree} pastes.`,
                    footer: 'Try again tomorrow or consider upgrading to premium.'
                  });
                  return false;
                }
                <?php endif; ?>

                return true;
              }

              let zkKey = null;
              let zkEncrypted = false;

              async function encryptContent() {
                const textarea = document.querySelector('textarea[name="content"]');
                if (!textarea || zkEncrypted || !textarea.value.trim()) return;
                const data = new TextEncoder().encode(textarea.value);
                const keyBytes = window.crypto.getRandomValues(new Uint8Array(32));
                const iv = window.crypto.getRandomValues(new Uint8Array(12));
                const cryptoKey = await window.crypto.subtle.importKey('raw', keyBytes, {name: 'AES-GCM'}, false, ['encrypt']);
                const encrypted = await window.crypto.subtle.encrypt({name: 'AES-GCM', iv}, cryptoKey, data);
                const combined = new Uint8Array(iv.byteLength + encrypted.byteLength);
                combined.set(iv, 0);
                combined.set(new Uint8Array(encrypted), iv.byteLength);
                textarea.value = btoa(String.fromCharCode(...combined));
                zkKey = btoa(String.fromCharCode(...keyBytes));
                zkEncrypted = true;
                sessionStorage.setItem('zkKey', zkKey);
              }

              function handlePasteSubmit(event) {
                const valid = validatePasteForm(event);
                if (!valid) return false;

                const zkBox = document.getElementById('zeroKnowledge');
                if (zkBox && zkBox.checked && zkKey) {
                  sessionStorage.setItem('zkKey', zkKey);
                }
                return true;
              }

              // Real-time content size indicator
              function updateContentStats() {
                const content = document.querySelector('textarea[name="content"]').value;
                const size = content.length;

                // Update or create size indicator
                let indicator = document.getElementById('content-size-indicator');
                if (!indicator) {
                  indicator = document.createElement('div');
                  indicator.id = 'content-size-indicator';
                  indicator.className = 'text-sm mt-1';
                  document.querySelector('textarea[name="content"]').parentNode.appendChild(indicator);
                }

                if (maxPasteSize > 0) {
                  const percentage = (size / maxPasteSize) * 100;
                  const isOverLimit = size > maxPasteSize;

                  indicator.className = `text-sm mt-1 ${isOverLimit ? 'text-red-500' : percentage > 90 ? 'text-yellow-500' : 'text-gray-500'}`;
                  indicator.innerHTML = `${size.toLocaleString()} / ${maxPasteSize.toLocaleString()} bytes (${percentage.toFixed(1)}%)`;
                } else {
                  indicator.className = 'text-sm mt-1 text-gray-500';
                  indicator.innerHTML = `${size.toLocaleString()} bytes`;
                }
              }

              // Initialize form with cloned content, fork data, or template if available
              window.addEventListener('DOMContentLoaded', () => {
                const clonedContent = sessionStorage.getItem('clonedContent');
                const clonedLanguage = sessionStorage.getItem('clonedLanguage');
                const forkContent = sessionStorage.getItem('forkContent');
                const forkPasteId = sessionStorage.getItem('forkPasteId');
                const forkLanguage = sessionStorage.getItem('forkLanguage');
                const forkTitle = sessionStorage.getItem('forkTitle');
                const templateContent = sessionStorage.getItem('templateContent');
                const templateLanguage = sessionStorage.getItem('templateLanguage');
                const templateName = sessionStorage.getItem('templateName');

                if (forkContent && forkPasteId) {
                  // Handle fork data for anonymous users
                  document.querySelector('textarea[name="content"]').value = forkContent;
                  if (forkLanguage) {
                    document.querySelector('select[name="language"]').value = forkLanguage;
                  }
                  if (forkTitle) {
                    document.querySelector('input[name="title"]').value = 'Fork of ' + forkTitle;
                  }

                  // Add hidden field for original paste ID
                  const hiddenField = document.createElement('input');
                  hiddenField.type = 'hidden';
                  hiddenField.name = 'original_paste_id';
                  hiddenField.value = forkPasteId;
                  document.querySelector('form').appendChild(hiddenField);

                  // Add hidden field to mark as fork
                  const forkField = document.createElement('input');
                  forkField.type = 'hidden';
                  forkField.name = 'is_fork';
                  forkField.value = '1';
                  document.querySelector('form').appendChild(forkField);

                  // Clear the stored fork data
                  sessionStorage.removeItem('forkContent');
                  sessionStorage.removeItem('forkPasteId');
                  sessionStorage.removeItem('forkLanguage');
                  sessionStorage.removeItem('forkTitle');
                } else if (clonedContent) {
                  document.querySelector('textarea[name="content"]').value = clonedContent;
                  if (clonedLanguage) {
                    document.querySelector('select[name="language"]').value = clonedLanguage;
                  }
                  // Clear the stored content
                  sessionStorage.removeItem('clonedContent');
                  sessionStorage.removeItem('clonedLanguage');
                } else if (templateContent) {
                  document.querySelector('textarea[name="content"]').value = templateContent;
                  if (templateLanguage) {
                    document.querySelector('select[name="language"]').value = templateLanguage;
                  }
                  if (templateName) {
                    document.querySelector('input[name="title"]').value = templateName;
                  }
                  // Clear the stored template
                  sessionStorage.removeItem('templateContent');
                  sessionStorage.removeItem('templateLanguage');
                  sessionStorage.removeItem('templateName');
                }

                // Initialize content size indicator
                const contentTextarea = document.querySelector('textarea[name="content"]');
                if (contentTextarea) {
                  updateContentStats();
                  contentTextarea.addEventListener('input', updateContentStats);
                  contentTextarea.addEventListener('blur', () => {
                    const zkBox = document.getElementById('zeroKnowledge');
                    if (zkBox && zkBox.checked) {
                      encryptContent();
                    }
                  });
                }
              });
            </script>
            <div class="space-y-6">

  <div class="flex justify-between items-center mb-4">
    <a href="index.php" class="text-blue-500 hover:text-blue-700 font-semibold flex items-center">
      <i class="fas fa-plus mr-1"></i>Create New Paste
    </a>
    <div class="space-x-2">
      <button type="button" id="loadTemplateBtn" class="border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 px-3 py-1 rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-600">Load Template</button>
      <button type="button" id="importBtn" class="border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 px-3 py-1 rounded text-sm hover:bg-gray-100 dark:hover:bg-gray-600">Import</button>
      <input type="file" id="importFile" accept=".php,.py,.js,.java,.cpp,.c,.cs,.rb,.go,.ts,.swift,.txt" class="hidden">
    </div>
  </div>

              <div class="paste-form-element">
                <label class="block text-sm font-medium mb-2">Title</label>
                <input type="text" name="title" required class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 transition-all" placeholder="Enter paste title">
              </div>

              <div class="paste-form-element">
                <label class="flex items-center space-x-2">
                  <input type="checkbox" name="zero_knowledge" id="zeroKnowledge" class="rounded">
                  <span>🔐 Make this a Zero-Knowledge Paste</span>
                  <span title="Encrypt your paste client-side. Server will never see the content. This must be selected before typing your content." class="cursor-help">ℹ️</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">If you lose this link, the paste cannot be recovered.</p>
              </div>

              <div class="field">
                <label class="label">Content</label>
                <div class="control">
                  <textarea name="content" required class="w-full h-96 p-3 rounded border dark:bg-gray-700 dark:border-gray-600 font-mono" placeholder="Enter your code here"></textarea>
                  <?php if ($user_id && SiteSettings::get('daily_paste_limit_free', 0) > 0): ?>
                    <?php
                    $today_start = strtotime('today');
                    $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND created_at >= ?");
                    $stmt->execute([$user_id, $today_start]);
                    $today_count = $stmt->fetchColumn();
                    $daily_limit = SiteSettings::get('daily_paste_limit_free');
                    ?>
                    <div class="text-sm mt-1 <?= $today_count >= $daily_limit ? 'text-red-500' : ($today_count >= $daily_limit * 0.8 ? 'text-yellow-500' : 'text-gray-500') ?>">
                      Daily pastes: <?= $today_count ?> / <?= $daily_limit ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <button type="button" id="toggleAdvanced" class="text-sm font-medium text-left w-full flex items-center justify-between bg-gray-100 dark:bg-gray-800 px-4 py-2 rounded">
                <span>⚙️ Advanced Options</span>
                <span id="advArrow" class="ml-2">▲</span>
              </button>

              <div id="advancedOptions" class="space-y-6 hidden">
                <div class="grid md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium mb-1">Language:</label>
                    <select name="language" class="w-full p-2 rounded border dark:bg-gray-700 dark:border-gray-600">
                    <option value="plaintext">Plain Text</option>
                    <option value="abap">ABAP</option>
                    <option value="actionscript">ActionScript</option>
                    <option value="ada">Ada</option>
                    <option value="apacheconf">Apache Config</option>
                    <option value="apl">APL</option>
                    <option value="applescript">AppleScript</option>
                    <option value="arduino">Arduino</option>
                    <option value="arff">ARFF</option>
                    <option value="asciidoc">AsciiDoc</option>
                    <option value="asm6502">6502 Assembly</option>
                    <option value="aspnet">ASP.NET</option>
                    <option value="autohotkey">AutoHotkey</option>
                    <option value="autoit">AutoIt</option>
                    <option value="bash">Bash</option>
                    <option value="basic">BASIC</option>
                    <option value="batch">Batch</option>
                    <option value="bison">Bison</option>
                    <option value="brainfuck">Brainfuck</option>
                    <option value="bro">Bro</option>
                    <option value="c">C</option>
                    <option value="clike">C-like</option>
                    <option value="cmake">CMake</option>
                    <option value="coffeescript">CoffeeScript</option>
                    <option value="cpp">C++</option>
                    <option value="crystal">Crystal</option>
                    <option value="csharp">C#</option>
                    <option value="csp">CSP</option>
                    <option value="css">CSS</option>
                    <option value="d">D</option>
                    <option value="dart">Dart</option>
                    <option value="diff">Diff</option>
                    <option value="django">Django/Jinja2</option>
                    <option value="docker">Docker</option>
                    <option value="eiffel">Eiffel</option>
                    <option value="elixir">Elixir</option>
                    <option value="elm">Elm</option>
                    <option value="erb">ERB</option>
                    <option value="erlang">Erlang</option>
                    <option value="flow">Flow</option>
                    <option value="fortran">Fortran</option>
                    <option value="fsharp">F#</option>
                    <option value="gedcom">GEDCOM</option>
                    <option value="gherkin">Gherkin</option>
                    <option value="git">Git</option>
                    <option value="glsl">GLSL</option>
                    <option value="go">Go</option>
                    <option value="graphql">GraphQL</option>
                    <option value="groovy">Groovy</option>
                    <option value="haml">Haml</option>
                    <option value="handlebars">Handlebars</option>
                    <option value="haskell">Haskell</option>
                    <option value="haxe">Haxe</option>
                    <option value="hcl">HCL</option>
                    <option value="http">HTTP</option>
                    <option value="hpkp">HTTP Public-Key-Pins</option>
                    <option value="hsts">HTTP Strict-Transport-Security</option>
                    <option value="ichigojam">IchigoJam</option>
                    <option value="icon">Icon</option>
                    <option value="inform7">Inform 7</option>
                    <option value="ini">Ini</option>
                    <option value="io">Io</option>
                    <option value="j">J</option>
                    <option value="java">Java</option>
                    <option value="javascript">JavaScript</option>
                    <option value="jolie">Jolie</option>
                    <option value="json">JSON</option>
                    <option value="jsx">JSX</option>
                    <option value="julia">Julia</option>
                    <option value="keyman">Keyman</option>
                    <option value="kotlin">Kotlin</option>
                    <option value="latex">LaTeX</option>
                    <option value="less">Less</option>
                    <option value="liquid">Liquid</option>
                    <option value="lisp">Lisp</option>
                    <option value="livescript">LiveScript</option>
                    <option value="lolcode">LOLCODE</option>
                    <option value="lua">Lua</option>
                    <option value="makefile">Makefile</option>
                    <option value="markdown">Markdown</option>
                    <option value="matlab">MATLAB</option>
                    <option value="mel">MEL</option>
                    <option value="mizar">Mizar</option>
                    <option value="monkey">Monkey</option>
                    <option value="moonscript">MoonScript</option>
                    <option value="n1ql">N1QL</option>
                    <option value="nasm">NASM</option>
                    <option value="nginx">nginx</option>
                    <option value="nim">Nim</option>
                    <option value="nix">Nix</option>
                    <option value="nsis">NSIS</option>
                    <option value="objectivec">Objective-C</option>
                    <option value="ocaml">OCaml</option>
                    <option value="opencl">OpenCL</option>
                    <option value="oz">Oz</option>
                    <option value="parigp">PARI/GP</option>
                    <option value="parser">Parser</option>
                    <option value="pascal">Pascal</option>
                    <option value="perl">Perl</option>
                    <option value="php">PHP</option>
                    <option value="plsql">PL/SQL</option>
                    <option value="powershell">PowerShell</option>
                    <option value="processing">Processing</option>
                    <option value="prolog">Prolog</option>
                    <option value="properties">Properties</option>
                    <option value="protobuf">Protocol Buffers</option>
                    <option value="pug">Pug</option>
                    <option value="puppet">Puppet</option>
                    <option value="pure">Pure</option>
                    <option value="python">Python</option>
                    <option value="q">Q</option>
                    <option value="qore">Qore</option>
                    <option value="r">R</option>
                    <option value="reason">Reason</option>
                    <option value="renpy">Ren'py</option>
                    <option value="rest">reST (reStructuredText)</option>
                    <option value="rip">Rip</option>
                    <option value="roboconf">Roboconf</option>
                    <option value="ruby">Ruby</option>
                    <option value="rust">Rust</option>
                    <option value="sas">SAS</option>
                    <option value="sass">Sass (Sass)</option>
                    <option value="scss">Sass (Scss)</option>
                    <option value="scala">Scala</option>
                    <option value="scheme">Scheme</option>
                    <option value="smalltalk">Smalltalk</option>
                    <option value="smarty">Smarty</option>
                    <option value="sql">SQL</option>
                    <option value="stylus">Stylus</option>
                    <option value="swift">Swift</option>
                    <option value="tcl">Tcl</option>
                    <option value="textile">Textile</option>
                    <option value="toml">TOML</option>
                    <option value="twig">Twig</option>
                    <option value="typescript">TypeScript</option>
                    <option value="vbnet">VB.Net</option>
                    <option value="velocity">Velocity</option>
                    <option value="verilog">Verilog</option>
                    <option value="vhdl">VHDL</option>
                    <option value="vim">vim</option>
                    <option value="visual-basic">Visual Basic</option>
                    <option value="wasm">WebAssembly</option>
                    <option value="wiki">Wiki markup</option>
                    <option value="xojo">Xojo (REALbasic)</option>
                    <option value="yaml">YAML</option>
                    <option value="zig">Zig</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium mb-1">Expiration:</label>
                  <select name="expire" class="w-full p-2 rounded border dark:bg-gray-700 dark:border-gray-600">
                    <option value="never">Never</option>
                    <option value="600">10 minutes</option>
                    <option value="3600">1 hour</option>
                    <option value="86400">1 day</option>
                    <option value="604800">1 week</option>
                    <option value="1209600">2 Weeks</option>
                    <option value="2592000">1 Month</option>
                    <option value="15552000">6 Months</option>
                    <option value="31536000">1 Year</option>
                  </select>
                </div>
              </div>

              <div class="paste-form-element">
                <label class="block text-sm font-medium mb-2">Tags (comma-separated)</label>
                <input type="text" name="tags" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="javascript, api, example">
              </div>

              <?php if ($user_id): ?>
              <?php
              // Get user's collections for the dropdown
              $stmt = $db->prepare("SELECT * FROM collections WHERE user_id = ? ORDER BY name");
              $stmt->execute([$user_id]);
              $user_collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
              ?>
              <?php if (!empty($user_collections)): ?>
              <div class="paste-form-element">
                <label class="block text-sm font-medium mb-2">Add to Collection (optional)</label>
                <select name="collection_id" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                  <option value="">-- Select Collection --</option>
                  <?php foreach ($user_collections as $collection): ?>
                    <option value="<?= $collection['id'] ?>"><?= htmlspecialchars($collection['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <?php endif; ?>

              <div class="grid md:grid-cols-2 gap-4 paste-form-element">
                <div>
                  <span class="block text-sm font-medium mb-1">Visibility:</span>
                  <div class="flex items-center space-x-4">
                    <label class="flex items-center space-x-1">
                      <input type="radio" name="visibility" value="public" checked class="rounded">
                      <span>Public</span>
                    </label>
                    <label class="flex items-center space-x-1">
                      <input type="radio" name="visibility" value="unlisted" class="rounded">
                      <span>Unlisted</span>
                    </label>
                    <label class="flex items-center space-x-1">
                      <input type="radio" name="visibility" value="private" class="rounded">
                      <span>Private</span>
                    </label>
                  </div>
                  <input type="hidden" name="is_public" id="is_public_hidden" value="1">
                </div>
                <div class="flex items-center mt-6">
                  <label class="flex items-center space-x-2">
                    <input type="checkbox" name="burn_after_read" class="rounded">
                    <span>Burn after reading</span>
                  </label>
                </div>
              </div>

              <div class="paste-form-element">
                <label class="block text-sm font-medium mb-2">Password (optional)</label>
                <input type="password" name="password" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700" placeholder="Password protect this paste">
              </div>

            </div>

            <!-- Hidden input for parent paste ID (for paste chains) -->
            <input type="hidden" name="parent_paste_id" value="<?php echo htmlspecialchars($_GET['parent_id'] ?? ''); ?>">

            <div class="text-center paste-form-element">
              <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-8 rounded-lg transition-all transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i>Create Paste
              </button>
            </div>
          </div>
          </form>
          <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
<!-- Template Modal -->
<div id="templateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-md w-full">
    <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
      <h3 class="text-lg font-semibold">Load Template</h3>
      <button onclick="closeTemplateModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div class="p-6 space-y-6">
      <div id="languageList" class="grid grid-cols-2 gap-3">
        <button type="button" data-lang="python" class="language-item px-3 py-2 border rounded">Python</button>
        <button type="button" data-lang="javascript" class="language-item px-3 py-2 border rounded">JavaScript</button>
        <button type="button" data-lang="php" class="language-item px-3 py-2 border rounded">PHP</button>
        <button type="button" data-lang="cpp" class="language-item px-3 py-2 border rounded">C++</button>
        <button type="button" data-lang="java" class="language-item px-3 py-2 border rounded">Java</button>
        <button type="button" data-lang="go" class="language-item px-3 py-2 border rounded">Go</button>
        <button type="button" data-lang="ruby" class="language-item px-3 py-2 border rounded">Ruby</button>
        <button type="button" data-lang="rust" class="language-item px-3 py-2 border rounded">Rust</button>
        <button type="button" data-lang="csharp" class="language-item px-3 py-2 border rounded">C#</button>
        <button type="button" data-lang="swift" class="language-item px-3 py-2 border rounded">Swift</button>
      </div>
      <div class="text-right">
        <button type="button" id="loadTemplateConfirm" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Load</button>
      </div>
    </div>
  </div>
</div>

  <script>
    function editPaste(id) {
      window.location.href = 'edit_paste.php?id=' + id;
    }

    function deletePaste(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `<input type="hidden" name="delete_paste" value="1"><input type="hidden" name="paste_id" value="${id}">`;
          document.body.appendChild(form);
          form.submit();
        }
      })
    }

  function openCollectionModal() {
      const modal = document.getElementById('collectionModal');
      if (modal) {
        modal.classList.remove('hidden');
      }
    }

    function closeCollectionModal() {
      const modal = document.getElementById('collectionModal');
      if (modal) {
        modal.classList.add('hidden');
      }
    }

    function confirmRemoveFromCollection(collectionName) {
      return confirm(`Remove this paste from the collection "${collectionName}"?`);
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('collectionModal');
      if (modal && e.target === modal) {
        closeCollectionModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeCollectionModal();
    }
  });

  // Advanced options toggle
  const advToggle = document.getElementById('toggleAdvanced');
  const advSection = document.getElementById('advancedOptions');
  const advArrow = document.getElementById('advArrow');
  if (advToggle && advSection && advArrow) {
    advToggle.addEventListener('click', () => {
      advSection.classList.toggle('hidden');
      if (advSection.classList.contains('hidden')) {
        advArrow.textContent = '▲';
      } else {
        advArrow.textContent = '▼';
      }
    });
  }

  // Visibility radio -> is_public handling
  const visibilityRadios = document.querySelectorAll('input[name="visibility"]');
  const isPublicHidden = document.getElementById('is_public_hidden');
  visibilityRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (radio.value === 'private') {
        isPublicHidden.disabled = true;
      } else {
        isPublicHidden.disabled = false;
      }
    });
  });

// Template modal logic
const templateBtn = document.getElementById("loadTemplateBtn");
const templateModal = document.getElementById("templateModal");
const languageItems = document.querySelectorAll("#languageList .language-item");
const templateLoad = document.getElementById("loadTemplateConfirm");
let selectedLang = null;
const templateSnippets = {
  python: "#!/usr/bin/env python3\n\"\"\"\nDescription: [Brief description of what this script does]\nAuthor: [Your name]\nDate: 2025-06-13\n\"\"\"\n\ndef main():\n    # Your code here\n    pass\n\nif __name__ == \"__main__\":\n    main()\n",
  javascript: "#!/usr/bin/env node\n/**\n * Description: [Brief description of what this script does]\n * Author: [Your name]\n * Date: 2025-06-13\n */\nfunction main() {\n  // Your code here\n}\n\nmain();\n",
  php: "<" + "?php\n/**\n * Description: [Brief description of what this script does]\n * Author: [Your name]\n * Date: 2025-06-13\n */\nfunction main() {\n    // Your code here\n}\n\nmain();\n",
  cpp: "#include <iostream>\n\n// Description: [Brief description of what this program does]\n// Author: [Your name]\n// Date: 2025-06-13\n\nint main() {\n    // Your code here\n    return 0;\n}\n",
  java: "/**\n * Description: [Brief description of what this program does]\n * Author: [Your name]\n * Date: 2025-06-13\n */\npublic class Main {\n    public static void main(String[] args) {\n        // Your code here\n    }\n}\n",
  go: "package main\n\nimport \"fmt\"\n\n// Description: [Brief description of what this program does]\n// Author: [Your name]\n// Date: 2025-06-13\n\nfunc main() {\n    // Your code here\n    fmt.Println(\"Hello\")\n}\n",
  ruby: "#!/usr/bin/env ruby\n# Description: [Brief description of what this script does]\n# Author: [Your name]\n# Date: 2025-06-13\n\ndef main\n  # Your code here\nend\n\nmain if __FILE__ == $PROGRAM_NAME\n",
  rust: "// Description: [Brief description of what this program does]\n// Author: [Your name]\n// Date: 2025-06-13\n\nfn main() {\n    // Your code here\n}\n",
  csharp: "using System;\n\n/// Description: [Brief description of what this program does]\n/// Author: [Your name]\n/// Date: 2025-06-13\nclass Program\n{\n    static void Main()\n    {\n        // Your code here\n    }\n}\n",
  swift: "import Foundation\n// Description: [Brief description of what this script does]\n// Author: [Your name]\n// Date: 2025-06-13\n\nfunc main() {\n    // Your code here\n}\n\nmain()\n"
};

if (templateBtn) {
  templateBtn.addEventListener('click', () => {
    if (templateModal) templateModal.classList.remove('hidden');
  });
}
if (templateModal) {
  languageItems.forEach(btn => {
    btn.addEventListener('click', () => {
      languageItems.forEach(b => b.classList.remove('bg-blue-500','text-white'));
      btn.classList.add('bg-blue-500','text-white');
      selectedLang = btn.dataset.lang;
    });
  });
}
if (templateLoad) {
  templateLoad.addEventListener('click', () => {
    if (!selectedLang) return;
    const textarea = document.querySelector('textarea[name="content"]');
    const langSelect = document.querySelector('select[name="language"]');
    if (textarea) textarea.value = templateSnippets[selectedLang] || '';
    if (langSelect) langSelect.value = selectedLang;
    closeTemplateModal();
  });
}
function closeTemplateModal() {
  if (templateModal) templateModal.classList.add('hidden');
}
document.addEventListener('click', e => {
  if (templateModal && e.target === templateModal) {
    closeTemplateModal();
  }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeTemplateModal();
});
// Import file logic
const importBtnEl = document.getElementById("importBtn");
const importFileInput = document.getElementById("importFile");
if (importBtnEl && importFileInput) {
  importBtnEl.addEventListener('click', () => importFileInput.click());
  importFileInput.addEventListener('change', () => {
    const file = importFileInput.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const textarea = document.querySelector('textarea[name="content"]');
      if (textarea) textarea.value = e.target.result;
    };
    reader.readAsText(file);
  });
}
    // Enhanced Share Modal Functions
    function openEnhancedShareModal(pasteId) {
      fetch('enhanced_paste_share.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=generate_enhanced_share_link&paste_id=${pasteId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showEnhancedShareModal(data);
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.error || 'Failed to generate share link'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to generate share link'
        });
      });
    }

    function showEnhancedShareModal(shareData) {
      const modalHtml = `
        <div id="enhancedShareModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
              <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-share mr-2"></i>Share "${shareData.title}"
              </h3>
              <button onclick="closeEnhancedShareModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <div class="p-6 space-y-6">
              <!-- Paste Metadata -->
              <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <div class="flex items-center justify-between text-sm">
                  <span><strong>Language:</strong> ${shareData.metadata.language}</span>
                  <span><strong>Lines:</strong> ${shareData.metadata.lines}</span>
                  <span><strong>Characters:</strong> ${shareData.metadata.characters.toLocaleString()}</span>
                </div>
              </div>

              <!-- Quick Share Buttons -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Quick Share
                </label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <button onclick="quickShare('twitter', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-blue-400 text-white rounded-lg hover:bg-blue-500 transition-colors">
                    <i class="fab fa-twitter text-2xl mb-2"></i>
                    <span class="text-sm">Twitter</span>
                  </button>
                  <button onclick="quickShare('facebook', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fab fa-facebook text-2xl mb-2"></i>
                    <span class="text-sm">Facebook</span>
                  </button>
                  <button onclick="quickShare('linkedin', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors">
                    <i class="fab fa-linkedin text-2xl mb-2"></i>
                    <span class="text-sm">LinkedIn</span>
                  </button>
                  <button onclick="quickShare('reddit', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                    <i class="fab fa-reddit text-2xl mb-2"></i>
                    <span class="text-sm">Reddit</span>
                  </button>
                  <button onclick="quickShare('discord', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors">
                    <i class="fab fa-discord text-2xl mb-2"></i>
                    <span class="text-sm">Discord</span>
                  </button>
                  <button onclick="quickShare('slack', '${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                    <i class="fab fa-slack text-2xl mb-2"></i>
                    <span class="text-sm">Slack</span>
                  </button>
                  <button onclick="copyToClipboard('${shareData.url}')" 
                          class="flex flex-col items-center p-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-copy text-2xl mb-2"></i>
                    <span class="text-sm">Copy Link</span>
                  </button>
                  <button onclick="shareViaEmail('${shareData.url}', '${shareData.title}')" 
                          class="flex flex-col items-center p-4 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                    <i class="fas fa-envelope text-2xl mb-2"></i>
                    <span class="text-sm">Email</span>
                  </button>
                </div>
              </div>

              <!-- Direct Link -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Direct Link
                </label>
                <div class="flex">
                  <input type="text" 
                         id="enhancedShareUrl" 
                         value="${shareData.url}" 
                         readonly 
                         class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-l-lg bg-gray-50 dark:bg-gray-700 text-sm">
                  <button onclick="copyToClipboard('${shareData.url}')" 
                          class="px-4 py-2 bg-blue-500 text-white rounded-r-lg hover:bg-blue-600">
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
              </div>

              <!-- QR Code -->
              <div class="text-center">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  QR Code for Mobile Sharing
                </label>
                <img src="${shareData.qr_code}" alt="QR Code" class="inline-block border rounded">
                <p class="text-xs text-gray-500 mt-2">Scan with your phone to open the paste</p>
              </div>

              <!-- Analytics (if owned by user) -->
              <div>
                <button onclick="loadShareAnalytics('${shareData.url.split('id=')[1]}')" 
                        class="text-blue-500 hover:text-blue-700 text-sm">
                  <i class="fas fa-chart-line mr-1"></i>View Share Analytics
                </button>
                <div id="shareAnalytics" class="hidden mt-4"></div>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    function closeEnhancedShareModal() {
      const modal = document.getElementById('enhancedShareModal');
      if (modal) {
        modal.remove();
      }
    }

    function quickShare(platform, url, title) {
      let shareUrl;
      const encodedUrl = encodeURIComponent(url);
      const encodedTitle = encodeURIComponent(title);

      switch (platform) {
        case 'twitter':
          shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(`Check out this code: ${title}`)}&url=${encodedUrl}`;
          break;
        case 'facebook':
          shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
          break;
        case 'linkedin':
          shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}&title=${encodedTitle}`;
          break;
        case 'reddit':
          shareUrl = `https://reddit.com/submit?url=${encodedUrl}&title=${encodedTitle}`;
          break;
        case 'discord':
          copyToClipboard(`Check out this code: ${title}\n${url}`);
          Swal.fire({
            icon: 'info',
            title: 'Discord',
            text: 'Link copied! Paste it in your Discord channel.'
          });
          return;
        case 'slack':
          copyToClipboard(`Check out this code: ${title}\n${url}`);
          Swal.fire({
            icon: 'info',
            title: 'Slack',
            text: 'Link copied! Paste it in your Slack channel.'
          });
          return;
        default:
          return;
      }

      window.open(shareUrl, '_blank', 'width=600,height=400');
    }

    function shareViaEmail(url, title) {
      const subject = encodeURIComponent(`Check out this code: ${title}`);
      const body = encodeURIComponent(`I thought you might find this code snippet interesting:\n\n${title}\n\n${url}\n\nShared via PasteForge`);
      window.location.href = `mailto:?subject=${subject}&body=${body}`;
    }

    function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
          icon: 'success',
          title: 'Copied!',
          text: 'Link copied to clipboard',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000
        });
      }).catch(() => {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);

        Swal.fire({
          icon: 'success',
          title: 'Copied!',
          text: 'Link copied to clipboard',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000
        });
      });
    }

    function loadShareAnalytics(pasteId) {
      fetch('enhanced_paste_share.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=get_share_analytics&paste_id=${pasteId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          displayShareAnalytics(data.analytics);
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.error || 'Failed to load analytics'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to load analytics'
        });
      });
    }

    function displayShareAnalytics(analytics) {
      const container = document.getElementById('shareAnalytics');

      if (analytics.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-sm">No shares yet.</p>';
      } else {
        let html = '<div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4"><h4 class="font-medium mb-3">Share Analytics</h4><div class="space-y-2">';

        analytics.forEach(item => {
          html += `
            <div class="flex justify-between items-center text-sm">
              <span class="flex items-center">
                <i class="fab fa-${item.platform} mr-2"></i>
                ${item.platform.charAt(0).toUpperCase() + item.platform.slice(1)}
              </span>
              <span>${item.share_count} shares • ${item.total_clicks} clicks</span>
            </div>
          `;
        });

        html += '</div></div>';
        container.innerHTML = html;
      }

      container.classList.remove('hidden');
    }

    // Legacy share modal function for backward compatibility
    function openShareModal(pasteId) {
      openEnhancedShareModal(pasteId);
    }

    function showShareModal(shareData) {
      const modalHtml = `
        <div id="shareModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b dark:border-gray-700">
              <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-share mr-2"></i>Share Paste
              </h3>
              <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <div class="p-6 space-y-6">
              <!-- Direct Link -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Direct Link
                </label>
                <div class="flex">
                  <input type="text" 
                         id="shareUrl" 
                         value="${shareData.url}" 
                         readonly 
                         class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-l-lg bg-gray-50 dark:bg-gray-700 text-sm">
                  <button onclick="copyShareUrl()" 
                          class="px-4 py-2 bg-blue-500 text-white rounded-r-lg hover:bg-blue-600">
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
              </div>

              <!-- Social Media Sharing -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Share on Social Media
                </label>
                <div class="flex flex-wrap gap-3">
                  <button onclick="shareOnTwitter('${shareData.url}', '${shareData.title}')" 
                          class="flex items-center px-4 py-2 bg-blue-400 text-white rounded hover:bg-blue-500">
                    <i class="fab fa-twitter mr-2"></i>Twitter
                  </button>
                  <button onclick="shareOnFacebook('${shareData.url}')" 
                          class="flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    <i class="fab fa-facebook mr-2"></i>Facebook
                  </button>
                  <button onclick="shareOnLinkedIn('${shareData.url}', '${shareData.title}')" 
                          class="flex items-center px-4 py-2 bg-blue-700 text-white rounded hover:bg-blue-800">
                    <i class="fab fa-linkedin mr-2"></i>LinkedIn
                  </button>
                  <button onclick="shareOnReddit('${shareData.url}', '${shareData.title}')" 
                          class="flex items-center px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600">
                    <i class="fab fa-reddit mr-2"></i>Reddit
                  </button>
                </div>
              </div>

              <!-- QR Code -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  QR Code for Mobile Sharing
                </label>
                <div class="flex justify-center">
                  <img src="${shareData.qr_code}" alt="QR Code" class="border rounded">
                </div>
                <p class="text-xs text-gray-500 text-center mt-2">Scan with your phone to open the paste</p>
              </div>

              <!-- Embed Code -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                  Embed in Website
                </label>
                <div class="space-y-3">
                  <div class="flex gap-2">
                    <select id="embedTheme" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700">
                      <option value="light">Light Theme</option>
                      <option value="dark">Dark Theme</option>
                    </select>
                    <input type="number" 
                           id="embedHeight" 
                           value="400" 
                           min="200" 
                           max="800" 
                           class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 w-24">
                    <span class="flex items-center text-sm text-gray-500">px</span>
                    <button onclick="generateEmbedCode()" 
                            class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                      Generate
                    </button>
                  </div>
                  <textarea id="embedCode" 
                            readonly 
                            rows="3" 
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-gray-50 dark:bg-gray-700 text-sm font-mono"
                            placeholder="Click 'Generate' to create embed code"></textarea>
                  <div class="flex justify-between">
                    <button onclick="copyEmbedCode()" 
                            class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                      <i class="fas fa-copy mr-1"></i>Copy Code
                    </button>
                    <button onclick="previewEmbed()" 
                            class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                      <i class="fas fa-eye mr-1"></i>Preview
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.insertAdjacentHTML('beforeend', modalHtml);

      // Store paste ID for embed generation
      document.getElementById('shareModal').dataset.pasteId = shareData.url.split('id=')[1];
    }

    function closeShareModal() {
      const modal = document.getElementById('shareModal');
      if (modal) {
        modal.remove();
      }
    }

    function copyShareUrl() {
      const urlInput = document.getElementById('shareUrl');
      urlInput.select();
      navigator.clipboard.writeText(urlInput.value).then(() => {
        Swal.fire({
          icon: 'success',
          title: 'Copied!',
          text: 'Share URL copied to clipboard',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000
        });
      });
    }

    function shareOnTwitter(url, title) {
      const twitterUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent('Check out this code: ' + title)}`;
      window.open(twitterUrl, '_blank');
    }

    function shareOnFacebook(url) {
      const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
      window.open(facebookUrl, '_blank');
    }

    function shareOnLinkedIn(url, title) {
      const linkedinUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}&title=${encodeURIComponent(title)}`;
      window.open(linkedinUrl, '_blank');
    }

    function shareOnReddit(url, title) {
      const redditUrl = `https://reddit.com/submit?url=${encodeURIComponent(url)}&title=${encodeURIComponent(title)}`;
      window.open(redditUrl, '_blank');
    }

    function generateEmbedCode() {
      const modal = document.getElementById('shareModal');
      const pasteId = modal.dataset.pasteId;
      const theme = document.getElementById('embedTheme').value;
      const height = document.getElementById('embedHeight').value;

      fetch('paste_share.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=generate_embed_code&paste_id=${pasteId}&theme=${theme}&height=${height}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('embedCode').value = data.embed_code;
          document.getElementById('embedCode').dataset.previewUrl = data.preview_url;
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to generate embed code'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to generate embed code'
        });
      });
    }

    function copyEmbedCode() {
      const embedCode = document.getElementById('embedCode');
      if (!embedCode.value) {
        Swal.fire({
          icon: 'warning',
          title: 'No Code',
          text: 'Please generate embed code first'
        });
        return;
      }

      embedCode.select();
      navigator.clipboard.writeText(embedCode.value).then(() => {
        Swal.fire({
          icon: 'success',
          title: 'Copied!',
          text: 'Embed code copied to clipboard',
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000
        });
      });
    }

    function previewEmbed() {
      const embedCode = document.getElementById('embedCode');
      if (!embedCode.value) {
        Swal.fire({
          icon: 'warning',
          title: 'No Code',
          text: 'Please generate embed code first'
        });
        return;
      }

      const previewUrl = embedCode.dataset.previewUrl;
      if (previewUrl) {
        window.open(previewUrl, '_blank');
      }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('shareModal');
      if (modal && e.target === modal) {
        closeShareModal();
      }
    });

    // Version Diff Functions
    function openDiffModal() {
      document.getElementById('diffModal').classList.remove('hidden');
      // Set default selections
      const fromSelect = document.getElementById('fromVersion');
      const toSelect = document.getElementById('toVersion');

      // Default: compare latest two versions
      if (fromSelect.options.length > 1) {
        fromSelect.selectedIndex = 1; // Second option (previous version)
      }
      toSelect.selectedIndex = 0; // Current version
    }

    function closeDiffModal() {
      document.getElementById('diffModal').classList.add('hidden');
      clearDiff();
    }

    function clearDiff() {
      document.getElementById('diffResult').classList.add('hidden');
      document.getElementById('noDiffMessage').classList.add('hidden');
      document.getElementById('diffLoading').classList.add('hidden');
      document.getElementById('diffContent').innerHTML = '';
    }

    function generateDiff() {
      const fromVersion = document.getElementById('fromVersion').value;
      const toVersion = document.getElementById('toVersion').value;

      if (fromVersion === toVersion) {
        Swal.fire({
          icon: 'warning',
          title: 'Same Version Selected',
          text: 'Please select two different versions to compare.'
        });
        return;
      }

      // Show loading
      clearDiff();
      document.getElementById('diffLoading').classList.remove('hidden');

      // Fetch diff data
      fetch('version_diff.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `paste_id=<?= $paste['id'] ?>&from_version=${fromVersion}&to_version=${toVersion}`
      })
      .then(response => response.json())
      .then(data => {
        document.getElementById('diffLoading').classList.add('hidden');

        if (data.success) {
          if (data.has_differences) {
            document.getElementById('diffContent').innerHTML = data.diff_html;
            document.getElementById('diffResult').classList.remove('hidden');
          } else {
            document.getElementById('noDiffMessage').classList.remove('hidden');
          }
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.error || 'Failed to generate diff'
          });
        }
      })
      .catch(error => {
        document.getElementById('diffLoading').classList.add('hidden');
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to generate diff'
        });
      });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('diffModal');
      if (modal && e.target === modal) {
        closeDiffModal();
      }
    });

    // Embed Modal Functions
    function openEmbedModal(pasteId) {
      <?php if ($is_flagged_for_blur): ?>
        Swal.fire({
          icon: 'warning',
          title: 'Content Protected',
          text: 'This paste is under moderation review and cannot be embedded.'
        });
        return;
      <?php endif; ?>

      const modal = document.getElementById('embedModal');
      if (modal) {
        modal.classList.remove('hidden');
        updateEmbedCode();
      }
    }

    function closeEmbedModal() {
      const modal = document.getElementById('embedModal');
      if (modal) {
        modal.classList.add('hidden');
      }
    }

    function updateEmbedPreview() {
      const theme = document.getElementById('embedTheme').value;
      const height = document.getElementById('embedHeight').value;
      const preview = document.getElementById('embedPreview');

      if (preview) {
        preview.src = `embed.php?id=<?= $paste['id'] ?>&theme=${theme}`;
        preview.height = height;
      }

      updateEmbedCode();
    }

    function updateEmbedCode() {
      const theme = document.getElementById('embedTheme').value;
      const width = document.getElementById('embedWidth').value;
      const height = document.getElementById('embedHeight').value;
      const baseUrl = window.location.origin;

      const embedUrl = `${baseUrl}/embed.php?id=<?= $paste['id'] ?>&theme=${theme}`;
      const embedCode = `<iframe src="${embedUrl}" width="${width}" height="${height}" frameborder="0"></iframe>`;

      const textarea = document.getElementById('embedCodeTextarea');
      if (textarea) {
        textarea.value = embedCode;
      }
    }

    function copyEmbedCode() {
      const textarea = document.getElementById('embedCodeTextarea');
      if (textarea && textarea.value) {
        textarea.select();
        navigator.clipboard.writeText(textarea.value).then(() => {
          Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Embed code copied to clipboard',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
          });
        }).catch(() => {
          // Fallback for older browsers
          document.execCommand('copy');
          Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Embed code copied to clipboard',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
          });
        });
      }
    }

    // Close embed modal when clicking outside
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('embedModal');
      if (modal && e.target === modal) {
        closeEmbedModal();
      }
    });

    // Enhanced Comments Functions
    function toggleReplyForm(commentId) {
      const form = document.getElementById(`reply-form-${commentId}`);
      if (form) {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) {
          form.querySelector('textarea').focus();
        }
      }
    }

    function toggleReplies(commentId) {
      const replies = document.getElementById(`replies-${commentId}`);
      if (replies) {
        replies.classList.toggle('hidden');
      }
    }

    function deleteComment(commentId, replyId, pasteId) {
      Swal.fire({
        title: 'Delete Comment?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="action" value="delete_comment">
            <input type="hidden" name="comment_id" value="${commentId}">
            ${replyId ? `<input type="hidden" name="reply_id" value="${replyId}">` : ''}
            ${replyId ? `<input type="hidden" name="parent_comment_id" value="${commentId}">` : ''}
            <input type="hidden" name="paste_id" value="${pasteId}">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    function reportComment(commentId, replyId, pasteId) {
      Swal.fire({
        title: 'Report Comment',
        html: `
          <div class="text-left space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Reason:</label>
              <select id="report-reason" class="w-full p-2 border rounded">
                <option value="spam">Spam</option>
                <option value="harassment">Harassment</option>
                <option value="inappropriate">Inappropriate Content</option>
                <option value="off-topic">Off Topic</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Additional Details (optional):</label>
              <textarea id="report-description" class="w-full p-2 border rounded" rows="3" placeholder="Provide additional context..."></textarea>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit Report',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const reason = document.getElementById('report-reason').value;
          const description = document.getElementById('report-description').value;

          if (!reason) {
            Swal.showValidationMessage('Please select a reason');
            return false;
          }

          return { reason, description };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="action" value="report_comment">
            ${commentId ? `<input type="hidden" name="comment_id" value="${commentId}">` : ''}
            ${replyId ? `<input type="hidden" name="reply_id" value="${replyId}">` : ''}
            <input type="hidden" name="paste_id" value="${pasteId}">
            <input type="hidden" name="reason" value="${result.value.reason}">
            <input type="hidden" name="description" value="${result.value.description}">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    // Auto-expand all replies on page load
    document.addEventListener('DOMContentLoaded', function() {
      window.pasteDecrypted = false;
      window.decryptedText = null;
      // Show all replies by default
      document.querySelectorAll('[id^="replies-"]').forEach(function(element) {
        element.classList.remove('hidden');
      });

      // Load templates lazily only when needed
      const templateSelector = document.getElementById('templateSelector');
      if (templateSelector) {
        templateSelector.addEventListener('focus', function() {
          if (!this.dataset.loaded) {
            loadTemplatesForSelector();
            this.dataset.loaded = 'true';
          }
        }, { once: true });
      }

      // Initialize countdown timers
      initializeCountdownTimers();

      <?php if (isset($paste) && $paste['zero_knowledge']): ?>
      (async function() {
        let match = window.location.hash.match(/key=([^&]+)/);
        const container = document.getElementById('zkContent');
        const banner = document.getElementById('zkPrivateLink');
        if (!container) return;
        if (!match) {
          const stored = sessionStorage.getItem('zkKey');
          if (stored) {
            window.location.hash = 'key=' + encodeURIComponent(stored);
            match = ['#key=' + stored, stored];
            sessionStorage.removeItem('zkKey');
          }
        }
        if (!match) {
          container.textContent = 'This paste is encrypted. You must access it using the original link that includes the key.';
          return;
        }
        try {
          const keyBytes = Uint8Array.from(atob(decodeURIComponent(match[1])), c => c.charCodeAt(0));
          const encData = Uint8Array.from(atob("<?= htmlspecialchars($paste['content'], ENT_QUOTES) ?>"), c => c.charCodeAt(0));
          const iv = encData.slice(0,12);
          const cipher = encData.slice(12);
          const key = await crypto.subtle.importKey('raw', keyBytes, {name:'AES-GCM'}, false, ['decrypt']);
          const decrypted = await crypto.subtle.decrypt({name:'AES-GCM', iv}, key, cipher);
          const plain = new TextDecoder().decode(decrypted);
          container.textContent = plain;
          window.decryptedText = plain;
          if (banner) {
            const fullLink = window.location.href;
            banner.innerHTML = `<strong>Private Link:</strong> <a href="${fullLink}" class="underline break-all">${fullLink}</a><br><em>Save this link — without the key, this paste cannot be recovered.</em>`;
          }
          if (window.Prism) Prism.highlightAllUnder(container.parentElement);
          window.pasteDecrypted = true;
          document.querySelectorAll('.zk-restricted').forEach(el => el.classList.remove('hidden'));
        } catch (e) {
          container.textContent = 'Failed to decrypt paste. The key may be invalid.';
        }
      })();
      <?php endif; ?>
    });

    // Countdown timer functionality
    function initializeCountdownTimers() {
      // Initialize all countdown timers on the page
      document.querySelectorAll('[data-expires]').forEach(function(element) {
        const expireTime = parseInt(element.dataset.expires);
        if (expireTime > 0) {
          startCountdown(element, expireTime);
        }
      });
    }

    function startCountdown(element, expireTime) {
      function updateCountdown() {
        const now = Math.floor(Date.now() / 1000);
        const timeLeft = expireTime - now;

        if (timeLeft <= 0) {
          element.innerHTML = '<span class="text-red-500 font-bold">EXPIRED</span>';
          // If viewing an expired paste, redirect to homepage
          if (window.location.search.includes('id=')) {
            setTimeout(() => {
              window.location.href = '/';
            }, 2000);
          }
          return;
        }

        const days = Math.floor(timeLeft / 86400);
        const hours = Math.floor((timeLeft % 86400) / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;

        let display = '';
        let urgencyClass = 'countdown-normal';

        if (timeLeft < 60) {
          urgencyClass = 'countdown-urgent';
          display = `${seconds}s`;
        } else if (timeLeft < 3600) {
          urgencyClass = 'countdown-warning';
          display = `${minutes}m ${seconds}s`;
        } else if (timeLeft < 86400) {
          display = `${hours}h ${minutes}m`;
        } else {
          display = `${days}d ${hours}h`;
        }

        const iconColor = timeLeft < 60 ? 'text-red-500' : (timeLeft < 3600 ? 'text-yellow-500' : 'text-gray-500');

        element.innerHTML = `<i class="fas fa-clock ${iconColor}"></i> <span class="countdown-timer ${urgencyClass}">${display}</span>`;

        // Update frequency based on urgency
        const updateInterval = timeLeft < 60 ? 1000 : (timeLeft < 3600 ? 1000 : 60000);
        setTimeout(updateCountdown, updateInterval);
      }

      updateCountdown();
    }

    // Template functionality
    function loadTemplatesForSelector() {
      const selector = document.getElementById('templateSelector');
      if (!selector) return;

      fetch('?action=get_templates')
        .then(response => response.json())
        .then(templates => {
          // Clear existing options except the first one
          selector.innerHTML = '<option value="">Choose a template...</option>';

          // Group templates by category
          const grouped = {};
          templates.forEach(template => {
            if (!grouped[template.category]) {
              grouped[template.category] = [];
            }
            grouped[template.category].push(template);
          });

          // Add options grouped by category
          Object.keys(grouped).sort().forEach(category => {
            const optgroup = document.createElement('optgroup');
            optgroup.label = category.charAt(0).toUpperCase() + category.slice(1);

            grouped[category].forEach(template => {
              const option = document.createElement('option');
              option.value = template.id;
              option.textContent = `${template.name} (${template.language})`;
              optgroup.appendChild(option);
            });

            selector.appendChild(optgroup);
          });
        })
        .catch(error => console.error('Error loading templates:', error));
    }

    function loadSelectedTemplate() {
      const selector = document.getElementById('templateSelector');
      const templateId = selector.value;

      if (!templateId) {
        alert('Please select a template first');
        return;
      }

      fetch(`?action=get_template&id=${templateId}`)
        .then(response => response.json())
        .then(template => {
          if (template && !template.error) {
            // Fill form fields
            document.querySelector('input[name="title"]').value = template.name;
            document.querySelector('textarea[name="content"]').value = template.content;
            document.querySelector('select[name="language"]').value = template.language;

            // Show success message
            Swal.fire({
              icon: 'success',
              title: 'Template Loaded!',
              text: `"${template.name}" has been loaded`,
              toast: true,
              position: 'top-end',
              showConfirmButton: false,
              timer: 2000
            });
          } else {
            alert('Error loading template');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error loading template');
        });
    }

    function saveCurrentAsTemplate() {
      <?php if (!$user_id): ?>
        Swal.fire({
          icon: 'info',
          title: 'Login Required',
          text: 'You need to be logged in to save templates',
          showCancelButton: true,
          confirmButtonText: 'Login',
          cancelButtonText: 'Cancel'
        }).then((result) => {
          if (result.isConfirmed) {
            window.location.href = '?page=login';
          }
        });
        return;
      <?php endif; ?>

      const content = document.querySelector('textarea[name="content"]').value;
      const language = document.querySelector('select[name="language"]').value;
      const title = document.querySelector('input[name="title"]').value;

      if (!content.trim()) {
        Swal.fire({
          icon: 'warning',
          title: 'No Content',
          text: 'Please enter some content first'
        });
        return;
      }

      Swal.fire({
        title: 'Save as Template',
        html: `
          <div class="text-left space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">Template Name:</label>
              <input id="template-name" class="w-full p-2 border rounded" value="${title || ''}" placeholder="My Custom Template">
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Description:</label>
              <input id="template-description" class="w-full p-2 border rounded" placeholder="Brief description">
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">Category:</label>
              <input id="template-category" class="w-full p-2 border rounded" value="general" placeholder="general">
            </div>
            <div>
              <label class="flex items-center space-x-2">
                <input type="checkbox" id="template-public" checked>
                <span>Make template public</span>
              </label>
            </div>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Template',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
          const name = document.getElementById('template-name').value.trim();
          const description = document.getElementById('template-description').value.trim();
          const category = document.getElementById('template-category').value.trim();
          const isPublic = document.getElementById('template-public').checked;

          if (!name) {
            Swal.showValidationMessage('Please enter a template name');
            return false;
          }

          return { name, description, category, isPublic };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'save_template');
          formData.append('template_name', result.value.name);
          formData.append('template_description', result.value.description);
          formData.append('template_content', content);
          formData.append('template_language', language);
          formData.append('template_category', result.value.category);
          if (result.value.isPublic) {
            formData.append('is_public', '1');
          }

          fetch('', {
            method: 'POST',
            body: formData
          }).then(() => {
            Swal.fire({
              icon: 'success',
              title: 'Template Saved!',
              text: 'Your template has been saved successfully',
              toast: true,
              position: 'top-end',
              showConfirmButton: false,
              timer: 2000
            });

            // Reload templates for selector
            loadTemplatesForSelector();
          }).catch(error => {
            console.error('Error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to save template'
            });
          });
        }
      });
    }
  </script>
</body>
</html>