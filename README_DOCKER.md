# 主机系统 Docker 部署

## 使用 Docker Compose 一键部署

```bash
# 克隆项目
git clone https://github.com/1160269339/2.git
cd 2

# 启动服务
docker-compose up -d

# 查看状态
docker-compose ps

# 查看日志
docker-compose logs -f
```

## 环境变量配置

复制 `.env.example` 到 `.env` 并修改配置：

```bash
cp .env.example .env
```

### 环境变量说明

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| `DB_HOST` | mysql | 数据库主机 |
| `DB_NAME` | hosting | 数据库名称 |
| `DB_USER` | hosting | 数据库用户 |
| `DB_PASSWORD` | hosting123 | 数据库密码 |
| `APP_ENV` | production | 运行环境 |
| `APP_DEBUG` | false | 调试模式 |
| `NGINX_PORT` | 80 | Nginx端口 |
| `PHP_VERSION` | 8.1 | PHP版本 |

## Docker 命令

```bash
# 停止服务
docker-compose down

# 重启服务
docker-compose restart

# 重新构建
docker-compose up -d --build

# 进入容器
docker-compose exec php bash
docker-compose exec mysql bash

# 备份数据库
docker-compose exec -T mysql mysqldump -u$$DB_USER -p$$DB_PASSWORD $$DB_NAME > backup.sql

# 恢复数据库
cat backup.sql | docker-compose exec -T mysql mysql -u$$DB_USER -p$$DB_PASSWORD $$DB_NAME
```

## 目录映射

| 宿主机目录 | 容器目录 | 说明 |
|-----------|---------|------|
| `./app` | `/var/www/app` | 应用代码 |
| `./public` | `/var/www/public` | 公共文件 |
| `./runtime` | `/var/www/runtime` | 运行时目录 |
| `./data/mysql` | `/var/lib/mysql` | 数据库数据 |

## 访问地址

- 前台: http://localhost
- 后台: http://localhost/admin
- phpMyAdmin: http://localhost:8080

## 默认账号

- 后台: `admin` / `admin123`
