<?php
use think\Db;
use think\Request;
use pay\alipay;

$user = Db::name('user')->where('id', session("userid"))->find();
$file = file_exists(PATH . "/plugins/pay/" . $data1["plugins"] . "/set.php");
if ($file) {
    if ($data1["data"]) {
        $ppay = json_decode($data1["data"], true);
    } else {
        exit("<title>出错啦!</title>没有参数文件!");
    }
} else {
    exit("<title>出错啦!</title>没有参数文件!");
}

$config = [
    'appid'         => $ppay["0"]["value"],
    'private_key'   => $ppay["1"]["value"],
    'alipay_pubkey' => $ppay["2"]["value"],
];
$paytype = $ppay["3"]["value"];

if ($config['appid'] == "" || $config['private_key'] == "" || $config['alipay_pubkey'] == "") {
    exit("<title>出错啦!</title>此站点未配置支付宝接口!");
}

// 生成订单号并落库
$out_trade_no = date('YmdHis') . rand(1000, 9999);
$money = input("money");
Db::name('pay')->insertGetId([
    "name"        => "余额充值",
    "ordernumber" => $out_trade_no,
    "pay"         => input("payid"),
    "money"       => $money,
    "userid"      => $user["id"],
    "time"        => time(),
    "state"       => "2",
]);

$notify_url = Request::instance()->domain() . "/index/notify/" . $data1["id"] . "/";
$return_url = Request::instance()->domain() . "/user/return/" . $data1["id"] . "/";
$subject    = "账号ID:" . $user["id"] . ",余额充值";

// 自动模式：手机跳手机网站支付，电脑出当面付扫码
if ($paytype == "自动") {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
        $paytype = "手机网站";
    } else {
        $paytype = "当面付扫码";
    }
}

$ali = new alipay($config);

/* ---------------- 电脑网站支付 ---------------- */
if ($paytype == "电脑网站") {
    echo $ali->page($out_trade_no, $money, $subject, $notify_url, $return_url);
    exit();
}

/* ---------------- 手机网站支付(H5) ---------------- */
if ($paytype == "手机网站") {
    echo $ali->wap($out_trade_no, $money, $subject, $notify_url, $return_url);
    exit();
}

/* ---------------- 当面付扫码 ---------------- */
if ($paytype == "当面付扫码") {
    $qr_code = $ali->precreate($out_trade_no, $money, $subject, $notify_url);
    if (!$qr_code) {
        exit("<title>出错啦!</title>当面付下单失败,请检查配置!");
    }
    $qr = 'https://api.pwmqr.com/qrcode/create/?url=' . urlencode($qr_code);
    exit(alipay_qr_page($out_trade_no, $subject, $money, $qr, $qr_code, $data1["id"]));
}

/* ---------------- APP支付 ---------------- */
if ($paytype == "APP") {
    $orderString = $ali->app($out_trade_no, $money, $subject, $notify_url);
    exit(json_encode(["code" => 1, "data" => ["orderString" => $orderString, "out_trade_no" => $out_trade_no]]));
}

exit("<title>出错啦!</title>未知的支付方式!");


/* ================= 扫码页模板 ================= */
function alipay_qr_page($out_trade_no, $subject, $money, $qr, $qr_code, $payid)
{
    return "
<!DOCTYPE html><html lang='zh'><head><meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>支付宝支付</title>
<style>
body{background:#f7f7f7;font-family:-apple-system,'Microsoft Yahei';margin:0}
.box{max-width:420px;margin:30px auto;background:#fff;border-radius:12px;padding:28px 20px;text-align:center;box-shadow:0 3px 12px rgba(0,0,0,.06)}
h2{color:#1677ff;margin:0 0 18px}
.od{background:#fbfbfb;border-radius:6px;padding:10px;color:#888;font-size:13px;margin:10px 0;text-align:left}
.price{color:#1677ff;font-size:30px;font-weight:700;margin:12px 0}
.price small{font-size:16px}
img.qr{width:210px;height:210px;border:5px solid #eee;border-radius:6px}
.tip{color:#999;font-size:14px;margin-top:14px}
.btn{display:inline-block;margin-top:14px;padding:10px 26px;background:#1677ff;color:#fff;border-radius:22px;text-decoration:none;font-weight:700}
</style></head><body>
<div class='box'>
<h2>支付宝扫码支付</h2>
<div class='od'>订单号：{$out_trade_no}<br>商品：{$subject}</div>
<div class='price'>{$money}<small> 元</small></div>
<img class='qr' src='{$qr}'/>
<div class='tip'>请使用支付宝扫一扫完成支付</div>
<a class='btn' href='alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode=" . urlencode($qr_code) . "'>手机端点此启动支付宝</a>
</div>
<script src='//lib.baomitu.com/jquery/1.12.4/jquery.min.js'></script>
<script src='https://www.layuicdn.com/layer/layer.js'></script>
<script>
var t=setInterval(function(){
  $.post('/user/return/{$payid}',{no:'{$out_trade_no}',t:Math.random()},function(d){
    if(d.code=='1'){layer.msg('支付成功，正在跳转...');clearInterval(t);setTimeout(function(){location.href='/user';},1000);}
  },'json');
},2000);
</script>
</body></html>";
}
