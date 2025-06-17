
<?php
require_once 'achievements_helper.php';

echo "Starting retroactive achievement check...\n";

try {
    $db = new PDO('sqlite:database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $achievements_helper = new AchievementsHelper();
    
    // Get all users
    $stmt = $db->prepare("SELECT id, username, created_at FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users to check\n\n";
    
    foreach ($users as $user) {
        echo "Checking user: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
        $new_achievements = [];
        
        // Check Early Adopter achievement
        $early_adopter = $achievements_helper->awardAchievement($user['id'], 'early_adopter');
        if ($early_adopter) {
            $new_achievements[] = $early_adopter;
        }
        
        // Check paste-related achievements
        $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $paste_count = $stmt->fetchColumn();
        
        if ($paste_count > 0) {
            echo "  - Found {$paste_count} pastes\n";
            
            // First paste
            $first_paste = $achievements_helper->awardAchievement($user['id'], 'first_paste');
            if ($first_paste) {
                $new_achievements[] = $first_paste;
            }
            
            // Paste milestones
            $milestones = [
                10 => 'paste_creator_10',
                50 => 'paste_creator_50', 
                100 => 'paste_creator_100'
            ];
            
            foreach ($milestones as $count => $achievement_name) {
                if ($paste_count >= $count) {
                    $milestone = $achievements_helper->awardAchievement($user['id'], $achievement_name);
                    if ($milestone) {
                        $new_achievements[] = $milestone;
                    }
                }
            }
        }
        
        // Check popularity achievements
        $stmt = $db->prepare("SELECT MAX(views) FROM pastes WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $max_views = $stmt->fetchColumn() ?: 0;
        
        if ($max_views > 0) {
            echo "  - Highest paste views: {$max_views}\n";
            
            $view_milestones = [
                100 => 'popular_paste_100',
                500 => 'popular_paste_500',
                1000 => 'popular_paste_1000'
            ];
            
            foreach ($view_milestones as $views => $achievement_name) {
                if ($max_views >= $views) {
                    $popular = $achievements_helper->awardAchievement($user['id'], $achievement_name);
                    if ($popular) {
                        $new_achievements[] = $popular;
                    }
                }
            }
        }
        
        // Check language diversity
        $stmt = $db->prepare("SELECT COUNT(DISTINCT language) FROM pastes WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $language_count = $stmt->fetchColumn();
        
        if ($language_count >= 10) {
            echo "  - Used {$language_count} different languages\n";
            $explorer = $achievements_helper->awardAchievement($user['id'], 'language_explorer');
            if ($explorer) {
                $new_achievements[] = $explorer;
            }
        }
        
        // Check fork achievements
        $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND original_paste_id IS NOT NULL");
        $stmt->execute([$user['id']]);
        $fork_count = $stmt->fetchColumn();
        
        if ($fork_count > 0) {
            echo "  - Created {$fork_count} forks\n";
            $fork_achievement = $achievements_helper->awardAchievement($user['id'], 'first_fork');
            if ($fork_achievement) {
                $new_achievements[] = $fork_achievement;
            }
        }
        
        // Check chain achievements
        $stmt = $db->prepare("SELECT COUNT(*) FROM pastes WHERE user_id = ? AND parent_paste_id IS NOT NULL");
        $stmt->execute([$user['id']]);
        $chain_count = $stmt->fetchColumn();
        
        if ($chain_count > 0) {
            echo "  - Participated in {$chain_count} paste chains\n";
            $chain_achievement = $achievements_helper->awardAchievement($user['id'], 'chain_contributor');
            if ($chain_achievement) {
                $new_achievements[] = $chain_achievement;
            }
        }
        
        // Check if user created any chains
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM pastes p1 
            WHERE p1.user_id = ? 
            AND EXISTS (SELECT 1 FROM pastes p2 WHERE p2.parent_paste_id = p1.id)
        ");
        $stmt->execute([$user['id']]);
        $created_chains = $stmt->fetchColumn();
        
        if ($created_chains > 0) {
            echo "  - Created {$created_chains} paste chains\n";
            $chain_creator = $achievements_helper->awardAchievement($user['id'], 'first_chain');
            if ($chain_creator) {
                $new_achievements[] = $chain_creator;
            }
        }
        
        // Check social achievements
        if (class_exists('PDO') && $db) {
            // Check following count
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
                $stmt->execute([$user['id']]);
                $following_count = $stmt->fetchColumn();
                
                if ($following_count >= 5) {
                    echo "  - Following {$following_count} users\n";
                    $social = $achievements_helper->awardAchievement($user['id'], 'social_butterfly');
                    if ($social) {
                        $new_achievements[] = $social;
                    }
                }
            } catch (PDOException $e) {
                // user_follows table might not exist
            }
            
            // Check comments
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM paste_comments WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $comment_count = $stmt->fetchColumn();
                
                if ($comment_count >= 10) {
                    echo "  - Left {$comment_count} comments\n";
                    $commenter = $achievements_helper->awardAchievement($user['id'], 'commenter');
                    if ($commenter) {
                        $new_achievements[] = $commenter;
                    }
                }
            } catch (PDOException $e) {
                // paste_comments table might not exist
            }
            
            // Check collections
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM collections WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $collection_count = $stmt->fetchColumn();
                
                if ($collection_count > 0) {
                    echo "  - Created {$collection_count} collections\n";
                    $organizer = $achievements_helper->awardAchievement($user['id'], 'collection_creator');
                    if ($organizer) {
                        $new_achievements[] = $organizer;
                    }
                }
            } catch (PDOException $e) {
                // collections table might not exist
            }
            
            // Check projects
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE owner_id = ?");
                $stmt->execute([$user['id']]);
                $project_count = $stmt->fetchColumn();
                
                if ($project_count > 0) {
                    echo "  - Created {$project_count} projects\n";
                    $project_manager = $achievements_helper->awardAchievement($user['id'], 'project_manager');
                    if ($project_manager) {
                        $new_achievements[] = $project_manager;
                    }
                }
            } catch (PDOException $e) {
                // projects table might not exist
            }
            
            // Check templates
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM paste_templates WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $template_count = $stmt->fetchColumn();
                
                if ($template_count > 0) {
                    echo "  - Created {$template_count} templates\n";
                    $template_master = $achievements_helper->awardAchievement($user['id'], 'template_creator');
                    if ($template_master) {
                        $new_achievements[] = $template_master;
                    }
                }
            } catch (PDOException $e) {
                // paste_templates table might not exist
            }
        }
        
        // Check night owl achievement (pastes created between midnight and 6 AM)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM pastes 
            WHERE user_id = ? 
            AND strftime('%H', datetime(created_at, 'unixepoch')) BETWEEN '00' AND '05'
        ");
        $stmt->execute([$user['id']]);
        $night_pastes = $stmt->fetchColumn();
        
        if ($night_pastes >= 10) {
            echo "  - Created {$night_pastes} pastes during night hours\n";
            $night_owl = $achievements_helper->awardAchievement($user['id'], 'night_owl');
            if ($night_owl) {
                $new_achievements[] = $night_owl;
            }
        }
        
        if (!empty($new_achievements)) {
            echo "  ✅ Awarded " . count($new_achievements) . " achievements:\n";
            foreach ($new_achievements as $achievement) {
                echo "     - " . $achievement['title'] . " (" . $achievement['points'] . " pts)\n";
            }
        } else {
            echo "  - No new achievements to award\n";
        }
        
        echo "\n";
    }
    
    echo "Retroactive achievement check completed!\n";
    
    // Show summary statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT ua.user_id) as users_with_achievements,
            COUNT(*) as total_achievements_awarded,
            SUM(a.points) as total_points_awarded
        FROM user_achievements ua 
        JOIN achievements a ON ua.achievement_id = a.id
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== SUMMARY ===\n";
    echo "Users with achievements: " . $stats['users_with_achievements'] . "\n";
    echo "Total achievements awarded: " . $stats['total_achievements_awarded'] . "\n";
    echo "Total points awarded: " . number_format($stats['total_points_awarded']) . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
