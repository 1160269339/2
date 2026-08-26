#!/bin/bash
# 虚拟主机系统与2号仓库一键部署脚本

set -e

echo "╔════════════════════════════════════════╗"
echo "║   虚拟主机系统与2号仓库整合部署      ║"
echo "║   v1.0.0                               ║"
echo "╚════════════════════════════════════════╝"
echo ""

# 检查是否为root用户
if [ "$EUID" -ne 0 ]; then
    echo "❌ 请使用root用户运行此脚本"
    echo "   使用方式: sudo bash deploy_integration.sh"
    exit 1
fi

# 检查系统
if [[ ! -f /etc/os-release ]]; then
    echo "❌ 不支持的系统"
    exit 1
fi

source /etc/os-release
echo "📋 系统: $PRETTY_NAME"
echo ""

# 安装依赖
install_dependencies() {
    echo "📦 安装系统依赖..."
    
    if [[ "$ID" == "ubuntu" || "$ID" == "debian" ]]; then
        apt-get update
        apt-get install -y \
            nginx \
            php8.1-fpm \
            php8.1-mysql \
            php8.1-curl \
            php8.1-gd \
            php8.1-mbstring \
            php8.1-xml \
            php8.1-zip \
            php8.1-intl \
            php8.1-openssl \
            php8.1-fileinfo \
            php8.1-bcmath \
            mysql-server \
            git \
            unzip
    elif [[ "$ID" == "centos" || "$ID" == "rhel" ]]; then
        yum install -y epel-release
        yum install -y \
            nginx \
            php-fpm \
            php-mysqlnd \
            php-curl \
            php-gd \
            php-mbstring \
            php-xml \
            php-zip \
            php-intl \
            php-openssl \
            php-fileinfo \
            php-bcmath \
            mariadb-server \
            git \
            unzip
    fi
    
    echo "✅ 依赖安装完成"
}

# 克隆项目
clone_project() {
    echo "📥 克隆项目..."
    
    PROJECT_DIR="/opt/hosting"
    
    if [ -d "$PROJECT_DIR" ]; then
        echo "📂 项目目录已存在: $PROJECT_DIR"
        read -p "是否重新部署? (y/N): " REDEPLOY
        if [[ "$REDEPLOY" =~ ^[Yy]$ ]]; then
            rm -rf "$PROJECT_DIR"
        fi
    fi
    
    if [ ! -d "$PROJECT_DIR" ]; then
        mkdir -p "$(dirname $PROJECT_DIR)"
        git clone https://github.com/1160269339/2.git "$PROJECT_DIR"
    fi
    
    echo "✅ 项目克隆完成"
}

# 配置数据库
setup_database() {
    echo "🗄️ 配置数据库..."
    
    DB_PASSWORD=$(openssl rand -base64 12)
    DB_NAME="hosting"
    DB_USER="hosting"
    
    echo "📝 数据库信息:"
    echo "   数据库名: $DB_NAME"
    echo "   数据库用户: $DB_USER"
    echo "   数据库密码: $DB_PASSWORD"
    
    # 启动MySQL
    systemctl start mysql
    systemctl enable mysql
    
    # 创建数据库
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # 导入SQL
    if [ -f "$PROJECT_DIR/sjk.sql" ]; then
        echo "📥 导入数据库..."
        mysql "$DB_NAME" < "$PROJECT_DIR/sjk.sql" 2>/dev/null || \
        mariadb "$DB_NAME" < "$PROJECT_DIR/sjk.sql" 2>/dev/null
        echo "✅ 数据库导入完成"
    fi
    
    # 更新配置
    if [ -f "$PROJECT_DIR/app/database.php" ]; then
        cp "$PROJECT_DIR/app/database.php" "$PROJECT_DIR/app/database.php.bak"
        sed -i "s/'database' => ''/'database' => '${DB_NAME}'/g" "$PROJECT_DIR/app/database.php"
        sed -i "s/'username' => ''/'username' => '${DB_USER}'/g" "$PROJECT_DIR/app/database.php"
        sed -i "s/'password' => ''/'password' => '${DB_PASSWORD}'/g" "$PROJECT_DIR/app/database.php"
        echo "✅ 数据库配置完成"
    fi
}

# 配置Nginx
setup_nginx() {
    echo "🌐 配置Nginx..."
    
    # 创建Nginx配置
    cat > /etc/nginx/sites-available/hosting << 'EOF'
server {
    listen 80;
    server_name _;
    root /opt/hosting/public;
    index index.php index.html;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.ht {
        deny all;
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
