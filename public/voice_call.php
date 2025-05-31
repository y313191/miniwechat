<?php
declare(strict_types=1);
session_start();

// 检查用户是否登录
if(empty($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

// 引入配置文件
require_once '../config/config.php';

// 获取房间ID
$roomId = isset($_GET['room']) ? htmlspecialchars($_GET['room']) : '';
if(empty($roomId)) {
    header('Location: ../index.php');
    exit;
}

// 确保目录存在
$voiceRoomDir = __DIR__ . '/../data/voice_rooms';
if(!is_dir($voiceRoomDir)) {
    mkdir($voiceRoomDir, 0755, true);
}

// 获取当前用户信息
$username = $_SESSION['username'];
$displayName = $_SESSION['display_name'] ?? $username;
$userAvatar = !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : 'default.png';

// 处理头像URL
if (strpos($userAvatar, 'http') === 0) {
    // 如果是完整的URL，直接使用
    $avatarUrl = $userAvatar;
} else {
    // 检查头像文件位置
    if (file_exists(__DIR__ . '/../avatars/' . $userAvatar)) {
        // 如果在avatars目录下
        $avatarUrl = '../avatars/' . $userAvatar;
        error_log("头像在avatars目录: " . $avatarUrl);
    } elseif (file_exists(__DIR__ . '/../uploads/avatars/' . $userAvatar)) {
        // 如果在uploads/avatars目录下
        $avatarUrl = '../uploads/avatars/' . $userAvatar;
        error_log("头像在uploads/avatars目录: " . $avatarUrl);
    } else {
        // 默认头像位置
        $avatarUrl = '../avatars/default.png';
        error_log("使用默认头像: " . $avatarUrl);
    }
}

// 调试输出
error_log("最终用户头像路径: " . $avatarUrl);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>语音通话 - <?= htmlspecialchars($roomId) ?></title>
    <style>
        :root {
            --bg-color: #1a1a2e;
            --header-bg: #16213e;
            --border-color: #0f3460;
            --text-color: white;
            --btn-bg: #0f3460;
            --btn-hover: #1a4b8c;
            --green: #4CAF50;
            --red: #f44336;
            --muted-text: #aaa;
        }
        
        .dark-mode {
            --bg-color: #121212;
            --header-bg: #1e1e1e;
            --border-color: #333;
            --text-color: #e4e6eb;
            --btn-bg: #333;
            --btn-hover: #444;
            --green: #4CAF50;
            --red: #f44336;
            --muted-text: #888;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .call-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .call-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .call-title {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .header-btn {
            padding: 8px 12px;
            background-color: var(--btn-bg);
            color: var(--text-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .header-btn:hover {
            background-color: var(--btn-hover);
        }
        
        .call-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }
        
        .participants-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
            max-width: 800px;
        }
        
        .participant {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .participant-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            position: relative;
            border: 2px solid var(--border-color);
        }
        
        .participant-name {
            font-size: 14px;
            max-width: 100px;
            text-align: center;
            word-break: break-word;
        }
        
        .speaking {
            border: 3px solid var(--green);
        }
        
        .muted::after {
            content: "🔇";
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .call-controls {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .control-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .mic-btn {
            background-color: var(--green);
        }
        
        .mic-btn.muted {
            background-color: var(--red);
        }
        
        .end-call-btn {
            background-color: var(--red);
        }
        
        .control-btn:hover {
            opacity: 0.9;
            transform: scale(1.05);
        }
        
        .room-id {
            margin-top: 20px;
            font-size: 14px;
            color: var(--muted-text);
        }
        
        .copy-btn {
            background: none;
            border: none;
            color: var(--green);
            cursor: pointer;
            margin-left: 10px;
        }
        
        #localAudio, #remoteAudio {
            display: none;
        }
        
        .connection-status {
            margin-top: 15px;
            color: var(--muted-text);
            font-size: 14px;
        }
        
        /* 黑夜模式切换按钮 */
        .dark-mode-btn {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 8px 12px;
            background-color: var(--btn-bg);
            color: var(--text-color);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            z-index: 100;
        }
        
        .dark-mode-btn:hover {
            background-color: var(--btn-hover);
        }
    </style>
</head>
<body>  
    <div class="call-container">
        <div class="call-header">
            <div class="call-title">语音通话</div>
            <div class="room-info">房间: <?= htmlspecialchars($roomId) ?></div>
            <button onclick="window.close()" class="header-btn">返回聊天</button>
        </div>
        
        <div class="call-body">
            <div class="participants-container" id="participants">
                <!-- 本地用户 -->
                <div class="participant local-participant">
                    <div class="participant-avatar" style="background-image: url('<?= $avatarUrl ?>');" id="localAvatar"></div>
                    <div class="participant-name"><?= htmlspecialchars($displayName) ?> (我)</div>
                </div>
                <!-- 远程用户会动态添加 -->
            </div>
            
            <p class="connection-status" id="connectionStatus">正在连接...</p>
            
            <div class="call-controls">
                <button class="control-btn mic-btn" id="micBtn" title="静音">🎤</button>
                <button class="control-btn end-call-btn" id="endCallBtn" title="结束通话">📞</button>
            </div>
            
            <div class="room-id">
                房间链接: <span id="roomLink"><?= htmlspecialchars($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?></span>
                <button class="copy-btn" id="copyBtn">复制</button>
            </div>
            
            <!-- 移动设备音频控制区域 -->
            <div id="mobileAudioControls" style="display: none; margin-top: 15px;">
                <p style="color: #aaa; font-size: 14px; margin-bottom: 10px;">如果您听不到声音，请点击下方按钮：</p>
                <button id="resumeAudioBtn" class="header-btn">启用音频播放</button>
            </div>
            
            <!-- 音频元素 -->
            <audio id="localAudio" autoplay muted></audio>
            <div id="remoteAudios"></div>
        </div>
    </div>
    
    <script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('语音通话页面已加载');
        
        // 黑夜模式切换功能
        const darkModeBtn = document.getElementById('darkModeBtn');
        if (darkModeBtn) {
            // 检查本地存储的暗色模式设置
            const isDarkMode = localStorage.getItem('darkMode') === 'true';
            
            // 根据保存的设置应用暗色模式
            if (isDarkMode) {
                document.body.classList.add('dark-mode');
                darkModeBtn.innerText = '☀️'; // 太阳图标表示可以切换到亮色模式
            }
            
            // 添加点击事件
            darkModeBtn.addEventListener('click', function() {
                // 切换暗色模式
                const isDarkModeEnabled = document.body.classList.toggle('dark-mode');
                
                // 更新图标
                darkModeBtn.innerText = isDarkModeEnabled ? '☀️' : '🌙';
                
                // 保存设置到本地存储
                localStorage.setItem('darkMode', isDarkModeEnabled);
            });
        }
        
        // 用户和房间信息
        const roomId = "<?= htmlspecialchars($roomId) ?>";
        const currentUser = {
            username: "<?= htmlspecialchars($username) ?>",
            displayName: "<?= htmlspecialchars($displayName) ?>",
            avatar: "<?= $avatarUrl ?>"
        };
        
        console.log('房间ID:', roomId);
        console.log('当前用户:', currentUser.username);
        console.log('当前用户头像URL:', currentUser.avatar);
        
        // 检测是否为移动设备
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        console.log('是否为移动设备:', isMobile ? '是' : '否');
        
        // 状态变量
        let isMuted = false;
        let myPeer = null;
        let myStream = null;
        let connections = {};
        let peers = {};
        
        // DOM元素
        const micBtn = document.getElementById('micBtn');
        const endCallBtn = document.getElementById('endCallBtn');
        const copyBtn = document.getElementById('copyBtn');
        const localAvatar = document.getElementById('localAvatar');
        const connectionStatus = document.getElementById('connectionStatus');
        const participantsContainer = document.getElementById('participants');
        const remoteAudios = document.getElementById('remoteAudios');
        const mobileAudioControls = document.getElementById('mobileAudioControls');
        const resumeAudioBtn = document.getElementById('resumeAudioBtn');
        
        // 初始化通话
        async function initializeCall() {
            try {
                console.log('正在初始化通话...');
                
                // 请求麦克风权限
                myStream = await navigator.mediaDevices.getUserMedia({ 
                    audio: true, 
                    video: false 
                });
                console.log('获取到麦克风权限');
                
                // 设置本地音频元素
                const localAudio = document.getElementById('localAudio');
                localAudio.srcObject = myStream;
                
                // 如果是移动设备，显示音频控制区域
                if (isMobile) {
                    mobileAudioControls.style.display = 'block';
                    
                    // 添加通用的音频解锁处理
                    resumeAudioBtn.addEventListener('click', function() {
                        console.log('用户点击了音频解锁按钮');
                        
                        // 尝试解锁所有音频元素
                        const allAudios = document.querySelectorAll('audio');
                        allAudios.forEach(audio => {
                            if (audio && audio.paused && !audio.muted) {
                                audio.play().then(() => {
                                    console.log(`解锁音频元素成功: ${audio.id}`);
                                }).catch(err => {
                                    console.error(`解锁音频元素失败: ${audio.id}`, err);
                                });
                            }
                        });
                        
                        // 尝试解锁AudioContext
                        if (window.sharedAudioContext && window.sharedAudioContext.state === 'suspended') {
                            window.sharedAudioContext.resume().then(() => {
                                console.log('AudioContext已解锁');
                            }).catch(err => {
                                console.error('解锁AudioContext失败:', err);
                            });
                        }
                        
                        // 闪烁按钮表示操作进行中
                        resumeAudioBtn.textContent = '正在启用...';
                        setTimeout(() => {
                            resumeAudioBtn.textContent = '启用音频播放';
                        }, 2000);
                    });
                    
                    // 在iOS上，创建并尝试立即解锁AudioContext
                    if (!window.sharedAudioContext) {
                        window.sharedAudioContext = new (window.AudioContext || window.webkitAudioContext)();
                        console.log('为移动设备创建AudioContext, 状态:', window.sharedAudioContext.state);
                    }
                }
                
                // 创建随机ID
                const randomId = Math.random().toString(36).substring(2, 7);
                const peerId = `${roomId}-${currentUser.username}-${randomId}`;
                console.log('生成Peer ID:', peerId);
                
                // 初始化PeerJS
                myPeer = new Peer(peerId);
                
                // 监听连接打开
                myPeer.on('open', (id) => {
                    console.log('Peer连接已打开, ID:', id);
                    connectionStatus.textContent = '已连接，等待其他人加入...';
                    joinRoom(id);
                });
                
                // 监听接入通话
                myPeer.on('call', (call) => {
                    console.log('收到通话请求:', call.peer);
                    call.answer(myStream);
                    
                    call.on('stream', (remoteStream) => {
                        console.log('收到远程流');
                        addRemoteStream(call.peer, remoteStream);
                    });
                    
                    call.on('close', () => {
                        console.log('通话结束:', call.peer);
                        removeParticipant(call.peer);
                    });
                    
                    call.on('error', (err) => {
                        console.error('通话错误:', err);
                    });
                    
                    peers[call.peer] = call;
                });
                
                // 监听连接
                myPeer.on('connection', (conn) => {
                    console.log('收到数据连接:', conn.peer);
                    setupConnection(conn);
                });
                
                // 监听错误
                myPeer.on('error', (err) => {
                    console.error('Peer连接错误:', err);
                    connectionStatus.textContent = '连接出错: ' + err.message;
                });
                
                // 监听断开
                myPeer.on('disconnected', () => {
                    console.log('Peer连接断开，尝试重连...');
                    connectionStatus.textContent = '连接断开，尝试重连...';
                    myPeer.reconnect();
                });
                
                // 延迟启动音频电平检测
                setTimeout(checkLocalAudio, 1000);
                
            } catch (err) {
                console.error('初始化失败:', err);
                connectionStatus.textContent = '无法访问麦克风，请检查权限设置';
            }
        }
        
        // 加入房间
        function joinRoom(myPeerId) {
            console.log('尝试加入房间:', roomId);
            
            // 获取房间内的现有用户
            fetch(`../includes/get_room_peers.php?room=${encodeURIComponent(roomId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`服务器返回: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(users => {
                    console.log('获取到房间用户:', users);
                    
                    // 更新自己在房间中的状态
                    updateRoomPeers(myPeerId);
                    
                    // 如果有其他用户，尝试连接
                    if (users && users.length > 0) {
                        users.forEach(user => {
                            if (user !== myPeerId) {
                                console.log('尝试连接到用户:', user);
                                connectToUser(user);
                            }
                        });
                    } else {
                        console.log('房间内没有其他用户');
                    }
                })
                .catch(err => {
                    console.error('获取房间用户失败:', err);
                    connectionStatus.textContent = '获取房间信息失败，但您仍可等待他人加入';
                });
        }
        
        // 更新房间用户信息
        function updateRoomPeers(myPeerId) {
            console.log('更新房间信息:', roomId, myPeerId);
            
            const formData = new FormData();
            formData.append('room', roomId);
            formData.append('peerId', myPeerId);
            formData.append('action', 'update');
            
            fetch('../includes/update_room_peers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`服务器返回: ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('房间信息更新成功:', data);
            })
            .catch(err => {
                console.error('更新房间信息失败:', err);
            });
        }
        
        // 连接到指定用户
        function connectToUser(userId) {
            console.log('开始连接到用户:', userId);
            
            try {
                // 创建数据连接
                const conn = myPeer.connect(userId, {
                    reliable: true
                });
                
                if (conn) {
                    setupConnection(conn);
                    
                    // 创建媒体连接
                    console.log('呼叫用户:', userId);
                    const call = myPeer.call(userId, myStream);
                    
                    if (call) {
                        call.on('stream', (remoteStream) => {
                            console.log('收到远程流:', userId);
                            addRemoteStream(userId, remoteStream);
                        });
                        
                        call.on('close', () => {
                            console.log('通话结束:', userId);
                            removeParticipant(userId);
                        });
                        
                        call.on('error', (err) => {
                            console.error('通话错误:', err);
                        });
                        
                        peers[userId] = call;
                    } else {
                        console.error('创建媒体连接失败');
                    }
                } else {
                    console.error('创建数据连接失败');
                }
            } catch (err) {
                console.error('连接用户失败:', userId, err);
            }
        }
        
        // 设置数据连接
        function setupConnection(conn) {
            conn.on('open', () => {
                console.log('数据连接已打开:', conn.peer);
                connections[conn.peer] = conn;
                
                // 发送用户信息
                const userInfo = {
                    type: 'user-info',
                    data: {
                        username: currentUser.username,
                        displayName: currentUser.displayName,
                        avatar: currentUser.avatar
                    }
                };
                
                console.log('发送用户信息:', userInfo);
                conn.send(userInfo);
                
                // 接收消息
                conn.on('data', (data) => {
                    console.log('收到数据:', data);
                    handlePeerData(conn.peer, data);
                });
                
                // 连接关闭
                conn.on('close', () => {
                    console.log('数据连接关闭:', conn.peer);
                    delete connections[conn.peer];
                    removeParticipant(conn.peer);
                });
                
                // 连接错误
                conn.on('error', (err) => {
                    console.error('数据连接错误:', err);
                });
            });
        }
        
        // 处理对等数据
        function handlePeerData(peerId, data) {
            console.log('处理对等数据:', peerId, data);
            
            if (data && data.type === 'user-info') {
                addParticipantToUI(peerId, data.data);
            }
        }
        
        // 添加远程音频流
        function addRemoteStream(peerId, stream) {
            console.log('添加远程音频:', peerId);
            
            // 创建音频元素
            const audioElement = document.createElement('audio');
            audioElement.id = `audio-${peerId}`;
            audioElement.autoplay = true;
            audioElement.controls = false; // 不显示控件
            audioElement.muted = false; // 确保不静音
            audioElement.playsInline = true; // 对iOS很重要
            
            // 设置音频流
            audioElement.srcObject = stream;
            remoteAudios.appendChild(audioElement);
            
            // 强制开始播放 - 这对某些移动浏览器很重要
            audioElement.onloadedmetadata = () => {
                console.log(`远程音频 ${peerId} 元数据已加载，尝试播放...`);
                audioElement.play()
                    .then(() => {
                        console.log(`远程音频 ${peerId} 开始播放`);
                    })
                    .catch(err => {
                        console.error(`远程音频 ${peerId} 播放失败:`, err);
                        // 尝试在用户交互后再次播放
                        const playButton = document.createElement('button');
                        playButton.textContent = `播放 ${userData?.displayName || '用户'} 的声音`;
                        playButton.className = 'header-btn';
                        playButton.style.margin = '5px';
                        playButton.style.display = 'none'; // 默认隐藏，仅在需要时显示
                        
                        playButton.onclick = () => {
                            audioElement.play()
                                .then(() => {
                                    playButton.style.display = 'none';
                                    console.log(`用户交互后，远程音频 ${peerId} 播放成功`);
                                })
                                .catch(e => {
                                    console.error(`即使用户交互后，仍然无法播放远程音频 ${peerId}:`, e);
                                });
                        };
                        
                        // 只在特定错误类型时添加按钮
                        if (err.name === 'NotAllowedError') {
                            remoteAudios.appendChild(playButton);
                            playButton.style.display = 'block';
                            alert('由于浏览器策略，需要点击按钮启动音频播放');
                        }
                    });
            };
            
            // 添加错误处理
            audioElement.onerror = (event) => {
                console.error(`远程音频 ${peerId} 出错:`, event);
            };
            
            // 添加音频电平检测
            detectAudioLevel(peerId, stream);
        }
        
        // 检测音频电平
        function detectAudioLevel(peerId, stream) {
            try {
                console.log(`初始化音频电平检测 - 用户: ${peerId}`);
                
                // 创建或获取AudioContext
                let audioContext;
                if (window.sharedAudioContext) {
                    audioContext = window.sharedAudioContext;
                    console.log('使用现有AudioContext');
                } else {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    window.sharedAudioContext = audioContext;
                    console.log('创建新AudioContext');
                    
                    // 在iOS上，在第一次用户交互时解锁AudioContext
                    if (audioContext.state === 'suspended') {
                        console.log('AudioContext已暂停，等待用户交互解锁');
                        
                        const unlockAudio = () => {
                            if (audioContext.state === 'suspended') {
                                audioContext.resume().then(() => {
                                    console.log('AudioContext已解锁');
                                }).catch(err => {
                                    console.error('解锁AudioContext失败:', err);
                                });
                            }
                            
                            // 解除事件监听
                            document.body.removeEventListener('touchstart', unlockAudio);
                            document.body.removeEventListener('mousedown', unlockAudio);
                            micBtn.removeEventListener('click', unlockAudio);
                        };
                        
                        // 绑定到常见交互事件
                        document.body.addEventListener('touchstart', unlockAudio, false);
                        document.body.addEventListener('mousedown', unlockAudio, false);
                        micBtn.addEventListener('click', unlockAudio, false);
                    }
                }
                
                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                source.connect(analyser);
                
                const bufferLength = analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);
                
                function checkAudioLevel() {
                    analyser.getByteFrequencyData(dataArray);
                    let sum = 0;
                    for(let i = 0; i < bufferLength; i++) {
                        sum += dataArray[i];
                    }
                    
                    const average = sum / bufferLength;
                    const peerAvatar = document.getElementById(`avatar-${peerId}`);
                    
                    if (peerAvatar && average > 20) {
                        peerAvatar.classList.add('speaking');
                    } else if (peerAvatar) {
                        peerAvatar.classList.remove('speaking');
                    }
                    
                    requestAnimationFrame(checkAudioLevel);
                }
                
                checkAudioLevel();
            } catch (err) {
                console.error(`音频检测初始化失败 - 用户: ${peerId}:`, err);
            }
        }
        
        // 添加参与者到UI
        function addParticipantToUI(peerId, userData) {
            console.log('添加参与者到UI:', peerId, userData);
            
            if (document.getElementById(`participant-${peerId}`)) {
                console.log('参与者已存在，不重复添加');
                return;
            }
            
            // 处理远程用户头像URL
            let avatarUrl = userData.avatar;
            console.log('原始头像URL:', avatarUrl);
            
            // 如果头像URL不是以http开头且不是以/开头，可能需要添加路径前缀
            if (avatarUrl && !avatarUrl.startsWith('http') && !avatarUrl.startsWith('/')) {
                // 检查是否是绝对路径（以/开头）
                if (avatarUrl.startsWith('/')) {
                    // 保持绝对路径不变
                    avatarUrl = avatarUrl;
                } else {
                    // 相对路径，首先检查是否在avatars目录
                    const avatarsPath = '../avatars/' + avatarUrl;
                    const uploadsPath = '../uploads/avatars/' + avatarUrl;
                    
                    // 这里我们无法直接检查文件是否存在，只能根据约定决定路径
                    // 优先使用avatars目录
                    avatarUrl = avatarsPath;
                }
            }
            
            console.log('最终头像URL:', avatarUrl);
            
            const participantDiv = document.createElement('div');
            participantDiv.className = 'participant';
            participantDiv.id = `participant-${peerId}`;
            
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'participant-avatar';
            avatarDiv.id = `avatar-${peerId}`;
            avatarDiv.style.backgroundImage = `url('${avatarUrl}')`;
            
            // 添加一个错误处理程序，如果头像加载失败，使用默认头像
            avatarDiv.innerHTML = `
                <img src="${avatarUrl}" style="display:none;" 
                     onerror="this.onerror=null; this.parentElement.style.backgroundImage='url(\\'../avatars/default.png\\')';">
            `;
            
            const nameDiv = document.createElement('div');
            nameDiv.className = 'participant-name';
            nameDiv.textContent = userData.displayName || userData.username;
            
            participantDiv.appendChild(avatarDiv);
            participantDiv.appendChild(nameDiv);
            participantsContainer.appendChild(participantDiv);
            
            updateConnectionStatus();
        }
        
        // 移除参与者
        function removeParticipant(peerId) {
            console.log('移除参与者:', peerId);
            
            const participant = document.getElementById(`participant-${peerId}`);
            if (participant) {
                participant.remove();
            }
            
            const audio = document.getElementById(`audio-${peerId}`);
            if (audio) {
                audio.remove();
            }
            
            if (peers[peerId]) {
                peers[peerId].close();
                delete peers[peerId];
            }
            
            if (connections[peerId]) {
                connections[peerId].close();
                delete connections[peerId];
            }
            
            updateConnectionStatus();
        }
        
        // 更新连接状态显示
        function updateConnectionStatus() {
            const peerCount = Object.keys(peers).length;
            console.log('更新连接状态, 连接数:', peerCount);
            
            if (peerCount === 0) {
                connectionStatus.textContent = '已连接，等待其他人加入...';
            } else {
                connectionStatus.textContent = `已连接 ${peerCount} 人`;
            }
        }
        
        // 静音控制
        micBtn.addEventListener('click', function() {
            isMuted = !isMuted;
            console.log('麦克风状态变更:', isMuted ? '静音' : '解除静音');
            
            // 更新UI
            if (isMuted) {
                micBtn.innerHTML = '🔇';
                micBtn.classList.add('muted');
                localAvatar.classList.add('muted');
            } else {
                micBtn.innerHTML = '🎤';
                micBtn.classList.remove('muted');
                localAvatar.classList.remove('muted');
            }
            
            // 应用到流
            if (myStream) {
                myStream.getAudioTracks().forEach(track => {
                    track.enabled = !isMuted;
                });
            }
        });
        
        // 结束通话
        endCallBtn.addEventListener('click', function() {
            console.log('结束通话');
            
            // 关闭所有连接
            Object.values(peers).forEach(peer => {
                if (peer && typeof peer.close === 'function') {
                    peer.close();
                }
            });
            
            // 关闭自己的媒体流
            if (myStream) {
                myStream.getTracks().forEach(track => track.stop());
            }
            
            // 关闭对等连接
            if (myPeer) {
                myPeer.destroy();
            }
            
            // 返回聊天页面
            window.close();
        });
        
        // 复制房间链接
        copyBtn.addEventListener('click', function() {
            const roomLink = window.location.href;
            console.log('复制房间链接:', roomLink);
            
            // 使用navigator.clipboard API
            if (navigator.clipboard) {
                navigator.clipboard.writeText(roomLink)
                    .then(() => {
                        copyBtn.textContent = '已复制';
                        setTimeout(() => {
                            copyBtn.textContent = '复制';
                        }, 2000);
                    })
                    .catch(err => {
                        console.error('复制失败:', err);
                        fallbackCopy(roomLink);
                    });
            } else {
                fallbackCopy(roomLink);
            }
        });
        
        // 后备复制方法
        function fallbackCopy(text) {
            console.log('使用后备复制方法');
            
            // 创建临时输入框
            const input = document.createElement('input');
            input.style.position = 'fixed';
            input.style.opacity = '0';
            input.value = text;
            document.body.appendChild(input);
            
            // 选择并复制
            input.select();
            document.execCommand('copy');
            
            // 移除临时元素
            document.body.removeChild(input);
            
            copyBtn.textContent = '已复制';
            setTimeout(() => {
                copyBtn.textContent = '复制';
            }, 2000);
        }
        
        // 本地音频电平检测
        function checkLocalAudio() {
            if (!myStream) {
                console.log('本地流不可用，无法检测音频电平');
                return;
            }
            
            try {
                console.log('初始化本地音频电平检测');
                
                // 使用共享AudioContext或创建新的
                let audioContext;
                if (window.sharedAudioContext) {
                    audioContext = window.sharedAudioContext;
                    console.log('使用现有AudioContext检测本地音频');
                } else {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    window.sharedAudioContext = audioContext;
                    console.log('创建新AudioContext检测本地音频');
                    
                    // 处理iOS/Safari的AudioContext暂停状态
                    if (audioContext.state === 'suspended') {
                        console.log('本地AudioContext已暂停，等待用户交互解锁');
                        
                        const unlockAudio = () => {
                            if (audioContext.state === 'suspended') {
                                audioContext.resume().then(() => {
                                    console.log('本地AudioContext已解锁');
                                }).catch(err => {
                                    console.error('解锁本地AudioContext失败:', err);
                                });
                            }
                            
                            // 解除事件监听
                            document.body.removeEventListener('touchstart', unlockAudio);
                            document.body.removeEventListener('mousedown', unlockAudio);
                            micBtn.removeEventListener('click', unlockAudio);
                        };
                        
                        // 绑定到常见交互事件
                        document.body.addEventListener('touchstart', unlockAudio, false);
                        document.body.addEventListener('mousedown', unlockAudio, false);
                        micBtn.addEventListener('click', unlockAudio, false);
                    }
                }
                
                const source = audioContext.createMediaStreamSource(myStream);
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                source.connect(analyser);
                
                const bufferLength = analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);
                
                function updateAudioLevel() {
                    if (isMuted) {
                        localAvatar.classList.remove('speaking');
                        requestAnimationFrame(updateAudioLevel);
                        return;
                    }
                    
                    analyser.getByteFrequencyData(dataArray);
                    let sum = 0;
                    for(let i = 0; i < bufferLength; i++) {
                        sum += dataArray[i];
                    }
                    
                    const average = sum / bufferLength;
                    
                    if (average > 20) {
                        localAvatar.classList.add('speaking');
                    } else {
                        localAvatar.classList.remove('speaking');
                    }
                    
                    requestAnimationFrame(updateAudioLevel);
                }
                
                updateAudioLevel();
            } catch (err) {
                console.error('本地音频检测初始化失败:', err);
            }
        }
        
        // 页面关闭时清理
        window.addEventListener('beforeunload', function() {
            console.log('页面即将关闭，清理资源');
            
            try {
                // 离开房间
                const formData = new FormData();
                formData.append('room', roomId);
                formData.append('peerId', myPeer ? myPeer.id : '');
                formData.append('action', 'leave');
                
                // 使用navigator.sendBeacon确保数据在页面关闭前发送
                if (navigator.sendBeacon) {
                    navigator.sendBeacon('../includes/update_room_peers.php', formData);
                } else {
                    // 后备方案
                    fetch('../includes/update_room_peers.php', {
                        method: 'POST',
                        body: formData,
                        keepalive: true
                    });
                }
                
                // 停止媒体流
                if (myStream) {
                    myStream.getTracks().forEach(track => track.stop());
                }
                
                // 关闭所有连接
                Object.values(peers).forEach(peer => {
                    if (peer && typeof peer.close === 'function') {
                        peer.close();
                    }
                });
                
                // 销毁对等连接
                if (myPeer) {
                    myPeer.destroy();
                }
            } catch (err) {
                console.error('清理资源失败:', err);
            }
        });
        
        // 启动通话
        console.log('开始初始化语音通话...');
        initializeCall();
    });
    </script>
</body>
</html>