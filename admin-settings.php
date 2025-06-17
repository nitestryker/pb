<?php
require_once(__DIR__ . '/admin-session.php');
check_admin_auth();
handle_logout();

$db = new PDO('sqlite:database.sqlite');

// Create settings table if not exists
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

// Insert default settings if not exist
$db->exec("INSERT OR IGNORE INTO site_settings (id) VALUES (1)");

// Handle settings update - AJAX only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    // Force JSON response
    header('Content-Type: application/json');
    
    try {
        $db->beginTransaction();
        
        // Validate inputs
        $site_name = trim($_POST['site_name']);
        if (empty($site_name)) {
            $site_name = 'PasteForge'; // Use default if empty
        }
        
        $max_paste_size = intval($_POST['max_paste_size']);
        if ($max_paste_size < 0 || $max_paste_size > 10000000) {
            throw new Exception('Max paste size must be between 0 and 10,000,000 bytes (0 = no limit)');
        }
        
        $default_expiry = intval($_POST['default_expiry']);
        if ($default_expiry < 0) {
            throw new Exception('Default expiry must be 0 or greater (0 = no default expiry)');
        }
        
        $daily_limit_free = intval($_POST['daily_paste_limit_free']);
        $daily_limit_premium = intval($_POST['daily_paste_limit_premium']);
        if ($daily_limit_free < 0 || $daily_limit_premium < 0) {
            throw new Exception('Daily paste limits must be 0 or greater (0 = no limit)');
        }
        
        // Get current settings first
        $current_settings = $db->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        
        // Handle logo upload first
        $logo_path = $current_settings['site_logo']; // Keep current logo by default
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['site_logo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_size = $_FILES['site_logo']['size'];
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Logo must be a JPG, PNG, GIF, or WebP image');
            }
            
            if ($file_size > 2 * 1024 * 1024) { // 2MB limit
                throw new Exception('Logo file size must be less than 2MB');
            }
            
            // Create uploads directory if it doesn't exist
            if (!file_exists('uploads')) {
                mkdir('uploads', 0755, true);
            }
            
            // Remove old logo if it exists
            if ($current_settings['site_logo'] && file_exists($current_settings['site_logo'])) {
                unlink($current_settings['site_logo']);
            }
            
            $logo_path = 'uploads/logo_' . time() . '.' . $ext;
            if (!move_uploaded_file($_FILES['site_logo']['tmp_name'], $logo_path)) {
                throw new Exception('Failed to upload logo file');
            }
        }
        
        // Validate email domains
        $allowed_domains = trim($_POST['allowed_email_domains']);
        if (empty($allowed_domains)) {
            $allowed_domains = '*'; // Default to allow all
        }
        
        // Update settings
        $stmt = $db->prepare("UPDATE site_settings SET 
            site_name = ?,
            max_paste_size = ?,
            default_expiry = ?,
            registration_enabled = ?,
            email_verification_required = ?,
            allowed_email_domains = ?,
            ai_moderation_enabled = ?,
            shadowban_enabled = ?,
            auto_blur_threshold = ?,
            auto_delete_threshold = ?,
            theme_default = ?,
            daily_paste_limit_free = ?,
            daily_paste_limit_premium = ?,
            encryption_enabled = ?,
            maintenance_mode = ?,
            site_logo = ?
            WHERE id = 1");
            
        $stmt->execute([
            $site_name,
            $max_paste_size,
            intval($_POST['default_expiry']),
            isset($_POST['registration_enabled']) ? 1 : 0,
            isset($_POST['email_verification_required']) ? 1 : 0,
            $allowed_domains,
            isset($_POST['ai_moderation_enabled']) ? 1 : 0,
            isset($_POST['shadowban_enabled']) ? 1 : 0,
            intval($_POST['auto_blur_threshold']),
            intval($_POST['auto_delete_threshold']),
            $_POST['theme_default'],
            intval($_POST['daily_paste_limit_free']),
            intval($_POST['daily_paste_limit_premium']),
            isset($_POST['encryption_enabled']) ? 1 : 0,
            isset($_POST['maintenance_mode']) ? 1 : 0,
            $logo_path
        ]);
        
        // Log the settings update
        require_once 'audit_logger.php';
        $audit_logger = new AuditLogger();
        $audit_logger->log('settings_updated', $_SESSION['admin_id'], [
            'site_name' => $site_name,
            'theme_default' => $_POST['theme_default']
        ]);
        
        $db->commit();
        
        // Get updated settings
        $updated_settings = $db->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        
        if (isset($_POST['maintenance_mode']) && $_POST['maintenance_mode']) {
            $success_message = 'Settings updated successfully! ⚠️ MAINTENANCE MODE IS NOW ACTIVE - The site will show a maintenance page to all visitors except admins.';
        } else {
            $success_message = 'Settings updated successfully! Changes will take effect immediately.';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'settings' => $updated_settings
        ]);
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error_message = 'Error updating settings: ' . $e->getMessage();
        
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit;
    }
}

// Fetch current settings
$settings = $db->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
?>

<div class="bg-gray-800 p-6 rounded-lg">
    <!-- Message Display -->
    <?php if (!empty($success_message)): ?>
        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200 rounded">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="mb-4 p-4 bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold">Site Settings</h2>
        <button onclick="backupSettings()" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded">
            <i class="fas fa-download mr-2"></i>Backup Settings
        </button>
    </div></div>
    
    <form id="settingsForm" method="POST" action="" class="space-y-8" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_settings">
        
        <!-- Site Branding -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Site Branding</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Site Name</label>
                    <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" 
                           maxlength="100" placeholder="PasteForge (default)"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">This will appear in the browser title and header. Leave empty to use "PasteForge"</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Site Logo</label>
                    <?php if (!empty($settings['site_logo']) && file_exists($settings['site_logo'])): ?>
                        <div class="mb-3 p-3 bg-gray-600 rounded border">
                            <p class="text-sm text-gray-300 mb-2">Current Logo:</p>
                            <img src="<?= htmlspecialchars($settings['site_logo']) ?>" alt="Current Logo" 
                                 class="max-h-16 max-w-32 object-contain border border-gray-500 rounded">
                            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($settings['site_logo']) ?></p>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="site_logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Supported formats: JPG, PNG, GIF, WebP. Max size: 2MB. Recommended: 200x50px</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Default Theme</label>
                    <select name="theme_default" class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                        <option value="light" <?= $settings['theme_default'] === 'light' ? 'selected' : '' ?>>Light Theme</option>
                        <option value="dark" <?= $settings['theme_default'] === 'dark' ? 'selected' : '' ?>>Dark Theme</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Default theme for new visitors and users who haven't set a preference</p>
                </div>
            </div>
        </div>

        <!-- Feature Toggles -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Feature Toggles</h3>
            <div class="space-y-4">
                <label class="flex items-center">
                    <input type="checkbox" name="ai_moderation_enabled" value="1" 
                           <?= $settings['ai_moderation_enabled'] ? 'checked' : '' ?> 
                           class="mr-2">
                    <span>Enable AI Moderation</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="encryption_enabled" value="1" 
                           <?= $settings['encryption_enabled'] ? 'checked' : '' ?> 
                           class="mr-2">
                    <span>Enable Encryption</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="shadowban_enabled" value="1" 
                           <?= $settings['shadowban_enabled'] ? 'checked' : '' ?> 
                           class="mr-2">
                    <span>Enable Shadowban</span>
                </label>
            </div>
        </div>

        <!-- Paste Configuration -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Paste Configuration</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Max Paste Size (bytes)</label>
                    <input type="number" name="max_paste_size" value="<?= $settings['max_paste_size'] ?>" 
                           min="0" max="10000000" placeholder="0 = no limit"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Maximum size in bytes for paste content. Set to 0 to disable limit. Recommended: 500,000 (500KB)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Default Expiry (seconds)</label>
                    <input type="number" name="default_expiry" value="<?= $settings['default_expiry'] ?>" 
                           min="0" placeholder="0 = never expire by default"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Default expiration time for pastes when user selects "Never". Set to 0 to allow permanent pastes. Example: 604800 = 1 week</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Daily Paste Limit (Free Users)</label>
                    <input type="number" name="daily_paste_limit_free" value="<?= $settings['daily_paste_limit_free'] ?>" 
                           min="0" placeholder="0 = no limit"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Maximum pastes per day for free users. Set to 0 to disable limit. Recommended: 10-50</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Daily Paste Limit (Premium Users)</label>
                    <input type="number" name="daily_paste_limit_premium" value="<?= $settings['daily_paste_limit_premium'] ?>" 
                           min="0" placeholder="0 = no limit"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Maximum pastes per day for premium users. Set to 0 to disable limit. Should be higher than free limit</p>
                </div>
            </div>
        </div>

        <!-- Account Settings -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Account Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="registration_enabled" value="1" 
                               <?= $settings['registration_enabled'] ? 'checked' : '' ?> 
                               class="mr-2">
                        <span>Allow New Registrations</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1 ml-6">When unchecked, new users cannot create accounts on the site</p>
                </div>
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" name="email_verification_required" value="1" 
                               <?= $settings['email_verification_required'] ? 'checked' : '' ?> 
                               class="mr-2">
                        <span>Require Email Verification</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1 ml-6">Users must verify their email before they can use the site</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Allowed Email Domains (comma-separated, * for all)</label>
                    <input type="text" name="allowed_email_domains" value="<?= htmlspecialchars($settings['allowed_email_domains']) ?>" 
                           placeholder="example.com, company.org, * for all domains"
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg border border-gray-500 focus:border-blue-400 focus:outline-none">
                    <p class="text-xs text-gray-400 mt-1">Examples: gmail.com, company.com or use * to allow all email domains</p>
                </div>
            </div>
        </div>

        <!-- Moderation Settings -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">Moderation Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Auto-blur Threshold (# of flags)</label>
                    <input type="number" name="auto_blur_threshold" value="<?= $settings['auto_blur_threshold'] ?>" 
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Auto-delete Threshold (# of flags)</label>
                    <input type="number" name="auto_delete_threshold" value="<?= $settings['auto_delete_threshold'] ?>" 
                           class="w-full px-3 py-2 bg-gray-600 rounded-lg">
                </div>
            </div>
        </div>

        <!-- System Settings -->
        <div class="bg-gray-700 p-4 rounded-lg">
            <h3 class="text-lg font-semibold mb-4">System Settings</h3>
            <div class="space-y-4">
                <div class="<?= $settings['maintenance_mode'] ? 'bg-red-600/20 border border-red-500' : 'bg-yellow-600/20 border border-yellow-500' ?> p-4 rounded-lg">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="maintenance_mode" value="1" 
                               <?= $settings['maintenance_mode'] ? 'checked' : '' ?> 
                               class="mt-1" id="maintenanceToggle">
                        <div>
                            <span class="font-semibold <?= $settings['maintenance_mode'] ? 'text-red-300' : 'text-yellow-300' ?>">
                                <i class="fas fa-tools mr-2"></i>Maintenance Mode
                            </span>
                            <p class="text-sm text-gray-300 mt-1">
                                When enabled, all visitors (except admins) will see a maintenance page. 
                                Use this when performing updates or maintenance.
                            </p>
                            <?php if ($settings['maintenance_mode']): ?>
                                <div class="mt-2 text-sm text-red-300 font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    ACTIVE: Site is currently showing maintenance page to visitors
                                </div>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex gap-4">
            <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded transition-colors">
                <i class="fas fa-save mr-2"></i>Save Settings
            </button>
            <button type="button" onclick="resetForm()" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded transition-colors">
                <i class="fas fa-undo mr-2"></i>Reset
            </button>
        </div>
    </form>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const siteNameInput = document.querySelector('input[name="site_name"]');
    const logoInput = document.querySelector('input[name="site_logo"]');
    
    // Site name validation
    siteNameInput.addEventListener('input', function() {
        const value = this.value.trim();
        if (value.length > 100) {
            this.setCustomValidity('Site name must be 100 characters or less');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Logo file validation
    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if (!allowedTypes.includes(file.type)) {
                alert('Please select a valid image file (JPG, PNG, GIF, or WebP)');
                this.value = '';
                return;
            }
            
            if (file.size > maxSize) {
                alert('Logo file size must be less than 2MB');
                this.value = '';
                return;
            }
            
            // Preview the image
            const reader = new FileReader();
            reader.onload = function(e) {
                showImagePreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Maintenance mode confirmation
    const maintenanceToggle = document.getElementById('maintenanceToggle');
    if (maintenanceToggle) {
        maintenanceToggle.addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('⚠️ Are you sure you want to enable Maintenance Mode?\n\nThis will show a maintenance page to all visitors except admins. Make sure to disable it when maintenance is complete.')) {
                    this.checked = false;
                }
            }
        });
    }
    
    // Form submission with AJAX to prevent page redirect
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        const maintenanceMode = document.getElementById('maintenanceToggle');
        
        // Extra confirmation for maintenance mode
        if (maintenanceMode && maintenanceMode.checked) {
            if (!confirm('🚨 FINAL CONFIRMATION 🚨\n\nYou are about to put the site into Maintenance Mode. All visitors will see a maintenance page.\n\nContinue?')) {
                return false;
            }
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
        
        // Create FormData from the form
        const formData = new FormData(this);
        
        // Submit via AJAX
        fetch('admin-settings.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                showTempMessage(result.message, 'success');
                
                // Update form fields with new settings if provided
                if (result.settings) {
                    updateFormFields(result.settings);
                }
                
                // If maintenance mode was enabled, show additional warning
                if (maintenanceMode && maintenanceMode.checked) {
                    setTimeout(() => {
                        showTempMessage('⚠️ Reminder: Maintenance mode is active. Remember to disable it when done!', 'warning');
                    }, 3000);
                }
            } else {
                showTempMessage(result.message || 'An error occurred while saving settings', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showTempMessage('Network error occurred while saving settings', 'error');
        })
        .finally(() => {
            // Reset button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});

function showImagePreview(src) {
    // Remove existing preview
    const existingPreview = document.getElementById('logo-preview');
    if (existingPreview) {
        existingPreview.remove();
    }
    
    // Create new preview
    const preview = document.createElement('div');
    preview.id = 'logo-preview';
    preview.className = 'mt-3 p-3 bg-gray-600 rounded border';
    preview.innerHTML = `
        <p class="text-sm text-gray-300 mb-2">Preview:</p>
        <img src="${src}" alt="Logo Preview" class="max-h-16 max-w-32 object-contain border border-gray-500 rounded">
    `;
    
    const logoInput = document.querySelector('input[name="site_logo"]');
    logoInput.parentNode.appendChild(preview);
}

function resetForm() {
    if (confirm('Are you sure you want to reset all changes? This will reload the current settings.')) {
        location.reload();
    }
}

function backupSettings() {
    const data = {
        site_name: '<?= addslashes($settings['site_name']) ?>',
        theme_default: '<?= addslashes($settings['theme_default']) ?>',
        max_paste_size: <?= $settings['max_paste_size'] ?>,
        default_expiry: <?= $settings['default_expiry'] ?>,
        registration_enabled: <?= $settings['registration_enabled'] ?>,
        email_verification_required: <?= $settings['email_verification_required'] ?>,
        allowed_email_domains: '<?= addslashes($settings['allowed_email_domains']) ?>',
        ai_moderation_enabled: <?= $settings['ai_moderation_enabled'] ?>,
        shadowban_enabled: <?= $settings['shadowban_enabled'] ?>,
        auto_blur_threshold: <?= $settings['auto_blur_threshold'] ?>,
        auto_delete_threshold: <?= $settings['auto_delete_threshold'] ?>,
        daily_paste_limit_free: <?= $settings['daily_paste_limit_free'] ?>,
        daily_paste_limit_premium: <?= $settings['daily_paste_limit_premium'] ?>,
        encryption_enabled: <?= $settings['encryption_enabled'] ?>,
        maintenance_mode: <?= $settings['maintenance_mode'] ?>,
        backup_date: new Date().toISOString()
    };
    
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `settings_backup_${new Date().toISOString().slice(0,10)}.json`;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    a.remove();
    
    // Show success message
    showTempMessage('Settings backup downloaded successfully!', 'success');
}

function updateFormFields(settings) {
    // Update all form fields with the latest settings
    if (settings.site_name !== undefined) {
        const siteNameField = document.querySelector('input[name="site_name"]');
        if (siteNameField) siteNameField.value = settings.site_name || '';
    }
    
    if (settings.max_paste_size !== undefined) {
        const maxSizeField = document.querySelector('input[name="max_paste_size"]');
        if (maxSizeField) maxSizeField.value = settings.max_paste_size || 0;
    }
    
    if (settings.default_expiry !== undefined) {
        const defaultExpiryField = document.querySelector('input[name="default_expiry"]');
        if (defaultExpiryField) defaultExpiryField.value = settings.default_expiry || 0;
    }
    
    if (settings.daily_paste_limit_free !== undefined) {
        const freeLimitField = document.querySelector('input[name="daily_paste_limit_free"]');
        if (freeLimitField) freeLimitField.value = settings.daily_paste_limit_free || 0;
    }
    
    if (settings.daily_paste_limit_premium !== undefined) {
        const premiumLimitField = document.querySelector('input[name="daily_paste_limit_premium"]');
        if (premiumLimitField) premiumLimitField.value = settings.daily_paste_limit_premium || 0;
    }
    
    if (settings.allowed_email_domains !== undefined) {
        const emailDomainsField = document.querySelector('input[name="allowed_email_domains"]');
        if (emailDomainsField) emailDomainsField.value = settings.allowed_email_domains || '*';
    }
    
    if (settings.auto_blur_threshold !== undefined) {
        const blurThresholdField = document.querySelector('input[name="auto_blur_threshold"]');
        if (blurThresholdField) blurThresholdField.value = settings.auto_blur_threshold || 5;
    }
    
    if (settings.auto_delete_threshold !== undefined) {
        const deleteThresholdField = document.querySelector('input[name="auto_delete_threshold"]');
        if (deleteThresholdField) deleteThresholdField.value = settings.auto_delete_threshold || 10;
    }
    
    if (settings.theme_default !== undefined) {
        const themeField = document.querySelector('select[name="theme_default"]');
        if (themeField) themeField.value = settings.theme_default || 'dark';
    }
    
    // Update checkboxes
    const checkboxes = [
        'registration_enabled',
        'email_verification_required', 
        'ai_moderation_enabled',
        'shadowban_enabled',
        'encryption_enabled',
        'maintenance_mode'
    ];
    
    checkboxes.forEach(fieldName => {
        if (settings[fieldName] !== undefined) {
            const checkbox = document.querySelector(`input[name="${fieldName}"]`);
            if (checkbox) {
                checkbox.checked = Boolean(parseInt(settings[fieldName]));
            }
        }
    });
}

function showTempMessage(message, type) {
    const messageDiv = document.createElement('div');
    let className;
    if (type === 'success') {
        className = 'bg-green-100 dark:bg-green-900 border border-green-400 text-green-700 dark:text-green-200';
    } else if (type === 'warning') {
        className = 'bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 text-yellow-700 dark:text-yellow-200';
    } else {
        className = 'bg-red-100 dark:bg-red-900 border border-red-400 text-red-700 dark:text-red-200';
    }
    
    messageDiv.className = `fixed top-4 right-4 p-4 rounded-lg z-50 max-w-sm ${className}`;
    messageDiv.innerHTML = `
        <div class="flex justify-between items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-lg">&times;</button>
        </div>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 3000);
}
</script>
