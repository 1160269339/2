<?php
use think\Db;
use pay\alipay;

$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file && $data1["data"]) {
    $ppay = json_decode($data1["data"], true);
} else {
    $msg = "没有参数文件";
    return;
}

$config = [
    'appid'         => $ppay["0"]["value"],
    'private_key'   => $ppay["1"]["value"],
    'alipay_pubkey' => $ppay["2"]["value"],
];
$ali = new alipay($config);

// 结算函数：查单成功则加余额
$settle = function ($no) {
    $db = Db::name('pay')->where(["ordernumber" => $no])->find();
    if (!$db) {
        return false;
    }
    if ($db["state"] == "1") {
        return true;
    }
    $user = Db::name('user')->where('id', $db["userid"])->find();
    Db::name('user')->where('id', $db["userid"])->update([
        "money" => round($user["money"] + $db["money"], 2),
    ]);
    Db::name('pay')->where(["ordernumber" => $no])->update(["state" => "1"]);
    return true;
};

/* ---------- 情况1：扫码页 ajax 轮询(POST no) ---------- */
if (input("no")) {
    $no = input("no");
    $db = Db::name('pay')->where(["ordernumber" => $no])->find();
    if (!$db) {
        exit(json_encode(["code" => "-1", "msg" => "未找到订单"]));
    }
    if ($db["state"] == "1") {
        exit(json_encode(["code" => "1", "msg" => "支付成功"]));
    }
    // 兜底主动查单
    $status = $ali->query($no);
    if ($status === 'TRADE_SUCCESS' || $status === 'TRADE_FINISHED') {
        $settle($no);
        exit(json_encode(["code" => "1", "msg" => "支付成功"]));
    }
    exit(json_encode(["code" => "0", "msg" => "等待支付"]));
}

/* ---------- 情况2：电脑/手机网站支付浏览器同步跳回(GET) ---------- */
if (isset($_GET['out_trade_no'])) {
    if ($ali->verify($_GET)) {
        $no     = $_GET['out_trade_no'];
        $status = $ali->query($no);
        if ($status === 'TRADE_SUCCESS' || $status === 'TRADE_FINISHED') {
            $settle($no);
            $msg = "充值成功";
        } else {
            $msg = "支付处理中,请稍后在充值记录查看";
        }
    } else {
        $msg = "验证失败";
    }
} else {
    $msg = "参数错误";
}
