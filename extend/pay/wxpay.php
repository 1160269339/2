<?php
namespace pay;

/**
 * 微信支付 V3 SDK（轻量自实现，无需 composer）
 * 支持：Native / H5 / JSAPI / APP / 小程序 下单，回调验签解密，JSAPI 支付签名
 *
 * 配置数组 $config:
 *   appid       公众号/APP/小程序 的 appid
 *   mchid       商户号
 *   apiv3key    APIv3 密钥
 *   serial_no   商户 API 证书序列号
 *   private_key 商户 API 私钥内容(PEM，含 BEGIN/END 或纯 base64 均可)
 */
class wxpay
{
    private $appid;
    private $mchid;
    private $apiv3key;
    private $serial_no;
    private $private_key;
    private $gateway = 'https://api.mch.weixin.qq.com';

    public function __construct($config)
    {
        $this->appid       = trim($config['appid']);
        $this->mchid       = trim($config['mchid']);
        $this->apiv3key    = trim($config['apiv3key']);
        $this->serial_no   = trim($config['serial_no']);
        $this->private_key = $this->formatKey($config['private_key']);
    }

    /** 把私钥整理成标准 PEM 格式 */
    private function formatKey($key)
    {
        $key = trim($key);
        if (strpos($key, 'BEGIN') !== false) {
            return $key;
        }
        return "-----BEGIN PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    }

    /** Native 扫码下单，返回 code_url */
    public function native($outTradeNo, $amount, $desc, $notifyUrl)
    {
        $body = [
            'appid'        => $this->appid,
            'mchid'        => $this->mchid,
            'description'  => $desc,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => ['total' => $this->yuanToFen($amount), 'currency' => 'CNY'],
        ];
        $res = $this->request('POST', '/v3/pay/transactions/native', $body);
        return isset($res['data']['code_url']) ? $res['data']['code_url'] : $this->err($res);
    }

    /** H5 下单，返回 h5_url */
    public function h5($outTradeNo, $amount, $desc, $notifyUrl, $clientIp)
    {
        $body = [
            'appid'        => $this->appid,
            'mchid'        => $this->mchid,
            'description'  => $desc,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => ['total' => $this->yuanToFen($amount), 'currency' => 'CNY'],
            'scene_info'   => ['payer_client_ip' => $clientIp, 'h5_info' => ['type' => 'Wap']],
        ];
        $res = $this->request('POST', '/v3/pay/transactions/h5', $body);
        return isset($res['data']['h5_url']) ? $res['data']['h5_url'] : $this->err($res);
    }

    /** JSAPI/小程序 下单，返回 prepay_id */
    public function jsapi($outTradeNo, $amount, $desc, $notifyUrl, $openid, $miniapp = false)
    {
        $path = $miniapp ? '/v3/pay/transactions/jsapi' : '/v3/pay/transactions/jsapi';
        $body = [
            'appid'        => $this->appid,
            'mchid'        => $this->mchid,
            'description'  => $desc,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => ['total' => $this->yuanToFen($amount), 'currency' => 'CNY'],
            'payer'        => ['openid' => $openid],
        ];
        $res = $this->request('POST', $path, $body);
        return isset($res['data']['prepay_id']) ? $res['data']['prepay_id'] : $this->err($res);
    }

    /** APP 下单，返回 prepay_id */
    public function app($outTradeNo, $amount, $desc, $notifyUrl)
    {
        $body = [
            'appid'        => $this->appid,
            'mchid'        => $this->mchid,
            'description'  => $desc,
            'out_trade_no' => $outTradeNo,
            'notify_url'   => $notifyUrl,
            'amount'       => ['total' => $this->yuanToFen($amount), 'currency' => 'CNY'],
        ];
        $res = $this->request('POST', '/v3/pay/transactions/app', $body);
        return isset($res['data']['prepay_id']) ? $res['data']['prepay_id'] : $this->err($res);
    }

    /** 主动查询订单状态，返回 trade_state（SUCCESS/NOTPAY等） */
    public function query($outTradeNo)
    {
        $path = '/v3/pay/transactions/out-trade-no/' . $outTradeNo . '?mchid=' . $this->mchid;
        $res  = $this->request('GET', $path, null);
        return isset($res['data']['trade_state']) ? $res['data']['trade_state'] : '';
    }

    /** 生成 JSAPI 前端调起支付所需参数（含 paySign） */
    public function jsapiPayParams($prepayId)
    {
        $timestamp = (string)time();
        $nonceStr  = $this->nonce();
        $package   = 'prepay_id=' . $prepayId;
        $message   = $this->appid . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $package . "\n";
        openssl_sign($message, $sign, $this->private_key, OPENSSL_ALGO_SHA256);
        return [
            'appId'     => $this->appid,
            'timeStamp' => $timestamp,
            'nonceStr'  => $nonceStr,
            'package'   => $package,
            'signType'  => 'RSA',
            'paySign'   => base64_encode($sign),
        ];
    }

    /** 生成 APP 调起支付参数 */
    public function appPayParams($prepayId)
    {
        $timestamp = (string)time();
        $nonceStr  = $this->nonce();
        $message   = $this->appid . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $prepayId . "\n";
        openssl_sign($message, $sign, $this->private_key, OPENSSL_ALGO_SHA256);
        return [
            'appid'     => $this->appid,
            'partnerid' => $this->mchid,
            'prepayid'  => $prepayId,
            'package'   => 'Sign=WXPay',
            'noncestr'  => $nonceStr,
            'timestamp' => $timestamp,
            'sign'      => base64_encode($sign),
        ];
    }

    /**
     * 解密并解析回调通知
     * @return array|false 明文订单数据
     */
    public function decryptNotify($rawBody)
    {
        $data = json_decode($rawBody, true);
        if (!isset($data['resource'])) {
            return false;
        }
        $resource   = $data['resource'];
        $ciphertext = base64_decode($resource['ciphertext']);
        $nonce      = $resource['nonce'];
        $aad        = isset($resource['associated_data']) ? $resource['associated_data'] : '';
        // 密文尾部 16 字节为 authTag
        $ctext = substr($ciphertext, 0, -16);
        $tag   = substr($ciphertext, -16);
        $plain = openssl_decrypt($ctext, 'aes-256-gcm', $this->apiv3key, OPENSSL_RAW_DATA, $nonce, $tag, $aad);
        if ($plain === false) {
            return false;
        }
        return json_decode($plain, true);
    }

    /** 通过 code 换取网页授权 openid（JSAPI 用），需 appsecret */
    public function oauthOpenid($code, $appsecret)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $this->appid
            . '&secret=' . $appsecret . '&code=' . $code . '&grant_type=authorization_code';
        $res = json_decode($this->curlGet($url), true);
        return isset($res['openid']) ? $res['openid'] : '';
    }

    /** 元转分 */
    private function yuanToFen($yuan)
    {
        return (int)round($yuan * 100);
    }

    private function nonce()
    {
        return strtoupper(md5(uniqid(mt_rand(), true)));
    }

    private function err($res)
    {
        $msg = isset($res['data']['message']) ? $res['data']['message'] : '未知错误';
        exit("<title>微信支付下单失败</title>下单失败：" . htmlspecialchars($msg) . " (HTTP " . $res['status'] . ")");
    }

    /** 发起带 V3 签名的请求 */
    private function request($method, $path, $body)
    {
        $timestamp = time();
        $nonce     = $this->nonce();
        $bodyStr   = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE);
        $message   = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyStr . "\n";
        openssl_sign($message, $sign, $this->private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($sign);
        $auth = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->mchid, $nonce, $timestamp, $this->serial_no, $signature
        );

        $url = $this->gateway . $path;
        $headers = [
            'Authorization: ' . $auth,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: wxpay-v3-lite/1.0',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        }
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'data' => json_decode($resp, true)];
    }

    private function curlGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
