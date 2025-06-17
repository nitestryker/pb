<?php
class ErrorHandler {
    private $audit_logger;
    private $log_file;
    
    public function __construct($audit_logger = null) {
        $this->audit_logger = $audit_logger;
        $this->log_file = 'error.log';
        
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error = [
            'type' => 'Error',
            'severity' => $this->getSeverityName($severity),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
        
        $this->logError($error);
        
        if ($severity === E_ERROR || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
            $this->showErrorPage(500, 'Internal Server Error');
            exit;
        }
        
        return true;
    }
    
    public function handleException($exception) {
        $error = [
            'type' => 'Exception',
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ];
        
        $this->logError($error);
        
        if ($this->audit_logger) {
            $this->audit_logger->log('exception_occurred', 'system', null, $error, 'error');
        }
        
        $this->showErrorPage(500, 'Internal Server Error');
    }
    
    public function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $error_data = [
                'type' => 'Fatal Error',
                'severity' => $this->getSeverityName($error['type']),
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ];
            
            $this->logError($error_data);
            
            if ($this->audit_logger) {
                $this->audit_logger->log('fatal_error_occurred', 'system', null, $error_data, 'critical');
            }
            
            $this->showErrorPage(500, 'Internal Server Error');
        }
    }
    
    private function logError($error) {
        $log_entry = json_encode($error) . "\n";
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function getSeverityName($severity) {
        $severities = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];
        
        return $severities[$severity] ?? 'UNKNOWN';
    }
    
    public function showErrorPage($code, $message = '') {
        http_response_code($code);
        
        $error_messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];
        
        $title = $error_messages[$code] ?? 'Error';
        $description = $message ?: $title;
        
        // Check if custom error page exists
        $error_page = "errors/{$code}.php";
        if (file_exists($error_page)) {
            include $error_page;
            return;
        }
        
        // Default error page
        $this->renderDefaultErrorPage($code, $title, $description);
    }
    
    private function renderDefaultErrorPage($code, $title, $description) {
        $theme = $_COOKIE['theme'] ?? 'dark';
        ?>
        <!DOCTYPE html>
        <html class="<?= $theme ?>">
        <head>
            <title><?= $code ?> - <?= htmlspecialchars($title) ?> | PasteForge</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <script src="https://cdn.tailwindcss.com"></script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
            <script>
                tailwind.config = {
                    darkMode: 'class'
                }
            </script>
        </head>
        <body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white min-h-screen flex items-center justify-center">
            <div class="max-w-md mx-auto text-center p-8">
                <div class="mb-8">
                    <i class="fas fa-exclamation-triangle text-6xl text-red-500 mb-4"></i>
                    <h1 class="text-4xl font-bold mb-2"><?= $code ?></h1>
                    <h2 class="text-xl text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars($title) ?></h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-8"><?= htmlspecialchars($description) ?></p>
                </div>
                
                <div class="space-y-4">
                    <a href="/" class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-home mr-2"></i>Go Home
                    </a>
                    
                    <div class="text-center">
                        <button onclick="history.back()" class="text-blue-500 hover:text-blue-700 underline">
                            <i class="fas fa-arrow-left mr-1"></i>Go Back
                        </button>
                    </div>
                </div>
                
                <?php if ($code >= 500): ?>
                <div class="mt-8 p-4 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        <i class="fas fa-info-circle mr-2"></i>
                        This error has been logged and our team has been notified.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }
    
    public static function show404() {
        $handler = new self();
        $handler->showErrorPage(404, 'The page you are looking for could not be found.');
        exit;
    }
    
    public static function show403() {
        $handler = new self();
        $handler->showErrorPage(403, 'You do not have permission to access this resource.');
        exit;
    }
    
    public static function show429($reset_time = null) {
        $handler = new self();
        $message = 'Too many requests. Please try again later.';
        if ($reset_time) {
            $wait_time = max(0, $reset_time - time());
            $message .= " (Reset in {$wait_time} seconds)";
        }
        $handler->showErrorPage(429, $message);
        exit;
    }
}
?>
