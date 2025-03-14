# Multiplayer Poker Game

A multiplayer poker game implemented with HTML, CSS, JS, and PHP. Multiple players can connect simultaneously to enjoy real-time poker games.

## Key Features

- Real-time multiplayer gameplay
- Intuitive user interface
- Texas Hold'em poker rules implementation
- Login and room creation functionality
- Betting system for each game round
- Real-time chat functionality
- Responsive design

## Technology Stack

- Frontend: HTML5, CSS3, JavaScript
- Backend: PHP
- Database: MySQL
- Real-time Communication: WebSocket (Ratchet library)

## Installation Guide

### Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Composer (for WebSocket server library installation)

### Step-by-Step Installation

1. Clone the repository

```
git clone https://github.com/tvj030728/PHP-Pocker-Online.git
cd multiplayer-poker
```

2. Database Configuration

- Create a MySQL database
- Modify database connection information in the `php/config.php` file

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'database_username');
define('DB_PASS', 'database_password');
define('DB_NAME', 'poker');
```

3. WebSocket Server Setup (Optional)

- Install Ratchet library

```
composer require cboden/ratchet
```

- Run WebSocket server

```
php bin/poker-server.php
```

4. Web Server Setup

- Connect the project folder to a web server like Apache or Nginx
- Or run PHP's built-in web server

```
php -S localhost:8000
```

5. Access from a web browser

- Access via `http://localhost:8000` or your configured URL

## How to Play

1. Login with a username
2. Create a room or join an existing room in the game lobby
3. Start the game in the room (minimum 2 players required)
4. Play according to Texas Hold'em poker rules

## Game Rules

- Follows Texas Hold'em poker rules
- Each player receives 2 cards initially
- Rounds proceed in order: pre-flop, flop, turn, river
- Players can check, call, bet, raise, or fold during each round
- The player who makes the highest hand with 5 community cards and 2 personal cards wins

## Important Notes

- Security settings should be strengthened for actual service deployment
- Server infrastructure should be properly configured for large-scale users
- The WebSocket server must be run separately

## License

This project is licensed under the MIT License. See the LICENSE file for details.
