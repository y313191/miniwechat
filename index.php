<?php
declare(strict_types=1);
error_log("index.php reached.");
session_start();

// 引入配置文件
require_once 'config/config.php';

// 引入功能模块
require_once 'includes/users.php';
require_once 'includes/messages.php';
require_once 'includes/uploads.php';
require_once 'includes/notifications.php';
require_once 'includes/request_handler.php';
require_once 'includes/emojis.php';  // 添加表情包模块
require_once 'includes/announcements.php'; // 引入公告模块

// 全局更新在线状态
if(!empty($_SESSION['username'])) {
    updateOnlineUsers();
}

// 请求处理
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $requestData = [];

    // 根据 Content-Type 解析请求体
    if (strpos($contentType, 'application/json') !== false) {
        $rawInput = file_get_contents('php://input');
        $requestData = json_decode($rawInput, true);
    } else {
        // 处理传统的表单提交
        $requestData = $_POST;
    }

    // 从解析出的数据中获取 action
    $action = $requestData['action'] ?? '';

    // 根据 action 调用相应的处理函数
    switch ($action) {
        case 'upload_emoji':
            header('Content-Type: application/json');
            if(empty($_SESSION['username'])) {
                echo json_encode(['success' => false, 'message' => '请先登录']);
                exit;
            }
            if(empty($_FILES['emoji'])) {
                echo json_encode(['success' => false, 'message' => '未选择文件']);
                exit;
            }
            echo json_encode(uploadEmoji($_FILES['emoji']));
            exit;
            
        case 'update_display_name':
            handleUpdateDisplayName();
            exit;
            
        case 'save_announcement':
            require_once 'includes/announcements.php';
            handleSaveAnnouncement();
            exit;
            
        default:
            handlePostRequest();
            exit;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(isset($_GET['action'])) {
        handleGetRequest();
        exit;
    }
    if(isset($_GET['get_online'])) {
        header('Content-Type: application/json');
        echo json_encode(['count' => countOnlineUsers()]);
        exit;
    }
}

// 获取当前背景
$currentBackground = getCurrentBackground();

// 获取系统设置
$settings = getSettings();

// 获取当前用户信息
$currentUser = null;
if(!empty($_SESSION['username'])) {
    $currentUser = getUserInfo($_SESSION['username']);
}

// 获取用户头像
$userAvatar = !empty($_SESSION['avatar']) ? getUserAvatarUrl($_SESSION['avatar']) : getUserAvatarUrl(DEFAULT_AVATAR);

// 获取公告内容
$announcementContent = loadAnnouncement();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['site_title']); ?></title>
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="public/css/chat.css?v=<?php echo time(); ?>">
    <style>
    body {
        background-image: url('<?= $currentBackground ?>');
    }
    /* 登录页面样式 */
    .login-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .login-container {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .form-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #eee;
    }

    .form-tab {
        flex: 1;
        text-align: center;
        padding: 10px;
        cursor: pointer;
        color: #666;
        transition: all 0.3s;
    }

    .form-tab.active {
        color: #007bff;
        border-bottom: 2px solid #007bff;
    }

    .form-content {
        display: none;
    }

    .form-content.active {
        display: block;
    }

    .login-title {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
        font-size: 24px;
    }

    .login-input {
        width: 100%;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        box-sizing: border-box;
    }

    .login-button {
        width: 100%;
        padding: 12px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        cursor: pointer;
        transition: background 0.3s;
    }

    .login-button:hover {
        background: #0056b3;
    }

    .login-error {
        color: #dc3545;
        margin-bottom: 15px;
        text-align: center;
        font-size: 14px;
    }

    .login-footer {
        text-align: center;
        margin-top: 20px;
        color: #666;
        font-size: 14px;
    }

    .login-footer a {
        color: #007bff;
        text-decoration: none;
    }

    .login-footer a:hover {
        text-decoration: underline;
    }

    /* 其他现有样式 */
    .emoji-tabs {
        display: flex;
        border-bottom: 1px solid #ddd;
        margin-bottom: 10px;
    }

    .emoji-tab {
        padding: 8px 16px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
    }

    .emoji-tab.active {
        border-bottom-color: #007bff;
        color: #007bff;
    }

    .custom-emoji-upload {
        padding: 10px;
        text-align: center;
    }

    .upload-emoji-btn {
        background: #007bff;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    .custom-emoji-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        padding: 10px;
    }

    .custom-emoji {
        width: 100%;
        aspect-ratio: 1;
        cursor: pointer;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }

    .custom-emoji img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    </style>
</head>
<body>
    <div class="chat-container">
        <?php if(!empty($_SESSION['username'])): ?>
        <!-- 顶部功能栏 -->
        <div class="header-bar">
            <div class="user-badge">
                <div class="user-avatar" style="background-image: url('<?= $userAvatar ?>')">
                    <div class="avatar-overlay">
                        <span class="avatar-icon">👤</span>
                    </div>
                </div>
                <span class="user-name-display"><?= htmlspecialchars($_SESSION['display_name']) ?></span>
            </div>
            
            <div class="header-controls">
                <button class="header-btn dark-mode-btn" id="darkModeBtn">🌙</button>
                <button class="header-btn announcement-btn" id="announcementBtn">公告</button>
                <button class="header-btn expand-btn" id="expandBtn">拓展</button>
                <div class="expand-menu" id="expandMenu">
                    <button class="expand-menu-item call-btn" id="callBtn">通话</button>
                    <button class="expand-menu-item video-btn" id="videoBtn">视频</button>
                    <div class="expand-menu-item ephemeral-btn-menu">
                        阅后即焚
                        <label class="toggle-switch">
                            <input type="checkbox" id="ephemeralMenuBtn">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <button class="header-btn settings-btn" onclick="toggleDropdown()">设置</button>
            </div>
        </div>
        
        <!-- 下拉菜单 -->
        <div class="dropdown-content" id="dropdownMenu">
            <h3 style="color: white; margin: 0 0 10px 0; font-size: 14px;">聊天室背景</h3>
            <div id="bgPreview" class="bg-preview" style="background-image: url('<?= $currentBackground ?>');"></div>
            <label for="background" class="bg-file-label">更换背景图片</label>
            <input type="file" id="background" class="bg-input" accept="image/*">
            <button class="bg-reset-btn" onclick="resetBackground()">恢复默认背景</button>
            
            <div class="dropdown-divider"></div>
            
            <h3 style="color: white; margin: 10px 0; font-size: 14px;">通知推送</h3>
            <button class="push-btn" onclick="pushMessage('manager')">推送1</button>
            <button class="push-btn" onclick="pushMessage('tech')">推送2</button>
            
            <div class="dropdown-divider"></div>

            <!-- 添加进入后台管理的链接 -->
            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <a href="admin/index.php" class="dropdown-menu-item admin-link">进入后台管理</a>
            <div class="dropdown-divider"></div>
            <?php endif; ?>
            
            <button class="logout-btn" onclick="logout()">退出登录</button>
        </div>
        <?php endif; ?>
        
        <div class="chat-box" id="chatBox">
            <?=loadMessages()?>
            <div id="anchor"></div>
        </div>

        <div class="footer">
            <div>当前在线人数：<span id="onlineCount"><?= countOnlineUsers() ?></span></div>
            <div>YiST©｜<a target="_blank" href="http://www.beian.gov.cn/" style="display:inline-block;text-decoration:none;height:20px;line-height:20px;"><img src="https://w-flac.org.cn/upload/gongan.png" style="float:left;"/>备案号填写处</a></div>
        </div>
        
        <?php if(empty($_SESSION['username'])): ?>
        <!-- 登录/注册表单 -->
        <div class="login-overlay">
            <div class="login-container">
                <h2 class="login-title">欢迎来到聊天室</h2>
                
                <div class="form-tabs">
                    <div id="login-tab" class="form-tab active" onclick="switchForm('login')">登录</div>
                    <div id="register-tab" class="form-tab" onclick="switchForm('register')">注册</div>
                </div>
                
                <!-- 登录表单 -->
                <div id="login-form" class="form-content active">
                    <div id="login-error" class="login-error"></div>
                    <input type="text" id="login-username" class="login-input" placeholder="用户名" maxlength="20">
                    <input type="password" id="login-password" class="login-input" placeholder="密码">
                    <button class="login-button" onclick="login()">登录</button>
                    <div class="login-footer">
                        还没有账号？ <a href="javascript:void(0)" onclick="switchForm('register')">立即注册</a>
                    </div>
                </div>
                
                <!-- 注册表单 -->
                <div id="register-form" class="form-content">
                    <div id="register-error" class="login-error"></div>
                    <input type="text" id="register-username" class="login-input" placeholder="用户名 (字母、数字、下划线，3-20个字符)" maxlength="20">
                    <input type="password" id="register-password" class="login-input" placeholder="密码 (至少6个字符)">
                    <input type="password" id="register-confirm-password" class="login-input" placeholder="确认密码">
                    <input type="text" id="register-display-name" class="login-input" placeholder="昵称 (显示在聊天中)" maxlength="12">
                    <button class="login-button" onclick="register()">注册</button>
                    <div class="login-footer">
                        已有账号？ <a href="javascript:void(0)" onclick="switchForm('login')">立即登录</a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- 消息输入区域 -->
        <div class="input-area">
            <div class="emoji-btn" id="emojiBtn">😊</div>
            <div class="emoji-panel" id="emojiPanel">
                <div class="emoji-tabs">
                    <div class="emoji-tab active" data-tab="default">默认表情</div>
                    <div class="emoji-tab" data-tab="custom">自定义表情</div>
                </div>
                
                <div class="emoji-content" id="defaultEmojis">
                    <div class="emoji-container">
                        <span class="emoji">😊</span>
                        <span class="emoji">😂</span>
                        <span class="emoji">🤣</span>
                        <span class="emoji">😍</span>
                        <span class="emoji">😒</span>
                        <span class="emoji">😘</span>
                        <span class="emoji">🥰</span>
                        <span class="emoji">😎</span>
                        <span class="emoji">🤔</span>
                        <span class="emoji">😉</span>
                        <span class="emoji">😢</span>
                        <span class="emoji">😭</span>
                        <span class="emoji">😤</span>
                        <span class="emoji">😡</span>
                        <span class="emoji">🥳</span>
                        <span class="emoji">🤯</span>
                        <span class="emoji">👍</span>
                        <span class="emoji">👋</span>
                        <span class="emoji">💪</span>
                        <span class="emoji">🙏</span>
                        <span class="emoji">😴</span>
                        <span class="emoji">🤤</span>
                        <span class="emoji">😪</span>
                        <span class="emoji">😵</span>
                        <span class="emoji">🤢</span>
                        <span class="emoji">🤮</span>
                        <span class="emoji">🤧</span>
                        <span class="emoji">😷</span>
                        <span class="emoji">🤒</span>
                        <span class="emoji">🤕</span>
                        <span class="emoji">😇</span>
                        <span class="emoji">🤠</span>
                        <span class="emoji">🤡</span>
                        <span class="emoji">👻</span>
                        <span class="emoji">👽</span>
                        <span class="emoji">🤖</span>
                        <span class="emoji">😺</span>
                        <span class="emoji">😸</span>
                        <span class="emoji">😹</span>
                        <span class="emoji">😻</span>
                        <span class="emoji">😼</span>
                        <span class="emoji">😽</span>
                        <span class="emoji">🙀</span>
                        <span class="emoji">😿</span>
                        <span class="emoji">😾</span>
                        <span class="emoji">👋</span>
                        <span class="emoji">🤚</span>
                        <span class="emoji">🖐️</span>
                        <span class="emoji">✋</span>
                        <span class="emoji">🖖</span>
                        <span class="emoji">👌</span>
                        <span class="emoji">🤌</span>
                        <span class="emoji">🤏</span>
                        <span class="emoji">✌️</span>
                        <span class="emoji">🤞</span>
                        <span class="emoji">🤟</span>
                        <span class="emoji">🤘</span>
                        <span class="emoji">👈</span>
                        <span class="emoji">👉</span>
                        <span class="emoji">👆</span>
                        <span class="emoji">🖕</span>
                        <span class="emoji">👇</span>
                        <span class="emoji">☝️</span>
                        <span class="emoji">👊</span>
                        <span class="emoji">✊</span>
                        <span class="emoji">🤛</span>
                        <span class="emoji">🤜</span>
                        <span class="emoji">👏</span>
                        <span class="emoji">🙌</span>
                        <span class="emoji">👐</span>
                        <span class="emoji">🤲</span>
                        <span class="emoji">🤝</span>
                        <span class="emoji">🙏</span>
                        <span class="emoji">✍️</span>
                        <span class="emoji">💪</span>
                        <span class="emoji">🦾</span>
                        <span class="emoji">🦿</span>
                        <span class="emoji">🦵</span>
                        <span class="emoji">🦶</span>
                        <span class="emoji">👂</span>
                        <span class="emoji">🦻</span>
                        <span class="emoji">👃</span>
                        <span class="emoji">🧠</span>
                        <span class="emoji">🫀</span>
                        <span class="emoji">🫁</span>
                        <span class="emoji">🦷</span>
                        <span class="emoji">🦴</span>
                        <span class="emoji">👀</span>
                        <span class="emoji">👁️</span>
                        <span class="emoji">👅</span>
                        <span class="emoji">👄</span>
                        <span class="emoji">🐶</span>
                        <span class="emoji">🐱</span>
                        <span class="emoji">🐭</span>
                        <span class="emoji">🐹</span>
                        <span class="emoji">🐰</span>
                        <span class="emoji">🦊</span>
                        <span class="emoji">🐻</span>
                        <span class="emoji">🐼</span>
                        <span class="emoji">🐨</span>
                        <span class="emoji">🐯</span>
                        <span class="emoji">🦁</span>
                        <span class="emoji">🐮</span>
                        <span class="emoji">🐷</span>
                        <span class="emoji">🐸</span>
                        <span class="emoji">🐵</span>
                        <span class="emoji">🐔</span>
                        <span class="emoji">🐧</span>
                        <span class="emoji">🐦</span>
                        <span class="emoji">🐤</span>
                        <span class="emoji">🦆</span>
                        <span class="emoji">🦅</span>
                        <span class="emoji">🦉</span>
                        <span class="emoji">🦇</span>
                        <span class="emoji">🐺</span>
                        <span class="emoji">🐗</span>
                        <span class="emoji">🐴</span>
                        <span class="emoji">🦄</span>
                        <span class="emoji">🐝</span>
                        <span class="emoji">🐛</span>
                        <span class="emoji">🦋</span>
                        <span class="emoji">🐌</span>
                        <span class="emoji">🐞</span>
                        <span class="emoji">🐜</span>
                        <span class="emoji">🦗</span>
                        <span class="emoji">🕷️</span>
                        <span class="emoji">🕸️</span>
                        <span class="emoji">🦂</span>
                        <span class="emoji">🦟</span>
                        <span class="emoji">🦠</span>
                        <span class="emoji">🍎</span>
                        <span class="emoji">🍐</span>
                        <span class="emoji">🍊</span>
                        <span class="emoji">🍋</span>
                        <span class="emoji">🍌</span>
                        <span class="emoji">🍉</span>
                        <span class="emoji">🍇</span>
                        <span class="emoji">🍓</span>
                        <span class="emoji">🫐</span>
                        <span class="emoji">🍈</span>
                        <span class="emoji">🍒</span>
                        <span class="emoji">🍑</span>
                        <span class="emoji">🥭</span>
                        <span class="emoji">🍍</span>
                        <span class="emoji">🥥</span>
                        <span class="emoji">🥝</span>
                        <span class="emoji">🍅</span>
                        <span class="emoji">🍆</span>
                        <span class="emoji">🥑</span>
                        <span class="emoji">🥦</span>
                        <span class="emoji">🥬</span>
                        <span class="emoji">🥒</span>
                        <span class="emoji">🌶️</span>
                        <span class="emoji">🫑</span>
                        <span class="emoji">🌽</span>
                        <span class="emoji">🥕</span>
                        <span class="emoji">🫒</span>
                        <span class="emoji">🧄</span>
                        <span class="emoji">🧅</span>
                        <span class="emoji">🥔</span>
                        <span class="emoji">🍠</span>
                        <span class="emoji">🥐</span>
                        <span class="emoji">🥯</span>
                        <span class="emoji">🍞</span>
                        <span class="emoji">🥖</span>
                        <span class="emoji">🥨</span>
                        <span class="emoji">🧀</span>
                        <span class="emoji">🥚</span>
                        <span class="emoji">🍳</span>
                        <span class="emoji">🧈</span>
                        <span class="emoji">🥞</span>
                        <span class="emoji">🧇</span>
                        <span class="emoji">🥓</span>
                        <span class="emoji">🥩</span>
                        <span class="emoji">🍗</span>
                        <span class="emoji">🍖</span>
                        <span class="emoji">🦴</span>
                        <span class="emoji">🌭</span>
                        <span class="emoji">🍔</span>
                        <span class="emoji">🍟</span>
                        <span class="emoji">🍕</span>
                        <span class="emoji">🫓</span>
                        <span class="emoji">🥪</span>
                        <span class="emoji">🥙</span>
                        <span class="emoji">🧆</span>
                        <span class="emoji">🌮</span>
                        <span class="emoji">🌯</span>
                        <span class="emoji">🫔</span>
                        <span class="emoji">🥗</span>
                        <span class="emoji">🥘</span>
                        <span class="emoji">🫕</span>
                        <span class="emoji">🥫</span>
                        <span class="emoji">🍝</span>
                        <span class="emoji">🍜</span>
                        <span class="emoji">🍲</span>
                        <span class="emoji">🍛</span>
                        <span class="emoji">🍣</span>
                        <span class="emoji">🍱</span>
                        <span class="emoji">🥟</span>
                        <span class="emoji">🫖</span>
                        <span class="emoji">☕</span>
                        <span class="emoji">🍵</span>
                        <span class="emoji">🧃</span>
                        <span class="emoji">🥤</span>
                        <span class="emoji">🧋</span>
                        <span class="emoji">🍶</span>
                        <span class="emoji">🍺</span>
                        <span class="emoji">🍷</span>
                        <span class="emoji">🥂</span>
                        <span class="emoji">🥃</span>
                        <span class="emoji">🍸</span>
                        <span class="emoji">🍹</span>
                        <span class="emoji">🧉</span>
                        <span class="emoji">🍾</span>
                        <span class="emoji">🧊</span>
                        <span class="emoji">🥄</span>
                        <span class="emoji">🍴</span>
                        <span class="emoji">🍽️</span>
                        <span class="emoji">🥢</span>
                        <span class="emoji">🧂</span>
                    </div>
                </div>
                
                <div class="emoji-content" id="customEmojis" style="display: none;">
                    <div class="custom-emoji-upload">
                        <input type="file" id="emojiUpload" accept="image/*" style="display: none;">
                        <button class="upload-emoji-btn" onclick="document.getElementById('emojiUpload').click()">上传表情</button>
                    </div>
                    <div class="custom-emoji-container">
                        <?php
                        $customEmojis = getAllEmojis();
                        foreach($customEmojis as $emoji) {
                            echo '<div class="custom-emoji" data-url="' . htmlspecialchars($emoji['url']) . '">';
                            echo '<img src="' . htmlspecialchars($emoji['url']) . '" alt="自定义表情">';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <input type="text" id="message" placeholder="我是输入框🤗" maxlength="130">
            <div class="toolbar">
                <button class="record-btn" id="recordBtn" onclick="toggleRecording()">语音</button>
                <input type="file" id="image" hidden accept="image/*">
                <button onclick="document.getElementById('image').click()">图片</button>
                <button onclick="sendMsg()">发送</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 视频链接输入对话框 -->
    <div id="videoDialog" class="video-dialog">
        <div class="video-dialog-content">
            <div class="video-dialog-header">
                <h3>添加视频链接</h3>
                <span id="closeVideoDialog" class="close-video-dialog">&times;</span>
            </div>
            <form id="videoForm">
                <input type="text" id="videoLink" placeholder="请输入视频链接（bilibili、优酷或直接.mp4等视频链接）" class="video-link-input">
                <div class="video-dialog-buttons">
                    <button type="button" id="cancelVideoBtn" onclick="hideVideoDialog()">取消</button>
                    <button type="submit" id="submitVideoBtn">添加</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 语音通话对话框 -->
    <div id="callDialog" class="video-dialog">
        <div class="video-dialog-content">
            <div class="video-dialog-header">
                <h3>创建语音通话</h3>
                <span id="closeCallDialog" class="close-video-dialog">&times;</span>
            </div>
            <form id="callForm">
                <div class="call-room-name">
                    <input type="text" id="callRoomName" placeholder="房间名称(可选)" class="video-link-input">
                </div>
                <div class="video-dialog-buttons">
                    <button type="button" id="cancelCallBtn">取消</button>
                    <button type="submit" id="createCallBtn">创建通话</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 公告容器 -->
    <div id="announcement-container" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>公告</h2>
            <textarea id="announcement-content" placeholder="在此输入公告内容..."><?= htmlspecialchars($announcementContent) ?></textarea>
            <button id="save-announcement">保存公告</button>
        </div>
    </div>

    <div id="modal-overlay" class="overlay"></div>

    <script>
    // 传递当前用户信息和最后消息ID到JS
    const currentUser = "<?= trim($_SESSION['display_name'] ?? '') ?>";
    const initialLastMsgId = <?= !empty($_SESSION['username']) ? microtime(true)*10000 : 0 ?>;
    </script>
    <script src="public/js/chat.js"></script>
    <script src="public/js/profile.js"></script>
    <script>
    // 在现有的JavaScript代码中添加以下内容
    document.addEventListener('DOMContentLoaded', function() {
        // 表情面板标签切换
        const emojiTabs = document.querySelectorAll('.emoji-tab');
        emojiTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                emojiTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const tabName = this.dataset.tab;
                document.getElementById('defaultEmojis').style.display = tabName === 'default' ? 'block' : 'none';
                document.getElementById('customEmojis').style.display = tabName === 'custom' ? 'block' : 'none';
            });
        });
        
        // 自定义表情上传
        const emojiUpload = document.getElementById('emojiUpload');
        emojiUpload.addEventListener('change', function() {
            if (this.files.length === 0) return;
            
            const formData = new FormData();
            formData.append('emoji', this.files[0]);
            formData.append('action', 'upload_emoji');
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const container = document.querySelector('.custom-emoji-container');
                    const emojiDiv = document.createElement('div');
                    emojiDiv.className = 'custom-emoji';
                    emojiDiv.dataset.url = data.url;
                    emojiDiv.innerHTML = `<img src="${data.url}" alt="自定义表情">`;
                    container.appendChild(emojiDiv);
                    
                    // 清空文件输入
                    this.value = '';
                } else {
                    alert(data.message || '上传失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('上传失败');
            });
        });
        
        // 自定义表情点击事件
        document.querySelector('.custom-emoji-container').addEventListener('click', function(e) {
            const emoji = e.target.closest('.custom-emoji');
            if (emoji) {
                const messageInput = document.getElementById('message');
                messageInput.value += `[emoji]${emoji.dataset.url}[/emoji]`;
            }
        });
    });
    </script>
</body>
</html> 
