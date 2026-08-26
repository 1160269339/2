<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

/**
 * 支付回调监控控制器
 */
class PayMonitor
{
    /**
     * 监控首页
     */
    public function index()
    {
        $day = input('day', date('Y-m-d'));
        $page = input('page', 1);
        $limit = input('limit', 50);

        // 获取统计信息
        $stats = Db::name('dd_pay_monitor')
            ->where('day', $day)
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

        // 总统计
        $totalStats = Db::name('dd_pay_monitor')
            ->where('day', $day)
            ->field('SUM(total_count) as total_count, SUM(success_count) as success_count, SUM(fail_count) as fail_count, SUM(sign_fail_count) as sign_fail_count')
            ->find();

        // 告警信息
        $highErrorRateAlerts = '';
        $highSignFailRateAlerts = '';

        $highErrorRates = Db::name('dd_pay_monitor')
            ->where('day', $day)
            ->where('fail_count', '>', 0)
            ->field('pay_type, fail_count, total_count')
            ->select()
            ->toArray();

        if (!empty($highErrorRates)) {
            foreach ($highErrorRates as $rate) {
                $failRate = round($rate['fail_count'] * 100.0 / $rate['total_count'], 2);
                if ($failRate > 20) {
                    $highErrorRateAlerts .= "<li>{$rate['pay_type']}: 失败率 {$failRate}%</li>";
                }
            }
        }

        $highSignFailRates = Db::name('dd_pay_monitor')
            ->where('day', $day)
            ->where('sign_fail_count', '>', 0)
            ->field('pay_type, sign_fail_count, total_count')
            ->select()
            ->toArray();

        if (!empty($highSignFailRates)) {
            foreach ($highSignFailRates as $rate) {
                $signFailRate = round($rate['sign_fail_count'] * 100.0 / $rate['total_count'], 2);
                if ($signFailRate > 10) {
                    $highSignFailRateAlerts .= "<li>{$rate['pay_type']}: 签名失败率 {$signFailRate}%</li>";
                }
            }
        }

        // 支付日志
        $logs = Db::name('dd_pay_log')
            ->where('notify_time', '>=', strtotime($day))
            ->where('notify_time', '<=', strtotime($day . ' 23:59:59'))
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $totalLogs = Db::name('dd_pay_log')
            ->where('notify_time', '>=', strtotime($day))
            ->where('notify_time', '<=', strtotime($day . ' 23:59:59'))
            ->count();

        // 错误记录
        $errors = Db::name('dd_pay_error')
            ->where('create_time', '>=', strtotime($day))
            ->where('create_time', '<=', strtotime($day . ' 23:59:59'))
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $totalErrors = Db::name('dd_pay_error')
            ->where('create_time', '>=', strtotime($day))
            ->where('create_time', '<=', strtotime($day . ' 23:59:59'))
            ->count();

        // 生成统计卡片数据
        $total_count = $totalStats['total_count'] ?? 0;
        $success_count = $totalStats['success_count'] ?? 0;
        $fail_count = $totalStats['fail_count'] ?? 0;
        $sign_fail_count = $totalStats['sign_fail_count'] ?? 0;
        $success_rate = $total_count > 0 ? round($success_count * 100.0 / $total_count, 2) : 0;
        $fail_rate = $total_count > 0 ? round($fail_count * 100.0 / $total_count, 2) : 0;
        $sign_fail_rate = $total_count > 0 ? round($sign_fail_count * 100.0 / $total_count, 2) : 0;

        // 生成统计表格行
        $statsRows = '';
        foreach ($stats as $stat) {
            $statsRows .= "
                <tr>
                    <td>{$stat['pay_type']}</td>
                    <td>{$stat['total_count']}</td>
                    <td>{$stat['success_count']}</td>
                    <td>{$stat['fail_count']}</td>
                    <td>{$stat['success_rate']}%</td>
                    <td>{$stat['fail_rate']}%</td>
                </tr>
            ";
        }

        // 生成日志表格行
        $logRows = '';
        foreach ($logs as $log) {
            $statusText = $log['status'] == 1 ? '<span class="badge badge-success">成功</span>' : '<span class="badge badge-danger">失败</span>';
            $signText = $log['sign_verify'] == 1 ? '<span class="badge badge-success">成功</span>' : '<span class="badge badge-danger">失败</span>';
            $logRows .= "
                <tr>
                    <td>{$log['id']}</td>
                    <td>{$log['pay_type']}</td>
                    <td>{$log['order_no']}</td>
                    <td>{$log['amount']}</td>
                    <td>{$statusText}</td>
                    <td>{$signText}</td>
                    <td>" . date('Y-m-d H:i:s', $log['notify_time']) . "</td>
                    <td>{$log['ip']}</td>
                </tr>
            ";
        }

        // 生成分页
        $pagination = $this->pagination($page, $totalLogs, $limit, '/admin/pay_monitor/index?day=' . $day);
        $errorPagination = $this->pagination($page, $totalErrors, $limit, '/admin/pay_monitor/index?day=' . $day);

        // 生成错误表格行
        $errorRows = '';
        foreach ($errors as $error) {
            $errorRows .= "
                <tr>
                    <td>{$error['id']}</td>
                    <td>{$error['pay_type']}</td>
                    <td>{$error['order_no']}</td>
                    <td>{$error['error_type']}</td>
                    <td>{$error['error_msg']}</td>
                    <td>{$error['ip']}</td>
                    <td>" . date('Y-m-d H:i:s', $error['create_time']) . "</td>
                </tr>
            ";
        }

        // 渲染页面
        $this->assign([
            'total_count' => $total_count,
            'success_count' => $success_count,
            'fail_count' => $fail_count,
            'sign_fail_count' => $sign_fail_count,
            'success_rate' => $success_rate,
            'fail_rate' => $fail_rate,
            'sign_fail_rate' => $sign_fail_rate,
            'high_error_rate_alerts' => $highErrorRateAlerts ?: '无',
            'high_sign_fail_rate_alerts' => $highSignFailRateAlerts ?: '无',
            'stats_rows' => $statsRows ?: '<tr><td colspan="6">暂无数据</td></tr>',
            'log_rows' => $logRows ?: '<tr><td colspan="8">暂无数据</td></tr>',
            'error_rows' => $errorRows ?: '<tr><td colspan="7">暂无数据</td></tr>',
            'pagination' => $pagination,
            'error_pagination' => $errorPagination,
            'day' => $day,
        ]);

        return $this->fetch();
    }

    /**
     * 获取支付日志
     */
    public function getLogs()
    {
        $page = input('page', 1);
        $limit = input('limit', 50);
        $payType = input('pay_type', '');

        $query = Db::name('dd_pay_log');

        if ($payType) {
            $query->where('pay_type', $payType);
        }

        $list = $query
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total = $query->count();

        return json([
            'code' => 1,
            'msg' => '成功',
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 获取支付统计
     */
    public function getStats()
    {
        $days = input('days', 7);
        $startTime = time() - ($days * 24 * 60 * 60);

        $stats = Db::name('dd_pay_monitor')
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

        return json([
            'code' => 1,
            'msg' => '成功',
            'data' => $stats,
        ]);
    }

    /**
     * 获取错误列表
     */
    public function getErrors()
    {
        $page = input('page', 1);
        $limit = input('limit', 50);
        $payType = input('pay_type', '');

        $query = Db::name('dd_pay_error');

        if ($payType) {
            $query->where('pay_type', $payType);
        }

        $list = $query
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $total = $query->count();

        return json([
            'code' => 1,
            'msg' => '成功',
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * 检查异常并生成告警
     */
    public function checkAlerts()
    {
        $alerts = [];

        // 检查高错误率
        $highErrorRates = Db::name('dd_pay_monitor')
            ->where('day', date('Y-m-d'))
            ->where('fail_count', '>', 0)
            ->field('pay_type, fail_count, total_count')
            ->select()
            ->toArray();

        foreach ($highErrorRates as $rate) {
            $failRate = round($rate['fail_count'] * 100.0 / $rate['total_count'], 2);
            if ($failRate > 20) {
                Db::name('pay_alert')->insert([
                    'pay_type' => $rate['pay_type'],
                    'alert_type' => 'high_error_rate',
                    'alert_level' => 'error',
                    'alert_content' => "支付失败率过高: {$rate['pay_type']} 失败率 {$failRate}%",
                    'alert_data' => json_encode($rate),
                    'is_read' => 0,
                    'create_time' => time(),
                ]);
            }
        }

        // 检查高签名失败率
        $highSignFailRates = Db::name('dd_pay_monitor')
            ->where('day', date('Y-m-d'))
            ->where('sign_fail_count', '>', 0)
            ->field('pay_type, sign_fail_count, total_count')
            ->select()
            ->toArray();

        foreach ($highSignFailRates as $rate) {
            $signFailRate = round($rate['sign_fail_count'] * 100.0 / $rate['total_count'], 2);
            if ($signFailRate > 10) {
                Db::name('pay_alert')->insert([
                    'pay_type' => $rate['pay_type'],
                    'alert_type' => 'high_sign_fail',
                    'alert_level' => 'warning',
                    'alert_content' => "签名验证失败率过高: {$rate['pay_type']} 失败率 {$signFailRate}%",
                    'alert_data' => json_encode($rate),
                    'is_read' => 0,
                    'create_time' => time(),
                ]);
            }
        }

        return json([
            'code' => 1,
            'msg' => '检查完成',
            'data' => $alerts,
        ]);
    }

    /**
     * 获取告警列表
     */
    public function getAlerts()
    {
        $isRead = input('is_read', null);

        $query = Db::name('dd_pay_alert');

        if ($isRead !== null) {
            $query->where('is_read', $isRead);
        }

        $list = $query
            ->order('id desc')
            ->select()
            ->toArray();

        return json([
            'code' => 1,
            'msg' => '成功',
            'data' => $list,
        ]);
    }

    /**
     * 标记告警已读
     */
    public function markAlertRead($id)
    {
        $result = Db::name('dd_pay_alert')->where('id', $id)->update([
            'is_read' => 1,
        ]);

        if ($result) {
            return json([
                'code' => 1,
                'msg' => '标记成功',
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '标记失败',
            ]);
        }
    }

    /**
     * 标记所有告警已读
     */
    public function markAllAlertsRead()
    {
        $result = Db::name('dd_pay_alert')->where('is_read', 0)->update([
            'is_read' => 1,
        ]);

        if ($result !== false) {
            return json([
                'code' => 1,
                'msg' => '标记成功',
            ]);
        } else {
            return json([
                'code' => 0,
                'msg' => '标记失败',
            ]);
        }
    }

    /**
     * 生成分页HTML
     */
    private function pagination($page, $total, $limit, $url)
    {
        $totalPages = ceil($total / $limit);
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<ul class="pagination">';
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=1">首页</a></li>';

        if ($page > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . ($page - 1) . '">上一页</a></li>';
        }

        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $page) {
                $html .= '<li class="page-item active"><a class="page-link">' . $i . '</a></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
            }
        }

        if ($page < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . ($page + 1) . '">下一页</a></li>';
        }

        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '&page=' . $totalPages . '">末页</a></li>';
        $html .= '</ul>';

        return $html;
    }
}
