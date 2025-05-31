<?php
declare(strict_types=1);

/**
 * 文件上传处理模块
 */

/**
 * 处理图片上传
 */
function handleImageUpload(string $messageType = 'img'): void {
    $file = $_FILES['image'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if(!isset(ALLOWED_MIME[$mime])) {
        exit(json_encode(['error' => "不支持的文件类型：".$mime]));
    }
    
    if($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit(json_encode(['error' => '上传错误: '.$file['error']]));
    }
    
    if($file['size'] > MAX_SIZE) {
        http_response_code(400);
        exit(json_encode(['error' => '文件大小超过限制']));
    }
    
    $ext = ALLOWED_MIME[$mime];
    $filename = uniqid().'.'.$ext;
    $dest = UPLOAD_DIR.$filename;
    
    if(move_uploaded_file($file['tmp_name'], $dest)) {
        // 保存图片消息 - 如果是阅后即焚模式，图片类型仍为img，但添加ephemeral标记
        if ($messageType === 'ephemeral') {
            saveMessage(UPLOAD_URL.$filename, 'img', true);
        } else {
            saveMessage(UPLOAD_URL.$filename, 'img');
        }
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        exit(json_encode(['error' => '文件保存失败']));
    }
}

/**
 * 处理语音上传
 */
function handleVoiceUpload(bool $isEphemeral = false): void {
    $file = $_FILES['voice'];
    if($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit(json_encode(['error' => '上传错误: '.$file['error']]));
    }
    
    if($file['size'] > MAX_SIZE) {
        http_response_code(400);
        exit(json_encode(['error' => '文件大小超过限制']));
    }
    
    // 确保文件夹存在并设置正确的权限
    if (!is_dir(VOICE_DIR)) {
        mkdir(VOICE_DIR, 0755, true);
    }
    
    $filename = uniqid().'.webm';
    $dest = VOICE_DIR.$filename;
    
    if(move_uploaded_file($file['tmp_name'], $dest)) {
        // 保存语音消息
        $duration = isset($_POST['duration']) ? floatval($_POST['duration']) : 0;
        $voiceUrl = VOICE_URL.$filename;
        
        // 如果是阅后即焚模式
        if ($isEphemeral) {
            saveMessage($voiceUrl.'|'.$duration, 'voice', true);
        } else {
            saveMessage($voiceUrl.'|'.$duration, 'voice');
        }
        
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        exit(json_encode(['error' => '文件保存失败']));
    }
}

/**
 * 处理背景图片上传
 */
function handleBackgroundUpload(): void {
    $file = $_FILES['background'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if(!isset(ALLOWED_MIME[$mime])) {
        exit(json_encode(['error' => "不支持的文件类型：".$mime]));
    }
    
    if($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        exit(json_encode(['error' => '上传错误: '.$file['error']]));
    }
    
    if($file['size'] > MAX_SIZE) {
        http_response_code(400);
        exit(json_encode(['error' => '文件大小超过限制']));
    }
    
    $ext = ALLOWED_MIME[$mime];
    $filename = 'background_'.uniqid().'.'.$ext;
    $dest = UPLOAD_DIR.$filename;
    
    if(move_uploaded_file($file['tmp_name'], $dest)) {
        // 保存背景图片URL到服务器配置
        $bgUrl = UPLOAD_URL.$filename;
        if(updateBackground($bgUrl)) {
            // 通知所有用户背景已更改
            saveSystemMessage("{$_SESSION['display_name']} 更新了聊天室背景");
            
            // 返回背景图片的URL
            echo json_encode([
                'success' => true,
                'url' => $bgUrl
            ]);
        } else {
            http_response_code(500);
            exit(json_encode(['error' => '保存背景配置失败']));
        }
    } else {
        http_response_code(500);
        exit(json_encode(['error' => '文件保存失败']));
    }
}

/**
 * 处理视频链接
 */
function handleVideoLink(bool $isEphemeral = false): void {
    if(empty($_POST['video'])) {
        http_response_code(400);
        exit(json_encode(['error' => '未提供视频链接']));
    }
    
    $videoLink = trim($_POST['video']);
    
    // 验证视频链接
    if(!isValidVideoLink($videoLink)) {
        http_response_code(400);
        exit(json_encode(['error' => '无效的视频链接']));
    }
    
    // 保存视频链接消息
    if ($isEphemeral) {
        saveMessage($videoLink, 'video', true);
    } else {
        saveMessage($videoLink, 'video');
    }
    
    // 返回成功响应
    http_response_code(200);
    exit(json_encode(['success' => true]));
}

/**
 * 验证视频链接是否有效
 */
function isValidVideoLink(string $url): bool {
    // 支持主流视频网站
    $videoPatterns = [
        'bilibili' => '/^https?:\/\/(www\.)?(bilibili\.com)\/.*$/i',
        'youku' => '/^https?:\/\/(www\.)?(youku\.com)\/.*$/i',
        'tencent' => '/^https?:\/\/(www\.)?(v\.qq\.com)\/.*$/i',
        'iqiyi' => '/^https?:\/\/(www\.)?(iqiyi\.com)\/.*$/i',
        'mgtv' => '/^https?:\/\/(www\.)?(mgtv\.com)\/.*$/i',
        'youtube' => '/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\/.*$/i',
        'vimeo' => '/^https?:\/\/(www\.)?(vimeo\.com)\/.*$/i'
    ];
    
    // 检查是否匹配视频网站
    foreach($videoPatterns as $pattern) {
        if(preg_match($pattern, $url)) {
            return true;
        }
    }
    
    // 检查是否是直接视频链接
    if(preg_match('/^https?:\/\/.*\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)(\?.*)?$/i', $url)) {
        return true;
    }
    
    return false;
} 