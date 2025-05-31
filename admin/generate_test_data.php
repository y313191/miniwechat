<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 检查配置文件
$configPath = __DIR__ . '/../config/config.php';
$messagesPath = __DIR__ . '/../includes/messages.php';

if (!file_exists($configPath)) {
    die("错误：找不到配置文件。路径：{$configPath}");
}

if (!file_exists($messagesPath)) {
    die("错误：找不到消息处理文件。路径：{$messagesPath}");
}

require_once $configPath;
require_once $messagesPath;

// 确保消息文件目录存在
$msgDir = dirname(MSG_FILE);
if (!file_exists($msgDir)) {
    if (!mkdir($msgDir, 0777, true)) {
        die("错误：无法创建消息文件目录。路径：{$msgDir}");
    }
}

try {
    // 测试用户列表
    $testUsers = [
        ['username' => 'user1', 'name' => '张三'],
        ['username' => 'user2', 'name' => '李四'],
        ['username' => 'user3', 'name' => '王五'],
        ['username' => 'user4', 'name' => '赵六'],
        ['username' => 'user5', 'name' => '系统']
    ];

    // 测试消息列表
    $testMessages = [
        "你好啊！最近怎么样？",
        "今天天气真不错！",
        "这个功能太棒了！",
        "大家下午好！",
        "有人在线吗？",
        "我刚刚完成了一个项目",
        "周末有什么计划？",
        "这个聊天室真热闹",
        "有人会编程吗？",
        "分享一个有趣的事情"
    ];

    // 生成随机时间（最近24小时内）
    function getRandomTime() {
        $now = time();
        $dayAgo = $now - (24 * 60 * 60);
        return date('Y-m-d H:i:s', rand($dayAgo, $now));
    }

    // 生成100条测试消息
    $messages = [];
    for ($i = 0; $i < 100; $i++) {
        $user = $testUsers[array_rand($testUsers)];
        $message = [
            'id' => uniqid(),
            'type' => 'text',
            'username' => $user['username'],
            'name' => $user['name'],
            'content' => $testMessages[array_rand($testMessages)],
            'time' => getRandomTime(),
            'read_by' => []
        ];

        // 随机标记一些消息为已读
        $readCount = rand(0, count($testUsers));
        for ($j = 0; $j < $readCount; $j++) {
            $reader = $testUsers[array_rand($testUsers)];
            if ($reader['username'] !== $user['username']) {
                $message['read_by'][] = $reader['username'];
            }
        }

        $messages[] = $message;
    }

    // 按时间排序
    usort($messages, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });

    // 写入文件，每行一条消息
    $fp = fopen(MSG_FILE, 'w');
    if (!$fp) {
        throw new Exception("无法打开消息文件进行写入");
    }

    foreach ($messages as $message) {
        if (fwrite($fp, json_encode($message, JSON_UNESCAPED_UNICODE) . "\n") === false) {
            throw new Exception("写入消息失败");
        }
    }

    fclose($fp);

    echo "<h2>测试数据生成成功！</h2>";
    echo "<p>已生成100条测试消息。</p>";
    echo "<p><a href='index.php'>返回管理界面</a></p>";
} catch (Exception $e) {
    echo "<h2>错误</h2>";
    echo "<p>生成测试数据时出错：" . $e->getMessage() . "</p>";
    echo "<p><a href='index.php'>返回管理界面</a></p>";
}
?> 