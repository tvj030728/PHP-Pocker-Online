<?php
class PokerGame {
    private $roomId;
    private $players = [];
    private $deck = [];
    private $communityCards = [];
    private $pot = 0;
    private $round = 'waiting'; // waiting, preflop, flop, turn, river, showdown, ended
    private $currentPlayerIndex = 0;
    private $dealerPosition = 0;
    private $smallBlindPosition = 0;
    private $bigBlindPosition = 0;
    private $smallBlindAmount;
    private $bigBlindAmount;
    private $lastBetAmount = 0;
    private $minRaise = 0;

    // Game state values
    private $isActive = false;
    private $winnerIds = [];
    private $timeLimit = 30; // in seconds
    
    /**
     * Constructor
     * @param int $roomId Game room ID
     * @param array $players Player list
     * @param int $smallBlind Small blind value
     * @param int $bigBlind Big blind value
     */
    public function __construct($roomId, $players, $smallBlind, $bigBlind) {
        $this->roomId = $roomId;
        $this->players = $players;
        $this->smallBlindAmount = $smallBlind;
        $this->bigBlindAmount = $bigBlind;
    }
    
    /**
     * Game initialization
     */
    public function initGame() {
        // Check if there are at least 2 players
        if (count($this->players) < 2) {
            return ['success' => false, 'message' => 'To start the game, you need at least 2 players.'];
        }
        
        // Initialize game state
        $this->isActive = true;
        $this->round = 'preflop';
        $this->pot = 0;
        $this->communityCards = [];
        $this->winnerIds = [];
        $this->lastBetAmount = 0;
        $this->minRaise = $this->bigBlindAmount;
        
        // Initialize player states
        foreach ($this->players as &$player) {
            $player['cards'] = [];
            $player['currentBet'] = 0;
            $player['folded'] = false;
            $player['lastAction'] = null;
        }
        
        // Set dealer position (random for the first game, otherwise next person)
        if ($this->dealerPosition === null || $this->dealerPosition === 0) {
            $this->dealerPosition = mt_rand(0, count($this->players) - 1);
        } else {
            $this->dealerPosition = ($this->dealerPosition + 1) % count($this->players);
        }
        
        // Set blind positions
        $this->setBlindPositions();
        
        // Post blinds
        $this->postBlinds();
        
        // Create and shuffle deck
        $this->createDeck();
        $this->shuffleDeck();
        
        // Deal cards
        $this->dealCards();
        
        // Set first player (next to big blind)
        $this->currentPlayerIndex = ($this->bigBlindPosition + 1) % count($this->players);
        
        return [
            'success' => true,
            'gameState' => $this->getGameState()
        ];
    }
    
    /**
     * Set blind positions
     */
    private function setBlindPositions() {
        $playerCount = count($this->players);
        
        if ($playerCount == 2) {
            // In heads-up, the dealer is small blind, the opponent is big blind
            $this->smallBlindPosition = $this->dealerPosition;
            $this->bigBlindPosition = ($this->dealerPosition + 1) % $playerCount;
        } else {
            // For 3 or more players, the dealer's next is small blind, then the next is big blind
            $this->smallBlindPosition = ($this->dealerPosition + 1) % $playerCount;
            $this->bigBlindPosition = ($this->dealerPosition + 2) % $playerCount;
        }
    }
    
    /**
     * Post blinds
     */
    private function postBlinds() {
        // Small blind
        $smallBlindPlayer = &$this->players[$this->smallBlindPosition];
        $smallBlindAmount = min($this->smallBlindAmount, $smallBlindPlayer['money']);
        $smallBlindPlayer['money'] -= $smallBlindAmount;
        $smallBlindPlayer['currentBet'] = $smallBlindAmount;
        $this->pot += $smallBlindAmount;
        
        // Big blind
        $bigBlindPlayer = &$this->players[$this->bigBlindPosition];
        $bigBlindAmount = min($this->bigBlindAmount, $bigBlindPlayer['money']);
        $bigBlindPlayer['money'] -= $bigBlindAmount;
        $bigBlindPlayer['currentBet'] = $bigBlindAmount;
        $this->pot += $bigBlindAmount;
        
        $this->lastBetAmount = $bigBlindAmount;
    }
    
    /**
     * Create deck
     */
    private function createDeck() {
        $this->deck = [];
        $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
        
        foreach ($suits as $suit) {
            for ($value = 1; $value <= 13; $value++) {
                $this->deck[] = [
                    'suit' => $suit,
                    'value' => $value
                ];
            }
        }
    }
    
    /**
     * Shuffle deck
     */
    private function shuffleDeck() {
        for ($i = 0; $i < 5; $i++) {
            shuffle($this->deck);
        }
    }
    
    /**
     * Deal cards
     */
    private function dealCards() {
        // Deal 2 cards to each player
        for ($i = 0; $i < 2; $i++) {
            foreach ($this->players as &$player) {
                $player['cards'][] = array_pop($this->deck);
            }
        }
    }
    
    /**
     * Move to next player
     */
    private function moveToNextPlayer() {
        $playerCount = count($this->players);
        $startIndex = $this->currentPlayerIndex;
        
        do {
            $this->currentPlayerIndex = ($this->currentPlayerIndex + 1) % $playerCount;
            
            // If all players are checked and next player is not found
            if ($this->currentPlayerIndex == $startIndex) {
                return false;
            }
        } while ($this->players[$this->currentPlayerIndex]['folded'] || $this->players[$this->currentPlayerIndex]['money'] == 0);
        
        return true;
    }
    
    /**
     * Check for round completion
     */
    private function isRoundComplete() {
        $activePlayers = $this->getActivePlayers();
        
        // If only one player remains, round ends
        if (count($activePlayers) == 1) {
            return true;
        }
        
        // Check if all active players have bet or all-in
        $targetBet = $this->lastBetAmount;
        
        foreach ($activePlayers as $player) {
            // If not yet bet or not meeting the bet
            if ($player['lastAction'] === null || 
                ($player['currentBet'] < $targetBet && $player['money'] > 0)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get active players
     */
    private function getActivePlayers() {
        $activePlayers = [];
        
        foreach ($this->players as $player) {
            if (!$player['folded']) {
                $activePlayers[] = $player;
            }
        }
        
        return $activePlayers;
    }
    
    /**
     * Proceed to next round
     */
    public function nextRound() {
        // Check active players
        $activePlayers = $this->getActivePlayers();
        
        // If only one player remains, end game
        if (count($activePlayers) == 1) {
            return $this->endGame();
        }
        
        // Proceed based on round
        switch ($this->round) {
            case 'preflop':
                $this->round = 'flop';
                $this->dealCommunityCards(3); // Flop has 3 cards
                break;
                
            case 'flop':
                $this->round = 'turn';
                $this->dealCommunityCards(1); // Turn has 1 card
                break;
                
            case 'turn':
                $this->round = 'river';
                $this->dealCommunityCards(1); // River has 1 card
                break;
                
            case 'river':
                $this->round = 'showdown';
                return $this->showdown();
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unknown round'
                ];
        }
        
        // Reset betting state
        foreach ($this->players as &$player) {
            $player['currentBet'] = 0;
            $player['lastAction'] = null;
        }
        
        $this->lastBetAmount = 0;
        $this->minRaise = $this->bigBlindAmount;
        
        // Start from dealer's next person
        if (count($activePlayers) >= 3) {
            $this->currentPlayerIndex = ($this->dealerPosition + 1) % count($this->players);
        } else {
            // In heads-up, start from dealer (small blind)
            $this->currentPlayerIndex = $this->dealerPosition;
        }
        
        // Adjust current player position (skip folded or no money players)
        while ($this->players[$this->currentPlayerIndex]['folded'] || 
               $this->players[$this->currentPlayerIndex]['money'] == 0) {
            if (!$this->moveToNextPlayer()) {
                break;
            }
        }
        
        return [
            'success' => true,
            'roundName' => $this->round,
            'gameState' => $this->getGameState()
        ];
    }
    
    /**
     * Deal community cards
     */
    private function dealCommunityCards($count) {
        for ($i = 0; $i < $count; $i++) {
            $this->communityCards[] = array_pop($this->deck);
        }
    }
    
    /**
     * Showdown (determine winner)
     */
    private function showdown() {
        $activePlayers = $this->getActivePlayers();
        $winners = [];
        $bestRank = 0;
        
        // Evaluate hand rank for each player
        foreach ($activePlayers as $index => $player) {
            $cards = array_merge($player['cards'], $this->communityCards);
            $handRank = $this->evaluateHand($cards);
            
            $activePlayers[$index]['handRank'] = $handRank;
            
            if ($handRank > $bestRank) {
                $bestRank = $handRank;
                $winners = [$index];
            } elseif ($handRank == $bestRank) {
                $winners[] = $index;
            }
        }
        
        // Distribute pot to winners
        $winAmount = floor($this->pot / count($winners));
        
        foreach ($winners as $winnerIndex) {
            $playerId = $activePlayers[$winnerIndex]['id'];
            
            // Find winner in player list
            foreach ($this->players as &$player) {
                if ($player['id'] == $playerId) {
                    $player['money'] += $winAmount;
                    $this->winnerIds[] = [
                        'id' => $player['id'],
                        'username' => $player['username'],
                        'winAmount' => $winAmount
                    ];
                    break;
                }
            }
        }
        
        // Game end processing
        $this->isActive = false;
        $this->round = 'ended';
        
        return [
            'success' => true,
            'winners' => $this->winnerIds,
            'gameState' => $this->getGameState()
        ];
    }
    
    /**
     * Game end (when only one player remains)
     */
    private function endGame() {
        $activePlayers = $this->getActivePlayers();
        
        if (count($activePlayers) == 1) {
            $winner = $activePlayers[0];
            
            // Find winner in player list
            foreach ($this->players as &$player) {
                if ($player['id'] == $winner['id']) {
                    $player['money'] += $this->pot;
                    $this->winnerIds[] = [
                        'id' => $player['id'],
                        'username' => $player['username'],
                        'winAmount' => $this->pot
                    ];
                    break;
                }
            }
        }
        
        // Game end processing
        $this->isActive = false;
        $this->round = 'ended';
        
        return [
            'success' => true,
            'winners' => $this->winnerIds,
            'gameState' => $this->getGameState()
        ];
    }
    
    /**
     * Evaluate hand rank
     */
    private function evaluateHand($cards) {
        // Poker hand evaluation is complex, so use a simple rank system
        // In actual implementation, use a more accurate hand evaluation algorithm
        $values = [];
        $suits = [];
        
        foreach ($cards as $card) {
            $values[] = $card['value'];
            $suits[] = $card['suit'];
        }
        
        // Value count
        $valueCounts = array_count_values($values);
        arsort($valueCounts);
        
        // Suit count
        $suitCounts = array_count_values($suits);
        arsort($suitCounts);
        
        // Straight check
        $uniqueValues = array_unique($values);
        sort($uniqueValues);
        $isStraight = $this->checkStraight($uniqueValues);
        
        // Flush check
        $isFlush = max($suitCounts) >= 5;
        
        // Royal straight flush (royal flush)
        if ($isFlush && $isStraight && in_array(1, $values) && in_array(13, $values)) {
            return 9;
        }
        
        // Straight flush
        if ($isFlush && $isStraight) {
            return 8;
        }
        
        // Four of a Kind
        if (max($valueCounts) == 4) {
            return 7;
        }
        
        // Full House
        if (max($valueCounts) == 3 && count($valueCounts) > 1 && next($valueCounts) >= 2) {
            return 6;
        }
        
        // Flush
        if ($isFlush) {
            return 5;
        }
        
        // Straight
        if ($isStraight) {
            return 4;
        }
        
        // Three of a Kind
        if (max($valueCounts) == 3) {
            return 3;
        }
        
        // Two Pair
        if (max($valueCounts) == 2 && count($valueCounts) > 2 && next($valueCounts) == 2) {
            return 2;
        }
        
        // One Pair
        if (max($valueCounts) == 2) {
            return 1;
        }
        
        // High card
        return 0;
    }
    
    /**
     * Straight check
     */
    private function checkStraight($values) {
        if (count($values) < 5) {
            return false;
        }
        
        // A-5 straight check (A is used as 1)
        // A-5 스트레이트 체크 (A는 1로 사용)
        if (in_array(1, $values) && in_array(2, $values) && in_array(3, $values) && in_array(4, $values) && in_array(5, $values)) {
            return true;
        }
        
        // 일반 스트레이트 체크
        $sequence = 1;
        $maxSequence = 1;
        
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] == $values[$i-1] + 1) {
                $sequence++;
                $maxSequence = max($maxSequence, $sequence);
            } else if ($values[$i] != $values[$i-1]) {
                $sequence = 1;
            }
        }
        
        return $maxSequence >= 5;
    }
    
    /**
     * 플레이어 액션 처리
     */
    public function playerAction($playerId, $action, $amount = 0) {
        if (!$this->isActive) {
            return ['success' => false, 'message' => '게임이 활성화되어 있지 않습니다.'];
        }
        
        // 현재 턴의 플레이어 확인
        $currentPlayer = $this->players[$this->currentPlayerIndex];
        
        if ($currentPlayer['id'] != $playerId) {
            return ['success' => false, 'message' => '당신의 턴이 아닙니다.'];
        }
        
        // 액션 처리
        switch ($action) {
            case 'fold':
                return $this->fold();
                
            case 'check':
                return $this->check();
                
            case 'call':
                return $this->call();
                
            case 'bet':
                return $this->bet($amount);
                
            case 'raise':
                return $this->raise($amount);
                
            default:
                return ['success' => false, 'message' => '알 수 없는 액션입니다.'];
        }
    }
    
    /**
     * 폴드 액션
     */
    private function fold() {
        $player = &$this->players[$this->currentPlayerIndex];
        $player['folded'] = true;
        $player['lastAction'] = 'fold';
        
        // 액션 결과
        $actionResult = [
            'success' => true,
            'action' => 'fold',
            'username' => $player['username']
        ];
        
        // 다음 플레이어로 이동
        if (!$this->moveToNextPlayer()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        // 라운드 완료 체크
        if ($this->isRoundComplete()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        return array_merge($actionResult, [
            'roundComplete' => false,
            'nextPlayer' => $this->getCurrentPlayerInfo()
        ]);
    }
    
    /**
     * 체크 액션
     */
    private function check() {
        // 체크 가능한지 확인
        if ($this->lastBetAmount > 0 && $this->players[$this->currentPlayerIndex]['currentBet'] < $this->lastBetAmount) {
            return ['success' => false, 'message' => '체크할 수 없습니다. 콜이나 레이즈를 해야 합니다.'];
        }
        
        $player = &$this->players[$this->currentPlayerIndex];
        $player['lastAction'] = 'check';
        
        // 액션 결과
        $actionResult = [
            'success' => true,
            'action' => 'check',
            'username' => $player['username']
        ];
        
        // 다음 플레이어로 이동
        if (!$this->moveToNextPlayer()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        // 라운드 완료 체크
        if ($this->isRoundComplete()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        return array_merge($actionResult, [
            'roundComplete' => false,
            'nextPlayer' => $this->getCurrentPlayerInfo()
        ]);
    }
    
    /**
     * 콜 액션
     */
    private function call() {
        $player = &$this->players[$this->currentPlayerIndex];
        
        // 콜 금액 계산
        $callAmount = $this->lastBetAmount - $player['currentBet'];
        
        // 올인 상황
        if ($callAmount >= $player['money']) {
            $callAmount = $player['money'];
            $player['money'] = 0;
        } else {
            $player['money'] -= $callAmount;
        }
        
        $player['currentBet'] += $callAmount;
        $this->pot += $callAmount;
        $player['lastAction'] = 'call';
        
        // 액션 결과
        $actionResult = [
            'success' => true,
            'action' => 'call',
            'username' => $player['username'],
            'amount' => $callAmount
        ];
        
        // 다음 플레이어로 이동
        if (!$this->moveToNextPlayer()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        // 라운드 완료 체크
        if ($this->isRoundComplete()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        return array_merge($actionResult, [
            'roundComplete' => false,
            'nextPlayer' => $this->getCurrentPlayerInfo()
        ]);
    }
    
    /**
     * 벳 액션
     */
    private function bet($amount) {
        // 이미 베팅이 있는지 확인
        if ($this->lastBetAmount > 0) {
            return ['success' => false, 'message' => '이미 베팅이 있습니다. 콜이나 레이즈를 해야 합니다.'];
        }
        
        // 금액 유효성 검사
        if ($amount < $this->bigBlindAmount) {
            return ['success' => false, 'message' => "최소 빅 블라인드($this->bigBlindAmount) 이상 베팅해야 합니다."];
        }
        
        $player = &$this->players[$this->currentPlayerIndex];
        
        if ($amount > $player['money']) {
            return ['success' => false, 'message' => '보유한 금액보다 많이 베팅할 수 없습니다.'];
        }
        
        // 베팅 처리
        $player['money'] -= $amount;
        $player['currentBet'] = $amount;
        $this->pot += $amount;
        $this->lastBetAmount = $amount;
        $this->minRaise = $amount;
        $player['lastAction'] = 'bet';
        
        // 액션 결과
        $actionResult = [
            'success' => true,
            'action' => 'bet',
            'username' => $player['username'],
            'amount' => $amount
        ];
        
        // 다음 플레이어로 이동
        if (!$this->moveToNextPlayer()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        return array_merge($actionResult, [
            'roundComplete' => false,
            'nextPlayer' => $this->getCurrentPlayerInfo()
        ]);
    }
    
    /**
     * 레이즈 액션
     */
    private function raise($amount) {
        // 베팅이 없는지 확인
        if ($this->lastBetAmount == 0) {
            return ['success' => false, 'message' => '현재 베팅이 없습니다. 베팅을 해야 합니다.'];
        }
        
        $player = &$this->players[$this->currentPlayerIndex];
        
        // 총 필요 금액 계산 (콜 금액 + 추가 레이즈)
        $callAmount = $this->lastBetAmount - $player['currentBet'];
        $totalAmount = $callAmount + $amount;
        
        // 최소 레이즈 체크
        $minRaiseAmount = $this->lastBetAmount + $this->minRaise;
        
        if ($player['currentBet'] + $totalAmount < $minRaiseAmount) {
            return [
                'success' => false, 
                'message' => "최소 $this->minRaise 이상으로 레이즈해야 합니다. (총 $minRaiseAmount)"
            ];
        }
        
        if ($totalAmount > $player['money']) {
            return ['success' => false, 'message' => '보유한 금액보다 많이 레이즈할 수 없습니다.'];
        }
        
        // 레이즈 처리
        $player['money'] -= $totalAmount;
        $player['currentBet'] += $totalAmount;
        $this->pot += $totalAmount;
        $this->lastBetAmount = $player['currentBet'];
        $this->minRaise = $amount; // 다음 레이즈 금액은 최소 이번 레이즈 금액 이상
        $player['lastAction'] = 'raise';
        
        // 액션 결과
        $actionResult = [
            'success' => true,
            'action' => 'raise',
            'username' => $player['username'],
            'amount' => $player['currentBet']
        ];
        
        // 다음 플레이어로 이동
        if (!$this->moveToNextPlayer()) {
            $this->endRound();
            return array_merge($actionResult, ['roundComplete' => true]);
        }
        
        return array_merge($actionResult, [
            'roundComplete' => false,
            'nextPlayer' => $this->getCurrentPlayerInfo()
        ]);
    }
    
    /**
     * 라운드 종료 처리
     */
    private function endRound() {
        $activePlayers = $this->getActivePlayers();
        
        // 한 명만 남았으면 게임 종료
        if (count($activePlayers) == 1) {
            return $this->endGame();
        }
        
        // 모든 플레이어가 올인했거나, 리버까지 도달했으면 쇼다운
        $allPlayersAllIn = true;
        foreach ($activePlayers as $player) {
            if ($player['money'] > 0) {
                $allPlayersAllIn = false;
                break;
            }
        }
        
        if ($allPlayersAllIn || $this->round == 'river') {
            return $this->showdown();
        }
        
        // 다음 라운드로 진행
        return $this->nextRound();
    }
    
    /**
     * 현재 플레이어 정보 가져오기
     */
    private function getCurrentPlayerInfo() {
        return [
            'id' => $this->players[$this->currentPlayerIndex]['id'],
            'username' => $this->players[$this->currentPlayerIndex]['username'],
            'timeLimit' => $this->timeLimit
        ];
    }
    
    /**
     * 게임 상태 가져오기
     */
    public function getGameState() {
        $gameState = [
            'isActive' => $this->isActive,
            'round' => $this->round,
            'pot' => $this->pot,
            'communityCards' => $this->communityCards,
            'currentPlayer' => $this->isActive ? $this->players[$this->currentPlayerIndex]['id'] : null,
            'players' => $this->getPlayersState(),
            'smallBlind' => $this->smallBlindAmount,
            'bigBlind' => $this->bigBlindAmount,
            'dealerPosition' => $this->dealerPosition
        ];
        
        // 현재 베팅 상태에 따른 가능한 액션 추가
        if ($this->isActive) {
            $gameState = array_merge($gameState, $this->getAvailableActions());
        }
        
        // 쇼다운이나 게임 종료 시 승자 정보 추가
        if ($this->round == 'showdown' || $this->round == 'ended') {
            $gameState['winners'] = $this->winnerIds;
        }
        
        return $gameState;
    }
    
    /**
     * 플레이어들의 현재 상태 가져오기 (클라이언트에 전송용)
     */
    private function getPlayersState() {
        $playersState = [];
        
        foreach ($this->players as $player) {
            $playerState = [
                'id' => $player['id'],
                'username' => $player['username'],
                'money' => $player['money'],
                'currentBet' => $player['currentBet'],
                'folded' => $player['folded'],
                'lastAction' => $player['lastAction'],
                'seatPosition' => $player['seatPosition']
            ];
            
            // 내 카드는 보이고, 다른 사람 카드는 쇼다운 때만 보임
            if (isset($player['cards'])) {
                $playerState['cards'] = $player['cards'];
            }
            
            $playersState[] = $playerState;
        }
        
        return $playersState;
    }
    
    /**
     * 현재 플레이어의 가능한 액션 가져오기
     */
    private function getAvailableActions() {
        $player = $this->players[$this->currentPlayerIndex];
        $actions = [];
        
        // 항상 폴드는 가능
        $actions['canFold'] = true;
        
        // 체크 가능 여부
        $actions['canCheck'] = ($this->lastBetAmount == 0 || $player['currentBet'] == $this->lastBetAmount);
        
        // 콜 가능 여부
        $actions['canCall'] = ($this->lastBetAmount > 0 && $player['currentBet'] < $this->lastBetAmount);
        
        // 베팅 가능 여부
        $actions['canBet'] = ($this->lastBetAmount == 0 && $player['money'] > 0);
        
        // 레이즈 가능 여부
        $actions['canRaise'] = ($this->lastBetAmount > 0 && $player['money'] > 0);
        
        // 최소 베팅/레이즈 금액
        $actions['minBet'] = $this->bigBlindAmount;
        
        // 최소 레이즈 금액
        if ($actions['canRaise']) {
            $callAmount = $this->lastBetAmount - $player['currentBet'];
            $actions['minRaise'] = $this->minRaise;
        }
        
        return $actions;
    }
    
    /**
     * 플레이어 정보 업데이트
     */
    public function updatePlayer($playerId, $money) {
        foreach ($this->players as &$player) {
            if ($player['id'] == $playerId) {
                $player['money'] = $money;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 플레이어 추가
     */
    public function addPlayer($player) {
        $this->players[] = [
            'id' => $player['id'],
            'username' => $player['username'],
            'money' => $player['money'],
            'seatPosition' => $player['seatPosition'],
            'cards' => [],
            'currentBet' => 0,
            'folded' => false,
            'lastAction' => null
        ];
        
        return true;
    }
    
    /**
     * 플레이어 제거
     */
    public function removePlayer($playerId) {
        foreach ($this->players as $index => $player) {
            if ($player['id'] == $playerId) {
                array_splice($this->players, $index, 1);
                
                // 현재 플레이어가 제거되는 경우 다음 플레이어로 이동
                if ($this->isActive && $index == $this->currentPlayerIndex) {
                    $this->moveToNextPlayer();
                }
                
                // 플레이어가 1명 이하면 게임 종료
                if (count($this->players) <= 1 && $this->isActive) {
                    $this->endGame();
                }
                
                return true;
            }
        }
        
        return false;
    }
}
?> 