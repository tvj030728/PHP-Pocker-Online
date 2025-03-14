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
if (!isset($data['userId']) || !isset($data['roomId'])) {
    echo json_encode(['success' => false, 'message' => 'Required information is missing.']);
    exit;
}

// Database connection
$conn = getDbConnection();

// Check if player is in the room
$stmt = $conn->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
$stmt->bind_param("ii", $data['roomId'], $data['userId']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Player not found in the room.']);
    $stmt->close();
    $conn->close();
    exit;
}

// Remove player
$deleteStmt = $conn->prepare("DELETE FROM room_players WHERE room_id = ? AND user_id = ?");
$deleteStmt->bind_param("ii", $data['roomId'], $data['userId']);

if (!$deleteStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to leave room: ' . $deleteStmt->error]);
    $deleteStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

// Get game state
$gameStmt = $conn->prepare("SELECT game_data FROM game_states WHERE room_id = ?");
$gameStmt->bind_param("i", $data['roomId']);
$gameStmt->execute();
$gameResult = $gameStmt->get_result();
$gameData = $gameResult->fetch_assoc();

// Update game state - remove player
$gameState = json_decode($gameData['game_data'], true);
$players = $gameState['players'];
$updatedPlayers = [];

foreach ($players as $player) {
    if ($player['id'] != $data['userId']) {
        $updatedPlayers[] = $player;
    }
}

$gameState['players'] = $updatedPlayers;

// End game if only one player left and game is active
if ($gameState['isActive'] && count($updatedPlayers) < 2) {
    $gameState['isActive'] = false;
    $gameState['round'] = 'ended';
    
    // Also update room status
    $roomStmt = $conn->prepare("UPDATE rooms SET is_active = 0 WHERE id = ?");
    $roomStmt->bind_param("i", $data['roomId']);
    $roomStmt->execute();
    $roomStmt->close();
}

$updatedState = json_encode($gameState);
$updateStmt = $conn->prepare("UPDATE game_states SET game_data = ? WHERE room_id = ?");
$updateStmt->bind_param("si", $updatedState, $data['roomId']);

if (!$updateStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to update game state: ' . $updateStmt->error]);
    $updateStmt->close();
    $gameStmt->close();
    $deleteStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

// Delete room if no players left
$countStmt = $conn->prepare("SELECT COUNT(*) as player_count FROM room_players WHERE room_id = ?");
$countStmt->bind_param("i", $data['roomId']);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();

if ($countRow['player_count'] == 0) {
    // Delete game state
    $conn->query("DELETE FROM game_states WHERE room_id = " . $data['roomId']);
    
    // Delete room
    $conn->query("DELETE FROM rooms WHERE id = " . $data['roomId']);
}

echo json_encode(['success' => true]);

$countStmt->close();
$updateStmt->close();
$gameStmt->close();
$deleteStmt->close();
$stmt->close();
$conn->close();
?> 