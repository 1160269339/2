<?php
/**
 * 码支付 - 同步回调
 * 用于用户支付完成后跳转
 */

use think\Db;

// 加载码支付SDK
require_once __DIR__ . '/../../../extend/pay/maizhi.php';

// 获取配置
$config = get_config('maizhi');
if (empty($config['pid']) || empty($config['key'])) {
    redirect(url('index/user/payrecord'));
}

// 创建支付SDK实例
$maizhi = new \pay\maizhi($config);

// 验证签名
$signed = $maizhi->verifyReturn();

// 获取订单信息
$out_trade_no = input('order');
$order = Db::name('order')->where('oid', $out_trade_no)->where('uid', session('uid'))->find();

if ($signed && $order && $order['status'] == 1) {
    // 支付成功
    success('支付成功', url('index/user/payrecord'));
} else {
    // 支付失败或订单不存在
    redirect(url('index/user/payrecord'));
}
