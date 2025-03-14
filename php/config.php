<?php
// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'poker');

// Start session
session_start();

// Database connection function
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection error
    if ($conn->connect_error) {
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
    }
    
    // Set UTF-8 encoding
    $conn->set_charset('utf8mb4');
    
    return $conn;
}

// Response header settings
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database initialization function
function initializeDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create database: ' . $conn->error]));
    }
    
    $conn->select_db(DB_NAME);
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        money INT NOT NULL DEFAULT 10000,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create users table: ' . $conn->error]));
    }
    
    // Create rooms table
    $sql = "CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        creator_id INT NOT NULL,
        max_players INT NOT NULL DEFAULT 6,
        small_blind INT NOT NULL DEFAULT 10,
        big_blind INT NOT NULL DEFAULT 20,
        is_active BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create rooms table: ' . $conn->error]));
    }
    
    // Create room_players table (players in game rooms)
    $sql = "CREATE TABLE IF NOT EXISTS room_players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        user_id INT NOT NULL,
        seat_position INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE(room_id, user_id),
        UNIQUE(room_id, seat_position)
    )";
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create players table: ' . $conn->error]));
    }
    
    // Create game_states table
    $sql = "CREATE TABLE IF NOT EXISTS game_states (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL UNIQUE,
        game_data JSON NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )";
    if (!$conn->query($sql)) {
        die(json_encode(['success' => false, 'message' => 'Failed to create game states table: ' . $conn->error]));
    }
    
    $conn->close();
}

// Check database initialization
try {
    $testConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    $result = $testConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    
    if ($result->num_rows == 0) {
        // Initialize database if it doesn't exist
        initializeDatabase();
    }
    
    $testConn->close();
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Failed to check database connection: ' . $e->getMessage()]));
}
?> 