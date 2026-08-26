<?php
use think\Db;
use pay\wxpay;

// 微信支付 V3 异步回调
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    header('Content-Type: application/json');
    exit(json_encode(["code" => "FAIL", "message" => "没有参数文件"]));
}

$config = [
    'appid'       => $ppay["0"]["value"],
    'mchid'       => $ppay["1"]["value"],
    'apiv3key'    => $ppay["2"]["value"],
    'serial_no'   => $ppay["3"]["value"],
    'private_key' => $ppay["4"]["value"],
];

$wx      = new wxpay($config);
$rawBody = file_get_contents('php://input');
$plain   = $wx->decryptNotify($rawBody);

header('Content-Type: application/json');

if (!$plain || !isset($plain['out_trade_no'])) {
    exit(json_encode(["code" => "FAIL", "message" => "解密失败"]));
}

if ($plain['trade_state'] !== 'SUCCESS') {
    // 非成功状态，直接应答避免重复通知
    exit(json_encode(["code" => "SUCCESS", "message" => "OK"]));
}

$out_trade_no = $plain['out_trade_no'];
$db = Db::name('pay')->where(["ordernumber" => $out_trade_no])->find();

if (!$db) {
    exit(json_encode(["code" => "FAIL", "message" => "未找到订单"]));
}

if ($db["state"] == "1") {
    // 已处理过
    exit(json_encode(["code" => "SUCCESS", "message" => "OK"]));
}

// 金额校验（分）
$paidFen = isset($plain['amount']['total']) ? (int)$plain['amount']['total'] : 0;
if ($paidFen !== (int)round($db["money"] * 100)) {
    exit(json_encode(["code" => "FAIL", "message" => "金额不符"]));
}

$user = Db::name('user')->where('id', $db["userid"])->find();
Db::name('user')->where('id', $db["userid"])->update([
    "money" => round($user["money"] + $db["money"], 2),
]);
Db::name('pay')->where(["ordernumber" => $out_trade_no])->update([
    "state" => "1",
]);

exit(json_encode(["code" => "SUCCESS", "message" => "OK"]));
