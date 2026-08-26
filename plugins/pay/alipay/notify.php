<?php
use think\Db;
use pay\alipay;

// 支付宝异步回调
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    exit("fail");
}

$config = [
    'appid'         => $ppay["0"]["value"],
    'private_key'   => $ppay["1"]["value"],
    'alipay_pubkey' => $ppay["2"]["value"],
];

$ali    = new alipay($config);
$params = $_POST;

if (!$ali->verify($params)) {
    exit("fail");
}

if ($params['trade_status'] !== 'TRADE_SUCCESS' && $params['trade_status'] !== 'TRADE_FINISHED') {
    exit("success");
}

$out_trade_no = $params['out_trade_no'];
$db = Db::name('pay')->where(["ordernumber" => $out_trade_no])->find();

if (!$db) {
    exit("fail");
}

if ($db["state"] == "1") {
    exit("success");
}

// 金额校验
if (number_format($db["money"], 2, '.', '') != $params['total_amount']) {
    exit("fail");
}

$user = Db::name('user')->where('id', $db["userid"])->find();
Db::name('user')->where('id', $db["userid"])->update([
    "money" => round($user["money"] + $db["money"], 2),
]);
Db::name('pay')->where(["ordernumber" => $out_trade_no])->update([
    "state" => "1",
]);

exit("success");
