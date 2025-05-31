<?php
session_start();
require_once '../config/config.php';

// 检查是否已登录
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$message = '';
$error = '';

// 获取当前设置
$settings = getSettings();

// 处理密码修改
if (isset($_POST['change_password'])) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // 读取用户数据
    $users = unserialize(file_get_contents(USERS_FILE));
    
    // 验证新密码
    if ($newPassword !== $confirmPassword) {
        $error = "两次输入的新密码不一致";
    }
    // 检查密码强度
    elseif ($passwordErrors = checkPasswordStrength($newPassword)) {
        $error = implode("<br>", $passwordErrors);
    }
    // 更新密码
    else {
        // 确保用户数据存在
        if (!isset($users['admin'])) {
            $error = "管理员数据不存在，请联系系统管理员";
        } else {
            // 创建新的管理员数据
            $newAdminData = [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'display_name' => '管理员',
                'role' => 'admin',
                'avatar' => DEFAULT_AVATAR,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // 更新管理员数据
            $users['admin'] = $newAdminData;
            
            // 保存用户数据
            $serializedData = serialize($users);
            if (file_put_contents(USERS_FILE, $serializedData)) {
                // 重新读取保存的数据进行验证
                $savedUsers = unserialize(file_get_contents(USERS_FILE));
                
                // 验证新密码
                $verifyResult = password_verify($newPassword, $savedUsers['admin']['password']);
                
                if (isset($savedUsers['admin']) && $verifyResult) {
                    // 更新会话中的用户数据
                    $_SESSION['user'] = $savedUsers['admin'];
                    $_SESSION['user']['username'] = 'admin';
                    $message = "密码修改成功，新密码验证通过";
                } else {
                    $error = "密码保存验证失败，请重试。验证结果：" . ($verifyResult ? "通过" : "失败");
                }
            } else {
                $error = "密码修改失败，请检查文件权限";
            }
        }
    }
}

// 处理设置更新
if (isset($_POST['update_settings'])) {
    $newSettings = [
        'allow_registration' => isset($_POST['allow_registration']),
        'password_strength' => $_POST['password_strength'] ?? 'medium',
        'password_min_length' => (int)($_POST['password_min_length'] ?? 6),
        'password_require_special' => isset($_POST['password_require_special']),
        'password_require_number' => isset($_POST['password_require_number']),
        'password_require_uppercase' => isset($_POST['password_require_uppercase']),
        // 网站基本设置
        'site_title' => trim($_POST['site_title'] ?? '聊天室'),
        'site_description' => trim($_POST['site_description'] ?? ''),
        'site_favicon' => 'favicon.ico',
        'site_background' => DEFAULT_BACKGROUND,
        'site_announcement' => trim($_POST['site_announcement'] ?? '')
    ];

    // 根据密码强度级别自动设置其他选项
    switch ($newSettings['password_strength']) {
        case 'low':
            $newSettings['password_min_length'] = 6;
            $newSettings['password_require_special'] = false;
            $newSettings['password_require_number'] = false;
            $newSettings['password_require_uppercase'] = false;
            break;
        case 'medium':
            $newSettings['password_min_length'] = 8;
            $newSettings['password_require_special'] = false;
            $newSettings['password_require_number'] = true;
            $newSettings['password_require_uppercase'] = false;
            break;
        case 'high':
            $newSettings['password_min_length'] = 10;
            $newSettings['password_require_special'] = true;
            $newSettings['password_require_number'] = true;
            $newSettings['password_require_uppercase'] = true;
            break;
    }

    // 保存设置
    if (updateSettings($newSettings)) {
        $message = "设置已更新";
        $settings = $newSettings;
    } else {
        $error = "设置更新失败，请检查文件权限";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 后台管理系统</title>
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
                <li>
                    <a href="messages.php"><i class="fas fa-comments"></i> 消息管理</a>
                </li>
                <li class="active">
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

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- 密码修改卡片 -->
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">修改管理员密码</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label">新密码</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">确认新密码</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">修改密码</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 系统设置卡片 -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">系统设置</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <!-- 网站基本设置 -->
                                    <div class="mb-4">
                                        <h6 class="mb-3">网站基本设置</h6>
                                        <div class="mb-3">
                                            <label class="form-label">网站标题</label>
                                            <input type="text" class="form-control" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">网站描述</label>
                                            <textarea class="form-control" name="site_description" rows="2"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">网站公告</label>
                                            <textarea class="form-control" name="site_announcement" rows="3"><?php echo htmlspecialchars($settings['site_announcement']); ?></textarea>
                                        </div>
                                    </div>

                                    <!-- 用户注册设置 -->
                                    <div class="mb-4">
                                        <h6 class="mb-3">用户注册设置</h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="allow_registration" name="allow_registration" <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="allow_registration">允许新用户注册</label>
                                        </div>
                                    </div>

                                    <!-- 密码强度设置 -->
                                    <div class="mb-4">
                                        <h6 class="mb-3">密码强度设置</h6>
                                        <div class="mb-3">
                                            <label class="form-label">密码强度级别</label>
                                            <select class="form-select" name="password_strength" id="password_strength">
                                                <option value="low" <?php echo $settings['password_strength'] === 'low' ? 'selected' : ''; ?>>低（仅要求长度）</option>
                                                <option value="medium" <?php echo $settings['password_strength'] === 'medium' ? 'selected' : ''; ?>>中（要求长度和数字）</option>
                                                <option value="high" <?php echo $settings['password_strength'] === 'high' ? 'selected' : ''; ?>>高（要求长度、数字、大写字母和特殊字符）</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">密码最小长度</label>
                                            <input type="number" class="form-control" name="password_min_length" value="<?php echo $settings['password_min_length']; ?>" min="6" max="32">
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="password_require_number" name="password_require_number" <?php echo $settings['password_require_number'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_require_number">要求包含数字</label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="password_require_uppercase" name="password_require_uppercase" <?php echo $settings['password_require_uppercase'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_require_uppercase">要求包含大写字母</label>
                                        </div>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="password_require_special" name="password_require_special" <?php echo $settings['password_require_special'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_require_special">要求包含特殊字符</label>
                                        </div>
                                    </div>

                                    <button type="submit" name="update_settings" class="btn btn-primary">保存设置</button>
                                </form>
                            </div>
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

            // 根据密码强度级别自动设置其他选项
            $('#password_strength').on('change', function() {
                var strength = $(this).val();
                switch(strength) {
                    case 'low':
                        $('input[name="password_min_length"]').val(6);
                        $('#password_require_number').prop('checked', false);
                        $('#password_require_uppercase').prop('checked', false);
                        $('#password_require_special').prop('checked', false);
                        break;
                    case 'medium':
                        $('input[name="password_min_length"]').val(8);
                        $('#password_require_number').prop('checked', true);
                        $('#password_require_uppercase').prop('checked', false);
                        $('#password_require_special').prop('checked', false);
                        break;
                    case 'high':
                        $('input[name="password_min_length"]').val(10);
                        $('#password_require_number').prop('checked', true);
                        $('#password_require_uppercase').prop('checked', true);
                        $('#password_require_special').prop('checked', true);
                        break;
                }
            });
        });
    </script>
</body>
</html> 