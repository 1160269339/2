#!/bin/bash
# 主机系统一键部署脚本
# 使用方法: curl -fsSL https://raw.githubusercontent.com/1160269339/2/main/deploy.sh | bash

set -e

echo "🚀 开始一键部署主机系统..."

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查是否为root用户
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ 请使用root用户运行此脚本${NC}"
    echo "   使用方式: sudo bash deploy.sh"
    exit 1
fi

# 检查系统
if [[ ! -f /etc/os-release ]]; then
    echo -e "${RED}❌ 不支持的系统${NC}"
    exit 1
fi

source /etc/os-release
echo "📋 系统: $PRETTY_NAME"

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="/opt/hosting"

# 安装依赖
install_dependencies() {
    echo -e "${YELLOW}📦 安装系统依赖...${NC}"
    
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
            mysql-client \
            git \
            wget \
            curl
    elif [[ "$ID" == "centos" || "$ID" == "rhel" || "$ID" == "alibabacloud" ]]; then
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
            mariadb \
            git \
            wget \
            curl
        systemctl enable mariadb
    fi
    
    echo -e "${GREEN}✅ 依赖安装完成${NC}"
}

# 克隆项目
clone_project() {
    echo -e "${YELLOW}📥 克隆项目...${NC}"
    
    if [ -d "$PROJECT_DIR" ]; then
        echo "📂 项目目录已存在: $PROJECT_DIR"
        read -p "是否重新克隆? (y/N): " RECLONE
        if [[ "$RECLONE" =~ ^[Yy]$ ]]; then
            rm -rf "$PROJECT_DIR"
        fi
    fi
    
    if [ ! -d "$PROJECT_DIR" ]; then
        mkdir -p "$(dirname $PROJECT_DIR)"
        git clone https://github.com/1160269339/2.git "$PROJECT_DIR"
    fi
    
    echo -e "${GREEN}✅ 项目克隆完成${NC}"
}

# 配置数据库
setup_database() {
    echo -e "${YELLOW}🗄️ 配置数据库...${NC}"
    
    # 生成随机密码
    DB_PASSWORD=$(openssl rand -base64 16)
    DB_NAME="hosting"
    DB_USER="hosting"
    
    echo "📝 数据库信息:"
    echo "   数据库名: $DB_NAME"
    echo "   数据库用户: $DB_USER"
    echo "   数据库密码: $DB_PASSWORD"
    
    # 保存数据库配置
    cat > /tmp/db_config.sh << EOF
export DB_NAME="$DB_NAME"
export DB_USER="$DB_USER"
export DB_PASSWORD="$DB_PASSWORD"
EOF
    
    # 启动MySQL并创建数据库
    systemctl start mysql
    systemctl enable mysql
    
    if command -v mysql &> /dev/null; then
        mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
        mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
        mysql -e "FLUSH PRIVILEGES;"
        mysql -e "EXIT;"
    elif command -v mariadb &> /dev/null; then
        mariadb -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
        mariadb -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
        mariadb -e "FLUSH PRIVILEGES;"
        mariadb -e "EXIT;"
    fi
    
    # 导入SQL
    if [ -f "$PROJECT_DIR/sjk.sql" ]; then
        echo "📥 导入数据库..."
        mysql "$DB_NAME" < "$PROJECT_DIR/sjk.sql" 2>/dev/null || \
        mariadb "$DB_NAME" < "$PROJECT_DIR/sjk.sql" 2>/dev/null
        echo -e "${GREEN}✅ 数据库导入完成${NC}"
    fi
    
    # 更新配置文件
    if [ -f "$PROJECT_DIR/app/database.php" ]; then
        cp "$PROJECT_DIR/app/database.php" "$PROJECT_DIR/app/database.php.bak"
        sed -i "s/'database' => ''/'database' => '${DB_NAME}'/g" "$PROJECT_DIR/app/database.php"
        sed -i "s/'username' => ''/'username' => '${DB_USER}'/g" "$PROJECT_DIR/app/database.php"
        sed -i "s/'password' => ''/'password' => '${DB_PASSWORD}'/g" "$PROJECT_DIR/app/database.php"
        echo -e "${GREEN}✅ 数据库配置完成${NC}"
    fi
    
    # 保存数据库信息
    cat > "$PROJECT_DIR/.env" << EOF
DB_HOST=localhost
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
EOF
    
    echo -e "${GREEN}✅ 数据库配置完成${NC}"
    echo -e "${YELLOW}💾 请保存以下信息（后续登录需要）:${NC}"
    echo -e "   数据库名: ${GREEN}$DB_NAME${NC}"
    echo -e "   数据库用户: ${GREEN}$DB_USER${NC}"
    echo -e "   数据库密码: ${GREEN}$DB_PASSWORD${NC}"
}

# 配置Nginx
setup_nginx() {
    echo -e "${YELLOW}🌐 配置Nginx...${NC}"
    
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
    
    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF
    
    # 启用站点
    ln -sf /etc/nginx/sites-available/hosting /etc/nginx/sites-enabled/
    
    # 删除默认站点
    rm -f /etc/nginx/sites-enabled/default
    
    # 测试配置
    nginx -t
    systemctl restart nginx
    systemctl enable nginx
    
    echo -e "${GREEN}✅ Nginx配置完成${NC}"
}

# 设置权限
setup_permissions() {
    echo -e "${YELLOW}🔒 设置权限...${NC}"
    
    # 设置所有者
    chown -R www-data:www-data "$PROJECT_DIR"
    
    # 设置目录权限
    chmod -R 755 "$PROJECT_DIR"
    chmod -R 777 "$PROJECT_DIR/runtime"
    chmod -R 777 "$PROJECT_DIR/public/uploads" 2>/dev/null || true
    
    echo -e "${GREEN}✅ 权限设置完成${NC}"
}

# 获取服务器IP
get_server_ip() {
    echo -e "${YELLOW}🌍 获取服务器IP...${NC}"
    
    SERVER_IP=$(curl -s ifconfig.me)
    
    if [ -z "$SERVER_IP" ]; then
        SERVER_IP=$(hostname -I | awk '{print $1}')
    fi
    
    echo -e "${GREEN}✅ 服务器IP: $SERVER_IP${NC}"
}

# 主函数
main() {
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║       主机系统一键部署脚本             ║${NC}"
    echo -e "${GREEN}║       v1.0.0                          ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
    echo ""
    
    # 确认部署
    read -p "⚠️  此操作将安装所有依赖并配置系统，是否继续? (y/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}❌ 部署已取消${NC}"
        exit 0
    fi
    
    echo ""
    
    # 安装依赖
    install_dependencies
    
    # 克隆项目
    clone_project
    
    # 配置数据库
    setup_database
    
    # 配置Nginx
    setup_nginx
    
    # 设置权限
    setup_permissions
    
    # 获取IP
    get_server_ip
    
    # 完成
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║          ✅ 部署完成!                   ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${YELLOW}📋 部署信息:${NC}"
    echo -e "   🌐 访问地址: http://${SERVER_IP}"
    echo -e "   📂 项目目录: ${PROJECT_DIR}"
    echo -e "   🗄️  数据库名: ${DB_NAME:-$(grep 'DB_NAME' $PROJECT_DIR/.env 2>/dev/null | cut -d'=' -f2)}"
    echo -e "   🔑 数据库密码: ${DB_PASSWORD:-$(grep 'DB_PASSWORD' $PROJECT_DIR/.env 2>/dev/null | cut -d'=' -f2)}"
    echo ""
    echo -e "${YELLOW}📖 使用教程:${NC}"
    echo -e "   1. 打开 http://${SERVER_IP} 访问网站"
    echo -e "   2. 登录后台: http://${SERVER_IP}/admin"
    echo -e "   3. 默认账号: admin / admin123"
    echo ""
    echo -e "${GREEN}🎉 主机系统已部署完成!${NC}"
}

# 执行主函数
main
