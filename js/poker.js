// Global variables
let currentUser = null;
let currentRoom = null;
let socket = null;
let gameState = null;
let playerPositions = [];
let timer = null;

// DOM element caching
const DOM = {
  // Login and user elements
  loginForm: document.getElementById("loginForm"),
  username: document.getElementById("username"),
  loginBtn: document.getElementById("loginBtn"),
  userProfile: document.getElementById("userProfile"),
  displayName: document.getElementById("displayName"),
  userMoney: document.getElementById("userMoney"),
  logoutBtn: document.getElementById("logoutBtn"),

  // Lobby elements
  lobby: document.getElementById("lobby"),
  createRoomBtn: document.getElementById("createRoomBtn"),
  refreshRoomsBtn: document.getElementById("refreshRoomsBtn"),
  roomList: document.getElementById("roomList"),

  // Game room elements
  gameRoom: document.getElementById("gameRoom"),
  roomName: document.getElementById("roomName"),
  roomPlayers: document.getElementById("roomPlayers"),
  blindInfo: document.getElementById("blindInfo"),
  leaveRoomBtn: document.getElementById("leaveRoomBtn"),
  pokerTable: document.getElementById("pokerTable"),
  communityCards: document.getElementById("communityCards"),
  potAmount: document.getElementById("potAmount"),
  players: document.getElementById("players"),
  myPlayer: document.getElementById("myPlayer"),

  // Game controls
  actionBtns: document.getElementById("actionBtns"),
  foldBtn: document.getElementById("foldBtn"),
  checkBtn: document.getElementById("checkBtn"),
  callBtn: document.getElementById("callBtn"),
  betBtn: document.getElementById("betBtn"),
  raiseBtn: document.getElementById("raiseBtn"),
  betSlider: document.getElementById("betSlider"),
  betAmount: document.getElementById("betAmount"),

  // Game status
  timer: document.getElementById("timer"),
  gameMessage: document.getElementById("gameMessage"),

  // Modal
  createRoomModal: document.getElementById("createRoomModal"),
  createRoomForm: document.getElementById("createRoomForm"),
  newRoomName: document.getElementById("newRoomName"),
  maxPlayers: document.getElementById("maxPlayers"),
  smallBlind: document.getElementById("smallBlind"),
  bigBlind: document.getElementById("bigBlind"),
  cancelCreateRoom: document.getElementById("cancelCreateRoom"),
};

// Page load time initialization
document.addEventListener("DOMContentLoaded", () => {
  initApp();
});

// App initialization
function initApp() {
  // Get user information from local storage
  const savedUser = localStorage.getItem("pokerUser");
  if (savedUser) {
    currentUser = JSON.parse(savedUser);
    updateUserUI();
    loadRoomList();
  }

  // Register event listeners
  attachEventListeners();
}

// Register event listeners
function attachEventListeners() {
  // Login related
  DOM.loginBtn.addEventListener("click", handleLogin);
  DOM.logoutBtn.addEventListener("click", handleLogout);

  // Lobby related
  DOM.createRoomBtn.addEventListener("click", showCreateRoomModal);
  DOM.refreshRoomsBtn.addEventListener("click", loadRoomList);

  // Room creation modal
  DOM.createRoomForm.addEventListener("submit", handleCreateRoom);
  DOM.cancelCreateRoom.addEventListener("click", hideCreateRoomModal);

  // Game room related
  DOM.leaveRoomBtn.addEventListener("click", leaveRoom);

  // Game controls
  DOM.foldBtn.addEventListener("click", () => sendGameAction("fold"));
  DOM.checkBtn.addEventListener("click", () => sendGameAction("check"));
  DOM.callBtn.addEventListener("click", () => sendGameAction("call"));
  DOM.betBtn.addEventListener("click", () =>
    sendGameAction("bet", parseInt(DOM.betAmount.value))
  );
  DOM.raiseBtn.addEventListener("click", () =>
    sendGameAction("raise", parseInt(DOM.betAmount.value))
  );

  // Betting adjustment
  DOM.betSlider.addEventListener("input", updateBetAmount);
  DOM.betAmount.addEventListener("input", updateBetSlider);
}

// Login processing
function handleLogin() {
  const username = DOM.username.value.trim();
  if (!username) {
    alert("Please enter your username.");
    return;
  }

  fetch("php/login.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ username }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        currentUser = data.user;
        localStorage.setItem("pokerUser", JSON.stringify(currentUser));
        updateUserUI();
        loadRoomList();
      } else {
        alert(data.message || "Login failed.");
      }
    })
    .catch((error) => {
      console.error("Login error:", error);
      alert("An error occurred during login.");
    });
}

// Logout processing
function handleLogout() {
  fetch("php/logout.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ userId: currentUser.id }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        currentUser = null;
        localStorage.removeItem("pokerUser");
        updateUserUI();
        if (socket) {
          socket.close();
          socket = null;
        }
      } else {
        alert(data.message || "Logout failed.");
      }
    })
    .catch((error) => {
      console.error("Logout error:", error);
      alert("An error occurred during logout.");
    });
}

// Update user UI
function updateUserUI() {
  if (currentUser) {
    DOM.loginForm.classList.add("hidden");
    DOM.userProfile.classList.remove("hidden");
    DOM.displayName.textContent = currentUser.username;
    DOM.userMoney.textContent = `₩${currentUser.money.toLocaleString()}`;
  } else {
    DOM.loginForm.classList.remove("hidden");
    DOM.userProfile.classList.add("hidden");
    DOM.lobby.classList.remove("hidden");
    DOM.gameRoom.classList.add("hidden");
  }
}

// Load room list
function loadRoomList() {
  if (!currentUser) return;

  fetch("php/getRooms.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        renderRoomList(data.rooms);
      } else {
        alert(data.message || "Failed to load room list.");
      }
    })
    .catch((error) => {
      console.error("Room list load error:", error);
      alert("An error occurred while loading room list.");
    });
}

// Render room list
function renderRoomList(rooms) {
  DOM.roomList.innerHTML = "";

  if (rooms.length === 0) {
    DOM.roomList.innerHTML =
      '<p class="empty-message">No available game rooms. Create a new room!</p>';
    return;
  }

  rooms.forEach((room) => {
    const roomCard = document.createElement("div");
    roomCard.className = "room-card";
    roomCard.innerHTML = `
            <h3>${room.name}</h3>
            <div class="room-details">
                <p>Players: ${room.currentPlayers}/${room.maxPlayers}</p>
                <p>Blinds: ${room.smallBlind}/${room.bigBlind}</p>
                <p>Status: ${room.isActive ? "In Game" : "Waiting"}</p>
            </div>
            <button class="join-btn" data-room-id="${room.id}">Join</button>
        `;

    const joinBtn = roomCard.querySelector(".join-btn");
    joinBtn.addEventListener("click", () => joinRoom(room.id));

    DOM.roomList.appendChild(roomCard);
  });
}

// Show room creation modal
function showCreateRoomModal() {
  if (!currentUser) {
    alert("Please log in to use this feature.");
    return;
  }

  DOM.createRoomModal.classList.remove("hidden");
}

// Hide room creation modal
function hideCreateRoomModal() {
  DOM.createRoomModal.classList.add("hidden");
  DOM.createRoomForm.reset();
}

// Room creation processing
function handleCreateRoom(event) {
  event.preventDefault();

  const roomData = {
    name: DOM.newRoomName.value.trim(),
    maxPlayers: parseInt(DOM.maxPlayers.value),
    smallBlind: parseInt(DOM.smallBlind.value),
    bigBlind: parseInt(DOM.bigBlind.value),
    creatorId: currentUser.id,
  };

  fetch("php/createRoom.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(roomData),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        hideCreateRoomModal();
        joinRoom(data.roomId);
      } else {
        alert(data.message || "Failed to create room.");
      }
    })
    .catch((error) => {
      console.error("Room creation error:", error);
      alert("An error occurred while creating room.");
    });
}

// Join room
function joinRoom(roomId) {
  if (!currentUser) {
    alert("Please log in to use this feature.");
    return;
  }

  fetch("php/joinRoom.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      userId: currentUser.id,
      roomId: roomId,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        currentRoom = data.room;
        DOM.lobby.classList.add("hidden");
        DOM.gameRoom.classList.remove("hidden");
        updateRoomUI();
        connectWebSocket();
      } else {
        alert(data.message || "Failed to join room.");
      }
    })
    .catch((error) => {
      console.error("Room join error:", error);
      alert("An error occurred while joining room.");
    });
}

// Update room UI
function updateRoomUI() {
  if (!currentRoom) return;

  DOM.roomName.textContent = currentRoom.name;
  DOM.roomPlayers.textContent = `Players: ${currentRoom.players.length}/${currentRoom.maxPlayers}`;
  DOM.blindInfo.textContent = `Blinds: ${currentRoom.smallBlind}/${currentRoom.bigBlind}`;

  // Initialize game table
  resetGameTable();
}

// WebSocket connection
function connectWebSocket() {
  if (socket) {
    socket.close();
  }

  // Select protocol based on SSL activation
  const protocol = window.location.protocol === "https:" ? "wss:" : "ws:";
  const wsUrl = `${protocol}//${window.location.host}/php/gameSocket.php?userId=${currentUser.id}&roomId=${currentRoom.id}`;

  socket = new WebSocket(wsUrl);

  socket.onopen = () => {
    console.log("WebSocket connection successful");
  };

  socket.onmessage = (event) => {
    const message = JSON.parse(event.data);
    handleSocketMessage(message);
  };

  socket.onclose = () => {
    console.log("WebSocket connection closed");
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  };

  socket.onerror = (error) => {
    console.error("WebSocket error:", error);
    alert("An error occurred while connecting to game server.");
  };
}

// WebSocket message processing
function handleSocketMessage(message) {
  switch (message.type) {
    case "gameState":
      updateGameState(message.data);
      break;
    case "playerJoined":
      updatePlayers(message.data.players);
      DOM.roomPlayers.textContent = `Players: ${message.data.players.length}/${currentRoom.maxPlayers}`;
      showGameMessage(`${message.data.username} joined the game.`);
      break;
    case "playerLeft":
      updatePlayers(message.data.players);
      DOM.roomPlayers.textContent = `Players: ${message.data.players.length}/${currentRoom.maxPlayers}`;
      showGameMessage(`${message.data.username} left the game.`);
      break;
    case "gameStarted":
      showGameMessage("Game started!");
      updateGameState(message.data);
      break;
    case "roundStarted":
      showGameMessage(`${message.data.roundName} round started.`);
      updateGameState(message.data);
      break;
    case "playerAction":
      showGameMessage(
        `${message.data.username} ${getActionName(message.data.action)}${
          message.data.amount ? " ₩" + message.data.amount.toLocaleString() : ""
        }`
      );
      break;
    case "turnChanged":
      updateGameState(message.data);
      if (message.data.currentPlayer === currentUser.id) {
        showGameMessage("Your turn.");
        startTurnTimer(message.data.timeLimit || 30);
      } else {
        const currentPlayerName =
          getPlayerById(message.data.currentPlayer)?.username || "Other player";
        showGameMessage(`${currentPlayerName}'s turn.`);
      }
      break;
    case "gameEnded":
      showGameMessage("Game ended.");
      updateGameState(message.data);
      if (message.data.winners) {
        message.data.winners.forEach((winner) => {
          showGameMessage(
            `${winner.username} won! (₩${winner.winAmount.toLocaleString()})`
          );
        });
      }
      break;
    case "error":
      alert(message.message || "Game error occurred.");
      break;
  }
}

// Get game action name
function getActionName(action) {
  const actionNames = {
    fold: "Fold",
    check: "Check",
    call: "Call",
    bet: "Bet",
    raise: "Raise",
  };
  return actionNames[action] || action;
}

// Get player information by ID
function getPlayerById(playerId) {
  return gameState?.players?.find((player) => player.id === playerId);
}

// Send game action
function sendGameAction(action, amount = 0) {
  if (!socket || socket.readyState !== WebSocket.OPEN) {
    alert("Game server connection is not established.");
    return;
  }

  const message = {
    type: "action",
    action: action,
    amount: amount,
  };

  socket.send(JSON.stringify(message));

  // Stop timer
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}

// Update game state
function updateGameState(state) {
  gameState = state;

  // Update community cards
  updateCommunityCards(state.communityCards || []);

  // Update pot amount
  DOM.potAmount.textContent = `Pot: ₩${state.pot.toLocaleString()}`;

  // Update players
  updatePlayers(state.players);

  // Update my information
  updateMyPlayerInfo();

  // Update game controls
  updateGameControls();

  // Update my money (UI)
  const myPlayer = state.players.find((p) => p.id === currentUser.id);
  if (myPlayer) {
    DOM.userMoney.textContent = `₩${myPlayer.money.toLocaleString()}`;
  }
}

// Update community cards
function updateCommunityCards(cards) {
  DOM.communityCards.innerHTML = "";

  if (cards.length === 0) {
    return;
  }

  cards.forEach((card) => {
    const cardElement = createCardElement(card);
    DOM.communityCards.appendChild(cardElement);
  });
}

// Create card element
function createCardElement(card) {
  const cardElement = document.createElement("div");
  cardElement.className = "card";

  if (!card) {
    cardElement.classList.add("card-back");
    return cardElement;
  }

  const isRed = card.suit === "hearts" || card.suit === "diamonds";
  if (isRed) {
    cardElement.classList.add("red");
  }

  const suitSymbols = {
    spades: "♠",
    hearts: "♥",
    diamonds: "♦",
    clubs: "♣",
  };

  const valueDisplay = {
    1: "A",
    11: "J",
    12: "Q",
    13: "K",
  };

  const displayValue = valueDisplay[card.value] || card.value;

  cardElement.innerHTML = `
        <div class="card-value">${displayValue}</div>
        <div class="card-symbol">${suitSymbols[card.suit]}</div>
    `;

  return cardElement;
}

// Update all players
function updatePlayers(players) {
  if (!players || players.length === 0) return;

  // Other players excluding myself
  const otherPlayers = players.filter((player) => player.id !== currentUser.id);

  // Initialize player positions
  DOM.players.innerHTML = "";

  // Maximum 6 players to display positions
  const positions = 6;

  otherPlayers.forEach((player, index) => {
    if (index < positions) {
      const playerPosition = document.createElement("div");
      playerPosition.className = "player-position";
      playerPosition.dataset.playerId = player.id;

      playerPosition.innerHTML = `
                <div class="player-area ${player.folded ? "folded" : ""} ${
        player.id === gameState?.currentPlayer ? "active" : ""
      }">
                    <div class="player-info">
                        <span class="player-name">${player.username}</span>
                        <span class="player-money">₩${player.money.toLocaleString()}</span>
                    </div>
                    <div class="player-cards">
                        ${
                          player.cards
                            ? player.cards
                                .map(
                                  (card) => createCardElement(card).outerHTML
                                )
                                .join("")
                            : '<div class="card card-back"></div><div class="card card-back"></div>'
                        }
                    </div>
                    ${
                      player.currentBet
                        ? `<div class="player-bet">₩${player.currentBet.toLocaleString()}</div>`
                        : ""
                    }
                    ${
                      player.lastAction
                        ? `<div class="player-action">${getActionName(
                            player.lastAction
                          )}</div>`
                        : ""
                    }
                </div>
            `;

      DOM.players.appendChild(playerPosition);
    }
  });
}

// Update my player information
function updateMyPlayerInfo() {
  if (!gameState || !gameState.players) return;

  const myPlayer = gameState.players.find((p) => p.id === currentUser.id);
  if (!myPlayer) return;

  // Update my information
  DOM.myPlayer.innerHTML = `
        <div class="player-area ${myPlayer.folded ? "folded" : ""} ${
    myPlayer.id === gameState.currentPlayer ? "active" : ""
  }">
            <div class="player-info">
                <span class="player-name">Me (${myPlayer.username})</span>
                <span class="player-money">₩${myPlayer.money.toLocaleString()}</span>
            </div>
            <div class="player-cards">
                ${
                  myPlayer.cards
                    ? myPlayer.cards
                        .map((card) => createCardElement(card).outerHTML)
                        .join("")
                    : '<div class="card card-back"></div><div class="card card-back"></div>'
                }
            </div>
            ${
              myPlayer.currentBet
                ? `<div class="player-bet">₩${myPlayer.currentBet.toLocaleString()}</div>`
                : ""
            }
            ${
              myPlayer.lastAction
                ? `<div class="player-action">${getActionName(
                    myPlayer.lastAction
                  )}</div>`
                : ""
            }
        </div>
    `;
}

// Update game controls
function updateGameControls() {
  if (!gameState) return;

  const myPlayer = gameState.players.find((p) => p.id === currentUser.id);
  const isMyTurn =
    myPlayer && gameState.currentPlayer === currentUser.id && !myPlayer.folded;

  // Disable all action buttons
  DOM.foldBtn.disabled = !isMyTurn;
  DOM.checkBtn.disabled = !isMyTurn || !gameState.canCheck;
  DOM.callBtn.disabled = !isMyTurn || !gameState.canCall;
  DOM.betBtn.disabled = !isMyTurn || !gameState.canBet;
  DOM.raiseBtn.disabled = !isMyTurn || !gameState.canRaise;

  // Betting slider setting
  if (isMyTurn && (gameState.canBet || gameState.canRaise)) {
    const minBet = gameState.minBet || gameState.bigBlind;
    const maxBet = myPlayer.money;

    DOM.betSlider.min = minBet;
    DOM.betSlider.max = maxBet;
    DOM.betSlider.value = minBet;
    DOM.betAmount.value = minBet;
    DOM.betSlider.disabled = false;
    DOM.betAmount.disabled = false;
  } else {
    DOM.betSlider.disabled = true;
    DOM.betAmount.disabled = true;
  }
}

// Update betting amount slider
function updateBetAmount() {
  DOM.betAmount.value = DOM.betSlider.value;
}

// Update betting slider
function updateBetSlider() {
  const value = parseInt(DOM.betAmount.value);
  const min = parseInt(DOM.betSlider.min);
  const max = parseInt(DOM.betSlider.max);

  if (value >= min && value <= max) {
    DOM.betSlider.value = value;
  }
}

// Start turn timer
function startTurnTimer(seconds) {
  if (timer) {
    clearInterval(timer);
  }

  let remainingTime = seconds;
  updateTimer(remainingTime);

  timer = setInterval(() => {
    remainingTime--;
    updateTimer(remainingTime);

    if (remainingTime <= 0) {
      clearInterval(timer);
      timer = null;

      // Automatic fold if time runs out
      if (gameState && gameState.currentPlayer === currentUser.id) {
        sendGameAction("fold");
      }
    }
  }, 1000);
}

// Update timer
function updateTimer(seconds) {
  DOM.timer.textContent = `Remaining time: ${seconds} seconds`;

  if (seconds <= 10) {
    DOM.timer.style.backgroundColor = "#d32f2f";
  } else {
    DOM.timer.style.backgroundColor = "#8ab4f8";
  }
}

// Show game message
function showGameMessage(message) {
  DOM.gameMessage.textContent = message;

  // Animation effect
  DOM.gameMessage.style.animation = "none";
  setTimeout(() => {
    DOM.gameMessage.style.animation = "fadeInOut 2s";
  }, 10);
}

// Initialize game table
function resetGameTable() {
  DOM.communityCards.innerHTML = "";
  DOM.players.innerHTML = "";
  DOM.potAmount.textContent = "Pot: ₩0";
  DOM.timer.textContent = "Remaining time: 30 seconds";
  DOM.gameMessage.textContent = "Game is preparing...";
}

// Leave room
function leaveRoom() {
  if (!currentRoom) return;

  fetch("php/leaveRoom.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      userId: currentUser.id,
      roomId: currentRoom.id,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (socket) {
          socket.close();
          socket = null;
        }

        currentRoom = null;
        gameState = null;
        DOM.lobby.classList.remove("hidden");
        DOM.gameRoom.classList.add("hidden");
        loadRoomList();
      } else {
        alert(data.message || "Failed to leave room.");
      }
    })
    .catch((error) => {
      console.error("Room leave error:", error);
      alert("An error occurred while leaving room.");
    });
}
