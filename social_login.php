
<?php
session_start();
require_once 'database.php';
require_once 'social_media_integration.php';
require_once 'audit_logger.php';

$social = new SocialMediaIntegration();
$audit_logger = new AuditLogger();

$action = $_GET['action'] ?? null;
$provider = $_GET['provider'] ?? null;

if ($action === 'login' && $provider) {
    try {
        $redirect_uri = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . 
                       '://' . $_SERVER['HTTP_HOST'] . '/social_login.php?action=callback&provider=' . $provider;
        
        $auth_url = $social->generateSocialLoginUrl($provider, $redirect_uri);
        
        if (!$auth_url) {
            throw new Exception('Social login provider not configured or disabled');
        }
        
        header('Location: ' . $auth_url);
        exit;
        
    } catch (Exception $e) {
        $audit_logger->logSecurityEvent('social_login_failed', [
            'provider' => $provider,
            'error' => $e->getMessage()
        ], 'medium');
        
        header('Location: /?page=login&error=social_login_failed');
        exit;
    }
}

if ($action === 'callback' && $provider) {
    try {
        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        $error = $_GET['error'] ?? null;
        
        if ($error) {
            throw new Exception('OAuth error: ' . $error);
        }
        
        if (!$code || !$state) {
            throw new Exception('Missing authorization code or state');
        }
        
        $user_id = $social->handleSocialCallback($provider, $code, $state);
        
        // Get user info for session
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user['username'];
            
            $audit_logger->log('social_login_success', 'auth', $user_id, [
                'provider' => $provider,
                'username' => $user['username']
            ]);
            
            // Clear OAuth session data
            unset($_SESSION['oauth_state']);
            unset($_SESSION['oauth_provider']);
            
            header('Location: /');
            exit;
        } else {
            throw new Exception('User account creation failed');
        }
        
    } catch (Exception $e) {
        $audit_logger->logSecurityEvent('social_login_callback_failed', [
            'provider' => $provider,
            'error' => $e->getMessage()
        ], 'medium');
        
        header('Location: /?page=login&error=social_login_failed&message=' . urlencode($e->getMessage()));
        exit;
    }
}

// Invalid request
header('Location: /?page=login');
exit;
?>
