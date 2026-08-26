# 虚拟主机系统与2号仓库整合指南

## 📋 整合说明

本整合将虚拟主机网盘系统（cxym）与2号仓库（主机销售系统）完全整合，允许用户在购买主机套餐时获得网盘服务。

## 🚀 部署步骤

### 1. 部署主机系统

```bash
# 克隆仓库
git clone https://github.com/1160269339/2.git /opt/hosting
cd /opt/hosting

# 运行部署脚本
bash deploy.sh
```

### 2. 配置cxym网盘

1. 登录主机系统后台
2. 进入「主机管理」→「产品管理」→「添加产品」
3. 选择插件类型为「cxym网盘」
4. 填写配置：
   - 网盘标题: 文件外链
   - 最大上传大小: 100MB
   - 存储类型: 本地存储
   - 本地存储路径: /data/files
5. 保存并发布

### 3. 创建网盘套餐

1. 进入「产品管理」→「添加产品」
2. 填写套餐信息：
   - 套餐名称: 免费网盘
   - 存储空间: 1GB
   - 最大上传: 100MB
   - 价格: 0元
3. 选择插件: cxym网盘
4. 保存

### 4. 测试功能

1. 前台注册账号
2. 购买网盘套餐
3. 测试上传下载

## 📊 系统架构

```
2号仓库（主机销售系统）
├── 主机管理
├── 支付系统
├── 用户系统
└── 插件系统
    └── cxym网盘插件
        ├── index.php (首页)
        ├── upload.php (上传)
        ├── file.php (文件列表)
        ├── down.php (下载)
        ├── admin/ (管理后台)
        └── includes/ (核心类库)
```

## 🗄️ 数据库结构

### 主机系统表（dd_*）
- dd_admin
- dd_order
- dd_product
- dd_server
- dd_user
- dd_pay_log
- dd_pay_monitor
- dd_pay_error
- dd_pay_alert

### cxym网盘表（pre_*）
- pre_config
- pre_file
- pre_user
- pre_download

## 🔧 配置说明

### cxym网盘配置
```php
// plugins/host/cxym/config.php
return [
    'title' => '文件外链',
    'max_size' => 100,
    'storage_type' => 'local',
    'filepath' => '/data/files',
    'allow_guest' => 1,
    'require_pwd' => 0,
];
```

### 主机系统配置
```php
// app/database.php
return [
    'type' => 'mysql',
    'hostname' => 'localhost',
    'database' => 'hosting',
    'username' => 'root',
    'password' => '',
    'prefix' => 'dd_',
];
```

## 📝 工作流程

1. 用户购买网盘套餐
2. 主机系统创建订单
3. 插件创建网盘用户
4. 用户获得网盘访问权限
5. 用户上传下载文件

## 🔒 安全配置

1. 设置文件上传大小限制
2. 配置文件类型白名单
3. 启用密码保护
4. 设置存储配额
5. 定期备份

## 📞 技术支持

- 原作者QQ: 2150811531
- QQ群: 905412821
