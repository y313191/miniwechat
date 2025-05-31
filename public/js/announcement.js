// 公告容器组件
class AnnouncementContainer {
    constructor() {
        this.container = null;
        this.overlay = null;
        this.textarea = null;
        this.saveBtn = null;
    }

    // 初始化组件
    init() {
        this.render();
        this.bindEvents();
        this.loadAnnouncement(); // 加载公告内容
    }

    // 渲染组件
    render() {
        const container = document.createElement('div');
        container.className = 'announcement-container';
        container.innerHTML = `
            <div class="announcement-header">
                <h2>公告</h2>
                <button class="close-btn">×</button>
            </div>
            <div class="announcement-content">
                <textarea placeholder="请输入公告内容..."></textarea>
            </div>
            <div class="announcement-actions">
                <button class="save-btn">保存公告</button>
            </div>
        `;
        this.container = container;
        document.body.appendChild(container);

        // 创建遮罩层
        const overlay = document.createElement('div');
        overlay.className = 'announcement-overlay';
        document.body.appendChild(overlay);
        this.overlay = overlay;

        this.textarea = container.querySelector('textarea');
        this.saveBtn = container.querySelector('.save-btn');
    }

    // 绑定事件
    bindEvents() {
        // 关闭按钮
        this.container.querySelector('.close-btn').addEventListener('click', () => {
            this.hide();
        });

        // 遮罩层点击隐藏
        this.overlay.addEventListener('click', () => {
            this.hide();
        });

        // 保存按钮
        this.saveBtn.addEventListener('click', () => {
            this.saveAnnouncement();
        });
    }

    // 加载公告内容
    loadAnnouncement() {
        fetch('index.php?action=get_announcement')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.announcement) {
                    this.textarea.value = data.announcement;
                } else {
                    console.error('加载公告失败:', data.error || '未知错误');
                    this.textarea.value = '加载公告失败。';
                }
            })
            .catch(error => {
                console.error('加载公告请求失败:', error);
                this.textarea.value = '加载公告失败。';
            });
    }

    // 保存公告内容
    saveAnnouncement() {
        const announcementContent = this.textarea.value;

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_announcement',
                announcement: announcementContent
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('公告保存成功！');
                this.hide();
            } else if (data.error) {
                alert('保存公告失败: ' + data.error);
            } else {
                 alert('保存公告失败: 未知错误');
            }
        })
        .catch(error => {
            console.error('保存公告请求失败:', error);
            alert('保存公告失败，请重试');
        });
    }

    // 显示容器
    show() {
        this.container.style.display = 'flex';
        this.overlay.style.display = 'block';
        setTimeout(() => {
            this.container.classList.add('show');
            this.overlay.classList.add('show');
        }, 10);
         this.loadAnnouncement(); // 每次显示时重新加载确保最新
    }

    // 隐藏容器
    hide() {
        this.container.classList.remove('show');
        this.overlay.classList.remove('show');
        setTimeout(() => {
            this.container.style.display = 'none';
            this.overlay.style.display = 'none';
        }, 300); // 匹配 CSS 过渡时间
    }
}

// 导出组件
window.AnnouncementContainer = AnnouncementContainer; 