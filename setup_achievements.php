
<?php
try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Setting up achievements system...\n";

    // Create achievements table
    $db->exec("CREATE TABLE IF NOT EXISTS achievements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        description TEXT NOT NULL,
        icon TEXT NOT NULL,
        category TEXT DEFAULT 'general',
        points INTEGER DEFAULT 10,
        is_active BOOLEAN DEFAULT 1,
        created_at INTEGER DEFAULT (strftime('%s', 'now'))
    )");

    // Create user_achievements table
    $db->exec("CREATE TABLE IF NOT EXISTS user_achievements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        achievement_id INTEGER NOT NULL,
        unlocked_at INTEGER DEFAULT (strftime('%s', 'now')),
        progress_data TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id),
        FOREIGN KEY(achievement_id) REFERENCES achievements(id),
        UNIQUE(user_id, achievement_id)
    )");

    // Create user_achievement_progress table for tracking progress
    $db->exec("CREATE TABLE IF NOT EXISTS user_achievement_progress (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        achievement_name TEXT NOT NULL,
        current_progress INTEGER DEFAULT 0,
        target_progress INTEGER NOT NULL,
        last_updated INTEGER DEFAULT (strftime('%s', 'now')),
        FOREIGN KEY(user_id) REFERENCES users(id),
        UNIQUE(user_id, achievement_name)
    )");

    // Insert default achievements
    $achievements = [
        ['first_paste', 'First Paste', 'Created your first paste', 'fas fa-baby', 'creator', 10],
        ['paste_creator_10', '10 Pastes Created', 'Created 10 pastes', 'fas fa-pencil-alt', 'creator', 25],
        ['paste_creator_50', '50 Pastes Created', 'Created 50 pastes', 'fas fa-edit', 'creator', 50],
        ['paste_creator_100', '100 Pastes Created', 'Created 100 pastes', 'fas fa-pen-fancy', 'creator', 100],
        ['popular_paste_100', 'Popular Creator', 'One of your pastes reached 100 views', 'fas fa-fire', 'popularity', 30],
        ['popular_paste_500', 'Viral Creator', 'One of your pastes reached 500 views', 'fas fa-rocket', 'popularity', 75],
        ['popular_paste_1000', 'Internet Famous', 'One of your pastes reached 1000 views', 'fas fa-star', 'popularity', 150],
        ['first_chain', 'Chain Builder', 'Created your first paste chain', 'fas fa-link', 'collaboration', 20],
        ['chain_contributor', 'Chain Contributor', 'Contributed to a paste chain', 'fas fa-hands-helping', 'collaboration', 15],
        ['first_fork', 'Fork Creator', 'Created your first fork', 'fas fa-code-branch', 'collaboration', 20],
        ['social_butterfly', 'Social Butterfly', 'Followed 5 users', 'fas fa-users', 'social', 25],
        ['commenter', 'Commenter', 'Left 10 comments', 'fas fa-comments', 'social', 20],
        ['collection_creator', 'Organizer', 'Created your first collection', 'fas fa-folder-plus', 'organization', 15],
        ['early_adopter', 'Early Adopter', 'Joined PasteForge', 'fas fa-seedling', 'milestone', 5],
        ['week_streak', 'Weekly Creator', 'Created pastes for 7 consecutive days', 'fas fa-calendar-week', 'streak', 40],
        ['month_streak', 'Monthly Creator', 'Created pastes for 30 consecutive days', 'fas fa-calendar-alt', 'streak', 100],
        ['language_explorer', 'Language Explorer', 'Used 10 different programming languages', 'fas fa-globe', 'diversity', 50],
        ['night_owl', 'Night Owl', 'Created 10 pastes between midnight and 6 AM', 'fas fa-moon', 'quirky', 30],
        ['template_creator', 'Template Master', 'Created your first template', 'fas fa-copy', 'creator', 25],
        ['project_manager', 'Project Manager', 'Created your first project', 'fas fa-project-diagram', 'organization', 30]
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO achievements (name, title, description, icon, category, points) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($achievements as $achievement) {
        $stmt->execute($achievement);
    }

    // Create indexes for performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_achievements_user ON user_achievements(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_achievements_achievement ON user_achievements(achievement_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_achievement_progress_user ON user_achievement_progress(user_id)");

    echo "Achievements system setup complete!\n";
    echo "Created " . count($achievements) . " default achievements\n";
    echo "Tables created:\n";
    echo "- achievements: achievement definitions\n";
    echo "- user_achievements: unlocked achievements\n";
    echo "- user_achievement_progress: progress tracking\n";

} catch (PDOException $e) {
    echo "Error setting up achievements: " . $e->getMessage() . "\n";
}
?>
