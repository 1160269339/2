<?php
use think\Db;
use pay\wxpay;

// 前端轮询查询订单是否支付成功（QR/JSAPI 页面 ajax 调用）
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    exit(json_encode(["code" => "-1", "msg" => "没有参数文件"]));
}

$no = input("no");
if (!$no) {
    exit(json_encode(["code" => "-1", "msg" => "缺少订单号"]));
}

$db = Db::name('pay')->where(["ordernumber" => $no])->find();
if (!$db) {
    exit(json_encode(["code" => "-1", "msg" => "未找到订单"]));
}

// 已通过异步回调置成功
if ($db["state"] == "1") {
    exit(json_encode(["code" => "1", "msg" => "支付成功"]));
}

// 兜底：主动向微信查询一次，防止回调未到达
$config = [
    'appid'       => $ppay["0"]["value"],
    'mchid'       => $ppay["1"]["value"],
    'apiv3key'    => $ppay["2"]["value"],
    'serial_no'   => $ppay["3"]["value"],
    'private_key' => $ppay["4"]["value"],
];
$wx    = new wxpay($config);
$state = $wx->query($no);

if ($state === 'SUCCESS') {
    $user = Db::name('user')->where('id', $db["userid"])->find();
    Db::name('user')->where('id', $db["userid"])->update([
        "money" => round($user["money"] + $db["money"], 2),
    ]);
    Db::name('pay')->where(["ordernumber" => $no])->update([
        "state" => "1",
    ]);
    exit(json_encode(["code" => "1", "msg" => "支付成功"]));
}

exit(json_encode(["code" => "0", "msg" => "等待支付"]));
