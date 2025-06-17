<?php
try {
  $db = new PDO('sqlite:database.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Track schema version
  $db->exec("CREATE TABLE IF NOT EXISTS schema_version (
    version INTEGER PRIMARY KEY,
    applied_at INTEGER DEFAULT (strftime('%s', 'now'))
  )");

  // Function to check if a version has been applied
  function isVersionApplied($db, $version) {
    $stmt = $db->prepare("SELECT 1 FROM schema_version WHERE version = ?");
    $stmt->execute([$version]);
    return (bool)$stmt->fetch();
  }

  // Function to mark version as applied
  function markVersionApplied($db, $version) {
    $db->prepare("INSERT INTO schema_version (version) VALUES (?)")->execute([$version]);
  }

  // Base tables - Version 1
  if (!isVersionApplied($db, 1)) {
    // Users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
      id TEXT PRIMARY KEY,
      username TEXT UNIQUE,
      password TEXT NOT NULL,
      email TEXT,
      profile_image TEXT DEFAULT NULL,
      website TEXT DEFAULT NULL,
      tagline TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");

    // Pastes table
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
      zero_knowledge INTEGER DEFAULT 0,
      flags INTEGER DEFAULT 0,
      flag_type TEXT,
      flag_source TEXT,
      ai_score FLOAT DEFAULT 0,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    markVersionApplied($db, 1);
  }

  // Social features - Version 2
  if (!isVersionApplied($db, 2)) {
    // Comments table
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      paste_id INTEGER,
      user_id TEXT,
      content TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      FOREIGN KEY(user_id) REFERENCES users(id),
      FOREIGN KEY(paste_id) REFERENCES pastes(id)
    )");

    // User pastes (favorites) table
    $db->exec("CREATE TABLE IF NOT EXISTS user_pastes (
      user_id TEXT,
      paste_id INTEGER,
      is_favorite BOOLEAN DEFAULT 0,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      FOREIGN KEY(user_id) REFERENCES users(id),
      FOREIGN KEY(paste_id) REFERENCES pastes(id)
    )");

    markVersionApplied($db, 2);
  }

  // Analytics - Version 3
  if (!isVersionApplied($db, 3)) {
    // Paste views table
    $db->exec("CREATE TABLE IF NOT EXISTS paste_views (
      paste_id INTEGER,
      ip_address TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      PRIMARY KEY (paste_id, ip_address),
      FOREIGN KEY(paste_id) REFERENCES pastes(id)
    )");

    // System logs table
    $db->exec("CREATE TABLE IF NOT EXISTS system_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      type TEXT,
      message TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");

    markVersionApplied($db, 3);
  }

  // Admin features - Version 4
  if (!isVersionApplied($db, 4)) {
    // Admin users table
    $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT UNIQUE,
      password TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");

    // Site settings table
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
      theme_default TEXT DEFAULT 'light',
      site_logo TEXT DEFAULT NULL,
      daily_paste_limit_free INTEGER DEFAULT 10,
      daily_paste_limit_premium INTEGER DEFAULT 50,
      encryption_enabled INTEGER DEFAULT 1,
      maintenance_mode INTEGER DEFAULT 0
    )");

    // User warnings table
    $db->exec("CREATE TABLE IF NOT EXISTS user_warnings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id TEXT,
      reason TEXT,
      admin_id INTEGER,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      FOREIGN KEY(user_id) REFERENCES users(id),
      FOREIGN KEY(admin_id) REFERENCES admin_users(id)
    )");

    // Admin notes table
    $db->exec("CREATE TABLE IF NOT EXISTS admin_notes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      paste_id INTEGER,
      admin_id INTEGER,
      note TEXT,
      created_at INTEGER DEFAULT (strftime('%s', 'now')),
      FOREIGN KEY(paste_id) REFERENCES pastes(id),
      FOREIGN KEY(admin_id) REFERENCES admin_users(id)
    )");

    markVersionApplied($db, 4);
  }

  // Create avatars directory if it doesn't exist
  if (!file_exists('avatars')) {
    mkdir('avatars', 0777, true);
  }

  // Get current schema version
  $stmt = $db->query("SELECT MAX(version) as version FROM schema_version");
  $currentVersion = $stmt->fetch()['version'];

  echo "Database setup completed successfully!\n";
  echo "Current schema version: {$currentVersion}\n";
  echo "All tables have been created.\n";
  echo "Avatars directory has been created.\n";

} catch (PDOException $e) {
  die("Database setup failed: " . $e->getMessage() . "\n");
}
?>
