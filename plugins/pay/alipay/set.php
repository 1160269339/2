<?php
$data = [
    ["name" => "appid", 'title' => 'AppID', 'type' => 'input', "prompt" => "支付宝应用AppID", "value" => ""],

    ["name" => "private_key", 'title' => '应用私钥', 'type' => 'textarea', "prompt" => "应用私钥(RSA2)", "value" => ""],

    ["name" => "alipay_pubkey", 'title' => '支付宝公钥', 'type' => 'textarea', "prompt" => "支付宝公钥(RSA2)", "value" => ""],

    ["name" => "支付方式", 'title' => '支付方式', 'type' => 'select', "prompt" => "选择支付宝支付方式：自动=电脑出扫码/手机跳支付宝", "value" => "自动", "option" => ["自动", "电脑网站", "手机网站", "当面付扫码", "APP"]],
];
return $data;
