<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 支付回调监控系统
 */
class PayMonitor
{
    /**
     * 记录支付回调
     * @param string $payType 支付类型
     * @param string $orderNo 订单号
     * @param float $amount 金额
     * @param string $tradeNo 第三方交易号
     * @param int $status 支付状态: 0-待支付 1-成功 2-失败
     * @param bool $signVerify 签名验证结果
     * @param string $errorMsg 错误信息
     */
    public function log($payType, $orderNo, $amount, $tradeNo = '', $status = 0, $signVerify = true, $errorMsg = '')
    {
        $data = [
            'pay_type'   => $payType,
            'order_no'   => $orderNo,
            'amount'     => $amount,
            'trade_no'   => $tradeNo,
            'status'     => $status,
            'notify_time'=> time(),
            'ip'         => Request::instance()->ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_data'=> json_encode($_POST),
            'response_data'=> '',
            'sign_verify'=> $signVerify ? 1 : 0,
            'error_msg'  => $errorMsg,
            'create_time'=> time(),
        ];
        
        $id = Db::name('pay_log')->insertGetId($data);
        
        // 如果签名验证失败，记录错误
        if (!$signVerify) {
            $this->recordError($payType, $orderNo, 'sign_verify_error', '签名验证失败');
        }
        
        // 如果支付失败，记录错误
        if ($status == 2) {
            $this->recordError($payType, $orderNo, 'pay_error', $errorMsg);
        }
        
        // 更新监控统计
        $this->updateMonitorStats($payType, $status);
        
        return $id;
    }
    
    /**
     * 记录支付错误
     */
    private function recordError($payType, $orderNo, $errorType, $errorMsg)
    {
        Db::name('pay_error')->insert([
            'pay_type'   => $payType,
            'order_no'   => $orderNo,
            'error_type' => $errorType,
            'error_msg'  => $errorMsg,
            'ip'         => Request::instance()->ip(),
            'create_time'=> time(),
        ]);
    }
    
    /**
     * 更新监控统计
     */
    private function updateMonitorStats($payType, $status)
    {
        $today = date('Y-m-d');
        
        // 检查统计记录是否存在
        $exists = Db::name('pay_monitor')
            ->where('pay_type', $payType)
            ->where('day', $today)
            ->find();
        
        if ($exists) {
            // 更新统计
            Db::name('pay_monitor')->where('id', $exists['id'])->update([
                'total_count'  => $exists['total_count'] + 1,
                'success_count'=> $status == 1 ? $exists['success_count'] + 1 : $exists['success_count'],
                'fail_count'   => $status == 2 ? $exists['fail_count'] + 1 : $exists['fail_count'],
                'error_count'  => $status == 2 ? $exists['error_count'] + 1 : $exists['error_count'],
            ]);
        } else {
            // 创建新统计
            Db::name('pay_monitor')->insert([
                'pay_type'       => $payType,
                'day'            => $today,
                'total_count'    => 1,
                'success_count'  => $status == 1 ? 1 : 0,
                'fail_count'     => $status == 2 ? 1 : 0,
                'error_count'    => $status == 2 ? 1 : 0,
                'avg_time'       => 0,
                'max_time'       => 0,
                'min_time'       => 0,
                'create_time'    => time(),
            ]);
        }
    }
    
    /**
     * 获取支付日志
     */
    public function getPayLogs($page = 1, $limit = 50)
    {
        $list = Db::name('pay_log')
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        
        $total = Db::name('pay_log')->count();
        
        return [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }
    
    /**
     * 获取支付统计
     */
    public function getPayStats($days = 7)
    {
        $startTime = time() - ($days * 24 * 60 * 60);
        
        $stats = Db::name('pay_monitor')
            ->where('day', '>=', date('Y-m-d', $startTime))
            ->group('pay_type')
            ->field('pay_type, SUM(total_count) as total_count, SUM(success_count) as success_count, SUM(fail_count) as fail_count')
            ->select()
            ->toArray();
        
        foreach ($stats as &$stat) {
            $stat['success_rate'] = $stat['total_count'] > 0 
                ? round($stat['success_count'] * 100.0 / $stat['total_count'], 2) 
                : 0;
            $stat['fail_rate'] = $stat['total_count'] > 0 
                ? round($stat['fail_count'] * 100.0 / $stat['total_count'], 2) 
                : 0;
        }
        
        return $stats;
    }
    
    /**
     * 获取错误列表
     */
    public function getErrors($page = 1, $limit = 50)
    {
        $list = Db::name('pay_error')
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        
        $total = Db::name('pay_error')->count();
        
        return [
            'list'  => $list,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }
    
    /**
     * 检查异常并生成告警
     */
    public function checkAlerts()
    {
        $alerts = [];
        
        // 检查高错误率
        $highErrorRates = Db::name('pay_monitor')
            ->where('day', date('Y-m-d'))
            ->where('fail_count', '>', 0)
            ->field('pay_type, fail_count, total_count')
            ->select()
            ->toArray();
        
        foreach ($highErrorRates as $rate) {
            $failRate = round($rate['fail_count'] * 100.0 / $rate['total_count'], 2);
            if ($failRate > 20) {
                $alerts[] = [
                    'type'     => 'high_error_rate',
                    'level'    => 'error',
                    'content'  => "支付失败率过高: {$rate['pay_type']} 失败率 {$failRate}%",
                    'data'     => $rate,
                ];
            }
        }
        
        // 检查高签名失败率
        $highSignFailRates = Db::name('pay_monitor')
            ->where('day', date('Y-m-d'))
            ->where('sign_fail_count', '>', 0)
            ->field('pay_type, sign_fail_count, total_count')
            ->select()
            ->toArray();
        
        foreach ($highSignFailRates as $rate) {
            $signFailRate = round($rate['sign_fail_count'] * 100.0 / $rate['total_count'], 2);
            if ($signFailRate > 10) {
                $alerts[] = [
                    'type'     => 'high_sign_fail',
                    'level'    => 'warning',
                    'content'  => "签名验证失败率过高: {$rate['pay_type']} 失败率 {$signFailRate}%",
                    'data'     => $rate,
                ];
            }
        }
        
        // 保存告警
        foreach ($alerts as $alert) {
            Db::name('pay_alert')->insert([
                'pay_type'   => $alert['data']['pay_type'],
                'alert_type' => $alert['type'],
                'alert_level'=> $alert['level'],
                'alert_content'=> $alert['content'],
                'alert_data' => json_encode($alert['data']),
                'is_read'    => 0,
                'create_time'=> time(),
            ]);
        }
        
        return $alerts;
    }
    
    /**
     * 获取告警列表
     */
    public function getAlerts($isRead = null)
    {
        $query = Db::name('pay_alert');
        
        if ($isRead !== null) {
            $query->where('is_read', $isRead);
        }
        
        $list = $query
            ->order('id desc')
            ->select()
            ->toArray();
        
        return $list;
    }
    
    /**
     * 标记告警已读
     */
    public function markAlertRead($id)
    {
        return Db::name('pay_alert')->where('id', $id)->update([
            'is_read' => 1,
        ]);
    }
    
    /**
     * 标记所有告警已读
     */
    public function markAllAlertsRead()
    {
        return Db::name('pay_alert')->where('is_read', 0)->update([
            'is_read' => 1,
        ]);
    }
}
