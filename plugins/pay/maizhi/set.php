<?php
/**
 * 码支付 - 配置页面
 */

$data = [
    ["name" => "pid", 'title' => '商户ID (PID)', 'type' => 'input', "prompt" => "在码支付后台获取", "value" => ""],
    ["name" => "key", 'title' => '通信密钥 (Key)', 'type' => 'input', "prompt" => "在码支付后台获取，用于签名验证", "value" => ""],
    ["name" => "apiurl", 'title' => 'API地址', 'type' => 'input', "prompt" => "码支付API地址", "value" => "https://api.maizhifu.com/"],
];
return $data;
