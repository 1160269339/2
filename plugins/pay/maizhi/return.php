<?php
/**
 * 码支付 - 同步回调
 */

use think\Db;
use pay\maizhi;

// 获取支付配置
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    redirect(url('index/user/payrecord'));
}

$config = [
    'pid'    => $ppay["0"]["value"],
    'key'    => $ppay["1"]["value"],
    'apiurl' => $ppay["2"]["value"] ?? 'https://api.maizhifu.com/',
];

if (empty($config['pid']) || empty($config['key'])) {
    redirect(url('index/user/payrecord'));
}

// 创建支付SDK实例
$maizhi = new maizhi($config);

// 验证签名
if (!$maizhi->verifyReturn()) {
    redirect(url('index/user/payrecord'));
}

// 获取订单信息
$out_trade_no = input('order');
$db = Db::name('pay')->where(['ordernumber' => $out_trade_no, 'state' => '1'])->find();

if ($db) {
    success('支付成功', url('index/user/payrecord'));
} else {
    redirect(url('index/user/payrecord'));
}
