<?php
namespace sms;

/**
 * 短信验证码扩展（阿里云 / 腾讯云 可切换）
 *
 * 配置：
 *   $sms = new \sms\Sms([
 *       'channel'   => 'aliyun',   // 或 tencent
 *       'id'        => 'xxx',      // AccessKeyId / SecretId
 *       'key'       => 'xxx',      // AccessKeySecret / SecretKey
 *       'sign'      => '签名',      // 短信签名
 *       'template'  => 'SMS_xxx',  // 阿里=模板CODE  腾讯=模板ID
 *       'appid'     => '',         // 仅腾讯云需要：短信应用 SdkAppId
 *   ]);
 *   $res = $sms->send('13800138000', '123456');
 *   // $res = ['code'=>1,'msg'=>'发送成功']  或  ['code'=>-1,'msg'=>'原因']
 */
class Sms
{
    private $channel;
    private $id;
    private $key;
    private $sign;
    private $template;
    private $appid;

    public function __construct($config)
    {
        $this->channel  = isset($config['channel']) ? $config['channel'] : 'aliyun';
        $this->id       = isset($config['id']) ? trim($config['id']) : '';
        $this->key      = isset($config['key']) ? trim($config['key']) : '';
        $this->sign     = isset($config['sign']) ? trim($config['sign']) : '';
        $this->template = isset($config['template']) ? trim($config['template']) : '';
        $this->appid    = isset($config['appid']) ? trim($config['appid']) : '';
    }

    /**
     * 发送验证码短信
     * @param string $phone 手机号
     * @param string $code  验证码
     * @return array ['code'=>1|-1, 'msg'=>string]
     */
    public function send($phone, $code)
    {
        $phone = trim($phone);
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return ['code' => -1, 'msg' => '手机号格式不正确'];
        }
        if ($this->id === '' || $this->key === '' || $this->sign === '' || $this->template === '') {
            return ['code' => -1, 'msg' => '短信接口未配置完整，请联系管理员'];
        }
        if ($this->channel === 'tencent') {
            return $this->sendTencent($phone, $code);
        }
        return $this->sendAliyun($phone, $code);
    }

    /* ==================== 阿里云短信 ==================== */
    private function sendAliyun($phone, $code)
    {
        $params = [
            'PhoneNumbers'  => $phone,
            'SignName'      => $this->sign,
            'TemplateCode'  => $this->template,
            'TemplateParam' => json_encode(['code' => $code], JSON_UNESCAPED_UNICODE),
        ];
        $common = [
            'Format'           => 'JSON',
            'Version'          => '2017-05-25',
            'AccessKeyId'      => $this->id,
            'SignatureMethod'  => 'HMAC-SHA1',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce'   => md5(uniqid(mt_rand(), true)),
            'Action'           => 'SendSms',
            'RegionId'         => 'cn-hangzhou',
        ];
        $all = array_merge($common, $params);
        ksort($all);
        $canonical = '';
        foreach ($all as $k => $v) {
            $canonical .= '&' . $this->aliEncode($k) . '=' . $this->aliEncode($v);
        }
        $canonical = substr($canonical, 1);
        $stringToSign = 'GET&' . $this->aliEncode('/') . '&' . $this->aliEncode($canonical);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->key . '&', true));
        $all['Signature'] = $signature;

        $url = 'https://dysmsapi.aliyuncs.com/?' . http_build_query($all);
        $resp = $this->httpGet($url);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '短信接口请求失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (isset($json['Code']) && $json['Code'] === 'OK') {
            return ['code' => 1, 'msg' => '发送成功'];
        }
        $msg = isset($json['Message']) ? $json['Message'] : '短信发送失败';
        return ['code' => -1, 'msg' => $msg];
    }

    private function aliEncode($str)
    {
        $str = rawurlencode($str);
        $str = str_replace(['+', '*'], ['%20', '%2A'], $str);
        $str = str_replace('%7E', '~', $str);
        return $str;
    }

    /* ==================== 腾讯云短信 ==================== */
    private function sendTencent($phone, $code)
    {
        $service   = 'sms';
        $host      = 'sms.tencentcloudapi.com';
        $region    = 'ap-guangzhou';
        $action    = 'SendSms';
        $version   = '2021-01-11';
        $algorithm = 'TC3-HMAC-SHA256';
        $timestamp = time();
        $date      = gmdate('Y-m-d', $timestamp);

        $payloadArr = [
            'PhoneNumberSet'   => ['+86' . $phone],
            'SmsSdkAppId'      => $this->appid,
            'SignName'         => $this->sign,
            'TemplateId'       => $this->template,
            'TemplateParamSet' => [(string)$code],
        ];
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

        $canonicalHeaders = "content-type:application/json; charset=utf-8\n"
            . "host:" . $host . "\n"
            . "x-tc-action:" . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $canonicalRequest = "POST\n/\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . hash('sha256', $payload);

        $credentialScope = $date . '/' . $service . '/tc3_request';
        $stringToSign = $algorithm . "\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $this->key, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = $algorithm
            . ' Credential=' . $this->id . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $headers = [
            'Authorization: ' . $authorization,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $host,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . $timestamp,
            'X-TC-Version: ' . $version,
            'X-TC-Region: ' . $region,
        ];
        $resp = $this->httpPost('https://' . $host, $payload, $headers);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '短信接口请求失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!isset($json['Response'])) {
            return ['code' => -1, 'msg' => '短信接口返回异常'];
        }
        if (isset($json['Response']['Error'])) {
            $err = $json['Response']['Error'];
            return ['code' => -1, 'msg' => isset($err['Message']) ? $err['Message'] : '短信发送失败'];
        }
        $set = isset($json['Response']['SendStatusSet'][0]) ? $json['Response']['SendStatusSet'][0] : [];
        if (isset($set['Code']) && $set['Code'] === 'Ok') {
            return ['code' => 1, 'msg' => '发送成功'];
        }
        $msg = isset($set['Message']) ? $set['Message'] : '短信发送失败';
        return ['code' => -1, 'msg' => $msg];
    }

    /* ==================== 工具方法 ==================== */
    private function httpGet($url)
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $resp;
    }

    private function httpPost($url, $data, $headers)
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $resp = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $resp;
    }
}
