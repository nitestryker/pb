
<?php
// This file displays achievements on user profiles
require_once 'achievements_helper.php';

function displayUserAchievements($user_id, $show_stats = true) {
    $achievements_helper = new AchievementsHelper();
    $achievements = $achievements_helper->getUserAchievements($user_id);
    $stats = $achievements_helper->getUserAchievementStats($user_id);
    
    if ($show_stats): ?>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <h3 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                Achievement Progress
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-500"><?= $stats['unlocked_count'] ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Achievements Unlocked</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-500"><?= $stats['completion_percentage'] ?>%</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Completion Rate</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-500"><?= number_format($stats['total_points']) ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Points</div>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span>Overall Progress</span>
                    <span><?= $stats['unlocked_count'] ?> / <?= $stats['total_count'] ?></span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-3 rounded-full" 
                         style="width: <?= $stats['completion_percentage'] ?>%"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        <h3 class="text-xl font-semibold mb-6 flex items-center">
            <i class="fas fa-medal text-yellow-500 mr-2"></i>
            Achievements & Badges
            <span class="ml-2 text-sm font-normal text-gray-500">(<?= count($achievements) ?> unlocked)</span>
        </h3>
        
        <?php if (empty($achievements)): ?>
            <div class="text-center py-8">
                <i class="fas fa-trophy text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">No achievements unlocked yet.</p>
                <p class="text-sm text-gray-400 mt-2">Start creating pastes to earn your first badges!</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 
                                border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full 
                                           flex items-center justify-center text-white shadow-lg">
                                    <i class="<?= htmlspecialchars($achievement['icon']) ?> text-lg"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm">
                                    <?= htmlspecialchars($achievement['title']) ?>
                                </h4>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <?= htmlspecialchars($achievement['description']) ?>
                                </p>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs 
                                                 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                        <?= $achievement['points'] ?> pts
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?= date('M j, Y', $achievement['unlocked_at']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Achievement Categories -->
            <?php
            $categories = [];
            foreach ($achievements as $achievement) {
                $categories[$achievement['category']][] = $achievement;
            }
            
            if (count($categories) > 1): ?>
                <div class="mt-8">
                    <h4 class="text-lg font-semibold mb-4">By Category</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($categories as $category => $category_achievements): ?>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-center">
                                <div class="text-lg font-bold text-blue-500"><?= count($category_achievements) ?></div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 capitalize"><?= $category ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
    // Add animation for newly unlocked achievements
    document.addEventListener('DOMContentLoaded', function() {
        const achievementCards = document.querySelectorAll('.achievement-card');
        achievementCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
    
    // Achievement notification system
    function showAchievementNotification(achievement) {
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-gradient-to-r from-yellow-400 to-orange-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm';
        notification.innerHTML = `
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="${achievement.icon} text-lg"></i>
                </div>
                <div>
                    <div class="font-semibold">Achievement Unlocked!</div>
                    <div class="text-sm opacity-90">${achievement.title}</div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.style.transition = 'transform 0.3s ease';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Remove after 5 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }
    </script>
<?php
}
?>
