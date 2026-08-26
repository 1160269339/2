<?php
/**
 * 码支付 - 配置页面
 * 用于管理员配置码支付参数
 */

use think\Db;

// 处理配置保存
if (request()->isPost()) {
    $pid = input('pid', '', 'trim');
    $key = input('key', '', 'trim');
    $apiurl = input('apiurl', 'https://api.maizhifu.com/', 'trim');
    
    // 验证配置
    if (empty($pid) || empty($key)) {
        error('请填写完整的配置信息');
    }
    
    // 保存配置
    set_config('maizhi_pid', $pid);
    set_config('maizhi_key', $key);
    set_config('maizhi_apiurl', $apiurl);
    
    success('配置保存成功');
}

// 获取当前配置
$pid = get_config('maizhi_pid', '');
$key = get_config('maizhi_key', '');
$apiurl = get_config('maizhi_apiurl', 'https://api.maizhifu.com/');

assign([
    'pid' => $pid,
    'key' => $key,
    'apiurl' => $apiurl,
]);
