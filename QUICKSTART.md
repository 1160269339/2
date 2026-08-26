# 主机系统 - 快速入门

> 一个基于 ThinkPHP 5.x 的主机销售系统，支持多种支付方式和主机插件。

---

## 🚀 一键部署（推荐）

```bash
# 下载并运行部署脚本
curl -fsSL https://raw.githubusercontent.com/1160269339/2/main/deploy.sh | bash

# 或手动下载
wget https://raw.githubusercontent.com/1160269339/2/main/deploy.sh
bash deploy.sh
```

---

## 📦 三种部署方式

### 方式一：一键脚本（最简单）

```bash
curl -fsSL https://raw.githubusercontent.com/1160269339/2/main/deploy.sh | bash
```

**优点**：自动安装所有依赖，配置数据库和Nginx

---

### 方式二：Docker部署

```bash
# 克隆项目
git clone https://github.com/1160269339/2.git
cd 2

# 配置环境变量
cp .env.example .env
# 编辑 .env 文件，修改数据库密码

# 启动服务
docker-compose up -d

# 查看状态
docker-compose ps

# 查看日志
docker-compose logs -f
```

**访问地址**：
- 前台：http://localhost
- 后台：http://localhost/admin
- phpMyAdmin：http://localhost:8080

**默认账号**：`admin` / `admin123`

---

### 方式三：手动部署（传统方式）

```bash
# 1. 克隆项目
git clone https://github.com/1160269339/2.git /opt/hosting
cd /opt/hosting

# 2. 安装依赖
# Ubuntu/Debian
apt-get update && apt-get install -y nginx php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip php8.1-intl php8.1-openssl php8.1-fileinfo php8.1-bcmath mysql-server git

# 3. 配置数据库
mysql -u root -p -e "CREATE DATABASE hosting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hosting < sjk.sql

# 4. 修改数据库配置
vim app/database.php
# 修改数据库连接信息

# 5. 配置Nginx
# 设置运行目录为 public
# 配置伪静态为 thinkphp 伪静态

# 6. 导入数据库
# 导入 sjk.sql 文件

# 7. 访问后台
# 地址: http://your-domain/admin
# 账号: admin
# 密码: 123456
```

---

## 🔌 添加主机插件

### EasyPanel 对接

1. 登录后台 → 主机插件 → EasyPanel
2. 填写配置：
   - **主机地址**：EasyPanel地址，**不带** http/https
   - **安全码**：EasyPanel对接秘钥
3. 测试连接

### MNBT 对接

1. 登录后台 → 主机插件 → MNBT
2. 填写配置：
   - **主机地址**：MNBT地址，纯域名，**不带** http/https
   - **安全码**：MNBT后台系统API秘钥
   - **账号**：MNBT宝塔列表的宝塔编号
   - **密码**：MNBT宝塔列表的调用秘钥
3. 如果地址不是80端口，填写端口号
4. 如果MNBT使用HTTPS，开启SSL
5. 测试连接

---

## 📁 目录结构

```
2/
├── app/              # 应用代码
├── public/           # 公共文件（Nginx根目录）
├── runtime/          # 运行时目录
├── plugins/          # 插件目录
│   ├── pay/          # 支付插件
│   │   ├── alipay/
│   │   ├── wxpay/
│   │   ├── epay/
│   │   ├── f2fpay/
│   │   └── maizhi/   # 码支付
│   └── host/         # 主机插件
│       ├── easypanel/
│       ├── mnbt/
│       └── default/
├── extend/           # 扩展类库
│   ├── pay/          # 支付SDK
│   └── host/         # 主机SDK
├── database/         # 数据库配置
├── deploy.sh         # 一键部署脚本
├── Dockerfile        # Docker配置
├── docker-compose.yml
├── sjk.sql           # 数据库文件
└── README_*.md       # 文档
```

---

## 🔧 常见问题

### Q: 部署后后台打不开？
A: 检查Nginx配置是否正确，确认运行目录设为 `public`

### Q: 数据库连接失败？
A: 检查 `app/database.php` 配置是否正确

### Q: 主机插件连接失败？
A: 检查主机地址是否正确，确认防火墙允许访问

### Q: 如何升级系统？
A: 重新拉取代码并运行部署脚本

---

## 📞 技术支持

- 原作者QQ：2150811531
- QQ群：905412821
