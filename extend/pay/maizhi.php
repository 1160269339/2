<?php
namespace pay;

/**
 * 码支付 (MAIZHI) SDK
 * 支持：Native扫码 / H5 / JSAPI / 小程序
 * 回调验签、主动查单
 *
 * 配置数组 $config:
 *   pid     商户ID
 *   key     通信密钥
 *   apiurl  API地址 (默认: https://api.maizhifu.com/)
 */
class maizhi
{
    private $pid;
    private $key;
    private $apiurl;
    private $sign_type = 'MD5';
    
    public function __construct($config)
    {
        $this->pid    = trim($config['pid']);
        $this->key    = trim($config['key']);
        $this->apiurl = rtrim(trim($config['apiurl'] ?? 'https://api.maizhifu.com/'), '/');
    }
    
    /**
     * 发起支付（页面跳转）
     * @param array $param 支付参数 [money, order, title, callback, return]
     * @param string $button 按钮文本
     * @return string HTML表单
     */
    public function pagePay($param, $button = '正在跳转')
    {
        $param = $this->buildRequestParam($param);
        
        $html = '<form id="maizhiPay" action="' . $this->apiurl . '/submit.php" method="post">';
        foreach ($param as $k => $v) {
            $html .= '<input type="hidden" name="' . $k . '" value="' . htmlspecialchars($v) . '"/>
';
        }
        $html .= '<input type="submit" value="' . $button . '"></form>';
        $html .= '<script>document.getElementById("maizhiPay").submit();</script>';
        
        return $html;
    }
    
    /**
     * 发起支付（获取链接）
     * @param array $param 支付参数
     * @return string 支付链接
     */
    public function getPayLink($param)
    {
        $param = $this->buildRequestParam($param);
        return $this->apiurl . '/submit.php?' . http_build_query($param);
    }
    
    /**
     * 发起支付（API接口）
     * @param array $param 支付参数
     * @return array 响应数据
     */
    public function apiPay($param)
    {
        $param = $this->buildRequestParam($param);
        $response = $this->getHttpResponse($this->apiurl . '/mapi.php', http_build_query($param));
        return json_decode($response, true) ?? ['code' => 0, 'msg' => '解析失败'];
    }
    
    /**
     * 异步回调验证
     * @return bool
     */
    public function verifyNotify()
    {
        if (empty($_POST)) {
            return false;
        }
        
        $sign = $this->getSign($_POST);
        return $sign === ($_POST['sign'] ?? '');
    }
    
    /**
     * 同步回调验证
     * @return bool
     */
    public function verifyReturn()
    {
        if (empty($_GET)) {
            return false;
        }
        
        $sign = $this->getSign($_GET);
        return $sign === ($_GET['sign'] ?? '');
    }
    
    /**
     * 查询订单支付状态
     * @param string $trade_no 商户订单号
     * @return bool
     */
    public function orderStatus($trade_no)
    {
        $result = $this->queryOrder($trade_no);
        return ($result['code'] ?? 0) == 1;
    }
    
    /**
     * 查询订单
     * @param string $trade_no 商户订单号
     * @return array 订单信息
     */
    public function queryOrder($trade_no)
    {
        $url = $this->apiurl . '/api.php?act=query&pid=' . $this->pid . '&key=' . $this->key . '&trade_no=' . $trade_no;
        $response = $this->getHttpResponse($url);
        return json_decode($response, true) ?? ['code' => 0, 'msg' => '查询失败'];
    }
    
    /**
     * 关闭订单
     * @param string $trade_no 商户订单号
     * @return bool
     */
    public function closeOrder($trade_no)
    {
        $param = [
            'pid' => $this->pid,
            'key' => $this->key,
            'trade_no' => $trade_no,
        ];
        $sign = $this->getSign($param);
        $param['sign'] = $sign;
        $param['sign_type'] = $this->sign_type;
        
        $response = $this->getHttpResponse(
            $this->apiurl . '/api.php?act=close',
            http_build_query($param)
        );
        $result = json_decode($response, true);
        return ($result['code'] ?? 0) == 1;
    }
    
    // ========== 内部方法 ==========
    
    /**
     * 构建请求参数
     */
    private function buildRequestParam($param)
    {
        $param['pid'] = $this->pid;
        $param['key'] = $this->key;
        $sign = $this->getSign($param);
        $param['sign'] = $sign;
        $param['sign_type'] = $this->sign_type;
        return $param;
    }
    
    /**
     * 计算签名
     */
    private function getSign($param)
    {
        ksort($param);
        reset($param);
        
        $signstr = '';
        foreach ($param as $k => $v) {
            if ($k !== 'sign' && $k !== 'sign_type' && $v !== '') {
                $signstr .= $k . '=' . $v . '&';
            }
        }
        $signstr = substr($signstr, 0, -1);
        $signstr .= $this->key;
        
        return strtoupper(md5($signstr));
    }
    
    /**
     * 发送HTTP请求
     */
    private function getHttpResponse($url, $post = false, $timeout = 15)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
