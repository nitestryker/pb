
<?php
session_start();
require_once 'database.php';
require_once 'rate_limiter.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required', 'debug' => 'No user session found']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance()->getConnection();

// Rate limiting
$rate_limiter = new RateLimiter($db);
if (!$rate_limiter->checkRate($user_id, 'annotations', 30, 300)) { // 30 actions per 5 minutes
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'get') {
                getAnnotations();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'POST':
            if ($action === 'add') {
                addAnnotation();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'PUT':
            if ($action === 'edit') {
                editAnnotation();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteAnnotation();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log("Annotations API error: " . $e->getMessage());
}

function getAnnotations() {
    global $db;
    
    $paste_id = $_GET['paste_id'] ?? null;
    if (!$paste_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Paste ID required']);
        return;
    }
    
    // Verify paste exists and is accessible
    $stmt = $db->prepare("SELECT id, is_public, user_id FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch();
    
    if (!$paste) {
        http_response_code(404);
        echo json_encode(['error' => 'Paste not found']);
        return;
    }
    
    // Get annotations with user info
    $stmt = $db->prepare("
        SELECT pa.*, u.username, u.profile_image 
        FROM paste_annotations pa 
        LEFT JOIN users u ON pa.user_id = u.id 
        WHERE pa.paste_id = ? 
        ORDER BY pa.line_number, pa.created_at
    ");
    $stmt->execute([$paste_id]);
    $annotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['annotations' => $annotations]);
}

function addAnnotation() {
    global $db, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Debug logging
    error_log("Add annotation - User ID: " . $user_id);
    error_log("Add annotation - Input: " . json_encode($input));
    
    $paste_id = $input['paste_id'] ?? null;
    $line_number = $input['line_number'] ?? null;
    $annotation_text = trim($input['annotation_text'] ?? '');
    
    if (!$paste_id || !$line_number || !$annotation_text) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Missing required fields',
            'debug' => [
                'paste_id' => $paste_id,
                'line_number' => $line_number,
                'annotation_text_length' => strlen($annotation_text)
            ]
        ]);
        return;
    }
    
    if (strlen($annotation_text) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Annotation text too long (max 500 characters)']);
        return;
    }
    
    // Verify paste exists and is accessible
    $stmt = $db->prepare("SELECT id, is_public, user_id FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch();
    
    if (!$paste) {
        http_response_code(404);
        echo json_encode(['error' => 'Paste not found']);
        return;
    }
    
    // Insert annotation
    try {
        $stmt = $db->prepare("
            INSERT INTO paste_annotations (paste_id, user_id, line_number, annotation_text) 
            VALUES (?, ?, ?, ?)
        ");
        $result = $stmt->execute([$paste_id, $user_id, $line_number, $annotation_text]);
        
        if (!$result) {
            error_log("Failed to insert annotation: " . json_encode($stmt->errorInfo()));
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save annotation']);
            return;
        }
        
        $annotation_id = $db->lastInsertId();
        
        if (!$annotation_id) {
            error_log("No annotation ID returned after insert");
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create annotation']);
            return;
        }
    } catch (PDOException $e) {
        error_log("Database error inserting annotation: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        return;
    }
    
    // Get the created annotation with user info
    $stmt = $db->prepare("
        SELECT pa.*, u.username, u.profile_image 
        FROM paste_annotations pa 
        LEFT JOIN users u ON pa.user_id = u.id 
        WHERE pa.id = ?
    ");
    $stmt->execute([$annotation_id]);
    $annotation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'annotation' => $annotation]);
}

function editAnnotation() {
    global $db, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $annotation_id = $input['annotation_id'] ?? null;
    $annotation_text = trim($input['annotation_text'] ?? '');
    
    if (!$annotation_id || !$annotation_text) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    if (strlen($annotation_text) > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Annotation text too long (max 500 characters)']);
        return;
    }
    
    // Verify annotation exists and user owns it
    $stmt = $db->prepare("SELECT user_id FROM paste_annotations WHERE id = ?");
    $stmt->execute([$annotation_id]);
    $annotation = $stmt->fetch();
    
    if (!$annotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Annotation not found']);
        return;
    }
    
    if ($annotation['user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    // Update annotation
    $stmt = $db->prepare("
        UPDATE paste_annotations 
        SET annotation_text = ?, updated_at = strftime('%s', 'now') 
        WHERE id = ?
    ");
    $stmt->execute([$annotation_text, $annotation_id]);
    
    echo json_encode(['success' => true]);
}

function deleteAnnotation() {
    global $db, $user_id;
    
    $annotation_id = $_GET['annotation_id'] ?? null;
    
    if (!$annotation_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Annotation ID required']);
        return;
    }
    
    // Verify annotation exists and user owns it
    $stmt = $db->prepare("SELECT user_id FROM paste_annotations WHERE id = ?");
    $stmt->execute([$annotation_id]);
    $annotation = $stmt->fetch();
    
    if (!$annotation) {
        http_response_code(404);
        echo json_encode(['error' => 'Annotation not found']);
        return;
    }
    
    if ($annotation['user_id'] !== $user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    // Delete annotation
    $stmt = $db->prepare("DELETE FROM paste_annotations WHERE id = ?");
    $stmt->execute([$annotation_id]);
    
    echo json_encode(['success' => true]);
}
?>
