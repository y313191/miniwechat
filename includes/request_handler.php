<?php
declare(strict_types=1);

/**
 * 请求处理模块
 */

/**
 * 处理POST请求
 */
function handlePostRequest(): void {
    static $processed = false;
    if($processed) return;
    $processed = true;

    if(isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'login':
                handleLogin();
                return;
            case 'register':
                handleRegister();
                return;
            case 'logout':
                handleLogout();
                return;
            case 'withdraw':
                handleWithdrawMessage();
                return;
            case 'send_message':
                handleCustomMessage();
                return;
        }
    }
    
    if(empty($_SESSION['username'])) {
        http_response_code(403);
        exit;
    }

    if(isset($_POST['push_action'])) {
        handlePushMessage();
        return;
    }
    
    if(isset($_POST['reset_background']) && $_POST['reset_background'] === 'true') {
        handleResetBackground();
        return;
    }

    if(isset($_FILES['avatar'])) {
        handleAvatarUpload();
    } elseif(isset($_FILES['background'])) {
        handleBackgroundUpload();
    } elseif(isset($_FILES['voice'])) {
        $isEphemeral = isset($_POST['message_type']) && $_POST['message_type'] === 'ephemeral';
        handleVoiceUpload($isEphemeral);
    } elseif(isset($_FILES['image'])) {
        // 检查是否是阅后即焚消息
        $messageType = isset($_POST['message_type']) && $_POST['message_type'] === 'ephemeral' ? 'ephemeral' : 'img';
        handleImageUpload($messageType);
    } elseif(isset($_POST['video'])) {
        $isEphemeral = isset($_POST['message_type']) && $_POST['message_type'] === 'ephemeral';
        handleVideoLink($isEphemeral);
    } elseif(isset($_POST['msg'])) {
        // 检查是否是阅后即焚消息
        $messageType = isset($_POST['message_type']) && $_POST['message_type'] === 'ephemeral' ? 'ephemeral' : 'text';
        saveMessage($_POST['msg'], $messageType);
    }
}

/**
 * 处理重置背景
 */
function handleResetBackground(): void {
    if(resetBackgroundToDefault()) {
        saveSystemMessage("{$_SESSION['display_name']} 重置了聊天室背景");
        echo json_encode([
            'success' => true,
            'url' => DEFAULT_BACKGROUND
        ]);
    } else {
        http_response_code(500);
        exit(json_encode(['error' => '重置背景失败']));
    }
}

/**
 * 处理GET请求
 */
function handleGetRequest(): void {
    if(isset($_GET['action'])) {
        switch($_GET['action']) {
            case 'get':
                getNewMessages();
                break;
            case 'get_background':
                getCurrentBackgroundJSON();
                break;
            case 'get_user_avatar':
                getUserAvatarJson();
                break;
        }
    }
}

/**
 * 获取新消息的JSON响应
 */
function getNewMessages(): void {
    header('Content-Type: application/json');
    echo json_encode(getMessagesAfter((float)($_GET['last'] ?? 0)));
}

/**
 * 获取当前背景的JSON响应
 */
function getCurrentBackgroundJSON(): void {
    header('Content-Type: application/json');
    echo json_encode(['url' => getCurrentBackground()]);
}

/**
 * 获取用户头像JSON响应
 */
function getUserAvatarJson(): void {
    header('Content-Type: application/json');
    
    if(empty($_GET['username'])) {
        echo json_encode(['error' => '未提供用户名']);
        return;
    }
    
    $username = $_GET['username'];
    $user = getUserInfo($username);
    
    if(!$user) {
        echo json_encode(['error' => '用户不存在']);
        return;
    }
    
    $avatarUrl = getUserAvatarUrl($user['avatar'] ?? DEFAULT_AVATAR);
    echo json_encode(['avatar' => $avatarUrl]);
}

/**
 * 处理撤回消息请求
 */
function handleWithdrawMessage(): void {
    // 确保用户已登录
    if(empty($_SESSION['username'])) {
        http_response_code(403);
        exit(json_encode(['error' => '未登录']));
    }
    
    // 确保提供了消息ID
    if(empty($_POST['message_id'])) {
        http_response_code(400);
        exit(json_encode(['error' => '缺少消息ID']));
    }
    
    $messageId = $_POST['message_id'];
    
    // 尝试撤回消息
    $updatedMsg = withdrawMessage($messageId);
    if($updatedMsg) {
        echo json_encode([
            'success' => true,
            'message' => $updatedMsg
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => '撤回失败，可能是消息已被删除或没有权限撤回该消息']);
    }
}

/**
 * 处理自定义消息请求
 */
function handleCustomMessage(): void {
    // 确保用户已登录
    if(empty($_SESSION['username'])) {
        http_response_code(403);
        exit(json_encode(['error' => '未登录', 'status' => 'error']));
    }
    
    // 确保提供了消息内容
    if(empty($_POST['message'])) {
        http_response_code(400);
        exit(json_encode(['error' => '消息内容不能为空', 'status' => 'error']));
    }
    
    $message = $_POST['message'];
    $type = isset($_POST['type']) && $_POST['type'] === 'html' ? 'html' : 'text';
    
    try {
        // 保存消息到文件
        saveMessage($message, $type);
        
        // 返回成功状态
        echo json_encode([
            'status' => 'success',
            'message' => '消息已发送'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => '发送消息失败: ' . $e->getMessage()
        ]);
    }
} 