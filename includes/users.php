<?php
declare(strict_types=1);

/**
 * 用户管理模块
 */

/**
 * 获取所有用户
 */
function getAllUsers(): array {
    $users = [];
    $filepath = USERS_FILE;
    
    // 确保文件存在，如果不存在则创建空文件
    if (!file_exists($filepath)) {
        file_put_contents($filepath, serialize([]));
    }

    $fp = @fopen($filepath, 'r'); // 使用 @ 抑制警告以避免日志被淹没
    if ($fp) {
        if (flock($fp, LOCK_SH)) { // 获取共享锁
            $content = stream_get_contents($fp);
            $users = unserialize($content) ?: [];
            flock($fp, LOCK_UN); // 释放锁
        } else {
             error_log("Failed to acquire shared lock for users file: " . $filepath);
        }
        fclose($fp);
    } else {
        error_log("Failed to open users file for reading: " . $filepath);
    }
    
    error_log("getAllUsers: Loaded " . count($users) . " users from " . $filepath);
    
    return $users;
}

/**
 * 保存用户数据
 */
function saveUsers(array $users): bool {
    $maxRetries = 3;
    $retryCount = 0;
    $filepath = USERS_FILE;
    
    while ($retryCount < $maxRetries) {
        $fp = @fopen($filepath, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            try {
                // 先备份当前数据
                $backupContent = stream_get_contents($fp);
                $backupFile = $filepath . '.bak';
                file_put_contents($backupFile, $backupContent, LOCK_EX);
                
                // 写入新数据
                rewind($fp);
                ftruncate($fp, 0);
                $content_to_save = serialize($users);
                $result = fwrite($fp, $content_to_save) !== false;
                
                if ($result) {
                    // 写入成功，删除备份
                    @unlink($backupFile);
                    return true;
                } else {
                    // 写入失败，恢复备份
                    error_log("保存用户数据失败，尝试恢复备份");
                    rewind($fp);
                    ftruncate($fp, 0);
                    fwrite($fp, $backupContent);
                }
            } catch (Exception $e) {
                error_log("保存用户数据时发生错误: " . $e->getMessage());
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
        $retryCount++;
        if ($retryCount < $maxRetries) {
            usleep(100000); // 等待100ms后重试
        }
    }
    
    error_log("保存用户数据失败，已达到最大重试次数");
    return false;
}

/**
 * 获取用户信息
 */
function getUserInfo(string $username): ?array {
    $users = getAllUsers();
    return $users[$username] ?? null;
}

/**
 * 用户是否存在
 */
function userExists(string $username): bool {
    $users = getAllUsers();
    return isset($users[$username]);
}

/**
 * 验证用户凭据
 */
function verifyUserCredentials(string $username, string $password): bool {
    $user = getUserInfo($username);
    if(!$user) return false;
    
    return password_verify($password, $user['password']);
}

/**
 * 创建新用户
 */
function createUser(string $username, string $password, string $displayName): bool {
    if(userExists($username)) return false;
    
    $users = getAllUsers();
    $users[$username] = [
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'display_name' => $displayName,
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return saveUsers($users);
}

/**
 * 更新用户信息
 */
function updateUser(string $username, array $data): bool {
    if(!userExists($username)) return false;
    
    $users = getAllUsers();
    foreach($data as $key => $value) {
        if($key !== 'password' || !empty($value)) {
            $users[$username][$key] = $value;
        }
    }
    
    return saveUsers($users);
}

/**
 * 更新在线用户状态
 */
function updateOnlineUsers(): void {
    if(empty($_SESSION['username'])) return;

    $fp = fopen(ONLINE_FILE, 'c+');
    if(flock($fp, LOCK_EX)) {
        $data = unserialize(stream_get_contents($fp)) ?: [];
        $data[$_SESSION['username']] = [
            'id' => session_id(),
            'time' => time(),
            'display_name' => $_SESSION['display_name'] ?? $_SESSION['username']
        ];
        
        // 清理超时用户（30秒钟）
        foreach($data as $user => $info) {
            if(time() - $info['time'] > 30) unset($data[$user]);
        }
        
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, serialize($data));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

/**
 * 获取在线用户数量
 */
function countOnlineUsers(): int {
    $fp = fopen(ONLINE_FILE, 'r');
    if(flock($fp, LOCK_SH)) {
        $data = unserialize(stream_get_contents($fp)) ?: [];
        flock($fp, LOCK_UN);
        fclose($fp);
        return count($data);
    }
    fclose($fp);
    return 0;
}

/**
 * 处理用户登录
 */
function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '用户名和密码不能为空']);
        return;
    }
    
    if(verifyUserCredentials($username, $password)) {
        $user = getUserInfo($username);
        $_SESSION['username'] = $username;
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?? DEFAULT_AVATAR;
        
        updateOnlineUsers();
        saveSystemMessage("{$user['display_name']} 进入聊天室");
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => '用户名或密码错误']);
    }
}

/**
 * 处理用户注册
 */
function handleRegister(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    
    // 验证输入
    if(empty($username) || empty($password) || empty($displayName)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '所有字段都必须填写']);
        return;
    }
    
    if($password !== $confirmPassword) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '两次密码输入不一致']);
        return;
    }
    
    // 用户名格式验证
    if(!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '用户名只能包含字母、数字和下划线，长度3-20个字符']);
        return;
    }
    
    // 密码强度验证
    if(strlen($password) < 6) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '密码长度至少6个字符']);
        return;
    }
    
    // 昵称长度验证
    if(mb_strlen($displayName, 'UTF-8') > 12) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '昵称最长12个字符']);
        return;
    }
    
    // 检查用户是否已存在
    if(userExists($username)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => '用户名已存在']);
        return;
    }
    
    // 创建用户
    if(createUser($username, $password, $displayName)) {
        // 自动登录
        $user = getUserInfo($username);
        $_SESSION['username'] = $username;
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = $user['avatar'] ?? DEFAULT_AVATAR;
        
        updateOnlineUsers();
        saveSystemMessage("{$user['display_name']} 注册并进入聊天室");
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => '注册失败，请重试']);
    }
}

/**
 * 处理用户登出
 */
function handleLogout(): void {
    if(!empty($_SESSION['display_name'])) {
        $name = $_SESSION['display_name'];
        saveSystemMessage("{$name} 离开了聊天室");
    }
    
    session_destroy();
    header('Location: index.php');
    exit;
}

/**
 * 更新用户头像
 */
function updateUserAvatar(string $username, string $filename): bool {
    if(!userExists($username)) return false;
    
    $users = getAllUsers();
    
    // 删除旧头像（如果不是默认头像）
    if(!empty($users[$username]['avatar']) && $users[$username]['avatar'] !== DEFAULT_AVATAR) {
        $oldAvatar = AVATAR_DIR . $users[$username]['avatar'];
        if(file_exists($oldAvatar)) {
            @unlink($oldAvatar);
        }
    }
    
    // 更新头像
    $users[$username]['avatar'] = $filename;
    
    return saveUsers($users);
}

/**
 * 处理头像上传
 */
function handleAvatarUpload(): void {
    if(empty($_SESSION['username'])) {
        http_response_code(403);
        exit(json_encode(['error' => '请先登录']));
    }
    
    if(!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit(json_encode(['error' => '上传失败']));
    }
    
    $file = $_FILES['avatar'];
    
    // 验证文件类型
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if(!isset(ALLOWED_MIME[$mime])) {
        http_response_code(400);
        exit(json_encode(['error' => '不支持的文件类型: ' . $mime]));
    }
    
    // 验证文件大小（限制为5MB）
    if($file['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        exit(json_encode(['error' => '文件过大，请上传5MB以内的图片']));
    }
    
    // 保存头像
    $ext = ALLOWED_MIME[$mime];
    $filename = 'avatar_' . $_SESSION['username'] . '_' . uniqid() . '.' . $ext;
    $destPath = AVATAR_DIR . $filename;
    
    if(!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        exit(json_encode(['error' => '保存文件失败，请重试']));
    }
    
    // 更新用户头像记录
    if(updateUserAvatar($_SESSION['username'], $filename)) {
        // 更新会话中的头像
        $_SESSION['avatar'] = $filename;
        
        http_response_code(200);
        exit(json_encode([
            'success' => true,
            'avatar' => getUserAvatarUrl($filename)
        ]));
    } else {
        // 删除已上传的文件
        @unlink($destPath);
        http_response_code(500);
        exit(json_encode(['error' => '更新头像记录失败']));
    }
}

/**
 * 更新用户昵称
 */
function updateUserDisplayName(string $username, string $displayName): bool {
    error_log("updateUserDisplayName called for user: " . $username . " with display name: " . $displayName);
    
    if(!userExists($username)) {
        error_log("updateUserDisplayName: User does not exist: " . $username);
        return false;
    }
    
    $users = getAllUsers();
    
    // 验证昵称长度
    if(mb_strlen($displayName, 'UTF-8') > 12) {
        error_log("updateUserDisplayName: Display name too long: " . $displayName);
        return false;
    }
    
    // 检查用户是否存在于获取到的用户列表中
    if (!isset($users[$username])) {
         error_log("updateUserDisplayName: User not found in loaded users data: " . $username);
         return false;
    }

    // 更新昵称
    $users[$username]['display_name'] = $displayName;
    
    error_log("updateUserDisplayName: Attempting to save users after updating " . $username);
    return saveUsers($users);
}

/**
 * 处理更新昵称请求
 */
function handleUpdateDisplayName(): void {
    error_log("handleUpdateDisplayName called.");
    
    if(empty($_SESSION['username'])) {
        error_log("handleUpdateDisplayName: User not logged in.");
        http_response_code(403);
        exit(json_encode(['error' => '请先登录']));
    }
    
    $username = $_SESSION['username'];
    $data = json_decode(file_get_contents('php://input'), true);
    $displayName = trim($data['display_name'] ?? '');
    
    error_log("handleUpdateDisplayName: Logged in user: " . $username . ", Received display name: " . $displayName);
    
    if(empty($displayName)) {
        error_log("handleUpdateDisplayName: Display name is empty.");
        http_response_code(400);
        exit(json_encode(['error' => '昵称不能为空']));
    }
    
    if(updateUserDisplayName($username, $displayName)) {
        error_log("handleUpdateDisplayName: updateUserDisplayName returned true. Updating session and sending success.");
        // 更新会话中的昵称
        $_SESSION['display_name'] = $displayName;
        
        http_response_code(200);
        exit(json_encode(['success' => true]));
    } else {
        error_log("handleUpdateDisplayName: updateUserDisplayName returned false. Sending error.");
        // 如果 updateUserDisplayName 返回 false，说明更新失败（例如昵称长度不符合要求）
        http_response_code(400); // 或 500，取决于具体的失败原因，这里暂定 400 表示输入问题
        exit(json_encode(['error' => '更新昵称失败，请检查昵称长度或其他问题']));
    }
} 