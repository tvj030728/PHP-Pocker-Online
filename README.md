# Multiplayer Poker Game / 멀티플레이어 포커 게임 / マルチプレイヤーポーカーゲーム / 多人在线扑克游戏

[English](#en) | [한국어](#ko) | [日本語](#jp) | [中文](#zh)

# Multiplayer Poker Game <a id="en"></a>

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

---

# 멀티플레이어 포커 게임 <a id="ko"></a>

HTML, CSS, JS, PHP로 구현된 멀티플레이어 포커 게임입니다. 여러 플레이어가 동시에 접속하여 실시간 포커 게임을 즐길 수 있습니다.

## 주요 기능

- 실시간 멀티플레이어 게임플레이
- 직관적인 사용자 인터페이스
- 텍사스 홀덤 포커 규칙 구현
- 로그인 및 방 생성 기능
- 각 게임 라운드별 베팅 시스템
- 실시간 채팅 기능
- 반응형 디자인

## 기술 스택

- 프론트엔드: HTML5, CSS3, JavaScript
- 백엔드: PHP
- 데이터베이스: MySQL
- 실시간 통신: WebSocket (Ratchet 라이브러리)

## 설치 가이드

### 요구 사항

- PHP 7.0 이상
- MySQL 5.7 이상
- Composer (WebSocket 서버 라이브러리 설치용)

### 단계별 설치 방법

1. 저장소 복제

```
git clone https://github.com/tvj030728/PHP-Pocker-Online.git
cd multiplayer-poker
```

2. 데이터베이스 설정

- MySQL 데이터베이스 생성
- `php/config.php` 파일에서 데이터베이스 연결 정보 수정

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'database_username');
define('DB_PASS', 'database_password');
define('DB_NAME', 'poker');
```

3. WebSocket 서버 설정 (선택 사항)

- Ratchet 라이브러리 설치

```
composer require cboden/ratchet
```

- WebSocket 서버 실행

```
php bin/poker-server.php
```

4. 웹 서버 설정

- 프로젝트 폴더를 Apache 또는 Nginx와 같은 웹 서버에 연결
- 또는 PHP 내장 웹 서버 실행

```
php -S localhost:8000
```

5. 웹 브라우저에서 접속

- `http://localhost:8000` 또는 설정한 URL을 통해 접속

## 게임 방법

1. 사용자 이름으로 로그인
2. 게임 로비에서 방을 생성하거나 기존 방에 참여
3. 방에서 게임 시작 (최소 2명의 플레이어 필요)
4. 텍사스 홀덤 포커 규칙에 따라 게임 진행

## 게임 규칙

- 텍사스 홀덤 포커 규칙을 따름
- 각 플레이어는 처음에 2장의 카드를 받음
- 라운드는 순서대로 진행: 프리플롭, 플롭, 턴, 리버
- 플레이어는 각 라운드 동안 체크, 콜, 베팅, 레이즈 또는 폴드할 수 있음
- 5장의 커뮤니티 카드와 2장의 개인 카드로 가장 높은 패를 만든 플레이어가 승리

## 중요 참고 사항

- 실제 서비스 배포를 위해 보안 설정을 강화해야 함
- 대규모 사용자를 위해 서버 인프라를 적절하게 구성해야 함
- WebSocket 서버는 별도로 실행해야 함

## 라이선스

이 프로젝트는 MIT 라이선스에 따라 라이선스가 부여됩니다. 자세한 내용은 LICENSE 파일을 참조하세요.

---

# マルチプレイヤーポーカーゲーム <a id="jp"></a>

HTML、CSS、JS、PHP で実装されたマルチプレイヤーポーカーゲームです。複数のプレイヤーが同時に接続してリアルタイムでポーカーゲームを楽しむことができます。

## 主な機能

- リアルタイムマルチプレイヤーゲームプレイ
- 直感的なユーザーインターフェース
- テキサスホールデムポーカールールの実装
- ログインとルーム作成機能
- 各ゲームラウンドのベッティングシステム
- リアルタイムチャット機能
- レスポンシブデザイン

## 技術スタック

- フロントエンド: HTML5、CSS3、JavaScript
- バックエンド: PHP
- データベース: MySQL
- リアルタイム通信: WebSocket (Ratchet ライブラリ)

## インストールガイド

### 要件

- PHP 7.0 以上
- MySQL 5.7 以上
- Composer (WebSocket サーバーライブラリのインストール用)

### ステップバイステップのインストール

1. リポジトリをクローン

```
git clone https://github.com/tvj030728/PHP-Pocker-Online.git
cd multiplayer-poker
```

2. データベース設定

- MySQL データベースを作成
- `php/config.php`ファイルでデータベース接続情報を変更

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'database_username');
define('DB_PASS', 'database_password');
define('DB_NAME', 'poker');
```

3. WebSocket サーバーのセットアップ（オプション）

- Ratchet ライブラリをインストール

```
composer require cboden/ratchet
```

- WebSocket サーバーを実行

```
php bin/poker-server.php
```

4. Web サーバーのセットアップ

- プロジェクトフォルダを Apache や Nginx などの Web サーバーに接続
- または、PHP の組み込み Web サーバーを実行

```
php -S localhost:8000
```

5. Web ブラウザからアクセス

- `http://localhost:8000`または設定した URL からアクセス

## プレイ方法

1. ユーザー名でログイン
2. ゲームロビーでルームを作成するか、既存のルームに参加
3. ルームでゲームを開始（最低 2 人のプレイヤーが必要）
4. テキサスホールデムポーカーのルールに従ってプレイ

## ゲームルール

- テキサスホールデムポーカーのルールに従う
- 各プレイヤーは最初に 2 枚のカードを受け取る
- ラウンドは順番に進行：プリフロップ、フロップ、ターン、リバー
- プレイヤーは各ラウンド中にチェック、コール、ベット、レイズ、またはフォールドができる
- 5 枚のコミュニティカードと 2 枚の個人カードで最高の役を作ったプレイヤーが勝利

## 重要な注意事項

- 実際のサービス展開のためにセキュリティ設定を強化すべき
- 大規模ユーザー向けにサーバーインフラを適切に構成すべき
- WebSocket サーバーは別途実行する必要がある

## ライセンス

このプロジェクトは MIT ライセンスの下でライセンスされています。詳細については LICENSE ファイルを参照してください。

---

# 多人在线扑克游戏 <a id="zh"></a>

一款使用 HTML、CSS、JS 和 PHP 实现的多人扑克游戏。多个玩家可以同时连接，享受实时扑克游戏体验。

## 主要特点

- 实时多人游戏体验
- 直观的用户界面
- 德州扑克规则实现
- 登录和房间创建功能
- 每轮游戏的下注系统
- 实时聊天功能
- 响应式设计

## 技术栈

- 前端：HTML5、CSS3、JavaScript
- 后端：PHP
- 数据库：MySQL
- 实时通信：WebSocket（Ratchet 库）

## 安装指南

### 要求

- PHP 7.0 或更高版本
- MySQL 5.7 或更高版本
- Composer（用于 WebSocket 服务器库安装）

### 逐步安装

1. 克隆仓库

```
git clone https://github.com/tvj030728/PHP-Pocker-Online.git
cd multiplayer-poker
```

2. 数据库配置

- 创建 MySQL 数据库
- 修改`php/config.php`文件中的数据库连接信息

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'database_username');
define('DB_PASS', 'database_password');
define('DB_NAME', 'poker');
```

3. WebSocket 服务器设置（可选）

- 安装 Ratchet 库

```
composer require cboden/ratchet
```

- 运行 WebSocket 服务器

```
php bin/poker-server.php
```

4. Web 服务器设置

- 将项目文件夹连接到 Apache 或 Nginx 等 Web 服务器
- 或者运行 PHP 内置 Web 服务器

```
php -S localhost:8000
```

5. 通过 Web 浏览器访问

- 通过`http://localhost:8000`或您配置的 URL 访问

## 如何玩

1. 使用用户名登录
2. 在游戏大厅创建房间或加入现有房间
3. 在房间中开始游戏（至少需要 2 名玩家）
4. 按照德州扑克规则进行游戏

## 游戏规则

- 遵循德州扑克规则
- 每位玩家最初获得 2 张牌
- 回合按顺序进行：翻牌前、翻牌、转牌、河牌
- 玩家在每个回合可以选择跟注、看牌、下注、加注或弃牌
- 用 5 张公共牌和 2 张个人牌组成最高牌型的玩家获胜

## 重要说明

- 实际服务部署时应加强安全设置
- 应为大规模用户适当配置服务器基础设施
- WebSocket 服务器必须单独运行

## 许可证

本项目根据 MIT 许可证授权。详情请参阅 LICENSE 文件。
