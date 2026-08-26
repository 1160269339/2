<?php
/**
 * 码支付 - 异步回调
 * 用于处理支付结果通知
 */

use think\Db;

// 加载码支付SDK
require_once __DIR__ . '/../../../extend/pay/maizhi.php';

// 获取配置
$config = get_config('maizhi');
if (empty($config['pid']) || empty($config['key'])) {
    exit('fail');
}

// 创建支付SDK实例
$maizhi = new \pay\maizhi($config);

// 验证签名
if (!$maizhi->verifyNotify()) {
    exit('fail');
}

// 获取回调参数
$out_trade_no = input('order');
$trade_no = input('trade_no');
$money = floatval(input('money'));
$status = input('status');

// 检查订单
$order = Db::name('order')->where('oid', $out_trade_no)->find();
if (!$order) {
    exit('fail');
}

// 检查是否已支付
if ($order['status'] > 0) {
    exit('success');
}

// 金额校验
if (abs($order['price'] - $money) > 0.01) {
    // 记录异常
    Db::name('logs')->insert([
        'type' => 'pay_error',
        'content' => '金额不一致: 订单金额=' . $order['price'] . ', 回调金额=' . $money,
        'addtime' => time(),
    ]);
    exit('fail');
}

// 更新订单
Db::name('order')->where('oid', $out_trade_no)->update([
    'status' => 1,
    'trade_no' => $trade_no,
    'paytime' => time(),
]);

// 更新用户余额（如果是充值）
if ($order['type'] == 'recharge') {
    Db::name('user')->where('uid', $order['uid'])->inc('money', $money)->update();
}

// 记录支付日志
Db::name('pay_log')->insert([
    'oid' => $out_trade_no,
    'pid' => 'maizhi',
    'money' => $money,
    'trade_no' => $trade_no,
    'status' => 1,
    'addtime' => time(),
]);

// 返回成功
exit('success');
