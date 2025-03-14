<?php
require_once 'config.php';

// Get list of active game rooms
$conn = getDbConnection();

$sql = "SELECT r.*, 
        (SELECT COUNT(*) FROM room_players WHERE room_id = r.id) as current_players 
        FROM rooms r 
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Failed to get room list: ' . $conn->error]);
    $conn->close();
    exit;
}

$rooms = [];
while ($row = $result->fetch_assoc()) {
    $rooms[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'maxPlayers' => $row['max_players'],
        'currentPlayers' => $row['current_players'],
        'smallBlind' => $row['small_blind'],
        'bigBlind' => $row['big_blind'],
        'isActive' => $row['is_active'] == 1,
        'createdAt' => $row['created_at']
    ];
}

echo json_encode(['success' => true, 'rooms' => $rooms]);

$conn->close();
?> 