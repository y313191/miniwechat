<?php
declare(strict_types=1);

/**
 * 通知推送模块
 */

/**
 * 处理推送消息请求
 */
function handlePushMessage(): void {
    $pushType = $_POST['push_action'] ?? '';
    $content = match($pushType) {
        'manager' => '有新的任务开始！注意查看log',
        'tech' => '来消息啦！来消息啦！',
        default => ''
    };
    
    if(!array_key_exists($pushType, PUSH_TOKENS)) {
        http_response_code(400);
        exit(json_encode(['error' => '无效的推送请求']));
    }
    
    $result = pushPlusNotification(
        PUSH_TOKENS[$pushType],
        "来自 {$_SESSION['display_name']} 的推送",
        $content
    );
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

/**
 * 发送PushPlus通知
 */
function pushPlusNotification(string $token, string $title, string $content): array {
    $apiURL = 'http://www.pushplus.plus/send';
    
    $data = [
        'token' => $token,
        'title' => $title,
        'content' => $content,
        'template' => 'html'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    try {
        $response = file_get_contents($apiURL, false, stream_context_create($options));
        return json_decode($response, true) ?? ['code' => 500, 'msg' => '未知错误'];
    } catch (Exception $e) {
        return ['code' => 500, 'msg' => $e->getMessage()];
    }
} 