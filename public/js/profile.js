// 个人资料页面组件
class ProfilePage {
    constructor() {
        this.container = null;
        this.avatarUrl = '';
        this.displayName = '';
    }

    // 初始化组件
    init(avatarUrl, displayName) {
        this.avatarUrl = avatarUrl;
        this.displayName = displayName;
        this.render();
        this.bindEvents();
    }

    // 渲染组件
    render() {
        const container = document.createElement('div');
        container.className = 'profile-page';
        container.innerHTML = `
            <div class="profile-header">
                <h2>个人资料</h2>
                <button class="close-btn">×</button>
            </div>
            <div class="profile-content">
                <div class="profile-avatar-section">
                    <div class="profile-avatar" style="background-image: url('${this.avatarUrl}')">
                        <div class="avatar-overlay">
                            <span class="avatar-icon">📷</span>
                        </div>
                        <input type="file" id="profileAvatarInput" class="avatar-input" accept="image/*">
                    </div>
                    <p class="avatar-tip">点击更换头像</p>
                </div>
                <div class="profile-name-section">
                    <label for="displayName">昵称</label>
                    <input type="text" id="displayName" value="${this.displayName}" maxlength="12">
                    <p class="name-tip">最多12个字符</p>
                </div>
                <div class="profile-actions">
                    <button class="save-btn">保存修改</button>
                </div>
            </div>
        `;
        this.container = container;
        document.body.appendChild(container);
    }

    // 绑定事件
    bindEvents() {
        // 关闭按钮
        this.container.querySelector('.close-btn').addEventListener('click', () => {
            // 找到遮罩层并触发其点击事件，以同时隐藏个人资料页面和遮罩层
            const overlay = document.querySelector('.profile-overlay');
            if (overlay) {
                overlay.click();
            }
        });

        // 头像上传
        const avatarInput = this.container.querySelector('#profileAvatarInput');
        avatarInput.addEventListener('change', (e) => this.handleAvatarUpload(e));

        // 保存按钮
        this.container.querySelector('.save-btn').addEventListener('click', () => {
            this.saveChanges();
        });
    }

    // 处理头像上传
    handleAvatarUpload(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('avatar', file);

        // 显示上传中状态
        const avatarElement = this.container.querySelector('.profile-avatar');
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
                const newAvatarUrl = `${data.avatar}?t=${Date.now()}`;
                this.avatarUrl = newAvatarUrl;
                avatarElement.style.backgroundImage = `url('${newAvatarUrl}')`;
                avatarElement.innerHTML = `
                    <div class="avatar-overlay">
                        <span class="avatar-icon">📷</span>
                    </div>
                    <input type="file" id="profileAvatarInput" class="avatar-input" accept="image/*">
                `;
                
                // 更新主界面的头像
                const mainAvatar = document.querySelector('.user-avatar');
                if (mainAvatar) {
                    mainAvatar.style.backgroundImage = `url('${newAvatarUrl}')`;
                }
            } else if (data.error) {
                alert('上传失败: ' + data.error);
                avatarElement.style.backgroundImage = originalStyle;
                avatarElement.innerHTML = `
                    <div class="avatar-overlay">
                        <span class="avatar-icon">📷</span>
                    </div>
                    <input type="file" id="profileAvatarInput" class="avatar-input" accept="image/*">
                `;
            }
        })
        .catch(error => {
            console.error('上传头像失败:', error);
            alert('上传头像失败，请重试');
            avatarElement.style.backgroundImage = originalStyle;
            avatarElement.innerHTML = `
                <div class="avatar-overlay">
                    <span class="avatar-icon">📷</span>
                </div>
                <input type="file" id="profileAvatarInput" class="avatar-input" accept="image/*">
            `;
        });
    }

    // 保存修改
    saveChanges() {
        const newDisplayName = this.container.querySelector('#displayName').value.trim();
        
        if (newDisplayName === this.displayName) {
            this.hide();
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_display_name',
                display_name: newDisplayName
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.displayName = newDisplayName;
                // 更新主界面的显示名称
                const nameDisplay = document.querySelector('.user-name-display');
                if (nameDisplay) {
                    nameDisplay.textContent = newDisplayName;
                }
                this.hide();
                
                // 隐藏遮罩层
                const overlay = document.querySelector('.profile-overlay');
                if (overlay) {
                    overlay.classList.remove('show');
                    setTimeout(() => overlay.style.display = 'none', 300); // 匹配 chat.js 中的隐藏动画延迟
                }
            } else if (data.error) {
                alert('保存失败: ' + data.error);
            }
        })
        .catch(error => {
            console.error('保存失败:', error);
            alert('保存失败，请重试');
        });
    }

    // 显示组件
    show() {
        this.container.style.display = 'block';
        setTimeout(() => {
            this.container.classList.add('show');
        }, 10);
    }

    // 隐藏组件
    hide() {
        this.container.classList.remove('show');
        setTimeout(() => {
            this.container.style.display = 'none';
        }, 300);
    }
}

// 导出组件
window.ProfilePage = ProfilePage; 