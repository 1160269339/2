<?php
$data = [
    ["name" => "appid", 'title' => 'AppID', 'type' => 'input', "prompt" => "公众号/APP/小程序的AppID", "value" => ""],

    ["name" => "mchid", 'title' => '商户号', 'type' => 'input', "prompt" => "微信支付商户号mchid", "value" => ""],

    ["name" => "apiv3key", 'title' => 'APIv3密钥', 'type' => 'input', "prompt" => "微信支付APIv3密钥(32位)", "value" => ""],

    ["name" => "serial_no", 'title' => '证书序列号', 'type' => 'input', "prompt" => "商户API证书序列号", "value" => ""],

    ["name" => "private_key", 'title' => '商户私钥', 'type' => 'textarea', "prompt" => "商户API私钥apiclient_key.pem内容", "value" => ""],

    ["name" => "appsecret", 'title' => 'AppSecret', 'type' => 'input', "prompt" => "公众号AppSecret(仅JSAPI公众号支付需要,其余留空)", "value" => ""],

    ["name" => "支付方式", 'title' => '支付方式', 'type' => 'select', "prompt" => "选择微信支付方式：自动=根据用户设备自动选Native或H5", "value" => "自动", "option" => ["自动", "Native扫码", "H5", "JSAPI", "APP", "小程序"]],
];
return $data;
