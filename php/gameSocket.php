<?php
require_once 'config.php';
require_once 'PokerGame.php';

// Ratchet WebSocket 라이브러리가 필요합니다.
// 실제 서버에서 실행하려면 다음 명령으로 설치해야 합니다:
// composer require cboden/ratchet

// WebSocket 서버 구현 (실제 서버에서는 이 파일을 별도로 실행해야 함)
// 간단한 설명용 코드입니다. 이 파일은 실제로 WebSocket 서버로 실행되지 않습니다.

/**
 * 실제 WebSocket 서버 구현 예시
 * 이 코드는 설명용이며, 실제로 이 파일을 WebSocket 서버로 실행하려면
 * Ratchet 라이브러리를 설치하고 별도의 서버 스크립트를 작성해야 합니다.
 */
class PokerGameServer {
    private $clients = [];
    private $rooms = [];
    private $conn;
    
    public function __construct() {
        $this->conn = getDbConnection();
    }
    
    /**
     * 클라이언트 연결 시 호출
     */
    public function onOpen($conn, $request) {
        // 쿼리 파라미터에서 사용자 ID와 방 ID 추출
        $query = $request->getUri()->getQuery();
        parse_str($query, $params);
        
        if (!isset($params['userId']) || !isset($params['roomId'])) {
            $conn->close();
            return;
        }
        
        $userId = $params['userId'];
        $roomId = $params['roomId'];
        
        // 사용자 정보 가져오기
        $userStmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows == 0) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => '유효하지 않은 사용자입니다.'
            ]));
            $conn->close();
            return;
        }
        
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        // 방 정보 가져오기
        $roomStmt = $this->conn->prepare("SELECT * FROM rooms WHERE id = ?");
        $roomStmt->bind_param("i", $roomId);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        
        if ($roomResult->num_rows == 0) {
            $conn->send(json_encode([
                'type' => 'error',
                'message' => '유효하지 않은 방입니다.'
            ]));
            $conn->close();
            return;
        }
        
        $room = $roomResult->fetch_assoc();
        $roomStmt->close();
        
        // 클라이언트 정보 저장
        $clientId = uniqid();
        $this->clients[$clientId] = [
            'conn' => $conn,
            'userId' => $userId,
            'roomId' => $roomId,
            'username' => $user['username']
        ];
        
        // 방에 클라이언트 추가
        if (!isset($this->rooms[$roomId])) {
            // 방 정보 초기화
            $this->initRoom($roomId);
        }
        
        $this->rooms[$roomId]['clients'][$clientId] = $clientId;
        
        // 다른 클라이언트에게 입장 알림
        $this->broadcastToRoom($roomId, [
            'type' => 'playerJoined',
            'data' => [
                'userId' => $userId,
                'username' => $user['username'],
                'players' => $this->getPlayersInRoom($roomId)
            ]
        ], $clientId);
        
        // 현재 게임 상태 전송
        $conn->send(json_encode([
            'type' => 'gameState',
            'data' => $this->getGameState($roomId)
        ]));
    }
    
    /**
     * 클라이언트로부터 메시지 수신 시 호출
     */
    public function onMessage($from, $message) {
        $clientId = $this->getClientIdFromConn($from);
        
        if (!$clientId) {
            return;
        }
        
        $client = $this->clients[$clientId];
        $roomId = $client['roomId'];
        
        $data = json_decode($message, true);
        
        if (!isset($data['type'])) {
            return;
        }
        
        switch ($data['type']) {
            case 'action':
                $this->handleGameAction($clientId, $roomId, $data);
                break;
                
            case 'startGame':
                $this->handleStartGame($clientId, $roomId);
                break;
                
            case 'chat':
                $this->handleChatMessage($clientId, $roomId, $data);
                break;
                
            default:
                // 알 수 없는 메시지 유형
                break;
        }
    }
    
    /**
     * 클라이언트 연결 종료 시 호출
     */
    public function onClose($conn) {
        $clientId = $this->getClientIdFromConn($conn);
        
        if (!$clientId) {
            return;
        }
        
        $client = $this->clients[$clientId];
        $roomId = $client['roomId'];
        $userId = $client['userId'];
        
        // 게임 방에서 플레이어 제거
        if (isset($this->rooms[$roomId])) {
            $gameState = $this->getGameState($roomId);
            
            if ($gameState['isActive']) {
                // 게임이 진행 중인 경우, 플레이어의 액션을 폴드로 처리
                $this->handlePlayerLeave($roomId, $userId);
            } else {
                // 게임이 진행 중이 아닌 경우, 플레이어 목록에서 제거
                $this->removePlayerFromRoom($roomId, $userId);
            }
            
            // 방에서 클라이언트 제거
            unset($this->rooms[$roomId]['clients'][$clientId]);
            
            // 방에 남은 클라이언트가 없으면 방 정보 제거
            if (empty($this->rooms[$roomId]['clients'])) {
                unset($this->rooms[$roomId]);
            } else {
                // 다른 클라이언트에게 퇴장 알림
                $this->broadcastToRoom($roomId, [
                    'type' => 'playerLeft',
                    'data' => [
                        'userId' => $userId,
                        'username' => $client['username'],
                        'players' => $this->getPlayersInRoom($roomId)
                    ]
                ]);
            }
        }
        
        // 클라이언트 목록에서 제거
        unset($this->clients[$clientId]);
    }
    
    /**
     * 오류 발생 시 호출
     */
    public function onError($conn, $error) {
        echo "Error: {$error->getMessage()}\n";
    }
    
    /**
     * 방 초기화
     */
    private function initRoom($roomId) {
        $this->rooms[$roomId] = [
            'clients' => [],
            'game' => null
        ];
        
        // 게임 상태 가져오기
        $stateStmt = $this->conn->prepare("SELECT game_data FROM game_states WHERE room_id = ?");
        $stateStmt->bind_param("i", $roomId);
        $stateStmt->execute();
        $stateResult = $stateStmt->get_result();
        
        if ($stateResult->num_rows > 0) {
            $stateData = $stateResult->fetch_assoc();
            $this->rooms[$roomId]['gameState'] = json_decode($stateData['game_data'], true);
        }
        
        $stateStmt->close();
    }
    
    /**
     * 방에 있는 모든 플레이어 정보 가져오기
     */
    private function getPlayersInRoom($roomId) {
        $playersStmt = $this->conn->prepare("
            SELECT u.id, u.username, u.money, rp.seat_position
            FROM room_players rp
            JOIN users u ON rp.user_id = u.id
            WHERE rp.room_id = ?
            ORDER BY rp.seat_position");
        $playersStmt->bind_param("i", $roomId);
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
        
        $playersStmt->close();
        
        return $players;
    }
    
    /**
     * 방에서 플레이어 제거
     */
    private function removePlayerFromRoom($roomId, $userId) {
        // 방 플레이어 테이블에서 제거
        $stmtRemove = $this->conn->prepare("DELETE FROM room_players WHERE room_id = ? AND user_id = ?");
        $stmtRemove->bind_param("ii", $roomId, $userId);
        $stmtRemove->execute();
        $stmtRemove->close();
        
        // 게임 상태에서 플레이어 제거
        if (isset($this->rooms[$roomId]['gameState'])) {
            $gameState = $this->rooms[$roomId]['gameState'];
            $players = $gameState['players'];
            $updatedPlayers = [];
            
            foreach ($players as $player) {
                if ($player['id'] != $userId) {
                    $updatedPlayers[] = $player;
                }
            }
            
            $gameState['players'] = $updatedPlayers;
            $this->rooms[$roomId]['gameState'] = $gameState;
            
            // DB에 업데이트
            $updatedState = json_encode($gameState);
            $stmtUpdate = $this->conn->prepare("UPDATE game_states SET game_data = ? WHERE room_id = ?");
            $stmtUpdate->bind_param("si", $updatedState, $roomId);
            $stmtUpdate->execute();
            $stmtUpdate->close();
        }
    }
    
    /**
     * 게임 중 플레이어 퇴장 처리
     */
    private function handlePlayerLeave($roomId, $userId) {
        // 게임에서 플레이어 제거 (폴드 처리)
        if (isset($this->rooms[$roomId]['game'])) {
            $game = $this->rooms[$roomId]['game'];
            $result = $game->removePlayer($userId);
            
            // 게임 상태 업데이트
            $this->updateGameState($roomId, $game->getGameState());
            
            // 다음 턴 알림
            if ($result) {
                $this->broadcastToRoom($roomId, [
                    'type' => 'playerAction',
                    'data' => [
                        'userId' => $userId,
                        'username' => $this->clients[$this->getClientIdByUserId($userId, $roomId)]['username'],
                        'action' => 'fold'
                    ]
                ]);
            }
        }
        
        // DB에서 플레이어 제거
        $this->removePlayerFromRoom($roomId, $userId);
    }
    
    /**
     * 게임 시작 처리
     */
    private function handleStartGame($clientId, $roomId) {
        $client = $this->clients[$clientId];
        
        // 방 정보 가져오기
        $roomStmt = $this->conn->prepare("SELECT * FROM rooms WHERE id = ?");
        $roomStmt->bind_param("i", $roomId);
        $roomStmt->execute();
        $roomResult = $roomStmt->get_result();
        $room = $roomResult->fetch_assoc();
        $roomStmt->close();
        
        // 방장인지 확인
        if ($room['creator_id'] != $client['userId']) {
            $client['conn']->send(json_encode([
                'type' => 'error',
                'message' => '게임을 시작할 권한이 없습니다.'
            ]));
            return;
        }
        
        // 플레이어 수 확인
        $players = $this->getPlayersInRoom($roomId);
        if (count($players) < 2) {
            $client['conn']->send(json_encode([
                'type' => 'error',
                'message' => '게임을 시작하려면 최소 2명의 플레이어가 필요합니다.'
            ]));
            return;
        }
        
        // 게임 객체 생성
        $game = new PokerGame($roomId, $players, $room['small_blind'], $room['big_blind']);
        $result = $game->initGame();
        
        if (!$result['success']) {
            $client['conn']->send(json_encode([
                'type' => 'error',
                'message' => $result['message']
            ]));
            return;
        }
        
        // 게임 상태 저장
        $this->rooms[$roomId]['game'] = $game;
        $this->updateGameState($roomId, $result['gameState']);
        
        // 방 상태 업데이트 (활성화)
        $stmtUpdate = $this->conn->prepare("UPDATE rooms SET is_active = 1 WHERE id = ?");
        $stmtUpdate->bind_param("i", $roomId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // 모든 클라이언트에게 게임 시작 알림
        $this->broadcastToRoom($roomId, [
            'type' => 'gameStarted',
            'data' => $result['gameState']
        ]);
        
        // 첫 플레이어에게 턴 알림
        $this->notifyPlayerTurn($roomId);
    }
    
    /**
     * 게임 액션 처리
     */
    private function handleGameAction($clientId, $roomId, $data) {
        if (!isset($this->rooms[$roomId]['game'])) {
            $this->clients[$clientId]['conn']->send(json_encode([
                'type' => 'error',
                'message' => '게임이 시작되지 않았습니다.'
            ]));
            return;
        }
        
        $client = $this->clients[$clientId];
        $game = $this->rooms[$roomId]['game'];
        
        if (!isset($data['action'])) {
            return;
        }
        
        $action = $data['action'];
        $amount = isset($data['amount']) ? $data['amount'] : 0;
        
        // 게임 액션 처리
        $result = $game->playerAction($client['userId'], $action, $amount);
        
        if (!$result['success']) {
            $client['conn']->send(json_encode([
                'type' => 'error',
                'message' => $result['message']
            ]));
            return;
        }
        
        // 액션 결과 브로드캐스트
        $this->broadcastToRoom($roomId, [
            'type' => 'playerAction',
            'data' => [
                'userId' => $client['userId'],
                'username' => $client['username'],
                'action' => $result['action'],
                'amount' => isset($result['amount']) ? $result['amount'] : null
            ]
        ]);
        
        // 게임 상태 업데이트
        $this->updateGameState($roomId, $game->getGameState());
        
        // 라운드 완료 체크
        if (isset($result['roundComplete']) && $result['roundComplete']) {
            // 다음 라운드 처리
            $this->handleNextRound($roomId);
        } else {
            // 다음 플레이어 턴 알림
            $this->notifyPlayerTurn($roomId);
        }
    }
    
    /**
     * 다음 라운드 처리
     */
    private function handleNextRound($roomId) {
        $game = $this->rooms[$roomId]['game'];
        $result = $game->nextRound();
        
        if (!$result['success']) {
            $this->broadcastToRoom($roomId, [
                'type' => 'error',
                'message' => $result['message']
            ]);
            return;
        }
        
        // 게임 상태 업데이트
        $this->updateGameState($roomId, $result['gameState']);
        
        // 라운드 시작 알림
        if (isset($result['roundName'])) {
            $this->broadcastToRoom($roomId, [
                'type' => 'roundStarted',
                'data' => [
                    'roundName' => $result['roundName'],
                    'gameState' => $result['gameState']
                ]
            ]);
            
            // 다음 플레이어 턴 알림
            $this->notifyPlayerTurn($roomId);
        }
        
        // 게임 종료 체크
        if ($result['gameState']['round'] == 'ended') {
            $this->handleGameEnd($roomId, $result);
        }
    }
    
    /**
     * 게임 종료 처리
     */
    private function handleGameEnd($roomId, $result) {
        // 방 상태 업데이트 (비활성화)
        $stmtUpdate = $this->conn->prepare("UPDATE rooms SET is_active = 0 WHERE id = ?");
        $stmtUpdate->bind_param("i", $roomId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // 사용자 돈 업데이트
        if (isset($result['winners'])) {
            foreach ($result['winners'] as $winner) {
                $stmtMoney = $this->conn->prepare("UPDATE users SET money = money + ? WHERE id = ?");
                $stmtMoney->bind_param("ii", $winner['winAmount'], $winner['id']);
                $stmtMoney->execute();
                $stmtMoney->close();
            }
        }
        
        // 다시 플레이어 정보 가져와서 업데이트
        $players = $this->getPlayersInRoom($roomId);
        $result['gameState']['players'] = $players;
        
        // 게임 종료 알림
        $this->broadcastToRoom($roomId, [
            'type' => 'gameEnded',
            'data' => $result['gameState']
        ]);
        
        // 게임 객체 제거
        $this->rooms[$roomId]['game'] = null;
    }
    
    /**
     * 플레이어 턴 알림
     */
    private function notifyPlayerTurn($roomId) {
        if (!isset($this->rooms[$roomId]['game'])) {
            return;
        }
        
        $game = $this->rooms[$roomId]['game'];
        $gameState = $game->getGameState();
        
        if (!$gameState['isActive']) {
            return;
        }
        
        $currentPlayerId = $gameState['currentPlayer'];
        $timeLimit = 30; // 제한 시간 (초)
        
        $this->broadcastToRoom($roomId, [
            'type' => 'turnChanged',
            'data' => [
                'currentPlayer' => $currentPlayerId,
                'timeLimit' => $timeLimit,
                'gameState' => $gameState
            ]
        ]);
    }
    
    /**
     * 채팅 메시지 처리
     */
    private function handleChatMessage($clientId, $roomId, $data) {
        if (!isset($data['message'])) {
            return;
        }
        
        $client = $this->clients[$clientId];
        
        $this->broadcastToRoom($roomId, [
            'type' => 'chat',
            'data' => [
                'userId' => $client['userId'],
                'username' => $client['username'],
                'message' => $data['message'],
                'timestamp' => time()
            ]
        ]);
    }
    
    /**
     * 게임 상태 가져오기
     */
    private function getGameState($roomId) {
        if (isset($this->rooms[$roomId]['game'])) {
            return $this->rooms[$roomId]['game']->getGameState();
        } else if (isset($this->rooms[$roomId]['gameState'])) {
            return $this->rooms[$roomId]['gameState'];
        } else {
            return [
                'isActive' => false,
                'players' => $this->getPlayersInRoom($roomId),
                'communityCards' => [],
                'pot' => 0,
                'round' => 'waiting'
            ];
        }
    }
    
    /**
     * 게임 상태 업데이트
     */
    private function updateGameState($roomId, $gameState) {
        $this->rooms[$roomId]['gameState'] = $gameState;
        
        // DB에 업데이트
        $jsonState = json_encode($gameState);
        $stmtUpdate = $this->conn->prepare("UPDATE game_states SET game_data = ? WHERE room_id = ?");
        $stmtUpdate->bind_param("si", $jsonState, $roomId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // 모든 클라이언트에게 상태 전송
        $this->broadcastToRoom($roomId, [
            'type' => 'gameState',
            'data' => $gameState
        ]);
    }
    
    /**
     * 방에 있는 모든 클라이언트에게 메시지 전송
     */
    private function broadcastToRoom($roomId, $message, $excludeClientId = null) {
        if (!isset($this->rooms[$roomId])) {
            return;
        }
        
        $jsonMessage = json_encode($message);
        
        foreach ($this->rooms[$roomId]['clients'] as $clientId) {
            if ($excludeClientId && $clientId == $excludeClientId) {
                continue;
            }
            
            $this->clients[$clientId]['conn']->send($jsonMessage);
        }
    }
    
    /**
     * 연결 객체로 클라이언트 ID 찾기
     */
    private function getClientIdFromConn($conn) {
        foreach ($this->clients as $id => $client) {
            if ($client['conn'] === $conn) {
                return $id;
            }
        }
        
        return null;
    }
    
    /**
     * 사용자 ID로 클라이언트 ID 찾기
     */
    private function getClientIdByUserId($userId, $roomId) {
        foreach ($this->clients as $id => $client) {
            if ($client['userId'] == $userId && $client['roomId'] == $roomId) {
                return $id;
            }
        }
        
        return null;
    }
}

// WebSocket 클라이언트 코드 예시
if (isset($_GET['userId']) && isset($_GET['roomId'])) {
    $userId = $_GET['userId'];
    $roomId = $_GET['roomId'];
    
    // 이 코드는 실제로 WebSocket 연결을 처리하지 않고,
    // 클라이언트에게 WebSocket URL을 반환합니다.
    echo json_encode([
        'success' => true,
        'wsUrl' => "ws://{$_SERVER['HTTP_HOST']}/ws/game?userId=$userId&roomId=$roomId"
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'userId와 roomId가 필요합니다.'
    ]);
}

/*
 * 실제 WebSocket 서버 실행 방법
 * Ratchet 라이브러리를 사용한 독립 실행 스크립트가 필요합니다.
 * 예: bin/poker-server.php
 
require 'vendor/autoload.php';
require 'php/PokerGame.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new PokerGameServer()
        )
    ),
    8080
);

echo "Poker WebSocket server started on port 8080.\n";
$server->run();
*/

?> 