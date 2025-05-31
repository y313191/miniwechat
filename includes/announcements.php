<?php
declare(strict_types=1);

/**
 * 公告管理模块
 */

// 公告文件路径
const ANNOUNCEMENT_FILE = __DIR__ . '/../data/announcement.txt';

/**
 * 加载公告内容
 */
function loadAnnouncement(): string {
    if (file_exists(ANNOUNCEMENT_FILE)) {
        return file_get_contents(ANNOUNCEMENT_FILE) ?: '';
    }
    return '';
}

/**
 * 保存公告内容
 */
function saveAnnouncement(string $content): bool {
    // 这里可以添加权限检查，例如只有管理员才能保存公告
    // if (!isAdmin()) { return false; }
    
    // 使用独占锁确保写入安全
    $fp = fopen(ANNOUNCEMENT_FILE, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); // 清空文件内容
        rewind($fp); // 将文件指针移到文件开头
        $result = fwrite($fp, $content) !== false;
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result;
    }
    
    return false; // 获取锁或写入失败
}

/**
 * 处理加载公告请求
 */
function handleGetAnnouncement(): void {
    header('Content-Type: application/json');
    
    $announcement = loadAnnouncement();
    
    echo json_encode([
        'success' => true,
        'announcement' => $announcement
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 处理保存公告请求
 */
function handleSaveAnnouncement(): void {
    header('Content-Type: application/json');
    
    // 检查用户权限
    if (!isset($_SESSION['username'])) {
        http_response_code(403);
        echo json_encode(['error' => '请先登录']);
        exit;
    }

    // 获取请求数据
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!isset($data['content'])) {
        http_response_code(400);
        echo json_encode(['error' => '缺少公告内容']);
        exit;
    }

    $announcementContent = trim($data['content']);
    
    // 保存公告内容
    if (saveAnnouncement($announcementContent)) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '保存公告失败']);
    }
    exit;
}

// 需要 isAdmin() 函数来检查用户是否是管理员，假设它在其他地方定义
// 示例 isAdmin 函数 (需要根据您的实际用户角色判断逻辑实现)
function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
} 