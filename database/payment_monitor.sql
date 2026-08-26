-- 支付回调日志表
CREATE TABLE IF NOT EXISTS `dd_pay_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_type` varchar(50) NOT NULL COMMENT '支付类型',
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `trade_no` varchar(100) DEFAULT NULL COMMENT '第三方交易号',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态: 0-待支付 1-成功 2-失败',
  `notify_time` int(11) NOT NULL COMMENT '回调时间',
  `ip` varchar(50) DEFAULT NULL COMMENT '回调IP',
  `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
  `request_data` text COMMENT '请求参数',
  `response_data` text COMMENT '响应数据',
  `sign_verify` tinyint(1) NOT NULL DEFAULT '0' COMMENT '签名验证: 0-失败 1-成功',
  `error_msg` varchar(500) DEFAULT NULL COMMENT '错误信息',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `order_no` (`order_no`),
  KEY `pay_type` (`pay_type`),
  KEY `status` (`status`),
  KEY `notify_time` (`notify_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付回调日志表';

-- 支付监控统计表
CREATE TABLE IF NOT EXISTS `dd_pay_monitor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_type` varchar(50) NOT NULL COMMENT '支付类型',
  `day` date NOT NULL COMMENT '日期',
  `total_count` int(11) NOT NULL DEFAULT '0' COMMENT '总回调数',
  `success_count` int(11) NOT NULL DEFAULT '0' COMMENT '成功数',
  `fail_count` int(11) NOT NULL DEFAULT '0' COMMENT '失败数',
  `sign_fail_count` int(11) NOT NULL DEFAULT '0' COMMENT '签名失败数',
  `error_count` int(11) NOT NULL DEFAULT '0' COMMENT '错误数',
  `avg_time` decimal(10,3) DEFAULT '0.000' COMMENT '平均响应时间(秒)',
  `max_time` decimal(10,3) DEFAULT '0.000' COMMENT '最大响应时间(秒)',
  `min_time` decimal(10,3) DEFAULT '0.000' COMMENT '最小响应时间(秒)',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `pay_type_day` (`pay_type`,`day`),
  KEY `day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付监控统计表';

-- 支付错误记录表
CREATE TABLE IF NOT EXISTS `dd_pay_error` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_type` varchar(50) NOT NULL COMMENT '支付类型',
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `error_type` varchar(50) NOT NULL COMMENT '错误类型: sign_verify_error, amount_error, order_not_found等',
  `error_msg` text COMMENT '错误信息',
  `ip` varchar(50) DEFAULT NULL COMMENT '回调IP',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `pay_type` (`pay_type`),
  KEY `order_no` (`order_no`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付错误记录表';

-- 支付回调异常告警表
CREATE TABLE IF NOT EXISTS `dd_pay_alert` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pay_type` varchar(50) NOT NULL COMMENT '支付类型',
  `alert_type` varchar(50) NOT NULL COMMENT '告警类型: high_error_rate, high_sign_fail, etc.',
  `alert_level` varchar(20) NOT NULL COMMENT '告警级别: info, warning, error, critical',
  `alert_content` text COMMENT '告警内容',
  `alert_data` text COMMENT '告警数据',
  `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `pay_type` (`pay_type`),
  KEY `is_read` (`is_read`),
  KEY `create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='支付回调异常告警表';
