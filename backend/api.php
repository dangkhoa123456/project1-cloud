<?php
// ===== CORS HANDLING (Cho phép Frontend gọi vào) =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Lấy thông tin từ cấu hình Render
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');

// 2. Fallback cho Localhost
if (!$host) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'contact_db'; // Sửa đúng tên DB local của bạn
    $port = 3306;
}

try {
    // 3. SỬA LỖI KẾT NỐI Ở ĐÂY:
    // - Thêm port=$port
    // - Sửa $db thành $dbname
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // - Cấu hình SSL cho Aiven
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Dòng này quan trọng để chạy trên Render kết nối Aiven:
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, 
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    http_response_code(500);
    // In lỗi chi tiết để debug (xóa dòng message khi chạy thật)
    echo json_encode([
        'error' => 'Database connection failed', 
        'message' => $e->getMessage()
    ]);
    exit;
}

// ===== ROUTING =====
$method = $_SERVER['REQUEST_METHOD'];

// Lấy action từ query param hoặc mặc định
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    
    case 'POST':
        handlePost($pdo);
        break;
    
    case 'PUT':
        handlePut($pdo);
        break;
    
    case 'DELETE':
        handleDelete($pdo);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

// ===== CRUD FUNCTIONS =====

function handleGet($pdo) {
    $search = $_GET['q'] ?? '';
    
    try {
        if ($search) {
            $sql = "SELECT * FROM contacts WHERE name LIKE :search ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['search' => "%$search%"]);
        } else {
            $sql = "SELECT * FROM contacts ORDER BY id DESC";
            $stmt = $pdo->query($sql);
        }
        
        $contacts = $stmt->fetchAll();
        echo json_encode($contacts);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || empty($data['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and phone required']);
        return;
    }
    
    $sql = "INSERT INTO contacts (name, phone) VALUES (:name, :phone)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone']
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add contact: ' . $e->getMessage()]);
    }
}

function handlePut($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id']) || empty($data['name']) || empty($data['phone'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, name and phone required']);
        return;
    }
    
    $sql = "UPDATE contacts SET name = :name, phone = :phone WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':phone' => $data['phone']
        ]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update contact']);
    }
}

function handleDelete($pdo) {
    // Hỗ trợ lấy ID từ cả URL param (?id=1) hoặc Body JSON
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    $sql = "DELETE FROM contacts WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contact']);
    }
}
?>