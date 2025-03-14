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

// Check room exists
$stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->bind_param("i", $data['roomId']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Room does not exist.']);
    $stmt->close();
    $conn->close();
    exit;
}

$room = $result->fetch_assoc();

// Check player count in room
$countStmt = $conn->prepare("SELECT COUNT(*) as player_count FROM room_players WHERE room_id = ?");
$countStmt->bind_param("i", $data['roomId']);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();

if ($countRow['player_count'] >= $room['max_players']) {
    echo json_encode(['success' => false, 'message' => 'Room is full.']);
    $countStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

// Check user info
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->bind_param("i", $data['userId']);
$userStmt->execute();
$userResult = $userStmt->get_result();

if ($userResult->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'User information not found.']);
    $userStmt->close();
    $countStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

$user = $userResult->fetch_assoc();

// Check if already in room
$checkStmt = $conn->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
$checkStmt->bind_param("ii", $data['roomId'], $data['userId']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    // Already in room - return existing info
    $roomInfoStmt = $conn->prepare("
        SELECT r.*, 
        (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) as current_players,
        (SELECT game_data FROM game_states WHERE room_id = r.id) as game_state
        FROM rooms r 
        WHERE r.id = ?");
    $roomInfoStmt->bind_param("i", $data['roomId']);
    $roomInfoStmt->execute();
    $roomInfoResult = $roomInfoStmt->get_result();
    $roomInfo = $roomInfoResult->fetch_assoc();
    
    // Get player list
    $playersStmt = $conn->prepare("
        SELECT u.id, u.username, u.money, rp.seat_position
        FROM room_players rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.room_id = ?
        ORDER BY rp.seat_position");
    $playersStmt->bind_param("i", $data['roomId']);
    $playersStmt->execute();
    $playersResult = $playersStmt->get_result();
    
    $players = [];
    while ($player = $playersResult->fetch_assoc()) {
        $players[] = [
            'id' => $player['id'],
            'username' => $player['username'],
            'money' => $player['money'],
            'seatPosition' => $player['seat_position']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'room' => [
            'id' => $roomInfo['id'],
            'name' => $roomInfo['name'],
            'maxPlayers' => $roomInfo['max_players'],
            'currentPlayers' => $roomInfo['current_players'],
            'smallBlind' => $roomInfo['small_blind'],
            'bigBlind' => $roomInfo['big_blind'],
            'isActive' => $roomInfo['is_active'] == 1,
            'players' => $players,
            'gameState' => json_decode($roomInfo['game_state'])
        ]
    ]);
    
    $playersStmt->close();
    $roomInfoStmt->close();
    $checkStmt->close();
    $userStmt->close();
    $countStmt->close();
    $stmt->close();
    $conn->close();
    exit;
}

// Find empty seat
$seatStmt = $conn->prepare("
    SELECT seat_position 
    FROM room_players 
    WHERE room_id = ? 
    ORDER BY seat_position");
$seatStmt->bind_param("i", $data['roomId']);
$seatStmt->execute();
$seatResult = $seatStmt->get_result();

$takenSeats = [];
while ($seat = $seatResult->fetch_assoc()) {
    $takenSeats[] = $seat['seat_position'];
}

$seatPosition = 0;
for ($i = 0; $i < $room['max_players']; $i++) {
    if (!in_array($i, $takenSeats)) {
        $seatPosition = $i;
        break;
    }
}

// Add new player
$joinStmt = $conn->prepare("INSERT INTO room_players (room_id, user_id, seat_position) VALUES (?, ?, ?)");
$joinStmt->bind_param("iii", $data['roomId'], $data['userId'], $seatPosition);

if (!$joinStmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to join room: ' . $joinStmt->error]);
    $joinStmt->close();
    $seatStmt->close();
    $checkStmt->close();
    $userStmt->close();
    $countStmt->close();
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

// Update game state
$gameState = json_decode($gameData['game_data'], true);
$gameState['players'][] = [
    'id' => $user['id'],
    'username' => $user['username'],
    'money' => $user['money'],
    'seatPosition' => $seatPosition,
    'cards' => [],
    'currentBet' => 0,
    'folded' => false,
    'lastAction' => null
];

$updatedState = json_encode($gameState);
$updateStmt = $conn->prepare("UPDATE game_states SET game_data = ? WHERE room_id = ?");
$updateStmt->bind_param("si", $updatedState, $data['roomId']);
$updateStmt->execute();

// Return room info
$roomInfoStmt = $conn->prepare("
    SELECT r.*, 
    (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) as current_players
    FROM rooms r 
    WHERE r.id = ?");
$roomInfoStmt->bind_param("i", $data['roomId']);
$roomInfoStmt->execute();
$roomInfoResult = $roomInfoStmt->get_result();
$roomInfo = $roomInfoResult->fetch_assoc();

// Get player list
$playersStmt = $conn->prepare("
    SELECT u.id, u.username, u.money, rp.seat_position
    FROM room_players rp
    JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY rp.seat_position");
$playersStmt->bind_param("i", $data['roomId']);
$playersStmt->execute();
$playersResult = $playersStmt->get_result();

$players = [];
while ($player = $playersResult->fetch_assoc()) {
    $players[] = [
        'id' => $player['id'],
        'username' => $player['username'],
        'money' => $player['money'],
        'seatPosition' => $player['seat_position']
    ];
}

echo json_encode([
    'success' => true,
    'room' => [
        'id' => $roomInfo['id'],
        'name' => $roomInfo['name'],
        'maxPlayers' => $roomInfo['max_players'],
        'currentPlayers' => $roomInfo['current_players'],
        'smallBlind' => $roomInfo['small_blind'],
        'bigBlind' => $roomInfo['big_blind'],
        'isActive' => $roomInfo['is_active'] == 1,
        'players' => $players
    ]
]);

$playersStmt->close();
$roomInfoStmt->close();
$updateStmt->close();
$gameStmt->close();
$joinStmt->close();
$seatStmt->close();
$checkStmt->close();
$userStmt->close();
$countStmt->close();
$stmt->close();
$conn->close();
?> 