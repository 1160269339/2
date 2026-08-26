<?php
use think\Db;
use think\Request;
use pay\wxpay;

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
    'appid'       => $ppay["0"]["value"],
    'mchid'       => $ppay["1"]["value"],
    'apiv3key'    => $ppay["2"]["value"],
    'serial_no'   => $ppay["3"]["value"],
    'private_key' => $ppay["4"]["value"],
];
$appsecret = $ppay["5"]["value"];
$paytype   = $ppay["6"]["value"];

if ($config['mchid'] == "" || $config['apiv3key'] == "" || $config['serial_no'] == "" || $config['private_key'] == "") {
    exit("<title>出错啦!</title>此站点未配置微信支付接口!");
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
$desc       = "账号ID:" . $user["id"] . ",余额充值";
$clientIp   = Request::instance()->ip();

// 自动模式：手机浏览器用H5，其余用Native扫码；微信内置浏览器用JSAPI
if ($paytype == "自动") {
    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);
    if (strpos($ua, 'micromessenger') !== false) {
        $paytype = "JSAPI";
    } elseif (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
        $paytype = "H5";
    } else {
        $paytype = "Native扫码";
    }
}

$wx = new wxpay($config);

/* ---------------- Native 扫码 ---------------- */
if ($paytype == "Native扫码") {
    $code_url = $wx->native($out_trade_no, $money, $desc, $notify_url);
    $qr = 'https://api.pwmqr.com/qrcode/create/?url=' . urlencode($code_url);
    exit(wxpay_qr_page($out_trade_no, $desc, $money, $qr, $data1["id"]));
}

/* ---------------- H5 ---------------- */
if ($paytype == "H5") {
    $h5_url = $wx->h5($out_trade_no, $money, $desc, $notify_url, $clientIp);
    $return = Request::instance()->domain() . "/user";
    $jump   = $h5_url . '&redirect_url=' . urlencode($return);
    header("Location: " . $jump);
    exit();
}

/* ---------------- JSAPI（公众号内） ---------------- */
if ($paytype == "JSAPI") {
    $openid = session("wx_openid");
    if (!$openid) {
        // 走网页授权拿 openid
        if (input("code")) {
            $openid = $wx->oauthOpenid(input("code"), $appsecret);
            session("wx_openid", $openid);
        } else {
            $redirect = Request::instance()->domain() . Request::instance()->url();
            $sep = strpos($redirect, '?') === false ? '?' : '&';
            $back = urlencode($redirect);
            $oauth = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $config['appid']
                . "&redirect_uri=" . $back . "&response_type=code&scope=snsapi_base&state=1#wechat_redirect";
            header("Location: " . $oauth);
            exit();
        }
    }
    if (!$openid) {
        exit("<title>出错啦!</title>获取用户openid失败!");
    }
    $prepay_id = $wx->jsapi($out_trade_no, $money, $desc, $notify_url, $openid);
    $params    = $wx->jsapiPayParams($prepay_id);
    exit(wxpay_jsapi_page($params, $out_trade_no, $data1["id"]));
}

/* ---------------- 小程序 ---------------- */
if ($paytype == "小程序") {
    $openid = input("openid");
    if (!$openid) {
        exit(json_encode(["code" => -1, "msg" => "缺少openid参数"]));
    }
    $prepay_id = $wx->jsapi($out_trade_no, $money, $desc, $notify_url, $openid, true);
    $params    = $wx->jsapiPayParams($prepay_id);
    $params['out_trade_no'] = $out_trade_no;
    exit(json_encode(["code" => 1, "data" => $params]));
}

/* ---------------- APP ---------------- */
if ($paytype == "APP") {
    $prepay_id = $wx->app($out_trade_no, $money, $desc, $notify_url);
    $params    = $wx->appPayParams($prepay_id);
    $params['out_trade_no'] = $out_trade_no;
    exit(json_encode(["code" => 1, "data" => $params]));
}

exit("<title>出错啦!</title>未知的支付方式!");


/* ================= 页面模板函数 ================= */
function wxpay_qr_page($out_trade_no, $desc, $money, $qr, $payid)
{
    return "
<!DOCTYPE html><html lang='zh'><head><meta charset='utf-8'>
<meta name='viewport' content='width=device-width, initial-scale=1'>
<title>微信支付</title>
<style>
body{background:#f7f7f7;font-family:-apple-system,'Microsoft Yahei';margin:0}
.box{max-width:420px;margin:30px auto;background:#fff;border-radius:12px;padding:28px 20px;text-align:center;box-shadow:0 3px 12px rgba(0,0,0,.06)}
h2{color:#09bb07;margin:0 0 18px}
.od{background:#fbfbfb;border-radius:6px;padding:10px;color:#888;font-size:13px;margin:10px 0;text-align:left}
.price{color:#09bb07;font-size:30px;font-weight:700;margin:12px 0}
.price small{font-size:16px}
img.qr{width:210px;height:210px;border:5px solid #eee;border-radius:6px}
.tip{color:#999;font-size:14px;margin-top:14px}
</style></head><body>
<div class='box'>
<h2>微信扫码支付</h2>
<div class='od'>订单号：{$out_trade_no}<br>商品：{$desc}</div>
<div class='price'>{$money}<small> 元</small></div>
<img class='qr' src='{$qr}'/>
<div class='tip'>请使用微信扫一扫完成支付</div>
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

function wxpay_jsapi_page($params, $out_trade_no, $payid)
{
    $json = json_encode($params);
    return "
<!DOCTYPE html><html><head><meta charset='utf-8'>
<meta name='viewport' content='width=device-width,initial-scale=1'>
<title>微信支付</title></head><body>
<div style='text-align:center;margin-top:40vh;color:#888'>正在调起微信支付...</div>
<script>
var p={$json};
function onBridgeReady(){
  WeixinJSBridge.invoke('getBrandWCPayRequest',{
    appId:p.appId,timeStamp:p.timeStamp,nonceStr:p.nonceStr,
    package:p.package,signType:p.signType,paySign:p.paySign
  },function(res){
    if(res.err_msg=='get_brand_wcpay_request:ok'){
      location.href='/user';
    }else{
      document.body.innerHTML='<div style=\"text-align:center;margin-top:40vh;color:#e33\">支付已取消</div>';
    }
  });
}
if(typeof WeixinJSBridge=='undefined'){
  document.addEventListener('WeixinJSBridgeReady',onBridgeReady,false);
}else{onBridgeReady();}
</script>
</body></html>";
}
