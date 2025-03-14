<?php
require_once 'config.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || empty($data['username'])) {
    echo json_encode(['success' => false, 'message' => 'Username is required.']);
    exit;
}

$username = $data['username'];

// Database connection
$conn = getDbConnection();

// Check user
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Existing user login
    $user = $result->fetch_assoc();
    
    // Update last login time
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
} else {
    // Create new user
    $stmt = $conn->prepare("INSERT INTO users (username) VALUES (?)");
    $stmt->bind_param("s", $username);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $stmt->error]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    $userId = $conn->insert_id;
    
    // Get new user info
    $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();
    $userStmt->close();
}

// Save user info in session
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

// Send response
echo json_encode([
    'success' => true,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'money' => $user['money']
    ]
]);

$stmt->close();
$conn->close();
?> 