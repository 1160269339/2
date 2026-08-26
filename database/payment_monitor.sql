-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-08-26 06:07:10
-- 服务器版本： 5.7.34
-- PHP 版本： 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `root`
--

-- --------------------------------------------------------

--
-- 表的结构 `dd_admin`
--

CREATE TABLE `dd_admin` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL,
  `password` varchar(288) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '管理员手机号（接收短信通知）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `dd_admin`
--

INSERT INTO `dd_admin` (`id`, `name`, `qq`, `user`, `password`, `mail`, `phone`) VALUES
(1, '会遇见那个她吗', '2150811531', 'admin', '$2y$10$MgZM3u4783aTERsO9jJSmu83KVYYIVxIhNR9kkHzjdU1/6tiM97kO', '2150811531@qq.com', '');

-- --------------------------------------------------------

--
-- 表的结构 `dd_affsymoney`
--

CREATE TABLE `dd_affsymoney` (
  `id` int(11) NOT NULL,
  `information` text NOT NULL,
  `money` varchar(100) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(50) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_afftxjl`
--

CREATE TABLE `dd_afftxjl` (
  `id` int(11) NOT NULL,
  `information` text NOT NULL,
  `money` varchar(100) NOT NULL,
  `state` varchar(10) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(50) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_announcement`
--

CREATE TABLE `dd_announcement` (
  `id` int(11) NOT NULL,
  `name` text NOT NULL,
  `information` text NOT NULL,
  `time` varchar(288) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_cart`
--

CREATE TABLE `dd_cart` (
  `id` int(11) NOT NULL,
  `product` varchar(200) NOT NULL,
  `name` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `money` varchar(200) NOT NULL DEFAULT '0',
  `cycle` varchar(100) NOT NULL,
  `firstmo` varchar(288) NOT NULL DEFAULT '0',
  `serverid` varchar(200) NOT NULL,
  `upgrade` varchar(10) NOT NULL DEFAULT '0',
  `upgrades` text,
  `buy` varchar(10) NOT NULL DEFAULT '0',
  `hide` varchar(10) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `renew` varchar(100) NOT NULL DEFAULT '0',
  `limits` varchar(288) NOT NULL DEFAULT '0',
  `inventory` varchar(100) NOT NULL DEFAULT '0',
  `data1` text,
  `data2` text,
  `data3` text,
  `data4` text,
  `data5` text,
  `data6` text,
  `data7` text,
  `data8` text,
  `data9` text,
  `data10` text,
  `data11` text,
  `data12` text,
  `data13` text,
  `data14` text,
  `data15` text,
  `data16` text,
  `data17` text,
  `data18` text,
  `data19` text,
  `data20` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_order`
--

CREATE TABLE `dd_order` (
  `id` int(11) NOT NULL,
  `user` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `userid` varchar(100) NOT NULL,
  `cartid` varchar(100) NOT NULL,
  `atime` varchar(300) NOT NULL,
  `ztime` varchar(300) NOT NULL,
  `state` varchar(200) NOT NULL,
  `data1` text,
  `data2` text,
  `data3` text,
  `data4` text,
  `data5` text,
  `data6` text,
  `data7` text,
  `data8` text,
  `data9` text,
  `data10` text,
  `data11` text,
  `data12` text,
  `data13` text,
  `data14` text,
  `data15` text,
  `data16` text,
  `data17` text,
  `data18` text,
  `data19` text,
  `data20` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_pay`
--

CREATE TABLE `dd_pay` (
  `id` int(11) NOT NULL,
  `name` varchar(288) NOT NULL,
  `ordernumber` varchar(288) NOT NULL,
  `pay` varchar(288) NOT NULL,
  `money` varchar(288) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(288) NOT NULL,
  `state` varchar(288) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_pays`
--

CREATE TABLE `dd_pays` (
  `id` int(11) NOT NULL,
  `name` varchar(288) NOT NULL,
  `plugins` varchar(288) NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT '0',
  `data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_product`
--

CREATE TABLE `dd_product` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `introduce` text NOT NULL,
  `hide` varchar(10) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_server`
--

CREATE TABLE `dd_server` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `host` varchar(100) NOT NULL,
  `ip` varchar(200) NOT NULL,
  `security` text NOT NULL,
  `port` varchar(200) NOT NULL,
  `ssl` varchar(200) NOT NULL DEFAULT '0',
  `user` varchar(300) NOT NULL,
  `password` varchar(300) NOT NULL,
  `serverplugins` varchar(288) NOT NULL,
  `data1` text,
  `data2` text,
  `data3` text,
  `data4` text,
  `data5` text,
  `data6` text,
  `data7` text,
  `data8` text,
  `data9` text,
  `data10` text,
  `data11` text,
  `data12` text,
  `data13` text,
  `data14` text,
  `data15` text,
  `data16` text,
  `data17` text,
  `data18` text,
  `data19` text,
  `data20` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_ticket`
--

CREATE TABLE `dd_ticket` (
  `id` int(11) NOT NULL,
  `title` varchar(288) NOT NULL,
  `content` mediumtext NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(100) NOT NULL,
  `state` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_transaction`
--

CREATE TABLE `dd_transaction` (
  `id` int(11) NOT NULL,
  `userid` varchar(288) NOT NULL,
  `content` text NOT NULL,
  `time` varchar(288) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_transferrecord`
--

CREATE TABLE `dd_transferrecord` (
  `id` int(11) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `record` mediumtext NOT NULL,
  `remark` varchar(255) NOT NULL,
  `time` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_user`
--

CREATE TABLE `dd_user` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `user` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `money` varchar(200) NOT NULL DEFAULT '0',
  `mail` varchar(300) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `aff` varchar(100) NOT NULL,
  `affmoney` varchar(100) NOT NULL DEFAULT '0',
  `upperid` varchar(100) NOT NULL,
  `time` varchar(200) NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT '1',
  `realname` varchar(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `idcard` varchar(30) NOT NULL DEFAULT '' COMMENT '身份证号',
  `smrz` varchar(10) NOT NULL DEFAULT '0' COMMENT '实名状态 0未实名 1已实名',
  `smtime` varchar(20) NOT NULL DEFAULT '' COMMENT '实名认证时间',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `dd_web`
--

CREATE TABLE `dd_web` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `keywords` text NOT NULL,
  `favicon` text NOT NULL,
  `template` varchar(288) NOT NULL,
  `admintemplate` varchar(288) NOT NULL,
  `wh` varchar(10) NOT NULL DEFAULT '0',
  `whxx` text NOT NULL,
  `email` varchar(100) DEFAULT '0',
  `emailchar` varchar(500) NOT NULL,
  `emailsecure` varchar(100) NOT NULL,
  `emailport` varchar(500) NOT NULL,
  `emailhost` varchar(500) NOT NULL,
  `emailname` varchar(500) NOT NULL,
  `emailpass` varchar(500) NOT NULL,
  `emailauth` varchar(500) NOT NULL,
  `affdiscount` varchar(100) NOT NULL,
  `affwithdrawal` varchar(100) NOT NULL,
  `cronzz` varchar(100) NOT NULL,
  `cronsc` varchar(100) NOT NULL,
  `paycron` varchar(100) NOT NULL,
  `tickcron` varchar(100) NOT NULL,
  `zcyxyz` varchar(100) NOT NULL DEFAULT '0',
  `yxdl` varchar(288) NOT NULL DEFAULT '0',
  `templateset` text NOT NULL,
  `smrzkg` varchar(10) NOT NULL DEFAULT '0' COMMENT '实名认证总开关 0关 1开',
  `smrzqz` varchar(10) NOT NULL DEFAULT '0' COMMENT '强制实名才能下单 0关 1开',
  `smrzqd` varchar(20) NOT NULL DEFAULT 'tencent' COMMENT '核身渠道 tencent腾讯FaceID / aliyun阿里实人认证',
  `smrzid` varchar(200) NOT NULL DEFAULT '' COMMENT 'SecretId / AccessKeyId',
  `smrzkey` text NOT NULL COMMENT 'SecretKey / AccessKeySecret',
  `smrzscene` varchar(100) NOT NULL DEFAULT '' COMMENT '腾讯RuleId规则ID / 阿里SceneId场景ID',
  `smskg` varchar(10) NOT NULL DEFAULT '0' COMMENT '短信功能总开关 0关 1开',
  `smsdl` varchar(10) NOT NULL DEFAULT '0' COMMENT '短信验证码登录开关 0关 1开',
  `zcsmyz` varchar(10) NOT NULL DEFAULT '0' COMMENT '注册短信验证开关 0关 1开',
  `smschannel` varchar(20) NOT NULL DEFAULT 'aliyun' COMMENT '短信渠道 aliyun阿里云 / tencent腾讯云',
  `smsid` varchar(200) NOT NULL DEFAULT '' COMMENT 'AccessKeyId / SecretId',
  `smskey` varchar(200) NOT NULL DEFAULT '' COMMENT 'AccessKeySecret / SecretKey',
  `smssign` varchar(100) NOT NULL DEFAULT '' COMMENT '短信签名',
  `smstemplate` varchar(100) NOT NULL DEFAULT '' COMMENT '阿里模板CODE / 腾讯模板ID',
  `smsappid` varchar(100) NOT NULL DEFAULT '' COMMENT '腾讯云短信应用 SdkAppId(仅腾讯云需要)',
  `geetest_type` varchar(10) NOT NULL DEFAULT '0' COMMENT '验证码类型 0系统图片 1极验v4',
  `gt4_id` varchar(100) NOT NULL DEFAULT '' COMMENT '极验v4 captcha_id',
  `gt4_key` varchar(200) NOT NULL DEFAULT '' COMMENT '极验v4 captcha_key',
  `captcha_type` varchar(10) NOT NULL DEFAULT 'system' COMMENT '验证码类型 system系统图片 / geetest极验',
  `geetest_id` varchar(100) NOT NULL COMMENT '极验 CaptchaId',
  `geetest_key` varchar(100) NOT NULL COMMENT '极验 PrivateKey',
  `notify_channel` varchar(10) NOT NULL DEFAULT 'auto' COMMENT '消息提醒渠道：auto/sms/email',
  `smstztemplate` varchar(100) NOT NULL DEFAULT '' COMMENT '短信通知模板（阿里云填模板CODE，腾讯云填模板ID）'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `dd_web`
--

INSERT INTO `dd_web` (`id`, `name`, `description`, `keywords`, `favicon`, `template`, `admintemplate`, `wh`, `whxx`, `email`, `emailchar`, `emailsecure`, `emailport`, `emailhost`, `emailname`, `emailpass`, `emailauth`, `affdiscount`, `affwithdrawal`, `cronzz`, `cronsc`, `paycron`, `tickcron`, `zcyxyz`, `yxdl`, `templateset`, `smrzkg`, `smrzqz`, `smrzqd`, `smrzid`, `smrzkey`, `smrzscene`, `smskg`, `smsdl`, `zcsmyz`, `smschannel`, `smsid`, `smskey`, `smssign`, `smstemplate`, `smsappid`, `geetest_type`, `gt4_id`, `gt4_key`, `captcha_type`, `geetest_id`, `geetest_key`, `notify_channel`, `smstztemplate`) VALUES
(1, '江湖主机', '江湖主机,提供快速、稳定、优质的虚拟主机服务！', '江湖主机,免费主机,公益主机,虚拟主机,香港虚拟主机,美国虚拟主机,免备案虚拟主机', '/favicon.ico', 'default', 'default', '0', '<title>维护中...</title>\r\n网站维护中...', '1', 'UTF-8', 'ssl', '465', 'smtp.qq.com', '123456@qq.com', '123456', 'true', '0.25', '10', '3', '3', '5', '3', '0', '0', '[{\"name\":\"\\u7f51\\u7ad9\\u80cc\\u666f\",\"title\":\"\\u7f51\\u7ad9\\u80cc\\u666f\",\"type\":\"input\",\"prompt\":\"\\u7f51\\u7ad9\\u80cc\\u666f\\u56fe\\u5730\\u5740 \",\"value\":\"https:\\/\\/image-assets.soutushenqi.com\\/UserUploadWallpaper_upload\\/1742534052658.jpg\"},{\"name\":\"\\u7f51\\u7ad9\\u6807\\u9898\",\"title\":\"\\u7f51\\u7ad9\\u6807\\u9898\",\"type\":\"input\",\"prompt\":\"\\u7f51\\u7ad9\\u9996\\u9875\\u6807\\u9898\",\"value\":\"\\u53ea\\u63d0\\u4f9b\\u5feb\\u901f\\u3001\\u7a33\\u5b9a\\u3001\\u4f18\\u8d28\\u7684\\u865a\\u62df\\u4e3b\\u673a\\u670d\\u52a1!\"},{\"name\":\"\\u516c\\u544a\\u5f00\\u5173\",\"title\":\"\\u662f\\u5426\\u5f00\\u542f\\u516c\\u544a\",\"type\":\"select\",\"prompt\":\"\\u516c\\u544a\\u5f00\\u5173\",\"value\":\"\\u5f00\",\"option\":[\"\\u5f00\",\"\\u5173\"]},{\"name\":\"\\u7f51\\u7ad9\\u516c\\u544a\",\"title\":\"\\u7f51\\u7ad9\\u516c\\u544a\",\"type\":\"textarea\",\"prompt\":\"\\u516c\\u544a\\u5185\\u5bb9,\\u652f\\u6301HTML,\\u6ce8\\u610f\\u8fd9\\u91cc\\u4e0d\\u80fd\\u56de\\u8f66\\u6362\\u884c,\\u53ea\\u80fd\\u7528<br\\/>\",\"value\":\"\\u5b98\\u65b9QQ\\u7fa4\\uff1a<a href=\\\"http:\\/\\/qm.qq.com\\/cgi-bin\\/qm\\/qr?_wv=1027&k=cAx0B2wnpLKJuxnXXzAwv9Tb9UDB0lCj&authKey=FXMLYeR%2BSJMdJ%2BPBsqo9kxmXmIZunnXOhvoczADvdlj%2FIUffADz5hvupmGZflR74&noverify=0&group_code=905412821\\\" style=\\\"font-size:16px\\\">905412821<\\/a><br\\/>TG\\u7fa4\\u7ec4\\uff1a<a href=\\\"https:\\/\\/t.me\\/knfyyfnb\\\" style=\\\"font-size:16px\\\">t.me\\/knfyyfnb<\\/a>\"},{\"name\":\"\\u4fa7\\u8fb9\\u680f\",\"title\":\"\\u4fa7\\u8fb9\\u680f\\u6837\\u5f0f\",\"type\":\"textarea\",\"prompt\":\"\\u4fa7\\u8fb9\\u680f\\u6837\\u5f0f,\\u6ce8\\u610f\\u8fd9\\u91cc\\u4e0d\\u80fd\\u56de\\u8f66\\u6362\\u884c,\\u53ea\\u80fd\\u7528<br\\/>\",\"value\":\"<li class=\\\"nav-main-item\\\"><a class=\\\"nav-main-link\\\" href=\\\"http:\\/\\/qm.qq.com\\/cgi-bin\\/qm\\/qr?_wv=1027&k=cAx0B2wnpLKJuxnXXzAwv9Tb9UDB0lCj&authKey=FXMLYeR%2BSJMdJ%2BPBsqo9kxmXmIZunnXOhvoczADvdlj%2FIUffADz5hvupmGZflR74&noverify=0&group_code=905412821\\\"><i class=\\\"nav-main-link-icon fas fa-paper-plane\\\"><\\/i><span class=\\\"nav-main-link-name\\\">\\u52a0\\u5165QQ\\u7fa4<\\/span><\\/a><\\/li>\"},{\"name\":\"\\u7f51\\u7ad9\\u5907\\u6848\\u53f7\",\"title\":\"\\u7f51\\u7ad9\\u5907\\u6848\\u53f7\",\"type\":\"input\",\"prompt\":\"\\u7f51\\u7ad9\\u5907\\u6848\\u53f7,\\u4e0d\\u586b\\u8bf7\\u7559\\u7a7a\",\"value\":\"\"}]', '0', '0', 'zhima', '', '', '', '0', '0', '0', 'aliyun', '', '', '', '', '', '1', '35ab6a20a1757a6396e49e30eaffa213', 'd09edd534fa9e9e248a0bd83be17b7b0', 'geetest', '0054ec684e8c504fad3e0ad1c89a7e90', 'b73482fd85f6918102cd21cdf592c35a', 'auto', '');

--
-- 转储表的索引
--

--
-- 表的索引 `dd_admin`
--
ALTER TABLE `dd_admin`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_affsymoney`
--
ALTER TABLE `dd_affsymoney`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_afftxjl`
--
ALTER TABLE `dd_afftxjl`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_announcement`
--
ALTER TABLE `dd_announcement`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_cart`
--
ALTER TABLE `dd_cart`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_order`
--
ALTER TABLE `dd_order`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_pay`
--
ALTER TABLE `dd_pay`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_pays`
--
ALTER TABLE `dd_pays`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_product`
--
ALTER TABLE `dd_product`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_server`
--
ALTER TABLE `dd_server`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_ticket`
--
ALTER TABLE `dd_ticket`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_transaction`
--
ALTER TABLE `dd_transaction`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_transferrecord`
--
ALTER TABLE `dd_transferrecord`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_user`
--
ALTER TABLE `dd_user`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `dd_web`
--
ALTER TABLE `dd_web`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `dd_admin`
--
ALTER TABLE `dd_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `dd_affsymoney`
--
ALTER TABLE `dd_affsymoney`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_afftxjl`
--
ALTER TABLE `dd_afftxjl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_announcement`
--
ALTER TABLE `dd_announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_cart`
--
ALTER TABLE `dd_cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_order`
--
ALTER TABLE `dd_order`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_pay`
--
ALTER TABLE `dd_pay`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_pays`
--
ALTER TABLE `dd_pays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_product`
--
ALTER TABLE `dd_product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_server`
--
ALTER TABLE `dd_server`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_ticket`
--
ALTER TABLE `dd_ticket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_transaction`
--
ALTER TABLE `dd_transaction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_transferrecord`
--
ALTER TABLE `dd_transferrecord`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_user`
--
ALTER TABLE `dd_user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dd_web`
--
ALTER TABLE `dd_web`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;



-- =====================================================
-- 支付回调监控表结构
-- 创建时间: 2026-08-26
-- =====================================================

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
  `alert_type` varchar(50) NOT NULL COMMENT '告警类型: high_error_rate, high_sign_fail等',
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
