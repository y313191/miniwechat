<?php
session_start();
require_once '../config/config.php';
require_once '../includes/users.php';

// 检查是否已登录
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$users = getAllUsers();
$message = '';

// 处理权限更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $username = $_POST['username'] ?? '';
    
    if ($_POST['action'] === 'toggle_admin') {
        if (isset($users[$username])) {
            // 保存用户当前的所有信息
            $currentUserData = $users[$username];
            
            // 更新role字段
            $currentUserData['role'] = $currentUserData['role'] === 'admin' ? 'user' : 'admin';
            // 更新用户数据
            $users[$username] = $currentUserData;
            
            if (saveUsers($users)) {
                // 如果当前登录用户就是被修改的用户，更新会话数据
                if ($_SESSION['user']['username'] === $username) {
                    $_SESSION['user'] = $currentUserData;
                    $_SESSION['user']['username'] = $username;
                }
                $message = "用户 {$username} 的权限已更新";
            } else {
                $message = "权限更新失败，请检查文件权限";
            }
        }
    } elseif ($_POST['action'] === 'delete_user') {
        if ($username !== 'admin' && isset($users[$username])) {
            unset($users[$username]);
            if (saveUsers($users)) {
                $message = "用户 {$username} 已删除";
            } else {
                $message = "删除用户失败，请检查文件权限";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - 后台管理系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .table th, .table td {
            padding: 0.5rem;
            font-size: 0.9rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .card {
            margin-bottom: 1rem;
        }
        .table-responsive {
            margin: 0;
            padding: 0;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .badge {
            font-size: 0.75rem;
            padding: 0.25em 0.6em;
        }
    </style>
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
                <li class="active">
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
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">用户列表</h5>
                        <span class="badge bg-primary"><?php echo count($users); ?> 个用户</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 15%">用户名</th>
                                        <th style="width: 15%">显示名称</th>
                                        <th style="width: 10%">角色</th>
                                        <th style="width: 20%">创建时间</th>
                                        <th style="width: 40%">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $username => $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($username); ?></td>
                                        <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                        <td class="action-buttons">
                                            <?php if ($username !== 'admin'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                                <input type="hidden" name="action" value="toggle_admin">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('确定要<?php echo $user['role'] === 'admin' ? '取消' : '设置'; ?>该用户的管理员权限吗？');">
                                                    <?php echo $user['role'] === 'admin' ? '取消管理员' : '设为管理员'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除此用户吗？此操作不可恢复！');">删除</button>
                                            </form>
                                            <?php else: ?>
                                            <span class="text-muted">主管理员账户</span>
                                            <?php endif; ?>
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