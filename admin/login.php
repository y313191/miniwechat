<?php
session_start();
require_once '../config/config.php';
require_once '../includes/users.php';

// 如果已经登录，直接跳转到后台首页
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("登录尝试 - 用户名: " . $username);
    
    $users = getAllUsers();
    error_log("登录验证 - 用户数据: " . print_r($users, true));
    
    if (isset($users[$username])) {
        error_log("登录验证 - 用户存在，密码哈希: " . $users[$username]['password']);
        $verifyResult = password_verify($password, $users[$username]['password']);
        error_log("登录验证 - 密码验证结果: " . ($verifyResult ? 'true' : 'false'));
        
        if ($verifyResult) {
            if ($users[$username]['role'] === 'admin') {
                $_SESSION['user'] = $users[$username];
                $_SESSION['user']['username'] = $username;
                error_log("登录成功 - 会话数据: " . print_r($_SESSION['user'], true));
                header('Location: index.php');
                exit;
            } else {
                $error = '您没有管理员权限';
            }
        } else {
            $error = '用户名或密码错误';
        }
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 24px;
            color: #343a40;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h1>管理员登录</h1>
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">用户名</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">密码</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">登录</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 