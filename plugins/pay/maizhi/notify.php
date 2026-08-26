<?php
/**
 * 码支付 - 异步回调
 */

use think\Db;
use pay\maizhi;

// 获取支付配置
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    exit('fail');
}

$config = [
    'pid'    => $ppay["0"]["value"],
    'key'    => $ppay["1"]["value"],
    'apiurl' => $ppay["2"]["value"] ?? 'https://api.maizhifu.com/',
];

if (empty($config['pid']) || empty($config['key'])) {
    exit('fail');
}

// 创建支付SDK实例
$maizhi = new maizhi($config);

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
$db = Db::name('pay')->where(['ordernumber' => $out_trade_no, 'state' => '2'])->find();
if (!$db) {
    exit('fail');
}

// 金额校验
if (abs(floatval($db['money']) - $money) > 0.01) {
    exit('fail');
}

// 更新订单状态
Db::name('pay')->where(['ordernumber' => $out_trade_no])->update([
    'state' => '1',
]);

// 更新用户余额
$user = Db::name('user')->where('id', $db['userid'])->find();
if ($user) {
    Db::name('user')->where('id', $db['userid'])->update([
        'money' => round(floatval($user['money']) + $money, 2),
    ]);
}

// 记录交易记录
Db::name('transaction')->insert([
    'userid' => $db['userid'],
    'content' => '码支付充值',
    'money' => $money,
    'time' => time(),
]);

// 返回成功
exit('success');
