#!/bin/bash
# 支付回调监控系统
# 使用方法: php /path/to/monitor.php

echo "🔍 开始监控支付回调..."

# 数据库配置
DB_HOST="localhost"
DB_USER="root"
DB_PASSWORD="root"
DB_NAME="root"
DB_PREFIX="dd_"

# 监控时间（最近7天）
END_DATE=$(date +%Y-%m-%d)
START_DATE=$(date -d "7 days ago" +%Y-%m-%d)

echo "📊 监控时间: $START_DATE ~ $END_DATE"
echo ""

# 1. 支付成功率统计
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "💰 支付成功率统计"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

mysql -h$DB_HOST -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "
SELECT 
    pay_type AS '支付方式',
    COUNT(*) AS '总回调数',
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS '成功数',
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS '失败数',
    ROUND(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS '成功率(%)'
FROM ${DB_PREFIX}pay_log
WHERE notify_time >= UNIX_TIMESTAMP('$START_DATE') 
  AND notify_time <= UNIX_TIMESTAMP('$END_DATE 23:59:59')
GROUP BY pay_type
ORDER BY pay_type;
"

echo ""

# 2. 签名验证失败统计
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔐 签名验证失败统计"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

mysql -h$DB_HOST -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "
SELECT 
    pay_type AS '支付方式',
    COUNT(*) AS '签名失败数',
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY pay_type), 2) AS '占比(%)'
FROM ${DB_PREFIX}pay_log
WHERE sign_verify = 0
  AND notify_time >= UNIX_TIMESTAMP('$START_DATE') 
  AND notify_time <= UNIX_TIMESTAMP('$END_DATE 23:59:59')
GROUP BY pay_type
ORDER BY pay_type;
"

echo ""

# 3. 错误统计
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "❌ 错误统计"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

mysql -h$DB_HOST -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "
SELECT 
    pay_type AS '支付方式',
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS '支付失败数',
    COUNT(*) AS '总回调数',
    ROUND(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS '失败率(%)'
FROM ${DB_PREFIX}pay_log
WHERE notify_time >= UNIX_TIMESTAMP('$START_DATE') 
  AND notify_time <= UNIX_TIMESTAMP('$END_DATE 23:59:59')
GROUP BY pay_type
ORDER BY pay_type;
"

echo ""

# 4. 异常告警
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⚠️  异常告警"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 高错误率告警（超过20%）
echo "🔴 高错误率告警（失败率 > 20%）:"
mysql -h$DB_HOST -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "
SELECT 
    pay_type AS '支付方式',
    COUNT(*) AS '总回调数',
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS '失败数',
    ROUND(SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS '失败率(%)'
FROM ${DB_PREFIX}pay_log
WHERE notify_time >= UNIX_TIMESTAMP('$START_DATE') 
  AND notify_time <= UNIX_TIMESTAMP('$END_DATE 23:59:59')
  AND SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) > 20
GROUP BY pay_type;
"

# 签名失败率告警（超过10%）
echo ""
echo "🔴 签名失败率告警（签名失败率 > 10%）:"
mysql -h$DB_HOST -u$DB_USER -p$DB_PASSWORD $DB_NAME -e "
SELECT 
    pay_type AS '支付方式',
    COUNT(*) AS '总回调数',
    SUM(CASE WHEN sign_verify = 0 THEN 1 ELSE 0 END) AS '签名失败数',
    ROUND(SUM(CASE WHEN sign_verify = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS '签名失败率(%)'
FROM ${DB_PREFIX}pay_log
WHERE notify_time >= UNIX_TIMESTAMP('$START_DATE') 
  AND notify_time <= UNIX_TIMESTAMP('$END_DATE 23:59:59')
  AND SUM(CASE WHEN sign_verify = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) > 10
GROUP BY pay_type;
"

echo ""
echo "✅ 监控完成！"
