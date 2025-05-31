<?php
require_once '../config/config.php';

// 新的管理员密码
$newPassword = '123456'; // 您可以根据需要修改这个密码

// 创建新的管理员账户信息
$newAdmin = [
    'password' => password_hash($newPassword, PASSWORD_DEFAULT),
    'display_name' => '管理员',
    'role' => 'admin',
    'avatar' => DEFAULT_AVATAR,
    'created_at' => date('Y-m-d H:i:s')
];

// 读取现有用户数据
$users = [];
if (file_exists(USERS_FILE)) {
    $users = unserialize(file_get_contents(USERS_FILE));
}

// 更新管理员账户
$users['admin'] = $newAdmin;

// 保存更新后的用户数据
if (file_put_contents(USERS_FILE, serialize($users))) {
    echo "管理员密码已重置成功！<br>";
    echo "新密码是: " . $newPassword . "<br>";
    echo "请使用 admin/" . $newPassword . " 登录后台管理系统。";
} else {
    echo "重置密码失败，请检查文件权限。";
} 