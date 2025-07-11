// 发送自定义HTML消息函数
function sendCustomMessage(messageText) {
    console.log('sendCustomMessage被调用');
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', messageText);
    formData.append('type', 'html');
    
    // 直接尝试获取页面的base URL
    const baseUrl = document.querySelector('base')?.href || window.location.origin + window.location.pathname;
    console.log('基础URL:', baseUrl);
    
    // 检测当前是否在public目录
    const isInPublic = window.location.pathname.toLowerCase().split('/').includes('public');
    console.log('是否在public目录:', isInPublic ? '是' : '否');
    
    // 构建绝对URL
    let url;
    if (isInPublic) {
        // 在public目录，需要回到上级
        url = '../index.php';
    } else {
        // 否则使用当前目录
        url = 'index.php';
    }
    
    console.log('最终使用的URL:', url);
    
    // 使用fetch API发送请求
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('收到响应:', response.status, response.statusText);
        
        // 尝试读取响应文本，便于调试
        response.text().then(text => {
            console.log('响应内容:', text);
            try {
                // 尝试解析为JSON
                const data = JSON.parse(text);
                console.log('解析的JSON数据:', data);
                
                if (data.status === 'success') {
                    console.log('通话邀请消息发送成功');
                    // 立即刷新消息列表显示新消息
                    loadNewMessages();
                } else {
                    console.warn('发送失败:', data.message || '未知错误');
                }
            } catch (jsonError) {
                console.error('解析JSON失败:', jsonError, '原始文本:', text);
            }
        });
    })
    .catch(error => {
        console.error('网络请求失败:', error);
    });
}
