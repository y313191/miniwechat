<?php
declare(strict_types=1);

// 系统路径配置
const MSG_FILE   = __DIR__ . '/../data/MSG.txt';
const ONLINE_FILE = __DIR__ . '/../data/online.dat';
const UPLOAD_DIR = __DIR__ . '/../uploads/';
const VOICE_DIR = __DIR__ . '/../voices/';
const AVATAR_DIR = __DIR__ . '/../avatars/'; // 头像存储目录
const BACKGROUND_FILE = __DIR__ . '/../data/background.dat'; // 背景图片配置文件
const USERS_FILE = __DIR__ . '/../data/users.dat'; // 用户数据文件
const SETTINGS_FILE = __DIR__ . '/../data/settings.dat'; // 系统设置文件

// 上传配置
const MAX_SIZE   = 20 * 1024 * 1024;
const ALLOWED_MIME = [
    'image/jpeg' => 'jpg', 
    'image/pjpeg' => 'jpg', 
    'image/png' => 'png', 
    'image/gif' => 'gif', 
    'audio/wav' => 'wav', 
    'audio/mp3' => 'mp3', 
    'audio/mpeg' => 'mp3'
];

// 默认背景图片
const DEFAULT_BACKGROUND = 'uploads/default.jpg';

// 默认头像
const DEFAULT_AVATAR = 'default.png';

// 默认系统设置
const DEFAULT_SETTINGS = [
    'allow_registration' => true, // 是否允许用户注册
    'password_strength' => 'medium', // 密码强度要求：low, medium, high
    'password_min_length' => 6, // 密码最小长度
    'password_require_special' => false, // 是否要求特殊字符
    'password_require_number' => true, // 是否要求数字
    'password_require_uppercase' => false, // 是否要求大写字母
    // 网站基本设置
    'site_title' => '聊天室', // 网站标题
    'site_description' => '一个简单的在线聊天室', // 网站描述
    'site_favicon' => 'favicon.ico', // 网站图标
    'site_background' => DEFAULT_BACKGROUND, // 首页背景图片
    'site_announcement' => '', // 网站公告
];

// 推送配置
const PUSH_TOKENS = [
    'manager' => '5c61b6722ac142a38a05ad9843731c3e',
    'tech'    => 'fdc279cb4c6c4b43be127f73a4a6984d'
];

// 默认用户配置
const DEFAULT_USERS = [
    'admin' => [
        'password' => '$2y$10$7Hyt4Kfej7bGQHp5VTLDh.yFSXXHxrOQHRuhJfvP0Sl3eD9Z3Ffay', // admin123
        'display_name' => '管理员',
        'role' => 'admin',
        'avatar' => DEFAULT_AVATAR,
        'created_at' => '2023-01-01 00:00:00'
    ]
];

// 时区设置
date_default_timezone_set('PRC');

// 计算上传路径
$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$scriptPath = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$basePath = str_replace($docRoot, '', $scriptPath);
define('UPLOAD_URL', $basePath.'/uploads/');
define('VOICE_URL', $basePath.'/voices/');
define('AVATAR_URL', $basePath.'/avatars/');

// 初始化环境
function initEnvironment(): void {
    if(!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if(!is_dir(VOICE_DIR)) mkdir(VOICE_DIR, 0755, true);
    if(!is_dir(AVATAR_DIR)) mkdir(AVATAR_DIR, 0755, true);
    
    // 复制默认头像到头像目录
    if(!file_exists(AVATAR_DIR . DEFAULT_AVATAR)) {
        copy(__DIR__ . '/../public/img/' . DEFAULT_AVATAR, AVATAR_DIR . DEFAULT_AVATAR);
    }
    
    if(!file_exists(__DIR__ . '/../data')) {
        mkdir(__DIR__ . '/../data', 0755, true);
    }
    
    if(!file_exists(ONLINE_FILE)) {
        file_put_contents(ONLINE_FILE, serialize([]));
    }
    
    if(!file_exists(BACKGROUND_FILE)) {
        file_put_contents(BACKGROUND_FILE, DEFAULT_BACKGROUND);
    }
    
    // 初始化用户数据
    if(!file_exists(USERS_FILE)) {
        file_put_contents(USERS_FILE, serialize(DEFAULT_USERS), LOCK_EX);
    } else {
        // 只在文件损坏时进行修复
        $fp = fopen(USERS_FILE, 'r+');
        if ($fp && flock($fp, LOCK_EX)) {
            try {
                $content = stream_get_contents($fp);
                $users = unserialize($content);
                
                // 只在反序列化失败时使用默认数据
                if ($users === false) {
                    error_log("用户数据文件损坏，使用默认数据");
                    $users = DEFAULT_USERS;
                    
                    // 保存修复后的数据
                    rewind($fp);
                    ftruncate($fp, 0);
                    fwrite($fp, serialize($users));
                }
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        } else {
            error_log("无法获取用户数据文件的锁定");
        }
    }

    if(!file_exists(SETTINGS_FILE)) {
        file_put_contents(SETTINGS_FILE, serialize(DEFAULT_SETTINGS));
    }
}

// 获取系统设置
function getSettings(): array {
    if(file_exists(SETTINGS_FILE)) {
        return unserialize(file_get_contents(SETTINGS_FILE));
    }
    return DEFAULT_SETTINGS;
}

// 更新系统设置
function updateSettings(array $settings): bool {
    try {
        // 确保所有必需的设置项都存在
        $settings = array_merge(DEFAULT_SETTINGS, $settings);
        
        // 使用文件锁定写入
        $result = file_put_contents(SETTINGS_FILE, serialize($settings), LOCK_EX);
        
        // 清除文件缓存
        clearstatcache(true, SETTINGS_FILE);
        
        return $result !== false;
    } catch (Exception $e) {
        error_log("更新系统设置失败: " . $e->getMessage());
        return false;
    }
}

// 检查密码强度
function checkPasswordStrength(string $password): array {
    $settings = getSettings();
    $errors = [];
    
    if(strlen($password) < $settings['password_min_length']) {
        $errors[] = "密码长度不能少于 {$settings['password_min_length']} 个字符";
    }
    
    if($settings['password_require_number'] && !preg_match('/[0-9]/', $password)) {
        $errors[] = "密码必须包含数字";
    }
    
    if($settings['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "密码必须包含大写字母";
    }
    
    if($settings['password_require_special'] && !preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "密码必须包含特殊字符";
    }
    
    return $errors;
}

// 获取当前背景图片URL
function getCurrentBackground(): string {
    if(file_exists(BACKGROUND_FILE)) {
        return file_get_contents(BACKGROUND_FILE);
    }
    return DEFAULT_BACKGROUND;
}

// 更新背景图片
function updateBackground(string $url): bool {
    return file_put_contents(BACKGROUND_FILE, $url) !== false;
}

// 重置背景图片
function resetBackgroundToDefault(): bool {
    return file_put_contents(BACKGROUND_FILE, DEFAULT_BACKGROUND) !== false;
}

// 获取用户头像URL
function getUserAvatarUrl(string $avatarFilename): string {
    if(empty($avatarFilename) || !file_exists(AVATAR_DIR . $avatarFilename)) {
        return AVATAR_URL . DEFAULT_AVATAR;
    }
    return AVATAR_URL . $avatarFilename;
}

// 初始化环境
initEnvironment(); 