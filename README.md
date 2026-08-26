# 主机系统 - 一键部署

一个基于 ThinkPHP 5.x 的主机销售系统，支持多种支付方式和主机插件。

## 🚀 一键部署

### 方法1：使用部署脚本（推荐）

```bash
# 下载并运行部署脚本
curl -fsSL https://raw.githubusercontent.com/1160269339/2/main/deploy.sh | bash
```

### 方法2：手动部署

```bash
# 1. 克隆项目
git clone https://github.com/1160269339/2.git /opt/hosting
cd /opt/hosting

# 2. 安装依赖
# Ubuntu/Debian
apt-get update && apt-get install -y nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip php8.1-intl php8.1-openssl php8.1-fileinfo php8.1-bcmath mysql-server git

# CentOS/RHEL
yum install -y nginx php-fpm php-mysqlnd php-curl php-gd php-mbstring php-xml php-zip php-intl php-openssl php-fileinfo php-bcmath mariadb-server git

# 3. 配置数据库
mysql -u root -p -e "CREATE DATABASE hosting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hosting < sjk.sql

# 4. 更新配置
vim app/database.php
# 修改数据库连接信息

# 5. 配置Nginx
cat > /etc/nginx/sites-available/hosting << 'EOF'
server {
    listen 80;
    server_name _;
    root /opt/hosting/public;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
EOF

ln -sf /etc/nginx/sites-available/hosting /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

# 6. 设置权限
chown -R www-data:www-data /opt/hosting
chmod -R 755 /opt/hosting
chmod -R 777 /opt/hosting/runtime
```

## 📋 系统要求

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Nginx
- Git

## 🔧 功能特性

- ✅ 用户系统（注册、登录、找回）
- ✅ 产品管理（虚拟主机、域名等）
- ✅ 订单系统
- ✅ 支付对接（支付宝、微信、易支付）
- ✅ 主机插件（Default、EasyPanel、MNBT、魔方）
- ✅ 邮件系统
- ✅ 短信接口
- ✅ 实名认证
- ✅ 后台管理

## 📁 项目结构

```
hosting/
├── app/                # 应用目录
│   ├── admin/         # 后台管理
│   └── index/         # 前台用户
├── extend/             # 扩展库
│   ├── PHPMailer/     # 邮件发送
│   ├── pay/           # 支付对接
│   ├── realname/      # 实名认证
│   └── sms/           # 短信接口
├── frame/              # ThinkPHP框架
├── plugins/            # 插件系统
│   ├── host/          # 主机插件
│   └── pay/           # 支付插件
├── public/             # 公共文件
├── runtime/            # 运行时目录
├── vendor/             # Composer依赖
└── sjk.sql             # 数据库文件
```

## 🔗 相关链接

- GitHub: https://github.com/1160269339/2
- 安装教程: 安装教程.txt
- EasyPanel对接: easypanel对接教程.txt
- MNBT对接: mnbt对接教程.txt

## 📝 默认登录

- 后台地址: `/admin`
- 默认账号: `admin`
- 默认密码: `admin123`

## ⚠️ 注意事项

1. 请确保服务器防火墙开放80端口
2. 部署后请及时修改默认密码
3. 建议配置HTTPS（Let's Encrypt免费证书）
4. 定期备份数据库

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License
