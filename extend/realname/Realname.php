<?php
namespace realname;

/**
 * 实名认证 —— 人脸核身（二要素 + 活体人脸识别）
 *
 * 支持渠道：
 *   - tencent 腾讯云 慧眼 FaceID（DetectAuth / GetDetectInfoEnhanced，H5 人脸核身）
 *   - aliyun  阿里云 实人认证 Cloudauth（InitFaceVerify / DescribeFaceVerify，H5 人脸核身）
 *   - zhima   芝麻信用 实人认证 人脸核身（支付宝开放平台，RSA2 签名）
 *
 * 核身是「跳转 -> 刷脸 -> 回跳查询」两阶段异步流程：
 *   1) init($name,$idcard,$returnUrl)  发起核身，返回 ['code'=>1,'url'=>核身页,'token'=>核身单号]
 *   2) （用户跳转刷脸后回跳）
 *   3) query($token)                   查询核身结果，返回 ['code'=>1|-1,'msg'=>...]
 *
 * 配置：
 *   $rn = new \realname\Realname([
 *       'channel' => 'tencent',   // 或 aliyun / zhima
 *       'id'      => 'xxx',       // 腾讯=SecretId  阿里=AccessKeyId  芝麻=支付宝AppID
 *       'key'     => 'xxx',       // 腾讯=SecretKey  阿里=AccessKeySecret  芝麻=应用私钥(需含BEGIN/END)
 *       'scene'   => 'xxx',       // 腾讯=RuleId  阿里=SceneId  芝麻=商户PID(2088开头)
 *   ]);
 *
 * 芝麻信用渠道额外配置（支付宝开放平台）：
 *   - id      = AppID（应用ID）
 *   - key     = 应用私钥（RSA2，含 -----BEGIN PRIVATE KEY----- ... -----END PRIVATE KEY-----）
 *   - scene   = 商户PID（2088 开头，即 MerchantId）
 *   需要：应用已签约「芝麻信用-实人认证」产品，设置好应用公钥、支付宝公钥、回调域名。
 */
class Realname
{
    private $channel;
    private $id;
    private $key;
    private $scene;

    public function __construct($config)
    {
        $this->channel = isset($config['channel']) ? $config['channel'] : 'tencent';
        $this->id      = isset($config['id']) ? trim($config['id']) : '';
        $this->key     = isset($config['key']) ? trim($config['key']) : '';
        $this->scene   = isset($config['scene']) ? trim($config['scene']) : '';
    }

    /**
     * 发起人脸核身
     * @param string $name      真实姓名
     * @param string $idcard    身份证号
     * @param string $returnUrl 刷脸完成后回跳的地址
     * @return array ['code'=>1,'url'=>核身页URL,'token'=>核身单号]  或  ['code'=>-1,'msg'=>原因]
     */
    public function init($name, $idcard, $returnUrl)
    {
        $name   = trim($name);
        $idcard = strtoupper(trim($idcard));

        if ($name === '' || $idcard === '') {
            return ['code' => -1, 'msg' => '姓名和身份证号不能为空'];
        }
        if (!$this->checkIdCard($idcard)) {
            return ['code' => -1, 'msg' => '身份证号格式不正确'];
        }
        $nameLen = function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : (int)preg_match_all('/./us', $name);
        if ($nameLen < 2) {
            return ['code' => -1, 'msg' => '真实姓名格式不正确'];
        }
        if ($this->id === '' || $this->key === '' || $this->scene === '') {
            return ['code' => -1, 'msg' => '实名接口未配置完整，请联系管理员'];
        }

        if ($this->channel === 'aliyun') {
            return $this->initAliyun($name, $idcard, $returnUrl);
        }
        if ($this->channel === 'zhima') {
            return $this->initZhima($name, $idcard, $returnUrl);
        }
        return $this->initTencent($name, $idcard, $returnUrl);
    }

    /**
     * 查询核身结果
     * @param string $token 核身单号（腾讯=BizToken，阿里=CertifyId，芝麻=BizNo）
     * @return array ['code'=>1,'msg'=>'认证通过']  或  ['code'=>-1,'msg'=>原因]
     */
    public function query($token)
    {
        if ($token === '' || $token === null) {
            return ['code' => -1, 'msg' => '核身单号为空'];
        }
        if ($this->channel === 'aliyun') {
            return $this->queryAliyun($token);
        }
        if ($this->channel === 'zhima') {
            return $this->queryZhima($token);
        }
        return $this->queryTencent($token);
    }

    /* ==================== 腾讯云 FaceID ==================== */

    /** 发起：DetectAuth 拿 BizToken + 核身URL */
    private function initTencent($name, $idcard, $returnUrl)
    {
        $params = [
            'RuleId'     => $this->scene,   // 控制台创建的业务流程 RuleId
            'IdCard'     => $idcard,
            'Name'       => $name,
            'RedirectUrl' => $returnUrl,
        ];
        $resp = $this->tencentRequest('DetectAuth', $params);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '发起核身失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!isset($json['Response'])) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        if (isset($json['Response']['Error'])) {
            $err = $json['Response']['Error'];
            return ['code' => -1, 'msg' => isset($err['Message']) ? $err['Message'] : '发起核身失败'];
        }
        $url   = isset($json['Response']['Url']) ? $json['Response']['Url'] : '';
        $token = isset($json['Response']['BizToken']) ? $json['Response']['BizToken'] : '';
        if ($url === '' || $token === '') {
            return ['code' => -1, 'msg' => '核身接口返回数据不完整'];
        }
        return ['code' => 1, 'url' => $url, 'token' => $token];
    }

    /** 查询：GetDetectInfoEnhanced 查活体人脸结果 */
    private function queryTencent($token)
    {
        $params = [
            'BizToken'    => $token,
            'RuleId'      => $this->scene,
            'InfoType'    => '0', // 只要基础信息即可
        ];
        $resp = $this->tencentRequest('GetDetectInfoEnhanced', $params);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '查询核身结果失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!isset($json['Response'])) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        if (isset($json['Response']['Error'])) {
            $err = $json['Response']['Error'];
            return ['code' => -1, 'msg' => isset($err['Message']) ? $err['Message'] : '查询核身失败'];
        }
        // Text.ErrCode == 0 表示核身通过
        $text = isset($json['Response']['Text']) ? $json['Response']['Text'] : [];
        $errCode = isset($text['ErrCode']) ? (string)$text['ErrCode'] : '';
        if ($errCode === '0') {
            return ['code' => 1, 'msg' => '人脸核身通过'];
        }
        $errMsg = isset($text['ErrMsg']) ? $text['ErrMsg'] : '人脸核身未通过';
        return ['code' => -1, 'msg' => $errMsg];
    }

    /** 腾讯云 TC3-HMAC-SHA256 签名请求 */
    private function tencentRequest($action, $params)
    {
        $service   = 'faceid';
        $host      = 'faceid.tencentcloudapi.com';
        $region    = 'ap-guangzhou';
        $version   = '2018-03-01';
        $algorithm = 'TC3-HMAC-SHA256';
        $timestamp = time();
        $date      = gmdate('Y-m-d', $timestamp);

        $payload = json_encode($params, JSON_UNESCAPED_UNICODE);

        // 1. 规范请求串
        $canonicalHeaders = "content-type:application/json; charset=utf-8\n"
            . "host:" . $host . "\n"
            . "x-tc-action:" . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $hashedPayload = hash('sha256', $payload);
        $canonicalRequest = "POST\n"
            . "/\n"
            . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $hashedPayload;

        // 2. 待签名串
        $credentialScope = $date . '/' . $service . '/tc3_request';
        $stringToSign = $algorithm . "\n"
            . $timestamp . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);

        // 3. 计算签名
        $secretDate    = hash_hmac('sha256', $date, 'TC3' . $this->key, true);
        $secretService = hash_hmac('sha256', $service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature     = hash_hmac('sha256', $stringToSign, $secretSigning);

        // 4. Authorization
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
        return $this->httpPost('https://' . $host, $payload, $headers);
    }

    /* ==================== 阿里云 实人认证 Cloudauth ==================== */

    /** 发起：InitFaceVerify 拿 CertifyId，再拼 H5 核身URL */
    private function initAliyun($name, $idcard, $returnUrl)
    {
        $outerOrderNo = md5(uniqid('rn', true));
        $params = [
            'SceneId'      => $this->scene,      // 控制台创建的场景ID
            'OuterOrderNo' => $outerOrderNo,
            'ProductCode'  => 'ID_PRO',          // H5人脸核身
            'CertName'     => $name,
            'CertNo'       => $idcard,
            'CertType'     => 'IDENTITY_CARD',
            'ReturnUrl'    => $returnUrl,
            'Model'        => 'LIVENESS',        // 活体+人脸比对
            'MetaInfo'     => '{"deviceType":"h5"}',
        ];
        $resp = $this->aliyunRequest('InitFaceVerify', $params);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '发起核身失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        if (!isset($json['Code']) || $json['Code'] != 200 || !isset($json['ResultObject'])) {
            $msg = isset($json['Message']) ? $json['Message'] : '发起核身失败';
            return ['code' => -1, 'msg' => $msg];
        }
        $obj = $json['ResultObject'];
        $url   = isset($obj['CertifyUrl']) ? $obj['CertifyUrl'] : '';
        $token = isset($obj['CertifyId']) ? $obj['CertifyId'] : '';
        if ($url === '' || $token === '') {
            return ['code' => -1, 'msg' => '核身接口返回数据不完整'];
        }
        return ['code' => 1, 'url' => $url, 'token' => $token];
    }

    /** 查询：DescribeFaceVerify 查核身结果 */
    private function queryAliyun($token)
    {
        $params = [
            'SceneId'   => $this->scene,
            'CertifyId' => $token,
        ];
        $resp = $this->aliyunRequest('DescribeFaceVerify', $params);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '查询核身结果失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        if (!isset($json['Code']) || $json['Code'] != 200 || !isset($json['ResultObject'])) {
            $msg = isset($json['Message']) ? $json['Message'] : '查询核身失败';
            return ['code' => -1, 'msg' => $msg];
        }
        // Passed == 'T' 表示核身通过
        $passed = isset($json['ResultObject']['Passed']) ? $json['ResultObject']['Passed'] : '';
        if ($passed === 'T') {
            return ['code' => 1, 'msg' => '人脸核身通过'];
        }
        return ['code' => -1, 'msg' => '人脸核身未通过，请确保为本人操作'];
    }

    /** 阿里云 RPC 风格签名请求（Cloudauth 2019-03-07） */
    private function aliyunRequest($action, $bizParams)
    {
        $common = [
            'Format'           => 'JSON',
            'Version'          => '2019-03-07',
            'AccessKeyId'      => $this->id,
            'SignatureMethod'  => 'HMAC-SHA1',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
            'SignatureVersion' => '1.0',
            'SignatureNonce'   => md5(uniqid(mt_rand(), true)),
            'Action'           => $action,
        ];
        $params = array_merge($common, $bizParams);
        ksort($params);

        // 构造待签名串
        $canonical = '';
        foreach ($params as $k => $v) {
            $canonical .= '&' . $this->aliEncode($k) . '=' . $this->aliEncode($v);
        }
        $canonical = substr($canonical, 1);
        $stringToSign = 'POST&' . $this->aliEncode('/') . '&' . $this->aliEncode($canonical);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->key . '&', true));
        $params['Signature'] = $signature;

        $post = http_build_query($params);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        return $this->httpPost('https://cloudauth.aliyuncs.com/', $post, $headers);
    }

    /** 阿里云 RFC3986 编码 */
    private function aliEncode($str)
    {
        $str = rawurlencode($str);
        $str = str_replace(['+', '*'], ['%20', '%2A'], $str);
        $str = str_replace('%7E', '~', $str);
        return $str;
    }

    /* ==================== 芝麻信用 实人认证（支付宝开放平台）==================== */

    /**
     * 发起：zhima.customer.certification.initialize 拿 BizNo + 核身URL
     */
    private function initZhima($name, $idcard, $returnUrl)
    {
        $bizNo = 'ZHIMA_RN_' . date('YmdHis') . mt_rand(100000, 999999);
        $bizContent = [
            'transaction_id' => $bizNo,          // 商户请求唯一标识
            'biz_code'       => 'FACE',          // 业务场景：人脸识别
            'product_code'   => 'w1010100000000000369', // 实人认证产品码
            'merchant_id'    => $this->scene,    // 商户PID
            'name'           => $name,
            'cert_type'      => 'IDENTITY_CARD',
            'cert_no'        => $idcard,
            'return_url'     => $returnUrl,
            'callback_url'   => $returnUrl,
        ];
        $resp = $this->zhimaRequest('zhima.customer.certification.initialize', $bizContent);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '发起核身失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        // 支付宝 openapi 网关返回 code 10000 表示成功
        if (!isset($json['code']) || $json['code'] != '10000') {
            $msg = isset($json['sub_msg']) ? $json['sub_msg'] : (isset($json['msg']) ? $json['msg'] : '发起核身失败');
            return ['code' => -1, 'msg' => $msg];
        }
        $biz = isset($json['zhima_customer_certification_initialize_response'])
            ? $json['zhima_customer_certification_initialize_response'] : [];
        $bizNo  = isset($biz['biz_no']) ? $biz['biz_no'] : '';
        $token  = isset($biz['token']) ? $biz['token'] : '';
        if ($bizNo === '' || $token === '') {
            return ['code' => -1, 'msg' => '核身接口返回数据不完整'];
        }
        // 拼 H5 核身 URL
        $url = 'https://render.alipay.com/p/f/fd-jhsingersg/index.html'
            . '?token=' . $token;
        return ['code' => 1, 'url' => $url, 'token' => $bizNo];
    }

    /**
     * 查询：zhima.customer.certification.query 查核身结果
     */
    private function queryZhima($token)
    {
        $bizContent = [
            'biz_no'      => $token,
            'merchant_id' => $this->scene,
        ];
        $resp = $this->zhimaRequest('zhima.customer.certification.query', $bizContent);
        if ($resp === false) {
            return ['code' => -1, 'msg' => '查询核身结果失败，请稍后重试'];
        }
        $json = json_decode($resp, true);
        if (!is_array($json)) {
            return ['code' => -1, 'msg' => '核身接口返回异常'];
        }
        if (!isset($json['code']) || $json['code'] != '10000') {
            $msg = isset($json['sub_msg']) ? $json['sub_msg'] : (isset($json['msg']) ? $json['msg'] : '查询核身失败');
            return ['code' => -1, 'msg' => $msg];
        }
        $biz = isset($json['zhima_customer_certification_query_response'])
            ? $json['zhima_customer_certification_query_response'] : [];
        // passed == 'true' 表示核身通过
        $passed = isset($biz['passed']) ? $biz['passed'] : '';
        if ($passed === 'true' || $passed === true) {
            return ['code' => 1, 'msg' => '人脸核身通过'];
        }
        // 未通过返回 passed=false
        return ['code' => -1, 'msg' => '人脸核身未通过，请确保为本人操作'];
    }

    /**
     * 支付宝开放平台 OpenAPI 网关请求（RSA2 签名，form 表单 POST）
     */
    private function zhimaRequest($method, $bizContent)
    {
        $gateway = 'https://openapi.alipay.com/gateway.do';
        $params = [
            'app_id'      => $this->id,          // 支付宝AppID
            'method'      => $method,
            'format'      => 'JSON',
            'charset'     => 'utf-8',
            'sign_type'   => 'RSA2',
            'timestamp'   => date('Y-m-d H:i:s'),
            'version'     => '1.0',
            'biz_content' => json_encode($bizContent, JSON_UNESCAPED_UNICODE),
        ];
        // 排序并拼出待签名串
        ksort($params);
        $stringToSign = '';
        foreach ($params as $k => $v) {
            $stringToSign .= $k . '=' . $v . '&';
        }
        $stringToSign = rtrim($stringToSign, '&');

        // RSA2 签名（SHA256withRSA），使用应用私钥
        $privateKey = $this->key;
        if (stripos($privateKey, 'BEGIN') === false) {
            $privateKey = "-----BEGIN PRIVATE KEY-----\n"
                . wordwrap($privateKey, 64, "\n", true)
                . "\n-----END PRIVATE KEY-----";
        }
        $sign = '';
        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) {
            return false;
        }
        openssl_sign($stringToSign, $sign, $pkey, OPENSSL_ALGO_SHA256);
        $params['sign'] = base64_encode($sign);

        $post = http_build_query($params);
        $headers = ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'];
        return $this->httpPost($gateway, $post, $headers);
    }

    /* ==================== 工具方法 ==================== */

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

    /**
     * 校验身份证号（18位，含校验位）
     */
    public function checkIdCard($id)
    {
        if (!preg_match('/^\d{17}[\dXx]$/', $id)) {
            return false;
        }
        $year  = substr($id, 6, 4);
        $month = substr($id, 10, 2);
        $day   = substr($id, 12, 2);
        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            return false;
        }
        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $codes  = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += intval($id[$i]) * $factor[$i];
        }
        return strtoupper($id[17]) === $codes[$sum % 11];
    }
}