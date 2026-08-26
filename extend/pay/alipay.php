<?php
namespace pay;

/**
 * 支付宝支付 SDK（RSA2 签名，无需 composer）
 * 支持：电脑网站支付(page) / 手机网站支付(wap) / 当面付扫码(precreate) / APP支付(app)
 * 回调验签、主动查单
 *
 * 配置数组 $config:
 *   appid           支付宝应用 AppID
 *   private_key     应用私钥(PEM 或纯 base64)
 *   alipay_pubkey   支付宝公钥(PEM 或纯 base64)
 *   gateway         网关(默认正式环境)
 */
class alipay
{
    private $appid;
    private $private_key;
    private $alipay_pubkey;
    private $gateway = 'https://openapi.alipay.com/gateway.do';
    private $charset = 'utf-8';

    public function __construct($config)
    {
        $this->appid         = trim($config['appid']);
        $this->private_key   = $this->formatKey($config['private_key'], 'PRIVATE');
        $this->alipay_pubkey = $this->formatKey($config['alipay_pubkey'], 'PUBLIC');
        if (!empty($config['gateway'])) {
            $this->gateway = trim($config['gateway']);
        }
    }

    private function formatKey($key, $type)
    {
        $key = trim($key);
        if (strpos($key, 'BEGIN') !== false) {
            return $key;
        }
        if ($type === 'PRIVATE') {
            return "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
        }
        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }

    /** 电脑网站支付，返回自动提交的 form 表单 HTML */
    public function page($outTradeNo, $amount, $subject, $notifyUrl, $returnUrl)
    {
        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $this->fmtMoney($amount),
            'subject'      => $subject,
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
        ];
        return $this->buildPageForm('alipay.trade.page.pay', $biz, $notifyUrl, $returnUrl);
    }

    /** 手机网站支付(H5)，返回自动提交的 form 表单 HTML */
    public function wap($outTradeNo, $amount, $subject, $notifyUrl, $returnUrl)
    {
        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $this->fmtMoney($amount),
            'subject'      => $subject,
            'product_code' => 'QUICK_WAP_WAY',
        ];
        return $this->buildPageForm('alipay.trade.wap.pay', $biz, $notifyUrl, $returnUrl);
    }

    /** 当面付扫码，返回 qr_code(二维码链接) 或 false */
    public function precreate($outTradeNo, $amount, $subject, $notifyUrl)
    {
        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $this->fmtMoney($amount),
            'subject'      => $subject,
        ];
        $res = $this->execute('alipay.trade.precreate', $biz, $notifyUrl);
        $node = 'alipay_trade_precreate_response';
        if (isset($res[$node]['code']) && $res[$node]['code'] == '10000') {
            return $res[$node]['qr_code'];
        }
        return false;
    }

    /** APP支付，返回下单字符串(orderString，供客户端调起) */
    public function app($outTradeNo, $amount, $subject, $notifyUrl)
    {
        $biz = [
            'out_trade_no' => $outTradeNo,
            'total_amount' => $this->fmtMoney($amount),
            'subject'      => $subject,
            'product_code' => 'QUICK_MSECURITY_PAY',
        ];
        $params = $this->commonParams('alipay.trade.app.pay', $biz, $notifyUrl);
        $params['sign'] = $this->generateSign($params);
        return http_build_query($params);
    }

    /** 主动查单，返回 trade_status(TRADE_SUCCESS等) 或空 */
    public function query($outTradeNo)
    {
        $biz = ['out_trade_no' => $outTradeNo];
        $res = $this->execute('alipay.trade.query', $biz, null);
        $node = 'alipay_trade_query_response';
        if (isset($res[$node]['trade_status'])) {
            return $res[$node]['trade_status'];
        }
        return '';
    }

    /**
     * 验证异步/同步通知签名
     * @param array $params $_POST 或 $_GET
     */
    public function verify($params)
    {
        if (!isset($params['sign']) || !isset($params['sign_type'])) {
            return false;
        }
        $sign     = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign'], $params['sign_type']);
        $content  = $this->getSignContent($params);
        $res      = $this->alipay_pubkey;
        if ($signType == 'RSA2') {
            $ok = openssl_verify($content, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        } else {
            $ok = openssl_verify($content, base64_decode($sign), $res);
        }
        return $ok === 1;
    }

    /* ============ 内部方法 ============ */

    private function fmtMoney($amount)
    {
        return number_format($amount, 2, '.', '');
    }

    private function commonParams($method, $biz, $notifyUrl, $returnUrl = null)
    {
        $params = [
            'app_id'      => $this->appid,
            'method'      => $method,
            'format'      => 'JSON',
            'charset'     => $this->charset,
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'biz_content' => json_encode($biz, JSON_UNESCAPED_UNICODE),
        ];
        if ($notifyUrl) {
            $params['notify_url'] = $notifyUrl;
        }
        if ($returnUrl) {
            $params['return_url'] = $returnUrl;
        }
        return $params;
    }

    /** 生成自动提交表单(页面跳转类接口) */
    private function buildPageForm($method, $biz, $notifyUrl, $returnUrl)
    {
        $params = $this->commonParams($method, $biz, $notifyUrl, $returnUrl);
        $params['sign'] = $this->generateSign($params);

        $html = "<form id='alipaysubmit' name='alipaysubmit' action='" . $this->gateway . "?charset=" . $this->charset . "' method='POST'>";
        foreach ($params as $k => $v) {
            $v = str_replace("'", '&apos;', $v);
            $html .= "<input type='hidden' name='" . $k . "' value='" . $v . "'/>";
        }
        $html .= "<input type='submit' value='正在跳转...' style='display:none'></form>";
        $html .= "<script>document.forms['alipaysubmit'].submit();</script>";
        return $html;
    }

    /** 执行 API 请求(返回类接口，如 precreate/query) */
    private function execute($method, $biz, $notifyUrl)
    {
        $params = $this->commonParams($method, $biz, $notifyUrl);
        $params['sign'] = $this->generateSign($params);
        $result = $this->curlPost($this->gateway . '?charset=' . $this->charset, $params);
        return json_decode($result, true);
    }

    private function generateSign($params)
    {
        $content = $this->getSignContent($params);
        openssl_sign($content, $sign, $this->private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    private function getSignContent($params)
    {
        ksort($params);
        $str = '';
        $i   = 0;
        foreach ($params as $k => $v) {
            if ($this->notEmpty($v) && substr($v, 0, 1) !== '@') {
                $str .= ($i == 0 ? '' : '&') . $k . '=' . $v;
                $i++;
            }
        }
        return $str;
    }

    private function notEmpty($value)
    {
        return isset($value) && trim($value) !== '';
    }

    private function curlPost($url, $postData)
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
