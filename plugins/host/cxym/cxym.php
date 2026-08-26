<?php
/**
 * cxym网盘插件配置
 * 网盘类型: 外链网盘/文件存储
 */
use think\Db;

function cxym_ConfigOptions()
{
    $data = [
        ["name" => "title", "title" => "网盘标题", "type" => "input", "prompt" => "网盘首页显示的标题", "value" => "文件外链"],
        ["name" => "description", "title" => "网盘描述", "type" => "textarea", "prompt" => "网盘首页显示的描述信息", "value" => "免费外链网盘，支持大文件上传"],
        ["name" => "max_size", "title" => "最大上传大小(MB)", "type" => "input", "prompt" => "单个文件最大上传大小", "value" => "100"],
        ["name" => "storage_type", "title" => "存储类型", "type" => "select", "prompt" => "选择存储方式", "value" => "local", "options" => "local=本地存储,oss=阿里云OSS,qcloud=腾讯云COS,sae=新浪SAE"],
        ["name" => "oss_bucket", "title" => "OSS存储桶", "type" => "input", "prompt" => "阿里云OSS存储桶名称", "value" => ""],
        ["name" => "oss_endpoint", "title" => "OSSEndpoint", "type" => "input", "prompt" => "阿里云OSSEndpoint，如：oss-cn-hangzhou.aliyuncs.com", "value" => ""],
        ["name" => "oss_ak", "title" => "OSSAccessKey", "type" => "input", "prompt" => "阿里云OSSAccessKey", "value" => ""],
        ["name" => "oss_sk", "title" => "OSSSecretKey", "type" => "password", "prompt" => "阿里云OSSSecretKey", "value" => ""],
        ["name" => "qcloud_bucket", "title" => "COS存储桶", "type" => "input", "prompt" => "腾讯云COS存储桶名称", "value" => ""],
        ["name" => "qcloud_region", "title" => "COSRegion", "type" => "input", "prompt" => "腾讯云COSRegion，如：ap-guangzhou", "value" => ""],
        ["name" => "qcloud_id", "title" => "SecretId", "type" => "input", "prompt" => "腾讯云SecretId", "value" => ""],
        ["name" => "qcloud_key", "title" => "SecretKey", "type" => "password", "prompt" => "腾讯云SecretKey", "value" => ""],
        ["name" => "filepath", "title" => "本地存储路径", "type" => "input", "prompt" => "本地存储文件路径，如：/data/files", "value" => "/data/files"],
        ["name" => "allow_guest", "title" => "允许游客上传", "type" => "select", "prompt" => "是否允许未登录用户上传文件", "value" => "1", "options" => "1=是,0=否"],
        ["name" => "require_pwd", "title" => "上传后设置密码", "type" => "select", "prompt" => "是否要求上传后设置密码", "value" => "0", "options" => "1=是,0=否"],
    ];
    return $data;
}

// 获取网盘配置
function cxym_GetConfig($server)
{
    $config = [
        "title" => $server["title"] ?? "文件外链",
        "description" => $server["description"] ?? "",
        "max_size" => $server["max_size"] ?? 100,
        "storage_type" => $server["storage_type"] ?? "local",
        "filepath" => $server["filepath"] ?? "/data/files",
        "allow_guest" => $server["allow_guest"] ?? 1,
        "require_pwd" => $server["require_pwd"] ?? 0,
    ];
    
    // 云存储配置
    if ($server["storage_type"] == "oss") {
        $config["oss_bucket"] = $server["oss_bucket"];
        $config["oss_endpoint"] = $server["oss_endpoint"];
        $config["oss_ak"] = $server["oss_ak"];
        $config["oss_sk"] = $server["oss_sk"];
    } elseif ($server["storage_type"] == "qcloud") {
        $config["qcloud_bucket"] = $server["qcloud_bucket"];
        $config["qcloud_region"] = $server["qcloud_region"];
        $config["qcloud_id"] = $server["qcloud_id"];
        $config["qcloud_key"] = $server["qcloud_key"];
    }
    
    return $config;
}

// 控制面板
function cxym_ClientArea($b, $a, $data)
{
    $config = cxym_GetConfig($b);
    
    $text = "
    <div style='padding:20px; background:#f8f9fa; border-radius:8px;'>
        <h3 style='color:#333;'>{$config['title']}</h3>
        <p style='color:#666;'>{$config['description']}</p>
        <hr>
        <h4>网盘地址</h4>
        <p><a href='{$b['host']}' target='_blank' style='color:#007bff;'>{$b['host']}</a></p>
        <hr>
        <h4>使用说明</h4>
        <ul>
            <li>最大上传大小: {$config['max_size']}MB</li>
            <li>存储类型: " . ($config['storage_type'] == 'local' ? '本地存储' : ($config['storage_type'] == 'oss' ? '阿里云OSS' : '腾讯云COS')) . "</li>
            <li>游客上传: " . ($config['allow_guest'] ? '允许' : '不允许') . "</li>
        </ul>
        <a href='{$b['host']}' target='_blank' class='btn btn-primary'>访问网盘</a>
    </div>";
    
    return $text;
}

// 开通网盘
function cxym_CreateAccount($data3, $data2, $data, $times)
{
    // 在网盘数据库中创建用户
    try {
        // 连接网盘数据库
        $dbConfig = [
            'hostname' => $data3['db_host'] ?? 'localhost',
            'database' => $data3['db_name'] ?? 'cxym',
            'username' => $data3['db_user'] ?? 'root',
            'password' => $data3['db_pwd'] ?? '',
            'charset' => 'utf8',
        ];
        
        $db = Db::connect($dbConfig);
        
        // 检查用户是否已存在
        $existing = $db->name('user')->where('username', $data2['user'])->find();
        if ($existing) {
            return [
                'code' => '-1',
                'msg' => '用户名已存在',
            ];
        }
        
        // 创建用户
        $insertData = [
            'username' => $data2['user'],
            'password' => md5($data2['password']),
            'email' => $data2['email'] ?? '',
            'storage' => $data3['storage_limit'] ?? '1024', // 默认1GB
            'used' => 0,
            'status' => 1,
            'reg_time' => time(),
        ];
        
        $userId = $db->name('user')->insertGetId($insertData);
        
        // 创建用户目录
        $config = cxym_GetConfig($data3);
        if ($config['storage_type'] == 'local') {
            $dir = $config['filepath'] . '/' . $data2['user'];
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // 记录订单
        $orderData = [
            'user' => $data2['user'],
            'password' => $data2['password'],
            'userid' => session('userid'),
            'cartid' => $data['id'],
            'atime' => time(),
            'ztime' => time() + $times,
            'state' => '1',
            'data1' => $userId, // 网盘用户ID
            'data2' => '',
            'data3' => '',
            'data4' => '',
            'data5' => '',
            'data6' => '',
            'data7' => '',
            'data8' => '',
            'data9' => '',
            'data10' => '',
        ];
        
        $orderId = Db::name('order')->insertGetId($orderData);
        
        return [
            'code' => '1',
            'msg' => '创建成功',
            'id' => $orderId,
        ];
        
    } catch (\Exception $e) {
        return [
            'code' => '-1',
            'msg' => '创建失败: ' . $e->getMessage(),
        ];
    }
}

// 解除暂停
function cxym_UnsuspendAccount($b, $data)
{
    try {
        $dbConfig = [
            'hostname' => $b['db_host'] ?? 'localhost',
            'database' => $b['db_name'] ?? 'cxym',
            'username' => $b['db_user'] ?? 'root',
            'password' => $b['db_pwd'] ?? '',
            'charset' => 'utf8',
        ];
        
        $db = Db::connect($dbConfig);
        $db->name('user')->where('id', $data['data1'])->update(['status' => 1]);
        
        return [
            'code' => '1',
            'msg' => '开启成功',
        ];
    } catch (\Exception $e) {
        return [
            'code' => '-1',
            'msg' => $e->getMessage(),
        ];
    }
}

// 暂停
function cxym_SuspendAccount($data3, $order)
{
    try {
        $dbConfig = [
            'hostname' => $data3['db_host'] ?? 'localhost',
            'database' => $data3['db_name'] ?? 'cxym',
            'username' => $data3['db_user'] ?? 'root',
            'password' => $data3['db_pwd'] ?? '',
            'charset' => 'utf8',
        ];
        
        $db = Db::connect($dbConfig);
        $db->name('user')->where('id', $order['data1'])->update(['status' => 0]);
        
        return [
            'code' => '1',
            'msg' => '暂停成功',
        ];
    } catch (\Exception $e) {
        return [
            'code' => '-1',
            'msg' => $e->getMessage(),
        ];
    }
}

// 终止
function cxym_TerminateAccount($data3, $order)
{
    try {
        $dbConfig = [
            'hostname' => $data3['db_host'] ?? 'localhost',
            'database' => $data3['db_name'] ?? 'cxym',
            'username' => $data3['db_user'] ?? 'root',
            'password' => $data3['db_pwd'] ?? '',
            'charset' => 'utf8',
        ];
        
        $db = Db::connect($dbConfig);
        $db->name('user')->where('id', $order['data1'])->delete();
        
        return [
            'code' => '1',
            'msg' => '终止成功',
        ];
    } catch (\Exception $e) {
        return [
            'code' => '-1',
            'msg' => $e->getMessage(),
        ];
    }
}

// 续费
function cxym_renew($b, $data, $a, $times, $time)
{
    return [
        'code' => '1',
        'msg' => '续费成功',
    ];
}
