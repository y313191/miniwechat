<?php
declare(strict_types=1);
session_start();

// 检查用户是否登录
if(empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 检查房间参数
if(empty($_GET['room'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Room parameter is required']);
    exit;
}

$roomId = $_GET['room'];
$roomFile = __DIR__ . '/../data/voice_rooms/' . $roomId . '.json';

// 创建目录（如果不存在）
if(!is_dir(__DIR__ . '/../data/voice_rooms')) {
    mkdir(__DIR__ . '/../data/voice_rooms', 0755, true);
}

// 获取房间用户
$peers = [];
if(file_exists($roomFile)) {
    $roomData = json_decode(file_get_contents($roomFile), true);
    if(isset($roomData['peers'])) {
        $peers = $roomData['peers'];
        
        // 检查和清理过期的用户（10分钟未活动）
        $now = time();
        $peers = array_filter($peers, function($peer) use ($now) {
            return isset($peer['lastActive']) && ($now - $peer['lastActive'] < 600);
        });
        
        // 只返回ID列表
        $peers = array_column($peers, 'id');
    }
}

// 返回结果
header('Content-Type: application/json');
echo json_encode($peers); 