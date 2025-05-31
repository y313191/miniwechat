<?php
declare(strict_types=1);

/**
 * 消息处理模块
 */

/**
 * 检查并处理消息文件大小
 */
function handleMessageFileSize(): void {
    if (!file_exists(MSG_FILE)) return;
    
    $fileSize = filesize(MSG_FILE);
    if ($fileSize < 50 * 1024) return; // 如果文件小于50KB，直接返回
    
    // 读取所有消息
    $messages = [];
    $lines = file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($msg = json_decode($line, true)) {
            $messages[] = $msg;
        }
    }
    
    // 按时间排序
    usort($messages, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    // 确保data目录存在
    $dataDir = dirname(MSG_FILE);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    
    // 将所有消息移动到data/MSGEND.TXT
    if (!empty($messages)) {
        $msgendFile = $dataDir . '/MSGEND.TXT';
        $fp = fopen($msgendFile, 'a+');
        flock($fp, LOCK_EX);
        foreach ($messages as $msg) {
            fwrite($fp, json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    // 清空MSG_FILE文件
    $fp = fopen(MSG_FILE, 'w');
    flock($fp, LOCK_EX);
    fwrite($fp, ''); // 清空文件内容
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * 保存普通消息
 */
function saveMessage(string $content, string $type = 'text', bool $isEphemeral = false): void {
    // 验证消息类型
    $validTypes = ['text', 'img', 'video', 'voice', 'html', 'ephemeral'];
    $type = in_array($type, $validTypes) ? $type : 'text';
    
    $message = [
        'id' => (string)(microtime(true)*10000),
        'time' => date('H:i'),
        'name' => trim($_SESSION['display_name'] ?? $_SESSION['username']),
        'username' => $_SESSION['username'],
        'type' => $type,
        'content' => $content,
        'reply_to' => $_POST['reply_to'] ?? null,
        'read_by' => [],
        'expiry_time' => null
    ];
    
    // 如果是阅后即焚消息，添加标记
    if($type === 'ephemeral' || $isEphemeral) {
        $message['is_ephemeral'] = true;
    }
    
    // 使用缓存机制，减少文件写入
    static $messageCache = [];
    $messageCache[] = $message;
    
    // 当缓存达到一定大小时，批量写入文件
    if (count($messageCache) >= 10) {
        $fp = fopen(MSG_FILE, 'a+');
        flock($fp, LOCK_EX);
        foreach ($messageCache as $msg) {
            fwrite($fp, json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        $messageCache = []; // 清空缓存
        
        // 检查文件大小并处理
        handleMessageFileSize();
    }
    
    // 确保在函数结束时，如果缓存中还有未写入的消息，就将它们写入文件
    if (!empty($messageCache)) {
        $fp = fopen(MSG_FILE, 'a+');
        flock($fp, LOCK_EX);
        foreach ($messageCache as $msg) {
            fwrite($fp, json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        $messageCache = []; // 清空缓存
        
        // 检查文件大小并处理
        handleMessageFileSize();
    }
    
    // 如果是通话邀请，记录日志
    if ($type === 'html' && strpos($content, '语音通话邀请') !== false) {
        error_log('保存了通话邀请消息: ' . substr($content, 0, 50) . '...');
    }
}

/**
 * 保存系统消息
 */
function saveSystemMessage(string $content): void {
    $message = [
        'id' => (string)(microtime(true)*10000),
        'time' => date('H:i'),
        'name' => '系统',
        'type' => 'sys',
        'content' => $content
    ];
    
    $fp = fopen(MSG_FILE, 'a+');
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode($message, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * 加载消息列表
 */
function loadMessages(): string {
    if(!file_exists(MSG_FILE)) return '';
    
    $messages = [];
    $lines = file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // 获取所有消息
    foreach($lines as $line) {
        if($msg = json_decode($line, true)) {
            $messages[] = formatMessage($msg);
        }
    }
    
    return implode('', $messages);
}

/**
 * 格式化消息输出HTML
 */
function formatMessage(array $msg): string {
    $isCurrentUser = strtolower(trim($msg['name'])) === strtolower(trim($_SESSION['display_name'] ?? ''));
    $msgClass = match(true) {
        $msg['type'] === 'sys' => 'sys-msg',
        $isCurrentUser => 'my-msg',
        default => 'other-msg'
    };
    
    // 如果是回调消息，添加特殊类
    if(isset($msg['is_callback']) && $msg['is_callback']) {
        $msgClass .= ' callback-msg';
    }
    
    // 如果是阅后即焚消息，添加特殊类
    $isEphemeralMsg = $msg['type'] === 'ephemeral' || (isset($msg['is_ephemeral']) && $msg['is_ephemeral'] === true);
    if($isEphemeralMsg) {
        $msgClass .= ' ephemeral-msg';
        if(isset($msg['is_destroyed']) && $msg['is_destroyed']) {
            $msgClass .= ' destroyed-msg';
        }
    }
    
    // 获取用户头像
    $avatar = isset($msg['username']) ? getUserAvatar($msg['username']) : DEFAULT_AVATAR;
    $avatarUrl = getUserAvatarUrl($avatar);
    
    $content = '';
    if ($msg['type'] === 'voice') {
        $parts = explode('|', $msg['content']);
        $url = $parts[0] ?? '';
        $duration = isset($parts[1]) ? floatval($parts[1]) : 0;
        
        $content = sprintf(
            '<div class="voice-message" onclick="playVoice(\'%s\', this)">
                <div class="voice-icon"></div>
                <div class="voice-waves">%s</div>
                <span class="voice-duration">%s</span>
            </div>',
            htmlspecialchars($url),
            str_repeat('<div class="voice-wave"></div>', 5),
            $duration > 0 ? ceil($duration).'″' : '点击播放'
        );
    } elseif ($msg['type'] === 'img') {
        // 检查是否是销毁后的阅后即焚图片
        if (isset($msg['is_destroyed']) && $msg['is_destroyed']) {
            $content = '<div class="destroyed-message-content">[图片已销毁]</div>';
        } else {
            $content = '<img src="'.htmlspecialchars($msg['content']).'" class="chat-image">';
        }
    } elseif ($msg['type'] === 'video') {
        // 视频消息格式化
        $videoLink = htmlspecialchars($msg['content']);
        $siteName = getVideoSiteName($videoLink);
        
        // 检查是否是直接视频链接
        if (isDirectVideoLink($videoLink)) {
            // 直接嵌入视频播放器
            $content = sprintf(
                '<div class="video-message direct-video">
                    <video controls preload="metadata" class="direct-video-player">
                        <source src="%s" type="video/mp4">
                        您的浏览器不支持视频播放。<a href="%s" target="_blank">点击下载</a>
                    </video>
                </div>',
                $videoLink,
                $videoLink
            );
        } else {
            // 外部视频网站链接
            $content = sprintf(
                '<div class="video-message">
                    <div class="video-link-wrapper">
                        <a href="%s" target="_blank" class="video-link">
                            <div class="video-icon">🎬</div>
                            <div class="video-title">点击观看%s视频</div>
                        </a>
                    </div>
                </div>',
                $videoLink,
                $siteName ? $siteName : ''
            );
        }
    } elseif ($msg['type'] === 'html') {
        // HTML类型消息，直接输出内容
        $content = $msg['content'];
    } else {
        $content = processMessageContent($msg['content']);
    }

    $replyHtml = '';
    if (!empty($msg['reply_to'])) {
        $replyMsg = findMessageById($msg['reply_to']);
        if ($replyMsg) {
            $replyContent = $replyMsg['type'] === 'img' ? '[图片]' : 
                          ($replyMsg['type'] === 'voice' ? '[语音]' : 
                          mb_substr(strip_tags($replyMsg['content']), 0, 20).'...');
            $replyHtml = sprintf(
                '<div class="reply-preview" data-reply-id="%s" onclick="scrollToMessage(\'%s\')">
                    <span class="reply-sender">回复 %s：</span>
                    <span class="reply-content">%s</span>
                </div>',
                $replyMsg['id'],
                $replyMsg['id'],
                htmlspecialchars($replyMsg['name']),
                htmlspecialchars($replyContent)
            );
        }
    }
    
    // 添加已读状态 HTML
    $readStatusHtml = '';
    if ($isCurrentUser && is_array($msg['read_by'] ?? null) && !empty($msg['read_by'])) {
        $readCount = count($msg['read_by']);
        $readStatusHtml = sprintf(
            '<div class="msg-read-status">已读 (%d)</div>',
            $readCount
        );
    }
    
    // 阅后即焚消息的计时器
    $timerHtml = '';
    if ($isEphemeralMsg && $isCurrentUser && isset($msg['expiry_time']) && $msg['expiry_time'] !== null) {
        $remainingTime = max(0, $msg['expiry_time'] - time());
        if ($remainingTime > 0 && !isset($msg['is_destroyed'])) {
            $timerHtml = sprintf(
                '<div class="msg-timer" data-expiry="%d">%ds</div>',
                $msg['expiry_time'],
                $remainingTime
            );
        }
    }
    
    // 阅后即焚图标
    $ephemeralIcon = $isEphemeralMsg ? '<div class="ephemeral-icon">🔥</div>' : '';
    
    // 系统消息没有头像
    if ($msg['type'] === 'sys') {
        return sprintf(
            '<div class="msg %s" data-id="%s">
                <div class="msg-body">%s</div>
            </div>',
            $msgClass,
            $msg['id'],
            $content
        );
    }
    
    // 回调消息标志
    $callbackBadge = isset($msg['is_callback']) && $msg['is_callback'] ? 
        '<span class="callback-badge">回调历史消息</span>' : '';
    
    // 普通消息
    return sprintf(
        '<div class="msg %s" data-id="%s" onclick="handleMessageClick(this, event)">
            <div class="msg-head">
                <div class="msg-avatar" style="background-image: url(\'%s\')"></div>
                <div class="msg-info">
                    <span class="sender">%s</span>
                    <span class="time-wrapper"><span class="time">%s</span>%s</span>
                    %s
                </div>
            </div>
            <div class="msg-body">%s%s%s</div>
            %s
            %s
        </div>',
        $msgClass,
        $msg['id'],
        $avatarUrl,
        htmlspecialchars($msg['name']),
        $msg['time'],
        $callbackBadge,
        $ephemeralIcon,
        $replyHtml,
        $content,
        $timerHtml,
        $readStatusHtml,
        // 只有当前用户的消息才显示操作菜单
        $isCurrentUser ? '<div class="msg-actions">
            <div class="msg-action-btn" onclick="toggleMsgMenu(event, \''.$msg['id'].'\')">⋮</div>
            <div class="msg-actions-menu" id="menu-'.$msg['id'].'">
                <div class="msg-menu-item danger" onclick="withdrawMessage(\''.$msg['id'].'\')">撤回消息</div>
            </div>
        </div>' : ''
    );
}

/**
 * 根据ID查找消息
 */
function findMessageById(string $id): ?array {
    if(!file_exists(MSG_FILE)) return null;
    foreach(file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if($msg = json_decode($line, true)) {
            if($msg['id'] === $id) return $msg;
        }
    }
    return null;
}

/**
 * 获取新消息
 */
function getMessagesAfter(float $lastId): array {
    if(!file_exists(MSG_FILE)) return [];
    
    $messages = [];
    $updatedMessages = false;
    $currentUserName = trim($_SESSION['username'] ?? '');
    
    // 活跃状态判断 - 只有当前请求携带了is_active参数，才认为用户处于活跃状态
    $isUserActive = isset($_GET['is_active']) && $_GET['is_active'] == '1';
    
    $lines = file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $currentTime = time();
    
    // 先读取所有消息并处理阅后即焚和已读状态
    foreach($lines as $index => $line) {
        if($msg = json_decode($line, true)) {
            $needsUpdate = false;
            
            // 确保所有消息都有read_by字段
            if(!isset($msg['read_by']) || !is_array($msg['read_by'])) {
                $msg['read_by'] = [];
                $needsUpdate = true;
            }
            
            // 处理阅后即焚消息 - 包括type=ephemeral或有is_ephemeral=true标记的消息
            $isEphemeralMsg = $msg['type'] === 'ephemeral' || isset($msg['is_ephemeral']) && $msg['is_ephemeral'] === true;
            if($isEphemeralMsg) {
                // 检查消息是否被当前用户读取，并且不是当前用户发送的
                // 重要：只有当用户处于活跃状态时，才标记为已读
                if($msg['username'] !== $currentUserName && !empty($currentUserName) && 
                   !in_array($currentUserName, $msg['read_by']) && $isUserActive) {
                    
                    // 添加当前用户到已读列表
                    $msg['read_by'][] = $currentUserName;
                    $needsUpdate = true;
                    
                    // 如果是首次被该用户读取，设置过期时间
                    if($msg['expiry_time'] === null) {
                        // 根据消息类型设置不同的过期时间
                        if($msg['type'] === 'img' || strpos($msg['content'], UPLOAD_URL) === 0) {
                            // 图片消息 - 30秒过期
                            $msg['expiry_time'] = $currentTime + 30;
                        } elseif($msg['type'] === 'voice' || strpos($msg['content'], VOICE_URL) === 0) {
                            // 语音消息 - 30秒过期
                            $msg['expiry_time'] = $currentTime + 30;
                        } else {
                            // 文本消息 - 10秒过期
                            $msg['expiry_time'] = $currentTime + 10;
                        }
                        $needsUpdate = true;
                        error_log("阅后即焚消息 {$msg['id']} 被 {$currentUserName} 读取，设置过期时间为 {$msg['expiry_time']}");
                    }
                }
                
                // 检查是否已过期但还未标记为已销毁
                if($msg['expiry_time'] !== null && $currentTime > $msg['expiry_time'] && !isset($msg['is_destroyed'])) {
                    // 根据消息类型进行特殊处理
                    if($msg['type'] === 'img' || strpos($msg['content'], UPLOAD_URL) === 0) {
                        // 如果是图片，保存原始内容但设置为已销毁状态
                        $msg['original_content'] = $msg['content'];
                        $msg['original_type'] = 'img';
                        $msg['content'] = '[图片已销毁]';
                        $msg['type'] = 'img'; // 保持消息类型不变
                    } elseif($msg['type'] === 'voice' || strpos($msg['content'], VOICE_URL) === 0) {
                        // 如果是语音消息，保存原始内容但设置为已销毁状态
                        $msg['original_content'] = $msg['content'];
                        $msg['original_type'] = 'voice';
                        $msg['content'] = '[语音已销毁]';
                        $msg['type'] = 'voice'; // 保持消息类型不变
                    } else {
                        // 文本消息
                        $msg['content'] = '此消息已销毁';
                    }
                    
                    $msg['is_destroyed'] = true;
                    $needsUpdate = true;
                    error_log("阅后即焚消息 {$msg['id']} 已过期，标记为已销毁");
                }
            } 
            // 对于普通消息，检查是否需要标记为已读
            // 同样，只有用户活跃时才标记普通消息为已读
            else if($msg['username'] !== $currentUserName && !empty($currentUserName) && 
                    !in_array($currentUserName, $msg['read_by']) && $isUserActive) {
                $msg['read_by'][] = $currentUserName;
                $needsUpdate = true;
            }
            
            // 如果消息需要更新，保存更改
            if($needsUpdate) {
                $lines[$index] = json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $updatedMessages = true;
            }
            
            // 确定是否返回该消息
            $shouldReturn = false;
            
            // 情况1：消息ID大于最后一条ID
            if((float)$msg['id'] > $lastId) {
                $shouldReturn = true;
            } 
            // 情况2：阅后即焚消息的特殊处理
            else if($isEphemeralMsg) {
                // 如果是当前用户发的消息，检查已读状态变化
                if($msg['username'] === $currentUserName && isset($msg['read_by']) && !empty($msg['read_by'])) {
                    $shouldReturn = true;
                }
                // 如果是别人发给当前用户的消息，检查是否有过期时间更新
                else if($msg['username'] !== $currentUserName && isset($msg['read_by']) && in_array($currentUserName, $msg['read_by'])) {
                    $shouldReturn = true;
                }
            }
            // 情况3：普通消息的已读状态更新 - 当前用户发送的消息且有人已读
            else if($msg['username'] === $currentUserName && isset($msg['read_by']) && !empty($msg['read_by'])) {
                $shouldReturn = true;
            }
            // 情况4：消息被撤回的更新
            else if(isset($msg['is_withdrawn']) && $msg['is_withdrawn']) {
                $shouldReturn = true;
            }
            
            if($shouldReturn) {
                $messages[] = $msg;
            }
        }
    }
    
    // 如果有更新，重写消息文件
    if($updatedMessages) {
        $fp = fopen(MSG_FILE, 'w');
        if(flock($fp, LOCK_EX)) {
            foreach($lines as $line) {
                fwrite($fp, $line."\n");
            }
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
    
    return $messages;
}

/**
 * 获取用户头像文件名
 */
function getUserAvatar(string $username): string {
    $user = getUserInfo($username);
    
    // 如果用户信息不存在，返回默认头像文件名
    if ($user === null) {
        error_log("getUserAvatar: User info not found for username: " . $username . ", returning default avatar.");
        return DEFAULT_AVATAR;
    }

    return $user['avatar'] ?? DEFAULT_AVATAR;
}

/**
 * 根据视频链接获取站点名称
 */
function getVideoSiteName(string $url): string {
    $siteMappings = [
        'bilibili.com' => 'Bilibili',
        'youku.com' => '优酷',
        'v.qq.com' => '腾讯视频',
        'iqiyi.com' => '爱奇艺',
        'mgtv.com' => '芒果TV',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'vimeo.com' => 'Vimeo'
    ];
    
    foreach ($siteMappings as $domain => $name) {
        if (strpos($url, $domain) !== false) {
            return $name;
        }
    }
    
    return '';
}

/**
 * 检查是否是直接视频链接
 */
function isDirectVideoLink(string $url): bool {
    // preg_match 返回 1 (匹配成功), 0 (不匹配), 或 false (错误)
    // 我们只关心是否匹配成功 (返回 1)
    return preg_match('/^https?:\/\/.*\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)(\?.*)?$/i', $url) > 0;
}

/**
 * 撤回消息
 * 
 * @param string $messageId 要撤回的消息ID
 * @return array|bool 成功返回更新后的消息数据，失败返回false
 */
function withdrawMessage(string $messageId): array|bool {
    if (!file_exists(MSG_FILE)) return false;
    
    // 读取所有消息
    $lines = file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $newLines = [];
    $found = false;
    $updatedMsg = null;
    
    // 遍历所有消息，找到要撤回的消息
    foreach ($lines as $line) {
        $msg = json_decode($line, true);
        if ($msg && $msg['id'] === $messageId) {
            // 验证是否是当前用户的消息
            if ($msg['username'] !== $_SESSION['username']) {
                return false; // 不能撤回其他用户的消息
            }
            $found = true;
            // 保存原始内容
            $msg['original_content'] = $msg['content'];
            // 修改消息内容为撤回状态，保持原有消息类型
            $msg['content'] = '此消息已撤回';
            $msg['is_withdrawn'] = true;
            $updatedMsg = $msg;
            $newLines[] = json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } else {
            $newLines[] = $line;
        }
    }
    
    if (!$found) return false;
    
    // 写入新的消息文件
    $fp = fopen(MSG_FILE, 'w');
    flock($fp, LOCK_EX);
    foreach ($newLines as $line) {
        fwrite($fp, $line . "\n");
    }
    flock($fp, LOCK_UN);
    fclose($fp);
    
    return $updatedMsg;
}

// 在消息处理函数中添加表情处理
function processMessageContent(string $content): string {
    // 先转义
    $content = htmlspecialchars($content);
    // 再还原 [emoji] 标签为图片
    $content = preg_replace_callback('/\\[emoji\\](.*?)\\[\\/emoji\\]/', function($matches) {
        return '<img src="' . htmlspecialchars($matches[1]) . '" class="custom-emoji-in-message" alt="表情">';
    }, $content);
    // 处理链接
    $content = preg_replace('/(https?:\\/\\/[^\\s<]+)/', '<a href="$1" target="_blank">$1</a>', $content);
    return $content;
}

// 在发送消息时使用处理函数
function sendMessage(string $username, string $content): bool {
    $content = processMessageContent($content);
    // ... 其余发送消息的代码 ...
}

/**
 * 处理消息回调
 */
function callbackMessages(): array {
    if(!file_exists(MSG_FILE)) return ['success' => false, 'message' => '消息文件不存在'];
    
    $messages = [];
    $lines = file(MSG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // 获取所有消息
    foreach($lines as $line) {
        if($msg = json_decode($line, true)) {
            $messages[] = $msg;
        }
    }
    
    // 按时间排序
    usort($messages, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    // 获取最近50条消息
    $recentMessages = array_slice($messages, -50);
    
    // 获取50条之前的消息
    $historicalMessages = array_slice($messages, 0, -50);
    
    // 标记历史消息
    foreach($historicalMessages as &$msg) {
        $msg['is_historical'] = true;
    }
    
    // 合并消息
    $allMessages = array_merge($historicalMessages, $recentMessages);
    
    // 写入文件
    $fp = fopen(MSG_FILE, 'w');
    if(flock($fp, LOCK_EX)) {
        foreach($allMessages as $msg) {
            fwrite($fp, json_encode($msg, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return ['success' => true];
    }
    
    fclose($fp);
    return ['success' => false, 'message' => '写入文件失败'];
}

/**
 * 回调单条消息
 */
function callbackSingleMessage(string $messageId): bool {
    if (!file_exists(MSG_FILE)) {
        return false;
    }

    $messages = file(MSG_FILE);
    $found = false;
    $callbackMsg = null;

    // 找到要回调的消息
    foreach ($messages as $key => $msg) {
        $msgData = json_decode($msg, true);
        if ($msgData && $msgData['id'] === $messageId) {
            $callbackMsg = $msgData;
            unset($messages[$key]); // 从原位置删除
            $found = true;
            break;
        }
    }

    if ($found && $callbackMsg) {
        // 更新消息时间
        $callbackMsg['time'] = date('H:i');
        // 添加回调标志
        $callbackMsg['is_callback'] = true;
        // 添加到消息列表末尾
        $messages[] = json_encode($callbackMsg, JSON_UNESCAPED_UNICODE) . "\n";
        // 写入文件
        return file_put_contents(MSG_FILE, implode('', $messages)) !== false;
    }

    return false;
} 