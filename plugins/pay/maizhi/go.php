<?php
/**
 * 码支付 - 发起支付
 * 用于用户选择码支付后进行支付跳转
 */

use think\Db;
use think\Config;

// 加载码支付SDK
require_once __DIR__ . '/../../../extend/pay/maizhi.php';

// 获取订单信息
$oid = intval(input('oid'));
if (empty($oid)) {
    error('无效订单');
}

$order = Db::name('order')->where('oid', $oid)->where('uid', session('uid'))->find();
if (!$order) {
    error('订单不存在');
}

// 检查支付状态
if ($order['status'] > 0) {
    success('订单已支付');
}

// 获取配置
$config = get_config('maizhi');
if (empty($config['pid']) || empty($config['key'])) {
    error('码支付未配置');
}

// 生成商户订单号
$out_trade_no = $order['oid'];

// 构建支付参数
$param = [
    'money'  => $order['price'],
    'order'  => $out_trade_no,
    'title'  => '订单' . $out_trade_no . '支付',
    'callback' => url('notify', '', '', false, true),
    'return' => url('return', '', '', false, true),
];

// 创建支付SDK实例
$maizhi = new \pay\maizhi($config);

// 发起支付
$result = $maizhi->apiPay($param);

if ($result['code'] == 1) {
    // 支付创建成功
    redirect($result['url'] ?? $maizhi->getPayLink($param));
} else {
    error($result['msg'] ?? '支付创建失败');
}
