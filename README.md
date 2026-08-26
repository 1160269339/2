# 主机系统 - 一键部署

> 一个基于 ThinkPHP 5.x 的主机销售系统，支持多种支付方式和主机插件。

---

## 🚀 快速开始

### 一键部署（推荐）

```bash
curl -fsSL https://raw.githubusercontent.com/1160269339/2/main/deploy.sh | bash
```

### Docker部署

```bash
git clone https://github.com/1160269339/2.git
cd 2
cp .env.example .env
docker-compose up -d
```

---

## 📚 文档导航

| 文档 | 说明 |
|------|------|
| [QUICKSTART.md](QUICKSTART.md) | 🚀 快速入门 - 三种部署方式 |
| [TUTORIAL.md](TUTORIAL.md) | 📖 添加主机教程 - EasyPanel/MNBT完整配置 |
| [CHECKLIST.md](CHECKLIST.md) | ✅ 配置检查清单 - 使用前必查 |
| [README_DOCKER.md](README_DOCKER.md) | 🐳 Docker部署详解 |
| [PAYMENT_MONITOR_README.md](PAYMENT_MONITOR_README.md) | 💳 支付监控配置 |

---

## 🔌 添加主机

### EasyPanel 对接

1. 进入后台 → 主机管理 → 主机插件 → EasyPanel
2. 填写主机地址（不带http/https）和安全码
3. 测试连接 → 保存
4. 创建主机套餐 → 测试购买

### MNBT 对接

1. 进入后台 → 主机管理 → 主机插件 → MNBT
2. 填写主机地址、安全码、账号、密码
3. 测试连接 → 同步宝塔列表
4. 创建主机套餐 → 测试购买

**详细教程**：[TUTORIAL.md](TUTORIAL.md)

---

## 💳 支付方式

- ✅ 支付宝（官方/电脑网站/APP）
- ✅ 微信支付
- ✅ 码支付（MAIZHI）
- ✅ 易支付
- ✅ 其他支付平台

---

## 🗄️ 数据库

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE hosting CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 导入数据
mysql -u root -p hosting < sjk.sql
```

---

## 🔐 默认登录

- 后台地址：`http://your-domain.com/admin`
- 账号：`admin`
- 密码：`123456`

---

## 📞 技术支持

- 原作者QQ：2150811531
- QQ群：905412821
