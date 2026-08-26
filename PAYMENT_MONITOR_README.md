# 支付回调监控系统 - 集成指南

## 📋 功能概述

支付回调监控系统用于记录、监控和分析所有支付回调，提供实时统计、错误追踪和异常告警功能。

## 🗄️ 数据库表

### 1. dd_pay_log - 支付回调日志表
```sql
- id: 主键
- pay_type: 支付类型（alipay、wxpay、epay、maizhi等）
- order_no: 订单号
- amount: 金额
- trade_no: 第三方交易号
- status: 支付状态（0-待支付 1-成功 2-失败）
- notify_time: 回调时间
- ip: 回调IP
- user_agent: 用户代理
- request_data: 请求参数（JSON）
- response_data: 响应数据（JSON）
- sign_verify: 签名验证结果（0-失败 1-成功）
- error_msg: 错误信息
- create_time: 创建时间
```

### 2. dd_pay_monitor - 支付监控统计表
```sql
- id: 主键
- pay_type: 支付类型
- day: 日期
- total_count: 总回调数
- success_count: 成功数
- fail_count: 失败数
- sign_fail_count: 签名失败数
- error_count: 错误数
- avg_time: 平均响应时间（秒）
- max_time: 最大响应时间（秒）
- min_time: 最小响应时间（秒）
- create_time: 创建时间
```

### 3. dd_pay_error - 支付错误记录表
```sql
- id: 主键
- pay_type: 支付类型
- order_no: 订单号
- error_type: 错误类型
- error_msg: 错误信息
- ip: 回调IP
- create_time: 创建时间
```

### 4. dd_pay_alert - 支付回调异常告警表
```sql
- id: 主键
- pay_type: 支付类型
- alert_type: 告警类型
- alert_level: 告警级别
- alert_content: 告警内容
- alert_data: 告警数据
- is_read: 是否已读
- create_time: 创建时间
```

## 📁 文件结构

```
/
├── database/
│   └── payment_monitor.sql        # 数据库表结构
├── app/
│   └── admin/
│       └── controller/
│           └── PayMonitor.php     # 监控控制器
├── public/
│   └── monitor.html               # 监控页面
└── scripts/
    └── payment_monitor.sh         # 监控脚本
```

## 🔧 集成步骤

### 1. 创建数据库表

```bash
mysql -u root -p root < database/payment_monitor.sql
```

### 2. 复制监控类到项目

将 `PayMonitor.php` 复制到 `app/admin/controller/PayMonitor.php`

### 3. 创建监控页面

将 `monitor.html` 复制到 `public/monitor.html`

### 4. 在支付回调中集成

在所有支付回调文件中添加监控记录：

```php
use app\\admin\\controller\\PayMonitor;

$monitor = new PayMonitor();

// 记录回调
$monitor->log(
    'alipay',           // 支付类型
    $out_trade_no,      // 订单号
    $money,             // 金额
    $trade_no,          // 第三方交易号
    $status,            // 支付状态
    $sign_verify,       // 签名验证结果
    $error_msg          // 错误信息
);
```

### 5. 配置定时任务

添加定时任务，每天凌晨执行监控脚本：

```bash
# crontab -e
0 0 * * * /bin/bash /path/to/scripts/payment_monitor.sh >> /var/log/payment_monitor.log 2>&1
```

## 📊 API 接口

### 获取支付日志

```http
GET /admin/pay_monitor/logs?page=1&limit=50
```

### 获取支付统计

```http
GET /admin/pay_monitor/stats?days=7
```

### 获取错误列表

```http
GET /admin/pay_monitor/errors?page=1&limit=50
```

### 检查异常告警

```http
GET /admin/pay_monitor/check_alerts
```

### 获取告警列表

```http
GET /admin/pay_monitor/alerts?is_read=0
```

### 标记告警已读

```http
POST /admin/pay_monitor/mark_read/{id}
```

## 🚨 告警规则

### 1. 高错误率告警
- **触发条件**: 支付失败率 > 20%
- **告警级别**: Error
- **说明**: 支付失败率过高，需要检查支付配置

### 2. 高签名失败率告警
- **触发条件**: 签名验证失败率 > 10%
- **告警级别**: Warning
- **说明**: 签名验证失败率过高，可能配置错误

## 📈 监控指标

### 核心指标

1. **支付成功率**: 成功数 / 总回调数
2. **支付失败率**: 失败数 / 总回调数
3. **签名验证成功率**: 签名成功数 / 总回调数
4. **平均响应时间**: 总时间 / 回调数

### 异常指标

1. **高错误率**: 失败率 > 20%
2. **高签名失败率**: 签名失败率 > 10%
3. **异常IP**: 同一IP大量回调

## 🔍 使用示例

### 查看监控页面

访问: `http://your-domain.com/monitor.html`

### 查看告警

```php
$monitor = new PayMonitor();

// 获取未读告警
$alerts = $monitor->getAlerts(0);

// 标记所有告警已读
$monitor->markAllAlertsRead();

// 检查异常
$alerts = $monitor->checkAlerts();
```

### 手动监控

```bash
bash /path/to/scripts/payment_monitor.sh
```

## 📝 注意事项

1. **定期清理**: 定期清理旧日志（保留90天）
2. **性能优化**: 监控脚本建议每天执行一次
3. **告警通知**: 可以集成邮件/短信告警
4. **数据备份**: 定期备份数据库表

## 🎯 最佳实践

1. **实时监控**: 监控页面可以设置定时刷新
2. **告警通知**: 建议配置告警通知（邮件/短信/企业微信）
3. **日志分析**: 定期分析日志，发现潜在问题
4. **性能监控**: 关注平均响应时间，优化回调性能
