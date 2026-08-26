<?php
/**
 * 码支付 - 发起支付
 */

use think\Db;
use think\Request;
use pay\maizhi;

// 获取订单信息
$oid = input('oid');
if (empty($oid)) {
    exit("<title>出错啦!</title>无效订单");
}

// 获取支付配置
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    exit("<title>出错啦!</title>码支付未配置");
}

$config = [
    'pid'    => $ppay["0"]["value"],
    'key'    => $ppay["1"]["value"],
    'apiurl' => $ppay["2"]["value"] ?? 'https://api.maizhifu.com/',
];

if (empty($config['pid']) || empty($config['key'])) {
    exit("<title>出错啦!</title>码支付未配置完整");
}

// 检查订单
$db = Db::name('pay')->where(['ordernumber' => $oid, 'state' => '2'])->find();
if (!$db) {
    exit("<title>出错啦!</title>订单不存在或已支付");
}

// 生成商户订单号
$out_trade_no = $db['ordernumber'];
$money = floatval($db['money']);

// 回调地址
$notify_url = Request::instance()->domain() . "/index/notify/" . $data1["id"] . "/";
$return_url = Request::instance()->domain() . "/user/return/" . $data1["id"] . "/";

// 构建支付参数
$param = [
    'money'    => $money,
    'order'    => $out_trade_no,
    'title'    => '订单支付',
    'callback' => $notify_url,
    'return'   => $return_url,
];

// 创建支付SDK实例
$maizhi = new maizhi($config);

// 发起支付
$result = $maizhi->apiPay($param);

if ($result['code'] == 1) {
    // 支付创建成功，跳转到支付页面
    $pay_url = $result['url'] ?? $maizhi->getPayLink($param);
    header('Location: ' . $pay_url);
    exit();
} else {
    exit("<title>出错啦!</title>" . ($result['msg'] ?? '支付创建失败'));
}
