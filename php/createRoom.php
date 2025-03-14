<?php
require_once 'config.php';

// Login check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check required data
if (!isset($data['name']) || empty($data['name']) || 
    !isset($data['maxPlayers']) || 
    !isset($data['smallBlind']) || 
    !isset($data['bigBlind']) || 
    !isset($data['creatorId'])) {
    echo json_encode(['success' => false, 'message' => 'Required information is missing.']);
    exit;
}

// Validation
if ($data['maxPlayers'] < 2 || $data['maxPlayers'] > 8) {
    echo json_encode(['success' => false, 'message' => 'Number of players must be between 2 and 8.']);
    exit;
}

if ($data['smallBlind'] < 1 || $data['bigBlind'] < 2 || $data['smallBlind'] >= $data['bigBlind']) {
    echo json_encode(['success' => false, 'message' => 'Invalid blind values.']);
    exit;
}

// Database connection
$conn = getDbConnection();

// Create room
$stmt = $conn->prepare("INSERT INTO rooms (name, creator_id, max_players, small_blind, big_blind) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("siiii", $data['name'], $data['creatorId'], $data['maxPlayers'], $data['smallBlind'], $data['bigBlind']);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create room: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$roomId = $conn->insert_id;

// Create initial game state
$initialState = [
    'isActive' => false,
    'players' => [],
    'communityCards' => [],
    'pot' => 0,
    'round' => 'waiting',
    'currentPlayer' => null,
    'dealerPosition' => 0,
    'smallBlind' => $data['smallBlind'],
    'bigBlind' => $data['bigBlind']
];

$jsonState = json_encode($initialState);

// Save game state
$stateStmt = $conn->prepare("INSERT INTO game_states (room_id, game_data) VALUES (?, ?)");
$stateStmt->bind_param("is", $roomId, $jsonState);

if (!$stateStmt->execute()) {
    // Delete the room on failure
    $conn->query("DELETE FROM rooms WHERE id = $roomId");
    echo json_encode(['success' => false, 'message' => 'Failed to create game state: ' . $stateStmt->error]);
    $stateStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode(['success' => true, 'roomId' => $roomId]);

$stateStmt->close();
$stmt->close();
$conn->close();
?> 