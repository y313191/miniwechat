let lastMsgId = 0;
let autoScroll = true;
let isSending = false;
let replyTo = null;
let mediaRecorder = null;
let audioChunks = [];
let startTime = 0;
let currentAudio = null;
let isEphemeralMode = false; // 阅后即焚模式状态
let chatContainer = null; // 聊天容器引用
let newMsgCount = 0; // 新消息计数
let stopPolling = false; // 是否停止轮询
let isPolling = false; // 是否正在轮询
let isWindowActive = true; // 窗口是否活跃

// 头像缓存对象
const avatarCache = {};

// 初始化个人资料页面
let profilePage = null;

// 初始化
document.addEventListener('DOMContentLoaded', function() {
    // 设置初始的lastMsgId
    if (typeof initialLastMsgId !== 'undefined') {
        lastMsgId = initialLastMsgId;
    }
    
    // 从服务器加载背景图片
    loadBackgroundFromServer();
    
    // 设置聊天框滚动到底部
    const chatBox = document.getElementById('chatBox');
    chatContainer = chatBox; // 保存聊天容器引用
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
        chatBox.addEventListener('scroll', checkScroll);
    }
    
    // 黑夜模式按钮事件
    const darkModeBtn = document.getElementById('darkModeBtn');
    if (darkModeBtn) {
        // 检查本地存储的暗色模式设置
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        
        // 根据保存的设置应用暗色模式
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            darkModeBtn.innerText = '☀️'; // 太阳图标表示可以切换到亮色模式
        }
        
        darkModeBtn.addEventListener('click', toggleDarkMode);
    }
    
    // 拓展按钮点击事件
    const expandBtn = document.getElementById('expandBtn');
    const expandMenu = document.getElementById('expandMenu');
    if (expandBtn && expandMenu) {
        expandBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            expandMenu.classList.toggle('show');
        });
        
        // 点击菜单外部区域时关闭菜单
        document.addEventListener('click', function(e) {
            if (!expandMenu.contains(e.target) && e.target !== expandBtn) {
                expandMenu.classList.remove('show');
            }
        });
    }
    
    // 阅后即焚菜单按钮事件
    const ephemeralMenuBtn = document.getElementById('ephemeralMenuBtn');
    if (ephemeralMenuBtn) {
        ephemeralMenuBtn.addEventListener('change', function() {
            toggleEphemeralMode();
            expandMenu.classList.remove('show'); // 关闭拓展菜单
        });
    }
    
    // 表情包按钮事件
    const emojiBtn = document.getElementById('emojiBtn');
    if (emojiBtn) {
        emojiBtn.addEventListener('click', toggleEmojiPanel);
    }
    
    // 表情选择事件委托
    const emojiPanel = document.getElementById('emojiPanel');
    if (emojiPanel) {
        emojiPanel.addEventListener('click', function(e) {
            if (e.target.classList.contains('emoji')) {
                insertEmoji(e.target.innerText);
            }
        });
        
        // 点击表情面板外部关闭面板
        document.addEventListener('click', function(e) {
            if (!emojiBtn.contains(e.target) && 
                !emojiPanel.contains(e.target) && 
                emojiPanel.classList.contains('active')) {
                emojiPanel.classList.remove('active');
            }
        });
    }
    
    // 点击页面任意位置关闭所有消息菜单
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.msg-action-btn') && !e.target.closest('.msg-actions-menu')) {
            closeAllMsgMenus();
        }
    });
    
    // 消息输入框事件
    const messageInput = document.getElementById('message');
    if (messageInput) {
        messageInput.addEventListener('keypress', e => {
            if(e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                sendMsg();
            }
        });
        
        messageInput.addEventListener('keydown', e => {
            if(e.key === 'Escape' && replyTo) {
                replyTo = null;
                messageInput.setAttribute('placeholder', '我是输入框🤗');
            }
        });
    }
    
    // 图片上传事件
    const imageInput = document.getElementById('image');
    if (imageInput) {
        imageInput.addEventListener('change', e => {
            if(e.target.files.length) sendMsg();
        });
    }
    
    // 头像上传事件
    const avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', uploadAvatar);
    }
    
    // 视频按钮点击事件 - 确保按钮存在才绑定事件
    const videoBtn = document.getElementById('videoBtn');
    if (videoBtn) {
        videoBtn.addEventListener('click', showVideoDialog);
        console.log('视频按钮事件已绑定');
    }
    
    // 关闭视频对话框按钮事件
    const closeVideoDialog = document.getElementById('closeVideoDialog');
    if (closeVideoDialog) {
        closeVideoDialog.addEventListener('click', hideVideoDialog);
    }
    
    // 视频链接提交事件
    const videoForm = document.getElementById('videoForm');
    if (videoForm) {
        videoForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitVideoLink();
        });
    }
    
    // 背景图片上传事件
    const bgInput = document.getElementById('background');
    if (bgInput) {
        bgInput.addEventListener('change', e => {
            if(e.target.files.length) uploadBackground();
        });
    }
    
    // 昵称输入框回车事件
    const nickInput = document.getElementById('nick');
    if (nickInput) {
        nickInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                login();
            }
        });
    }
    
    // 启动轮询获取新消息
        loadNewMessages();
    
    // 定时获取在线用户数（取消原有的setInterval）
    setInterval(() => {
        fetch('?get_online=1')
            .then(response => response.json())
            .then(data => {
                const onlineCountEl = document.getElementById('onlineCount');
                if (onlineCountEl) {
                    onlineCountEl.textContent = data.count;
                }
            })
            .catch(error => console.error('获取在线用户数失败:', error));
    }, 3000);
    
    // 点击事件委托
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.dropdown');
        if (dropdown && !dropdown.contains(event.target)) {
            const dropdownMenu = document.getElementById('dropdownMenu');
            if (dropdownMenu) {
                dropdownMenu.classList.remove('show');
            }
        }
    });
    
    // 收集头像信息
    collectAvatars();

    // 语音通话功能
    const callBtn = document.getElementById('callBtn');
    const callDialog = document.getElementById('callDialog');
    const closeCallDialog = document.getElementById('closeCallDialog');
    const callForm = document.getElementById('callForm');
    const cancelCallBtn = document.getElementById('cancelCallBtn');
    
    if (callBtn) {
        callBtn.addEventListener('click', function() {
            callDialog.style.display = 'flex';
        });
    }
    
    if (closeCallDialog) {
        closeCallDialog.addEventListener('click', function() {
            callDialog.style.display = 'none';
        });
    }
    
    if (cancelCallBtn) {
        cancelCallBtn.addEventListener('click', function() {
            callDialog.style.display = 'none';
        });
    }
    
    if (callForm) {
        callForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('提交创建通话表单');
            
            const roomName = document.getElementById('callRoomName').value.trim() || 
                             'call_' + Math.random().toString(36).substring(2, 15);
            
            console.log('生成的房间名称:', roomName);
            
            // 获取当前时间
            const now = new Date();
            const timeStr = now.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
            
            console.log('准备创建邀请消息');
            // 发送通话邀请消息 - 更加友好和信息丰富的消息
            const message = `
                <div class="call-invitation">
                    <div class="call-invitation-icon">🔊</div>
                    <div class="call-invitation-content">
                        <div class="call-invitation-title">语音通话邀请 (${timeStr})</div>
                        <div class="call-invitation-text">我发起了语音通话，邀请你加入！</div>
                        <a href="public/voice_call.php?room=${encodeURIComponent(roomName)}" target="_blank" class="call-invite-btn">
                            点击加入通话
                        </a>
                        <div class="call-invitation-note">房间ID: ${roomName}</div>
                    </div>
                </div>
            `;
            
            console.log('邀请消息创建完成，准备发送');
            // 发送消息
            sendCustomMessage(message);
            
            console.log('打开通话页面');
            // 打开通话页面
            window.open(`public/voice_call.php?room=${encodeURIComponent(roomName)}`, '_blank');
            
            // 关闭对话框
            console.log('关闭对话框');
            callDialog.style.display = 'none';
            document.getElementById('callRoomName').value = '';
        });
    }

    // 在其他按钮事件处理后添加阅后即焚模式切换按钮的事件处理
    const ephemeralModeBtn = document.getElementById('ephemeralModeBtn');
    if (ephemeralModeBtn) {
        ephemeralModeBtn.addEventListener('click', toggleEphemeralMode);
    }

    // 初始化阅后即焚消息的计时器
    initializeEphemeralMessages();

    // 定时执行更新阅后即焚消息计时器
    setInterval(updateEphemeralTimers, 1000);
    
    // 启动轮询获取新消息之前，先执行一次即时检查
    console.log('页面加载完成，立即检查新消息...');
    fetchLatestMessages();
    
    // 短暂延迟后启动轮询，避免与即时检查冲突
    setTimeout(() => {
        console.log('启动定期消息轮询...');
        loadNewMessages();
    }, 2000);

    // 添加窗口焦点变化事件监听
    window.addEventListener('focus', function() {
        console.log('窗口获得焦点，标记为活跃');
        isWindowActive = true;
    });
    
    window.addEventListener('blur', function() {
        console.log('窗口失去焦点，标记为非活跃');
        isWindowActive = false;
    });
    
    // 添加可见性变化事件监听
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            console.log('页面隐藏，标记为非活跃');
            isWindowActive = false;
        } else {
            console.log('页面可见，标记为活跃');
            isWindowActive = true;
        }
    });
    
    // 初始化个人资料页面
    const userAvatar = document.querySelector('.user-avatar');
    const userNameDisplay = document.querySelector('.user-name-display');
    
    if (userAvatar && userNameDisplay) {
        // 创建遮罩层
        const overlay = document.createElement('div');
        overlay.className = 'profile-overlay';
        document.body.appendChild(overlay);
        
        // 初始化个人资料页面
        profilePage = new ProfilePage();
        const avatarUrl = userAvatar.style.backgroundImage.replace(/^url\(['"](.+?)['"]\)$/, '$1');
        profilePage.init(avatarUrl, userNameDisplay.textContent);
        
        // 点击头像打开个人资料页面
        userAvatar.addEventListener('click', () => {
            overlay.style.display = 'block';
            setTimeout(() => overlay.classList.add('show'), 10);
            profilePage.show();
        });
        
        // 点击遮罩层关闭个人资料页面
        overlay.addEventListener('click', () => {
            overlay.classList.remove('show');
            setTimeout(() => overlay.style.display = 'none', 300);
            profilePage.hide();
        });
    }

    // Handle announcement button click
    const announcementButton = document.getElementById('announcementBtn');
    const announcementContainer = document.getElementById('announcement-container');
    const announcementCloseButton = announcementContainer.querySelector('.close-button');
    const modalOverlay = document.getElementById('modal-overlay');

    if (announcementButton && announcementContainer && announcementCloseButton && modalOverlay) {
        announcementButton.addEventListener('click', () => {
            announcementContainer.style.display = 'flex';
            modalOverlay.style.display = 'block';
        });

        announcementCloseButton.addEventListener('click', () => {
            announcementContainer.style.display = 'none';
            modalOverlay.style.display = 'none';
        });

        modalOverlay.addEventListener('click', () => {
            announcementContainer.style.display = 'none';
            modalOverlay.style.display = 'none';
        });

        // 保存公告按钮点击事件
        const saveAnnouncementBtn = document.getElementById('save-announcement');
        if (saveAnnouncementBtn) {
            saveAnnouncementBtn.addEventListener('click', function() {
                const content = document.getElementById('announcement-content').value;
                
                fetch('index.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'save_announcement',
                        content: content
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('公告保存成功！');
                        document.getElementById('announcement-container').style.display = 'none';
                        document.getElementById('modal-overlay').style.display = 'none';
                    } else {
                        alert(data.message || '保存失败，请重试');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('保存失败，请重试');
                });
            });
        }

        // Prevent clicks inside the modal content from closing the modal
        announcementContainer.querySelector('.modal-content').addEventListener('click', (event) => {
            event.stopPropagation();
        });
    }

    // Adjust polling based on window focus
    let pollingInterval = 2000; // 2 seconds when window is active
});

// 从服务器加载背景图片
function loadBackgroundFromServer() {
    fetch('?action=get_background')
        .then(response => response.json())
        .then(data => {
            if (data.url) {
                document.body.style.backgroundImage = `url('${data.url}')`;
                
                // 更新预览图
                const preview = document.getElementById('bgPreview');
                if (preview) {
                    preview.style.backgroundImage = `url('${data.url}')`;
                }
            }
        })
        .catch(error => {
            console.error('获取背景失败:', error);
        });
}

// 上传背景图片
function uploadBackground() {
    const bgInput = document.getElementById('background');
    if (!bgInput.files[0]) return;
    
    const formData = new FormData();
    formData.append('background', bgInput.files[0]);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 应用新背景
            setBackground(data.url);
            // 更新预览
            updateBackgroundPreview(data.url);
            // 清空输入
            bgInput.value = '';
        } else if (data.error) {
            alert('上传失败: ' + data.error);
        }
    })
    .catch(error => {
        console.error('上传背景失败:', error);
        alert('上传背景失败，请重试');
    });
}

// 设置背景图片
function setBackground(url) {
    document.body.style.backgroundImage = `url('${url}')`;
}

// 更新背景预览
function updateBackgroundPreview(url) {
    const preview = document.getElementById('bgPreview');
    if (preview) {
        preview.style.backgroundImage = `url('${url}')`;
    }
}

// 重置背景为默认
function resetBackground() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'reset_background=true'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setBackground(data.url);
            updateBackgroundPreview(data.url);
        } else if (data.error) {
            alert('重置背景失败: ' + data.error);
        }
    })
    .catch(error => {
        console.error('重置背景失败:', error);
        alert('重置背景失败，请重试');
    });
}

/**
 * 切换下拉菜单显示状态
 */
function toggleDropdown() {
    const menu = document.getElementById('dropdownMenu');
    menu.classList.toggle('show');
}

/**
 * 切换背景选项
 */
function toggleBgOptions() {
    const menu = document.getElementById('dropdownMenu');
    
    // 如果菜单已经显示，隐藏它
    if (menu.classList.contains('show')) {
        menu.classList.remove('show');
        return;
    }
    
    // 显示菜单并滚动到背景部分
    menu.classList.add('show');
    
    // 所有标题元素
    const headers = menu.querySelectorAll('h3');
    
    // 滚动到背景部分
    if (headers.length > 0) {
        headers[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

/**
 * 切换推送选项
 */
function togglePushOptions() {
    const menu = document.getElementById('dropdownMenu');
    
    // 如果菜单已经显示，隐藏它
    if (menu.classList.contains('show')) {
        menu.classList.remove('show');
        return;
    }
    
    // 显示菜单并滚动到推送部分
    menu.classList.add('show');
    
    // 所有标题元素
    const headers = menu.querySelectorAll('h3');
    
    // 滚动到推送部分(第二个标题)
    if (headers.length > 1) {
        headers[1].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function checkScroll() {
    if (!chatContainer) return;
    
    const scrollPosition = chatContainer.scrollTop + chatContainer.clientHeight;
    const scrollHeight = chatContainer.scrollHeight;
    const threshold = 150; // 像素阈值，低于这个值认为用户在底部
    
    autoScroll = (scrollHeight - scrollPosition) < threshold;
    console.log('Scroll check - autoScroll:', autoScroll);
}

function maintainScroll() {
    if(autoScroll && chatContainer) {
        scrollToBottom();
    }
}

function loadNewMessages() {
    // 如果已经在轮询中，避免重复启动
    if (isPolling) return;

    isPolling = true;

    // 根据窗口活跃状态调整轮询间隔
    const activePollingInterval = 2000; // 窗口活跃时，例如 2 秒
    const inactivePollingInterval = 10000; // 窗口非活跃时，例如 10 秒
    const errorPollingInterval = 3000; // 错误发生时，例如 3 秒

    // 构建URL，加入活跃状态参数
    const url = `?action=get&last=${lastMsgId}&is_active=${isWindowActive ? '1' : '0'}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            isPolling = false;

            if (data.length > 0) {
                // 获取新消息
                const newUsers = new Set();
                let hasUpdates = false;

                data.forEach(msg => {
                    // 处理所有消息，包括ID小于等于lastMsgId的更新消息
                    console.log('处理消息:', msg.id, msg.type, msg.content && msg.content.substring(0, 30), 'is_withdrawn:', msg.is_withdrawn);
                    
                    // 检查是否是撤回消息的更新
                    if (msg.is_withdrawn) {
                        const existingMsg = document.querySelector(`.msg[data-id="${msg.id}"]`);
                        if (existingMsg) {
                            hasUpdates = processMessage(msg, newUsers) || hasUpdates;
                        }
                    }
                    
                    // 处理其他类型的消息更新
                    hasUpdates = processMessage(msg, newUsers) || hasUpdates;
                });

                // 滚动到底部
                if (hasUpdates && autoScroll) {
                    scrollToBottom();
                }

                // 更新在线用户列表
                if (newUsers.size > 0) {
                    updateOnlineUsers(Array.from(newUsers));
                }

                // 更新阅后即焚消息计时器
                updateEphemeralTimers();
            }

            // 继续轮询消息
            if (!stopPolling) {
                // 根据活跃状态设置下一个轮询的延迟
                const nextInterval = isWindowActive ? activePollingInterval : inactivePollingInterval;
                setTimeout(loadNewMessages, nextInterval);
            }
        })
        .catch(error => {
            isPolling = false;
            console.error('获取消息错误:', error);

            // 如果网络错误，放慢轮询频率
            if (!stopPolling) {
                setTimeout(loadNewMessages, errorPollingInterval);
            }
        });
}

/**
 * 处理单条消息的显示和更新
 * @param {Object} msg - 消息对象
 * @param {Set} newUsers - 收集需要更新头像的用户集合
 * @returns {boolean} 是否进行了更新
 */
function processMessage(msg, newUsers) {
    let updated = false;
    const msgId = msg.id;
    
    // 查找现有消息元素
    const existingMsg = document.querySelector(`.msg[data-id="${msgId}"]`);
    if (existingMsg) {
        // 如果消息已存在，更新状态
        
        // 处理撤回消息
        if (msg.is_withdrawn && !existingMsg.classList.contains('withdrawn-msg')) {
            existingMsg.classList.add('withdrawn-msg');
            const msgBody = existingMsg.querySelector('.msg-body');
            if (msgBody) {
                // 保留回复预览部分
                const replyPreview = msgBody.querySelector('.reply-preview');
                msgBody.innerHTML = '';
                if (replyPreview) {
                    msgBody.appendChild(replyPreview);
                }
                
                const withdrawnContent = document.createElement('div');
                withdrawnContent.className = 'withdrawn-message-content';
                withdrawnContent.textContent = '此消息已撤回';
                msgBody.appendChild(withdrawnContent);
            }
            
            // 移除操作菜单
            const actionsMenu = existingMsg.querySelector('.msg-actions');
            if (actionsMenu) {
                actionsMenu.remove();
            }
            
            updated = true;
        }
        
        // 更新已销毁状态 - 只针对阅后即焚消息
        if (msg.is_destroyed && !existingMsg.classList.contains('destroyed-msg')) {
            existingMsg.classList.add('destroyed-msg');
            const msgBody = existingMsg.querySelector('.msg-body');
            if (msgBody) {
                // 保留回复预览部分
                const replyPreview = msgBody.querySelector('.reply-preview');
                
                // 检查消息类型
                let destroyedText = '此消息已销毁';
                
                // 根据消息类型设置不同的销毁提示
                if (msg.type === 'img') {
                    destroyedText = '[图片已销毁]';
                } else if (msg.type === 'voice') {
                    destroyedText = '[语音已销毁]';
                } else if (msg.content === '[图片已销毁]') {
                    destroyedText = '[图片已销毁]';
                } else if (msg.content === '[语音已销毁]') {
                    destroyedText = '[语音已销毁]';
                }
                
                msgBody.innerHTML = '';
                if (replyPreview) {
                    msgBody.appendChild(replyPreview);
                }
                
                const destroyedContent = document.createElement('div');
                destroyedContent.className = 'destroyed-message-content';
                destroyedContent.textContent = destroyedText;
                msgBody.appendChild(destroyedContent);
            }
            
            // 移除计时器
            const timer = existingMsg.querySelector('.msg-timer');
            if (timer) {
                timer.remove();
            }
            
            updated = true;
        }
        
        // 处理对方的阅后即焚消息，确保当您查看时不会持续闪烁
        const isEphemeralMsg = msg.type === 'ephemeral' || (msg.is_ephemeral === true);
        const isOtherMsg = existingMsg.classList.contains('other-msg');
        
        if (isEphemeralMsg && isOtherMsg && !existingMsg.dataset.readProcessed) {
            // 标记为已处理，阻止闪烁
            existingMsg.dataset.readProcessed = 'true';
            
            // 停止任何正在进行的闪烁
            existingMsg.classList.remove('highlight');
            if (existingMsg.dataset.blinkInterval) {
                clearInterval(parseInt(existingMsg.dataset.blinkInterval));
                delete existingMsg.dataset.blinkInterval;
            }
            delete existingMsg.dataset.blinking;
            console.log(`停止对方阅后即焚消息 ${msgId} 的闪烁效果`);
            
            updated = true;
        }
        
        // 更新已读状态 - 适用于所有消息类型
        if (Array.isArray(msg.read_by) && msg.read_by.length > 0) {
            const hasReadStatus = existingMsg.querySelector('.msg-read-status');
            const isMyMsg = existingMsg.classList.contains('my-msg');
            
            // 只有自己发的消息才显示已读状态
            if (isMyMsg) {
                // 检查是否已有已读状态显示或数量有变化
                if (!hasReadStatus || (hasReadStatus && hasReadStatus.textContent !== `已读 (${msg.read_by.length})`)) {
                    console.log(`更新消息 ${msgId} 的已读状态: ${msg.read_by.length} 人已读`);
                    if (hasReadStatus) {
                        hasReadStatus.textContent = `已读 (${msg.read_by.length})`;
                    } else {
                        const readStatus = document.createElement('div');
                        readStatus.className = 'msg-read-status';
                        readStatus.textContent = `已读 (${msg.read_by.length})`;
                        existingMsg.appendChild(readStatus);
                    }
                    
                    // 如果是阅后即焚消息，确保在更新已读状态时不会导致闪烁
                    if (existingMsg.classList.contains('ephemeral-msg')) {
                        // 防止任何潜在的闪烁
                        existingMsg.classList.remove('highlight');
                        // 标记消息为已处理，防止其他函数重新应用闪烁效果
                        existingMsg.dataset.readProcessed = 'true';
                        // 停止任何正在进行的闪烁
                        if (existingMsg.dataset.blinkInterval) {
                            clearInterval(parseInt(existingMsg.dataset.blinkInterval));
                            delete existingMsg.dataset.blinkInterval;
                        }
                        console.log(`阅后即焚消息 ${msgId} 已读，停止任何闪烁效果`);
                    }
                    
                    updated = true;
                }
            }
        }
        
        // 更新计时器 - 对于阅后即焚消息，如果有过期时间且未销毁
        if (isEphemeralMsg && msg.expiry_time && !msg.is_destroyed) {
            let timer = existingMsg.querySelector('.msg-timer');
            const now = Math.floor(Date.now() / 1000);
            const remainingTime = Math.max(0, msg.expiry_time - now);
            
            // 检查是否需要创建或更新计时器
            if (!timer && remainingTime > 0) {
                // 如果不存在计时器元素，创建一个
                timer = document.createElement('div');
                timer.className = 'msg-timer';
                timer.setAttribute('data-expiry', msg.expiry_time);
                timer.textContent = `${remainingTime}s`;
                const msgBody = existingMsg.querySelector('.msg-body');
                if (msgBody) {
                    msgBody.parentNode.insertBefore(timer, msgBody.nextSibling);
                }
                updated = true;
            } else if (timer) {
                // 更新现有计时器
                timer.setAttribute('data-expiry', msg.expiry_time);
                timer.textContent = `${remainingTime}s`;
                updated = true;
            }
        }
    } else if (msgId > lastMsgId) {
        // 消息不存在，且ID大于最后一条消息ID，添加新消息
        // 移除本地临时消息
        const localMsgs = chatContainer.querySelectorAll('.msg[data-local="1"]');
        localMsgs.forEach(e => e.remove());
        const msgHtml = formatMessageJS(msg);
        chatContainer.insertAdjacentHTML('beforeend', msgHtml);
        
        // 获取新添加的消息元素
        const newMsg = chatContainer.lastElementChild;
        
        // 确定是否是对方的阅后即焚消息
        const isEphemeral = msg.type === 'ephemeral' || (msg.is_ephemeral === true);
        const isOtherMsg = msg.name.trim().toLowerCase() !== currentUser.trim().toLowerCase();
        const isSysMsg = msg.type === 'sys';
        
        // 对特殊消息类型进行闪烁处理
        if (isEphemeral || isSysMsg) {
            // 检查是否需要应用闪烁效果
            // 1. 不是已处理的已读消息
            // 2. 不是正在闪烁的消息
            if (!newMsg.dataset.readProcessed && !newMsg.dataset.blinking) {
                // 添加闪烁类，但不启动持续闪烁
                newMsg.classList.add('highlight');
                newMsg.dataset.blinking = 'true';
                
                // 创建只闪烁三次的效果
                let blinkCount = 0;
                const maxBlinks = 3;
                const blinkInterval = setInterval(() => {
                    newMsg.classList.toggle('highlight');
                    blinkCount++;
                    if (blinkCount >= maxBlinks * 2) { // *2是因为每次闪烁包含添加和移除两步
                        clearInterval(blinkInterval);
                        newMsg.classList.remove('highlight');
                        delete newMsg.dataset.blinking;
                        delete newMsg.dataset.blinkInterval;
                        
                        // 如果是对方的阅后即焚消息，标记为已处理以避免后续闪烁
                        if (isEphemeral && isOtherMsg) {
                            newMsg.dataset.readProcessed = 'true';
                            console.log(`对方的新阅后即焚消息 ${msgId} 闪烁结束，标记为已处理`);
                        }
                    }
                }, 500);
                
                // 保存闪烁计时器ID，以便在需要时可以停止闪烁
                newMsg.dataset.blinkInterval = blinkInterval;
            }
        }
        
        newMsgCount++;
        updated = true;
        
        // 更新最后一条消息ID
        lastMsgId = Math.max(lastMsgId, parseFloat(msgId));
    }
    
    // 收集新消息中的用户名，用于更新头像和在线状态
    if (msg.username && !msg.username.includes('游客')) {
        newUsers.add(msg.username);
    }
    
    return updated;
}

// 检查是否接近底部
function isNearBottom() {
    if (!chatContainer) return true;
    const tolerance = 150; // 像素容差
    return chatContainer.scrollHeight - chatContainer.scrollTop - chatContainer.clientHeight < tolerance;
}

// 滚动到底部
function scrollToBottom() {
    if (!chatContainer) return;
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // 只在特殊情况下添加高亮效果，普通消息不需要闪烁
    const lastMsg = chatContainer.lastElementChild;
    if (lastMsg && (lastMsg.classList.contains('ephemeral-msg') || lastMsg.classList.contains('sys-msg'))) {
        // 检查是否需要应用闪烁效果
        // 1. 不是已处理的已读消息
        // 2. 不是正在闪烁的消息
        if (!lastMsg.dataset.readProcessed && !lastMsg.dataset.blinking) {
            lastMsg.classList.add('highlight');
            lastMsg.dataset.blinking = 'true';
            
            // 创建只闪烁三次的效果
            let blinkCount = 0;
            const maxBlinks = 3;
            const blinkInterval = setInterval(() => {
                lastMsg.classList.toggle('highlight');
                blinkCount++;
                if (blinkCount >= maxBlinks * 2) { // *2是因为每次闪烁包含添加和移除两步
                    clearInterval(blinkInterval);
                    lastMsg.classList.remove('highlight');
                    delete lastMsg.dataset.blinking;
                    delete lastMsg.dataset.blinkInterval;
                }
            }, 500);
            
            // 保存闪烁计时器ID，以便在需要时可以停止闪烁
            lastMsg.dataset.blinkInterval = blinkInterval;
        }
    }
}

function formatMessageJS(msg) {
    const isCurrent = msg.name.trim().toLowerCase() === currentUser.trim().toLowerCase();
    
    let msgClass = isCurrent ? 'my-msg' : 'other-msg';
    if (msg.type === 'sys') {
        msgClass = 'sys-msg';
    }
    
    // 阅后即焚消息样式
    const isEphemeral = msg.type === 'ephemeral' || (msg.is_ephemeral === true);
    if (isEphemeral) {
        msgClass += ' ephemeral-msg';
        if (msg.is_destroyed) {
            msgClass += ' destroyed-msg';
        }
    }
    
    // 系统消息直接返回
    if (msg.type === 'sys') {
        return `
        <div class="msg ${msgClass}" data-id="${msg.id}">
            <div class="msg-body">${msg.content}</div>
        </div>`;
    }
    
    let content = '';
    
    if(msg.type === 'voice') {
        const parts = msg.content.split('|');
        const url = parts[0] || '';
        const duration = parseFloat(parts[1] || '0');
        
        content = `
            <div class="voice-message" onclick="playVoice('${url}', this)">
                <div class="voice-icon"></div>
                <div class="voice-waves">
                    ${Array(5).fill('<div class="voice-wave"></div>').join('')}
                </div>
                <span class="voice-duration">${duration > 0 ? Math.ceil(duration) + '″' : '点击播放'}</span>
            </div>`;
    } else if(msg.type === 'img') {
        // 检查是否是销毁后的阅后即焚图片
        if (msg.is_destroyed) {
            content = `<div class="destroyed-message-content">[图片已销毁]</div>`;
        } else {
            content = `<img src="${msg.content}" class="chat-image">`;
        }
    } else if(msg.type === 'video') {
        // 处理视频消息
        // 检查是否为直接视频链接
        if (isDirectVideoLink(msg.content)) {
            content = `
                <div class="video-message direct-video">
                    <video controls preload="metadata" class="direct-video-player">
                        <source src="${msg.content}" type="video/mp4">
                        您的浏览器不支持视频播放。<a href="${msg.content}" target="_blank">点击下载</a>
                    </video>
                </div>`;
        } else {
            // 外部视频网站链接
            content = `
                <div class="video-message">
                    <div class="video-link-wrapper">
                        <a href="${msg.content}" target="_blank" class="video-link">
                            <div class="video-icon">🎬</div>
                            <div class="video-title">点击观看视频</div>
                        </a>
                    </div>
                </div>`;
        }
    } else if(msg.type === 'html') {
        // 直接使用HTML内容，不做额外处理
        content = msg.content;
    } else {
        // 强化自定义表情正则，兼容各种情况
        console.log('formatMessageJS收到的msg.content:', msg.content);
        let rawContent = msg.content;
        if (typeof rawContent === 'string') {
            rawContent = rawContent.replace(/&amp;/g, '&')
                                   .replace(/&lt;/g, '<')
                                   .replace(/&gt;/g, '>')
                                   .replace(/&quot;/g, '"')
                                   .replace(/&#39;/g, "'");
        }
        content = rawContent.replace(/\[emoji\]([\s\S]*?)\[\/emoji\]/gi, function(match, p1) {
            return '<img src="' + p1.trim() + '" class="custom-emoji-in-message" alt="表情">';
        }).replace(/\n/g, '<br>');
    }

    // 处理回复消息
    let replyHtml = '';
    if (msg.reply_to) {
        // 从现有消息中查找被回复的消息
        const replyMsg = findMessageInDom(msg.reply_to);
        if (replyMsg) {
            let replyContent = '';
            if (replyMsg.querySelector('.chat-image')) {
                replyContent = '[图片]';
            } else if (replyMsg.querySelector('.voice-message')) {
                replyContent = '[语音]';
            } else {
                replyContent = replyMsg.querySelector('.msg-body').textContent.trim().substring(0, 20) + '...';
            }
            
            const replySender = replyMsg.querySelector('.sender')?.textContent || '未知用户';
            
            replyHtml = `
                <div class="reply-preview" data-reply-id="${msg.reply_to}" onclick="scrollToMessage('${msg.reply_to}')">
                    <span class="reply-sender">回复 ${replySender}：</span>
                    <span class="reply-content">${replyContent}</span>
                </div>`;
        }
    }
    
    // 获取用户头像URL
    let avatarUrl = `/avatars/default.png`; // 默认头像
    
    // 检查头像缓存
    if (avatarCache[msg.name]) {
        avatarUrl = avatarCache[msg.name];
    } else {
        // 当前用户的头像
        if (isCurrent) {
            const currentAvatar = document.querySelector('.upload-avatar');
            if (currentAvatar && currentAvatar.style.backgroundImage) {
                // 从当前页面的头像元素获取URL
                const bgImage = currentAvatar.style.backgroundImage;
                avatarUrl = bgImage.replace(/^url\(['"](.+?)['"]\)$/, '$1');
                
                // 缓存头像
                avatarCache[msg.name] = avatarUrl;
            }
        } else {
            // 如果是其他用户，尝试从页面上已有的消息查找头像
            const existingMsgs = document.querySelectorAll(`.msg:not(.sys-msg)`);
            let foundAvatar = false;
            
            for (const existingMsg of existingMsgs) {
                const sender = existingMsg.querySelector('.sender')?.textContent;
                if (sender === msg.name) {
                    const avatar = existingMsg.querySelector('.msg-avatar');
                    if (avatar && avatar.style.backgroundImage) {
                        avatarUrl = avatar.style.backgroundImage.replace(/^url\(['"](.+?)['"]\)$/, '$1');
                        
                        // 缓存头像
                        avatarCache[msg.name] = avatarUrl;
                        foundAvatar = true;
                        break;
                    }
                }
            }
            
            // 如果在DOM中没有找到头像，主动向服务器请求
            if (!foundAvatar) {
                // 用户头像请求是异步的，先生成消息，头像后续更新
                setTimeout(() => {
                    fetch(`?action=get_user_avatar&username=${encodeURIComponent(msg.name)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`服务器响应错误: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('获取用户头像失败:', data.error);
                                return;
                            }
                            
                            if (data.avatar) {
                                // 更新缓存
                                avatarCache[msg.name] = data.avatar;
                                
                                // 更新DOM中所有该用户的消息头像
                                const userMessages = document.querySelectorAll(`.msg:not(.sys-msg)`);
                                userMessages.forEach(msgElem => {
                                    const sender = msgElem.querySelector('.sender')?.textContent;
                                    if (sender === msg.name) {
                                        const avatar = msgElem.querySelector('.msg-avatar');
                                        if (avatar) {
                                            avatar.style.backgroundImage = `url('${data.avatar}')`;
                                        }
                                    }
                                });
                            }
                        })
                        .catch(error => {
                            console.error('获取用户头像失败:', error);
                        });
                }, 50);
            }
        }
    }
    
    const actionMenu = isCurrent ? `
        <div class="msg-actions">
            <div class="msg-action-btn" onclick="toggleMsgMenu(event, '${msg.id}')">⋮</div>
            <div class="msg-actions-menu" id="menu-${msg.id}">
                <div class="msg-menu-item danger" onclick="withdrawMessage('${msg.id}')">撤回消息</div>
            </div>
        </div>` : '';
    
    // 添加已读状态
    let readStatusHtml = '';
    if (isCurrent && Array.isArray(msg.read_by) && msg.read_by.length > 0) {
        readStatusHtml = `<div class="msg-read-status">已读 (${msg.read_by.length})</div>`;
    }
    
    // 阅后即焚消息的计时器
    let timerHtml = '';
    if (isEphemeral && isCurrent && msg.expiry_time && !msg.is_destroyed) {
        const now = Math.floor(Date.now() / 1000);
        const remainingTime = Math.max(0, msg.expiry_time - now);
        if (remainingTime > 0) {
            timerHtml = `<div class="msg-timer" data-expiry="${msg.expiry_time}">${remainingTime}s</div>`;
        }
    }
    
    // 阅后即焚图标
    const ephemeralIcon = isEphemeral ? '<div class="ephemeral-icon">🔥</div>' : '';
    
    // 在创建content变量的逻辑后，添加对已销毁消息的处理
    if (msg.is_destroyed) {
        content = '<div class="destroyed-message-content">此消息已销毁</div>';
    }
    
    return `
    <div class="msg ${msgClass}" 
         data-id="${msg.id}"
         onclick="handleMessageClick(this, event)">
        <div class="msg-head">
            <div class="msg-avatar" style="background-image: url('${avatarUrl}')"></div>
            <div class="msg-info">
                <span class="sender">${msg.name}</span>
                <span class="time">${msg.time}</span>
                ${ephemeralIcon}
            </div>
        </div>
        <div class="msg-body">
            ${replyHtml}
            ${content}
            ${timerHtml}
        </div>
        ${readStatusHtml}
        ${actionMenu}
    </div>`;
}

// 在DOM中查找消息
function findMessageInDom(msgId) {
    if (!msgId) return null;
    return document.querySelector(`.msg[data-id="${msgId}"]`);
}

/**
 * 用户登录
 */
function login() {
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const errorDiv = document.getElementById('login-error');
    
    if(!username || !password) {
        showLoginError('请输入用户名和密码');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('username', username);
    formData.append('password', password);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else if(data.error) {
            showLoginError(data.error);
        }
    })
    .catch(error => {
        console.error('登录失败:', error);
        showLoginError('网络错误，请重试');
    });
}

/**
 * 用户注册
 */
function register() {
    const username = document.getElementById('register-username').value.trim();
    const password = document.getElementById('register-password').value;
    const confirmPassword = document.getElementById('register-confirm-password').value;
    const displayName = document.getElementById('register-display-name').value.trim();
    const errorDiv = document.getElementById('register-error');
    
    if(!username || !password || !confirmPassword || !displayName) {
        showRegisterError('所有字段都必须填写');
        return;
    }
    
    if(password !== confirmPassword) {
        showRegisterError('两次密码输入不一致');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'register');
    formData.append('username', username);
    formData.append('password', password);
    formData.append('confirm_password', confirmPassword);
    formData.append('display_name', displayName);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            location.reload();
        } else if(data.error) {
            showRegisterError(data.error);
        }
    })
    .catch(error => {
        console.error('注册失败:', error);
        showRegisterError('网络错误，请重试');
    });
}

/**
 * 显示登录表单的错误信息
 */
function showLoginError(message) {
    const errorDiv = document.getElementById('login-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    
    // 添加抖动效果
    const loginForm = document.getElementById('login-form');
    loginForm.classList.add('shake');
    setTimeout(() => {
        loginForm.classList.remove('shake');
    }, 500);
}

/**
 * 显示注册表单的错误信息
 */
function showRegisterError(message) {
    const errorDiv = document.getElementById('register-error');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
    
    // 添加抖动效果
    const registerForm = document.getElementById('register-form');
    registerForm.classList.add('shake');
    setTimeout(() => {
        registerForm.classList.remove('shake');
    }, 500);
}

/**
 * 切换登录/注册表单
 */
function switchForm(formType) {
    // 隐藏所有表单内容
    document.querySelectorAll('.form-content').forEach(form => {
        form.classList.remove('active');
    });
    
    // 移除所有标签激活状态
    document.querySelectorAll('.form-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // 显示选定的表单
    document.getElementById(formType + '-form').classList.add('active');
    
    // 激活对应的标签
    document.getElementById(formType + '-tab').classList.add('active');
    
    // 隐藏错误信息
    document.getElementById('login-error').style.display = 'none';
    document.getElementById('register-error').style.display = 'none';
}

/**
 * 登出
 */
function logout() {
    if(!confirm('确定要退出登录吗？')) return;
    
    const formData = new FormData();
    formData.append('action', 'logout');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(() => {
        location.reload();
    })
    .catch(error => {
        console.error('登出失败:', error);
        alert('登出失败，请重试');
    });
}

/**
 * 在发送消息后监控已读状态变化
 * 每隔短时间主动获取一次最新消息，以获取已读状态更新
 */
function monitorReadStatus() {
    console.log('开始监控已读状态变化');
    
    // 连续监控5次，每次间隔1秒
    let count = 0;
    const maxAttempts = 5;
    const interval = 1000; // 1秒
    
    function checkStatus() {
        if (count >= maxAttempts) {
            console.log('已读状态监控结束');
            return;
        }
        
        console.log(`监控已读状态: 第 ${count + 1} 次尝试`);
        fetchLatestMessages();
        count++;
        setTimeout(checkStatus, interval);
    }
    
    // 开始监控
    setTimeout(checkStatus, interval);
}

function sendMsg() {
    if(isSending) return;
    isSending = true;
    
    const form = new FormData();
    const fileInput = document.getElementById('image');
    const file = fileInput ? fileInput.files[0] : null;
    const messageInput = document.getElementById('message');
    
    if(file) {
        form.append('image', file);
        // 如果是阅后即焚模式，设置消息类型
        if (isEphemeralMode) {
            form.append('message_type', 'ephemeral');
        }
    } else {
        const msg = messageInput ? messageInput.value.trim() : '';
        if(!msg) {
            isSending = false;
            return;
        }
        form.append('msg', msg);
        // 如果是阅后即焚模式，设置消息类型
        if (isEphemeralMode) {
            form.append('message_type', 'ephemeral');
        }
    }
    
    if(replyTo) {
        form.append('reply_to', replyTo);
    }
    
    fetch('', {
        method: 'POST',
        body: form
    })
    .then(response => {
        // 处理非JSON响应的情况
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            // 非JSON响应也视为成功
            return { success: true };
        }
    })
    .then(data => {
        if(messageInput) {
            // 立即本地渲染新消息
            const msg = messageInput.value.trim();
            console.log('输入框内容:', msg);
            if (msg) {
                const now = new Date();
                const time = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                const localMsg = {
                    id: Date.now(), // 临时ID
                    name: currentUser,
                    time: time,
                    type: isEphemeralMode ? 'ephemeral' : 'text',
                    content: msg,
                    read_by: [],
                    local: true // 标记本地消息
                };
                const html = formatMessageJS(localMsg);
                // 插入时加data-local=1
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const msgElem = tempDiv.firstElementChild;
                if (msgElem) msgElem.setAttribute('data-local', '1');
                chatContainer.appendChild(msgElem);
                scrollToBottom();
            }
            messageInput.value = ''; // Clear the input
            messageInput.focus(); // 添加这一行，发送后保持焦点
        }
        if(fileInput) fileInput.value = '';
        if(messageInput) messageInput.setAttribute('placeholder', '我是输入框🤗');
        replyTo = null;
        autoScroll = true;
        fetchLatestMessages();
        monitorReadStatus();
        console.log('消息发送成功:', data);
    })
    .catch(error => {
        console.error('发送消息失败:', error);
        // 移除错误提示，因为一般情况下消息仍然能成功发送
        // alert('发送失败，请重试');
    })
    .finally(() => {
        isSending = false;
    });
}

function pushMessage(pushType) {
    if(!confirm(`确认要发送${pushType === 'manager' ? '管理员' : '技术员'}请求吗？`)) return;
    
    const formData = new FormData();
    formData.append('push_action', pushType);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(response => {
        if(response.code === 200) {
            alert('请求已发送');
        } else {
            alert('发送失败: ' + (response.msg || '服务器错误'));
        }
    })
    .catch(() => alert('网络请求失败'));
}

async function toggleRecording() {
    const recordBtn = document.getElementById('recordBtn');
    
    if(!mediaRecorder) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            
            mediaRecorder.ondataavailable = (e) => {
                audioChunks.push(e.data);
            };
            
            mediaRecorder.onstop = async () => {
                const duration = (Date.now() - startTime) / 1000;
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                
                const formData = new FormData();
                formData.append('voice', audioBlob, 'recording.webm');
                formData.append('duration', duration.toString());
                
                // 如果是阅后即焚模式，添加标记
                if (isEphemeralMode) {
                    formData.append('message_type', 'ephemeral');
                }
                
                if (replyTo) {
                    formData.append('reply_to', replyTo);
                }
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if(data.error) {
                        throw new Error(data.error);
                    }
                    
                    loadNewMessages();
                    
                    // 重置回复状态
                    if (replyTo) {
                        const message = document.getElementById('message');
                        if (message) {
                            message.setAttribute('placeholder', '我是输入框🤗');
                        }
                        replyTo = null;
                    }
                    
                } catch(err) {
                    alert('语音发送失败：' + (err.message || '未知错误'));
                }
                
                audioChunks = [];
                recordBtn.classList.remove('recording');
                recordBtn.textContent = '语音';
            };
            
            mediaRecorder.start();
            startTime = Date.now();
            recordBtn.classList.add('recording');
            recordBtn.textContent = '停止';
        } catch(err) {
            alert('无法访问麦克风：' + err.message);
        }
    } else {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        mediaRecorder = null;
    }
}

function playVoice(url, element) {
    if(currentAudio) {
        currentAudio.pause();
        currentAudio = null;
    }
    
    const audio = new Audio(url);
    audio.type = 'audio/webm;codecs=opus';  // 设置正确的音频类型
    currentAudio = audio;
    
    const waves = element.querySelectorAll('.voice-wave');
    const durationSpan = element.querySelector('.voice-duration');
    const originalDuration = durationSpan.textContent;
    
    waves.forEach(wave => wave.classList.add('active'));
    durationSpan.textContent = '播放中...';
    
    audio.onended = () => {
        waves.forEach(wave => wave.classList.remove('active'));
        durationSpan.textContent = originalDuration;
        currentAudio = null;
    };
    
    audio.onerror = (err) => {
        console.error('音频播放错误:', err);
        waves.forEach(wave => wave.classList.remove('active'));
        durationSpan.textContent = originalDuration;
        currentAudio = null;
        alert('播放失败：' + (err.message || '未知错误'));
    };
    
    audio.play().catch(err => {
        console.error('音频播放错误:', err);
        waves.forEach(wave => wave.classList.remove('active'));
        durationSpan.textContent = originalDuration;
        currentAudio = null;
        alert('播放失败：' + (err.message || '未知错误'));
    });
}

function handleMessageClick(msgElement, event) {
    // 如果点击的是回复预览或者系统消息或者点击的是已销毁消息，不处理
    if (event.target.closest('.reply-preview') || 
        msgElement.classList.contains('sys-msg') || 
        msgElement.classList.contains('destroyed-msg')) {
        return;
    }
    
    // 如果点击的是消息操作按钮或菜单，不处理回复
    if (event.target.closest('.msg-actions') || 
        event.target.closest('.msg-actions-menu') ||
        event.target.closest('.destroyed-message-content')) {
        return;
    }
    
    // 如果是语音消息或图片消息，不允许回复
    if (msgElement.querySelector('.voice-message') || 
        msgElement.querySelector('.chat-image') ||
        msgElement.querySelector('.video-message')) {
        return;
    }
    
    const msgId = msgElement.dataset.id;
    replyTo = msgId;
    
    // 显示回复提示，但不复制内容到输入框
    const message = document.getElementById('message');
    if (message) {
        message.setAttribute('placeholder', '回复消息...');
    message.focus();
    }
}

function scrollToMessage(msgId) {
    const targetMsg = document.querySelector(`.msg[data-id="${msgId}"]`);
    if(targetMsg) {
        targetMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // 检查是否需要应用闪烁效果
        // 1. 不是已处理的已读消息
        // 2. 不是正在闪烁的消息
        if (!targetMsg.dataset.readProcessed && !targetMsg.dataset.blinking) {
            targetMsg.classList.add('highlight');
            targetMsg.dataset.blinking = 'true';
            
            // 创建只闪烁三次的效果
            let blinkCount = 0;
            const maxBlinks = 3;
            const blinkInterval = setInterval(() => {
                targetMsg.classList.toggle('highlight');
                blinkCount++;
                if (blinkCount >= maxBlinks * 2) {
                    clearInterval(blinkInterval);
                    targetMsg.classList.remove('highlight');
                    delete targetMsg.dataset.blinking;
                    delete targetMsg.dataset.blinkInterval;
                }
            }, 500);
            
            // 保存闪烁计时器ID，以便在需要时可以停止闪烁
            targetMsg.dataset.blinkInterval = blinkInterval;
        }
    }
}

/**
 * 上传用户头像
 */
function uploadAvatar(e) {
    const avatarInput = e.target;
    if (!avatarInput.files || !avatarInput.files[0]) return;
    
    const formData = new FormData();
    formData.append('avatar', avatarInput.files[0]);
    
    // 显示上传中状态
    const avatarElement = document.querySelector('.upload-avatar');
    const originalStyle = avatarElement.style.backgroundImage;
    avatarElement.style.backgroundImage = 'none';
    avatarElement.innerHTML = `
        <div style="display:flex; justify-content:center; align-items:center; height:100%; color:white;">
            <span class="loading-spinner">⏳</span>
        </div>
    `;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.avatar) {
            // 更新头像
            const newAvatarUrl = `${data.avatar}?t=${Date.now()}`;
            avatarElement.style.backgroundImage = `url('${newAvatarUrl}')`;
            avatarElement.innerHTML = `
                <div class="avatar-overlay">
                    <span class="avatar-icon">📷</span>
                </div>
                <input type="file" id="avatarInput" class="avatar-input" accept="image/*">
            `;
            
            // 更新头像缓存
            if(currentUser) {
                avatarCache[currentUser] = newAvatarUrl;
            }
            
            // 重新绑定事件
            document.getElementById('avatarInput').addEventListener('change', uploadAvatar);
        } else if (data.error) {
            alert('上传失败: ' + data.error);
            // 恢复原始头像
            avatarElement.style.backgroundImage = originalStyle;
            avatarElement.innerHTML = `
                <div class="avatar-overlay">
                    <span class="avatar-icon">📷</span>
                </div>
                <input type="file" id="avatarInput" class="avatar-input" accept="image/*">
            `;
            
            // 重新绑定事件
            document.getElementById('avatarInput').addEventListener('change', uploadAvatar);
        }
    })
    .catch(error => {
        console.error('上传头像失败:', error);
        alert('上传头像失败，请重试');
        
        // 恢复原始头像
        avatarElement.style.backgroundImage = originalStyle;
        avatarElement.innerHTML = `
            <div class="avatar-overlay">
                <span class="avatar-icon">📷</span>
            </div>
            <input type="file" id="avatarInput" class="avatar-input" accept="image/*">
        `;
        
        // 重新绑定事件
        document.getElementById('avatarInput').addEventListener('change', uploadAvatar);
    });
}

// 收集所有已有消息的头像信息到缓存
function collectAvatars() {
    const messages = document.querySelectorAll('.msg:not(.sys-msg)');
    messages.forEach(msg => {
        const sender = msg.querySelector('.sender')?.textContent;
        const avatar = msg.querySelector('.msg-avatar');
        
        if (sender && avatar && avatar.style.backgroundImage) {
            const avatarUrl = avatar.style.backgroundImage.replace(/^url\(['"](.+?)['"]\)$/, '$1');
            avatarCache[sender] = avatarUrl;
        }
    });
    
    // 添加当前用户头像到缓存
    if (currentUser) {
        const currentAvatar = document.querySelector('.upload-avatar');
        if (currentAvatar && currentAvatar.style.backgroundImage) {
            const bgImage = currentAvatar.style.backgroundImage;
            const avatarUrl = bgImage.replace(/^url\(['"](.+?)['"]\)$/, '$1');
            avatarCache[currentUser] = avatarUrl;
        }
    }
}

// 显示视频链接输入对话框
function showVideoDialog() {
    const videoDialog = document.getElementById('videoDialog');
    if (videoDialog) {
        videoDialog.style.display = 'flex';
        document.getElementById('videoLink').focus();
    }
}

// 隐藏视频链接输入对话框
function hideVideoDialog() {
    const videoDialog = document.getElementById('videoDialog');
    const videoLink = document.getElementById('videoLink');
    if (videoDialog) {
        videoDialog.style.display = 'none';
        if (videoLink) {
            videoLink.value = '';
        }
    }
}

// 提交视频链接
function submitVideoLink() {
    const videoLink = document.getElementById('videoLink').value.trim();
    if (!videoLink) {
        alert('请输入视频链接');
        return;
    }
    
    // 验证视频链接是否合法
    if (!isValidVideoLink(videoLink)) {
        alert('请输入有效的视频链接（支持bilibili、优酷、腾讯视频或直接的.mp4等视频链接）');
        return;
    }
    
    hideVideoDialog();
    
    // 发送视频链接消息
    const formData = new FormData();
    formData.append('video', videoLink);
    
    if (replyTo) {
        formData.append('reply_to', replyTo);
    }
    
    // 如果是阅后即焚模式，添加标记
    if (isEphemeralMode) {
        formData.append('message_type', 'ephemeral');
    }
    
    isSending = true; // 设置发送中状态
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // 处理非JSON响应
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return response.json();
        } else {
            return { success: true };
        }
    })
    .then(data => {
        if (replyTo) {
            const message = document.getElementById('message');
            if (message) {
                message.setAttribute('placeholder', '我是输入框🤗');
            }
            replyTo = null;
        }
        // 发送消息后强制设置自动滚动为true
        autoScroll = true;
        // 立即获取新消息
        fetchLatestMessages();
        
        // 开始监控已读状态变化
        monitorReadStatus();
    })
    .catch(error => {
        console.error('发送视频消息失败:', error);
    })
    .finally(() => {
        isSending = false;
    });
}

// 验证视频链接
function isValidVideoLink(url) {
    // 支持主流视频网站
    const videoRegex = /^(https?:\/\/)?(www\.)?(bilibili\.com|youku\.com|v\.qq\.com|iqiyi\.com|mgtv\.com|youtube\.com|vimeo\.com).*$/i;
    
    // 直接视频链接格式检查 (.mp4, .webm, .ogg等)
    const directVideoRegex = /^https?:\/\/.*\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)(\?.*)?$/i;
    
    return videoRegex.test(url) || directVideoRegex.test(url);
}

// 检查是否是直接视频链接
function isDirectVideoLink(url) {
    return /^https?:\/\/.*\.(mp4|webm|ogg|mov|avi|wmv|flv|mkv)(\?.*)?$/i.test(url);
}

/**
 * 切换表情面板显示状态
 */
function toggleEmojiPanel() {
    const panel = document.getElementById('emojiPanel');
    if (panel) {
        panel.classList.toggle('active');
    }
}

/**
 * 插入表情到输入框
 * @param {string} emoji - 要插入的表情
 */
function insertEmoji(emoji) {
    const messageInput = document.getElementById('message');
    if (messageInput) {
        // 保存当前光标位置
        const start = messageInput.selectionStart;
        const end = messageInput.selectionEnd;
        const text = messageInput.value;
        
        // 在光标位置插入表情
        messageInput.value = text.substring(0, start) + emoji + text.substring(end);
        
        // 重新设置光标位置到表情后面
        messageInput.focus();
        messageInput.setSelectionRange(start + emoji.length, start + emoji.length);
        
        // 关闭表情面板
        const panel = document.getElementById('emojiPanel');
        if (panel) {
            panel.classList.remove('active');
        }
    }
}

/**
 * 切换消息操作菜单
 */
function toggleMsgMenu(event, messageId) {
    event.stopPropagation();
    
    // 关闭所有其他菜单
    closeAllMsgMenus();
    
    // 切换当前菜单
    const menu = document.getElementById(`menu-${messageId}`);
    if (menu) {
        menu.classList.toggle('show');
    }
}

/**
 * 关闭所有消息操作菜单
 */
function closeAllMsgMenus() {
    const menus = document.querySelectorAll('.msg-actions-menu');
    menus.forEach(menu => {
        menu.classList.remove('show');
    });
}

/**
 * 撤回消息
 */
function withdrawMessage(messageId) {
    if (!confirm('确定要撤回这条消息吗？')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'withdraw');
    formData.append('message_id', messageId);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 获取更新后的消息数据
            const updatedMsg = data.message;
            if (updatedMsg) {
                // 查找消息元素
                const msgElement = document.querySelector(`.msg[data-id="${messageId}"]`);
                if (msgElement) {
                    // 更新消息内容
                    const msgBody = msgElement.querySelector('.msg-body');
                    if (msgBody) {
                        // 保留回复预览部分
                        const replyPreview = msgBody.querySelector('.reply-preview');
                        msgBody.innerHTML = '';
                        if (replyPreview) {
                            msgBody.appendChild(replyPreview);
                        }
                        
                        // 添加撤回后的内容
                        const withdrawnContent = document.createElement('div');
                        withdrawnContent.className = 'withdrawn-message-content';
                        withdrawnContent.textContent = '此消息已撤回';
                        msgBody.appendChild(withdrawnContent);
                    }
                    
                    // 添加撤回样式
                    msgElement.classList.add('withdrawn-msg');
                    
                    // 移除操作菜单
                    const actionsMenu = msgElement.querySelector('.msg-actions');
                    if (actionsMenu) {
                        actionsMenu.remove();
                    }
                }
            }
            // 关闭菜单
            closeAllMsgMenus();
        } else if (data.error) {
            alert('撤回失败: ' + data.error);
        }
    })
    .catch(error => {
        console.error('撤回消息失败:', error);
        alert('撤回消息失败，请重试');
    });
}

// 发送自定义消息的函数
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
        return response.text().then(text => {
            console.log('响应内容:', text);
            try {
                // 尝试解析为JSON
                const data = JSON.parse(text);
                console.log('解析的JSON数据:', data);
                
                if (data.status === 'success') {
                    console.log('通话邀请消息发送成功');
                    // 立即刷新消息列表显示新消息
                    fetchLatestMessages();
                    
                    // 开始监控已读状态变化
                    monitorReadStatus();
                } else {
                    console.warn('发送失败:', data.message || '未知错误');
                }
                return data;
            } catch (jsonError) {
                console.error('解析JSON失败:', jsonError, '原始文本:', text);
                // 非JSON响应也视为成功
                return { status: 'success' };
            }
        });
    })
    .catch(error => {
        console.error('网络请求失败:', error);
    });
}

/**
 * 切换阅后即焚模式
 */
function toggleEphemeralMode() {
    const ephemeralMenuBtn = document.getElementById('ephemeralMenuBtn');
    isEphemeralMode = ephemeralMenuBtn ? ephemeralMenuBtn.checked : !isEphemeralMode;
    
    const messageInput = document.getElementById('message');
    
    if (isEphemeralMode) {
        // 启用阅后即焚模式
        if (ephemeralMenuBtn && !ephemeralMenuBtn.checked) {
            ephemeralMenuBtn.checked = true;
        }
        if (messageInput) {
            messageInput.classList.add('ephemeral-input');
            messageInput.setAttribute('placeholder', '阅后即焚模式 - 文字10秒/图片30秒后销毁');
        }
    } else {
        // 关闭阅后即焚模式
        if (ephemeralMenuBtn && ephemeralMenuBtn.checked) {
            ephemeralMenuBtn.checked = false;
        }
        if (messageInput) {
            messageInput.classList.remove('ephemeral-input');
            messageInput.setAttribute('placeholder', '我是输入框🤗');
        }
    }
}

/**
 * 更新阅后即焚消息计时器
 */
function updateEphemeralTimers() {
    const timers = document.querySelectorAll('.msg-timer');
    const now = Math.floor(Date.now() / 1000);
    let hasExpiredTimers = false;
    
    timers.forEach(timer => {
        const expiryTime = parseInt(timer.getAttribute('data-expiry'));
        const remainingTime = Math.max(0, expiryTime - now);
        
        if (remainingTime > 0) {
            timer.textContent = remainingTime + 's';
        } else {
            hasExpiredTimers = true;
            // 时间到，标记为已销毁
            timer.textContent = '已销毁';
            const msgElement = timer.closest('.msg');
            if (msgElement) {
                msgElement.classList.add('destroyed-msg');
                
                // 更新消息内容
                const msgBody = msgElement.querySelector('.msg-body');
                if (msgBody) {
                    // 检查消息类型
                    const hasImage = msgBody.querySelector('.chat-image');
                    const hasVoice = msgBody.querySelector('.voice-message');
                    
                    // 确定销毁消息类型
                    let destroyedText = '此消息已销毁';
                    if (hasImage || msgBody.textContent.includes('[图片已销毁]')) {
                        destroyedText = '[图片已销毁]';
                    } else if (hasVoice || msgBody.textContent.includes('[语音已销毁]')) {
                        destroyedText = '[语音已销毁]';
                    }
                    
                    // 保留回复预览
                    const replyPreview = msgBody.querySelector('.reply-preview');
                    msgBody.innerHTML = '';
                    if (replyPreview) {
                        msgBody.appendChild(replyPreview);
                    }
                    
                    const destroyedContent = document.createElement('div');
                    destroyedContent.className = 'destroyed-message-content';
                    destroyedContent.textContent = destroyedText;
                    msgBody.appendChild(destroyedContent);
                }
            }
        }
    });
    
    // 主动触发一次消息加载，确保服务器处理了过期的消息
    if (hasExpiredTimers) {
        fetchLatestMessages();
    }
}

/**
 * 主动获取最新消息（不改变轮询状态）
 */
function fetchLatestMessages() {
    // 构建URL，加入活跃状态参数
    const url = `?action=get&last=${lastMsgId}&is_active=${isWindowActive ? '1' : '0'}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                console.log('fetchLatestMessages 获取到 ' + data.length + ' 条新消息');
                const newUsers = new Set();
                let hasUpdates = false;
                
                data.forEach(msg => {
                    hasUpdates = processMessage(msg, newUsers) || hasUpdates;
                });
                
                // 如果有新消息或更新，并且用户在底部附近，滚动到底部
                if (hasUpdates && autoScroll) {
                    scrollToBottom();
                }
                
                // 更新在线用户列表
                if (newUsers.size > 0) {
                    updateOnlineUsers(Array.from(newUsers));
                }
            }
        })
        .catch(error => {
            console.error('获取最新消息错误:', error);
        });
}

/**
 * 初始化页面上所有阅后即焚消息的状态
 */
function initializeEphemeralMessages() {
    console.log('初始化阅后即焚消息...');
    // 获取所有阅后即焚消息
    const ephemeralMessages = document.querySelectorAll('.ephemeral-msg');
    
    if (ephemeralMessages.length > 0) {
        console.log(`找到 ${ephemeralMessages.length} 条阅后即焚消息`);
        // 遍历所有阅后即焚消息
        ephemeralMessages.forEach(msg => {
            // 如果已经是销毁状态，确保样式正确
            if (msg.classList.contains('destroyed-msg')) {
                const msgBody = msg.querySelector('.msg-body');
                if (msgBody && !msgBody.querySelector('.destroyed-message-content')) {
                    // 检查消息类型
                    const hasImage = msgBody.querySelector('.chat-image');
                    const hasVoice = msgBody.querySelector('.voice-message');
                    
                    // 确定销毁消息类型
                    let destroyedText = '此消息已销毁';
                    if (hasImage || msgBody.textContent.includes('[图片已销毁]')) {
                        destroyedText = '[图片已销毁]';
                    } else if (hasVoice || msgBody.textContent.includes('[语音已销毁]')) {
                        destroyedText = '[语音已销毁]';
                    }
                    
                    // 保留回复预览
                    const replyPreview = msgBody.querySelector('.reply-preview');
                    msgBody.innerHTML = '';
                    if (replyPreview) {
                        msgBody.appendChild(replyPreview);
                    }
                    
                    const destroyedContent = document.createElement('div');
                    destroyedContent.className = 'destroyed-message-content';
                    destroyedContent.textContent = destroyedText;
                    msgBody.appendChild(destroyedContent);
                }
            }
            
            // 如果消息是自己发送的且有计时器，初始化计时器
            const timer = msg.querySelector('.msg-timer');
            if (timer) {
                const expiryTime = parseInt(timer.getAttribute('data-expiry'));
                const now = Math.floor(Date.now() / 1000);
                const remainingTime = Math.max(0, expiryTime - now);
                
                if (remainingTime > 0) {
                    timer.textContent = `${remainingTime}s`;
                    // 如果有过期时间，设置定时刷新
                    console.log(`阅后即焚消息 ${msg.getAttribute('data-id')} 剩余时间: ${remainingTime}s`);
                } else {
                    // 如果已过期但未标记为销毁，触发一次刷新
                    console.log(`阅后即焚消息 ${msg.getAttribute('data-id')} 已过期，触发刷新`);
                    fetchLatestMessages();
                }
            }
        });
        
        // 设置定期更新计时器
        setInterval(updateEphemeralTimers, 1000);
    }
}

/**
 * 更新在线用户列表和头像
 * @param {Array} usernames - 需要更新的用户名数组
 */
function updateOnlineUsers(usernames) {
    if (!usernames || usernames.length === 0) return;
    
    // 只在必要时向服务器请求头像
    usernames.forEach(username => {
        // 如果头像缓存中没有这个用户
        if (!avatarCache[username]) {
            // 向服务器请求用户头像
            fetch(`?action=get_user_avatar&username=${encodeURIComponent(username)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.avatar) {
                        // 更新头像缓存
                        avatarCache[username] = data.avatar;
                        
                        // 更新DOM中该用户的所有消息头像
                        document.querySelectorAll(`.msg:not(.sys-msg)`).forEach(msg => {
                            const sender = msg.querySelector('.sender')?.textContent;
                            if (sender === username) {
                                const avatar = msg.querySelector('.msg-avatar');
                                if (avatar) {
                                    avatar.style.backgroundImage = `url('${data.avatar}')`;
                                }
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('获取用户头像失败:', error);
                });
        }
    });
}

/**
 * 切换黑夜模式
 */
function toggleDarkMode() {
    // 切换暗色模式
    const isDarkModeEnabled = document.body.classList.toggle('dark-mode');
    
    // 更新图标
    const darkModeBtn = document.getElementById('darkModeBtn');
    if (darkModeBtn) {
        darkModeBtn.innerText = isDarkModeEnabled ? '☀️' : '🌙';
    }
    
    // 保存设置到本地存储
    localStorage.setItem('darkMode', isDarkModeEnabled);
}
  