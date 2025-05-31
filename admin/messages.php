<?php
session_start();
require_once '../config/config.php';
require_once '../includes/messages.php';

// 检查是否已登录
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$message = '';

// 处理消息删除和回调
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_message') {
        $messageId = (int)$_POST['message_id'];
        $messages = file(MSG_FILE);
        if (isset($messages[$messageId])) {
            unset($messages[$messageId]);
            file_put_contents(MSG_FILE, implode('', $messages));
            $message = "消息已删除";
        }
    } elseif ($_POST['action'] === 'callback_message') {
        $messageId = $_POST['message_id'];
        if (callbackSingleMessage($messageId)) {
            $message = "消息已回调";
            // 发送WebSocket消息通知客户端更新
            if (function_exists('sendWebSocketMessage')) {
                sendWebSocketMessage([
                    'type' => 'message_callback',
                    'message_id' => $messageId
                ]);
            }
        } else {
            $message = "消息回调失败";
        }
    }
}

// 读取消息
$messages = file_exists(MSG_FILE) ? file(MSG_FILE) : [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>消息管理 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- 侧边栏 -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3>后台管理</h3>
            </div>

            <ul class="list-unstyled components">
                <li>
                    <a href="index.php"><i class="fas fa-home"></i> 控制台</a>
                </li>
                <li>
                    <a href="users.php"><i class="fas fa-users"></i> 用户管理</a>
                </li>
                <li class="active">
                    <a href="messages.php"><i class="fas fa-comments"></i> 消息管理</a>
                </li>
                <li>
                    <a href="settings.php"><i class="fas fa-cog"></i> 系统设置</a>
                </li>
            </ul>
        </nav>

        <!-- 页面内容 -->
        <div id="content">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-info">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-auto">
                        <span class="me-3">欢迎，<?php echo htmlspecialchars($currentUser['display_name']); ?></span>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm">退出</a>
                    </div>
                </div>
            </nav>

            <div class="container-fluid">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">消息列表</h5>
                        <span class="badge bg-primary"><?php echo count($messages); ?> 条消息</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>发送者</th>
                                        <th>消息内容</th>
                                        <th>时间</th>
                                        <th>状态</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $id => $msg): ?>
                                    <?php
                                        $msgData = json_decode($msg, true);
                                        if (!$msgData) continue;
                                    ?>
                                    <tr>
                                        <td><?php echo $id; ?></td>
                                        <td><?php echo htmlspecialchars($msgData['username'] ?? '未知用户'); ?></td>
                                        <td>
                                            <?php
                                            if (isset($msgData['type']) && $msgData['type'] === 'image') {
                                                echo '<img src="' . htmlspecialchars($msgData['content']) . '" style="max-width: 100px; max-height: 100px;">';
                                            } elseif (isset($msgData['type']) && $msgData['type'] === 'voice') {
                                                echo '<audio controls><source src="' . htmlspecialchars($msgData['content']) . '" type="audio/mpeg">您的浏览器不支持音频播放</audio>';
                                            } else {
                                                echo htmlspecialchars($msgData['content'] ?? '');
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($msgData['time'] ?? ''); ?></td>
                                        <td>
                                            <?php if (isset($msgData['is_historical']) && $msgData['is_historical']): ?>
                                                <span class="badge bg-info">历史消息</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">当前消息</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if (!isset($msgData['is_historical']) || !$msgData['is_historical']): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('确定要回调此消息吗？');">
                                                    <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($msgData['id']); ?>">
                                                    <input type="hidden" name="action" value="callback_message">
                                                    <button type="submit" class="btn btn-warning btn-sm">回调</button>
                                                </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline ms-1" onsubmit="return confirm('确定要删除此消息吗？');">
                                                    <input type="hidden" name="message_id" value="<?php echo $id; ?>">
                                                    <input type="hidden" name="action" value="delete_message">
                                                    <button type="submit" class="btn btn-danger btn-sm">删除</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html> 