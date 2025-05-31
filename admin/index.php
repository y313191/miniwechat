<?php
session_start();
require_once '../config/config.php';

// 检查是否已登录
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$users = unserialize(file_get_contents(USERS_FILE));
$messages = file_exists(MSG_FILE) ? file(MSG_FILE) : [];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理系统</title>
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
                <li class="active">
                    <a href="index.php"><i class="fas fa-home"></i> 控制台</a>
                </li>
                <li>
                    <a href="users.php"><i class="fas fa-users"></i> 用户管理</a>
                </li>
                <li>
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
                <!-- 数据统计卡片 -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">用户总数</h5>
                                <p class="card-text display-4"><?php echo count($users); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">在线用户</h5>
                                <p class="card-text display-4"><?php echo count(unserialize(file_get_contents(ONLINE_FILE))); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">消息数量</h5>
                                <p class="card-text display-4"><?php echo count($messages); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <h5 class="card-title">上传文件</h5>
                                <p class="card-text display-4"><?php echo count(glob(UPLOAD_DIR . "*")); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 用户管理卡片 -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">用户管理</h5>
                                <a href="users.php" class="btn btn-primary btn-sm">查看全部</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>用户名</th>
                                                <th>显示名称</th>
                                                <th>角色</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $count = 0;
                                            foreach ($users as $username => $user): 
                                                if ($count >= 5) break;
                                                $count++;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($username); ?></td>
                                                <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                        <?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 消息管理卡片 -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">最新消息</h5>
                                <a href="messages.php" class="btn btn-primary btn-sm">查看全部</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>发送者</th>
                                                <th>消息内容</th>
                                                <th>时间</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $count = 0;
                                            foreach (array_reverse($messages) as $msg): 
                                                if ($count >= 5) break;
                                                $msgData = json_decode($msg, true);
                                                if (!$msgData) continue;
                                                $count++;
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($msgData['username'] ?? '未知用户'); ?></td>
                                                <td>
                                                    <?php
                                                    if (isset($msgData['type']) && $msgData['type'] === 'image') {
                                                        echo '<img src="' . htmlspecialchars($msgData['content']) . '" style="max-width: 50px; max-height: 50px;">';
                                                    } elseif (isset($msgData['type']) && $msgData['type'] === 'voice') {
                                                        echo '<i class="fas fa-volume-up"></i> 语音消息';
                                                    } else {
                                                        echo mb_substr(htmlspecialchars($msgData['content'] ?? ''), 0, 20) . '...';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($msgData['time'] ?? ''); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-controls">
                    <button onclick="generateTestData()" class="admin-btn">生成测试数据</button>
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

        function generateTestData() {
            if (confirm('确定要生成100条测试消息吗？这将覆盖现有的所有消息。')) {
                window.location.href = 'generate_test_data.php';
            }
        }

        function toggleMessageCallback() {
            if (confirm('确定要执行消息回调吗？这将把历史消息显示在最新消息中。')) {
                fetch('?action=callback_messages', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('消息回调成功！');
                        location.reload();
                    } else {
                        alert('消息回调失败：' + data.message);
                    }
                })
                .catch(error => {
                    alert('操作失败：' + error);
                });
            }
        }
    </script>
</body>
</html> 