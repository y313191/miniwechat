<?php
declare(strict_types=1);
session_start();

// 检查用户是否登录
if(empty($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 检查必要参数
if(empty($_POST['room']) || empty($_POST['peerId']) || empty($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$roomId = $_POST['room'];
$peerId = $_POST['peerId'];
$action = $_POST['action'];
$roomFile = __DIR__ . '/../data/voice_rooms/' . $roomId . '.json';

// 创建目录（如果不存在）
if(!is_dir(__DIR__ . '/../data/voice_rooms')) {
    mkdir(__DIR__ . '/../data/voice_rooms', 0755, true);
}

// 加载现有房间数据
$roomData = ['peers' => []];
if(file_exists($roomFile)) {
    $roomData = json_decode(file_get_contents($roomFile), true);
    if(!isset($roomData['peers'])) {
        $roomData['peers'] = [];
    }
}

// 清理过期的用户（10分钟未活动）
$now = time();
$roomData['peers'] = array_filter($roomData['peers'], function($peer) use ($now) {
    return isset($peer['lastActive']) && ($now - $peer['lastActive'] < 600);
});

// 执行操作
if($action === 'update') {
    // 更新或添加用户
    $peerExists = false;
    foreach($roomData['peers'] as &$peer) {
        if($peer['id'] === $peerId) {
            $peer['lastActive'] = $now;
            $peerExists = true;
            break;
        }
    }
    
    if(!$peerExists) {
        $roomData['peers'][] = [
            'id' => $peerId,
            'username' => $_SESSION['username'],
            'displayName' => $_SESSION['display_name'] ?? $_SESSION['username'],
            'lastActive' => $now
        ];
    }
} elseif($action === 'leave') {
    // 移除用户
    $roomData['peers'] = array_filter($roomData['peers'], function($peer) use ($peerId) {
        return $peer['id'] !== $peerId;
    });
}

// 保存房间数据
file_put_contents($roomFile, json_encode($roomData));

// 返回结果
header('Content-Type: application/json');
echo json_encode(['status' => 'success']); 