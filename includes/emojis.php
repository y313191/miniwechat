<?php
declare(strict_types=1);

// 表情包存储目录
define('EMOJI_DIR', __DIR__ . '/../emojis/');

// 获取所有表情包
function getAllEmojis(): array {
    $emojis = [];
    if (is_dir(EMOJI_DIR)) {
        $files = scandir(EMOJI_DIR);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file(EMOJI_DIR . $file)) {
                $emojis[] = [
                    'name' => $file,
                    'url' => 'emojis/' . $file
                ];
            }
        }
    }
    return $emojis;
}

// 上传表情包
function uploadEmoji(array $file): array {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => '无效的文件上传'];
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => '只允许上传JPG、PNG或GIF格式的图片'];
    }

    $maxSize = 4 * 1024 * 1024; // 4MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => '文件大小不能超过4MB'];
    }

    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = EMOJI_DIR . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'url' => 'emojis/' . $filename,
            'name' => $filename
        ];
    }

    return ['success' => false, 'message' => '上传失败'];
}

// 删除表情包
function deleteEmoji(string $filename): bool {
    $filepath = EMOJI_DIR . $filename;
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
} 