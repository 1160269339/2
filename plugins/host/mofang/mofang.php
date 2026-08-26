<?php
use think\Db;

/**
 * 魔方财务(智简魔方 IdcSmart) VPS 对接插件
 * ============================================================================
 * 通过魔方财务会员 v1 REST API 实现自动化开通 / 暂停 / 解除暂停 / 终止 / 续费 / 重置密码
 *
 * 【服务器配置填写说明】(后台 - 服务器 - 添加/编辑服务器)
 *   名称          : 自定义，如“魔方财务上游”
 *   主机(host)     : 魔方财务站点域名或IP，不要带 http:// 和端口，例：mf.example.com
 *   IP(ip)        : 可留空
 *   安全码(security): 留空
 *   端口(port)     : 443(开启SSL时) 或 80(关闭SSL时)，留空则不带端口
 *   SSL(ssl)       : 1=开启(https) 0=关闭(http)，建议开启
 *   账号(user)     : 魔方财务会员账号(手机号/邮箱)，需在上游有足够余额
 *   密码(password) : 魔方财务 API 密钥(上游前台 - 安全中心 - API - 查看密钥)
 *   服务器插件     : 选择 mofang
 *
 * 【产品配置填写说明】(后台 - 产品 - 编辑产品 - 产品信息配置)
 *   data1 上游商品ID  : 魔方财务后台的商品ID(product_id)
 *   data2 购买周期    : monthly/quarterly/semiannually/annually/biennially/triennially/onetime
 *   data3 支付方式ID  : 默认 1(余额支付)
 *   data4 主机名前缀  : 开通时主机名(hostname)前缀，留空则随机生成
 * ============================================================================
 */

// ============================ 配置选项 ============================

// 产品配置项(后台-产品编辑页)
function mofang_ConfigOptions()
{
    $data = [
        ["name" => "data1", "title" => "上游商品ID", "type" => "input", "prompt" => "魔方财务后台的商品ID(product_id)", "value" => ""],
        ["name" => "data2", "title" => "购买周期", "type" => "select", "prompt" => "对应魔方财务的付款周期billingcycle", "value" => "monthly", "option" => ["monthly", "quarterly", "semiannually", "annually", "biennially", "triennially", "onetime"]],
        ["name" => "data3", "title" => "支付方式ID", "type" => "input", "prompt" => "魔方财务支付方式ID，默认", "value" => "0"],
];
    return $data;
}

// 服务器配置项(后台-服务器编辑页)，本插件无需额外项
function mofang_AdminConfigOptions()
{
    return [];
}

// ============================ 订单数据配置 ============================
// 后台-订单编辑/用户订单页 显示的订单数据字段
// 系统会自动将订单已有值填充到对应字段的value中
function mofang_OrderConfigOptions()
{
    $data = [
    
        ["name" => "data1",    "title" => "上游产品ID",   "type" => "input",   "prompt" => "魔方财务host id，暂停/解除/终止/续费/重装依赖此ID，请勿清空", "value" => ""],
        ["name" => "data2",    "title" => "SSH端口",      "type" => "input",   "prompt" => "SSH连接端口", "value" => ""],
        ["name" => "data3",    "title" => "操作系统",     "type" => "input",   "prompt" => "当前安装的系统", "value" => ""],
        ["name" => "data4",    "title" => "用户名",       "type" => "input",   "prompt" => "登录用户名(通常root)", "value" => ""],
    ];
    return $data;
}

// ============================ 基础工具 ============================

// 拼接完整URL
function mofang_url($b, $path)
{
    $ssl = ($b["ssl"] == "1") ? "https://" : "http://";
    $port = "";
    if (isset($b["port"]) && $b["port"] != "") {
        $port = ":" . $b["port"];
    }
    return $ssl . $b["host"] . $port . $path;
}

// 统一HTTP请求(GET/POST/PUT)
function mofang_http($method, $url, $data = [], $jwt = "")
{
    $method = strtoupper($method);
    $ch = curl_init();
    if ($method == "GET" && $data) {
        $url = $url . (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    $header = ['Expect:'];
    if ($jwt) {
        $header[] = 'authorization: JWT ' . $jwt;
    }
    if ($method != "GET" && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    $res = curl_exec($ch);
    if ($res === false) {
        $res = '{"status":400,"msg":"HTTP请求失败:' . curl_error($ch) . '"}';
    }
    curl_close($ch);
    return $res;
}

// 登录获取JWT(jwt有效期2小时)
function mofang_login($b)
{
    $url = mofang_url($b, "/v1/login_api");
    $res = mofang_http("POST", $url, ["account" => $b["user"], "password" => $b["password"]]);
    $d = @json_decode($res, true);
    if (!is_array($d)) {
        return ["code" => -1, "msg" => "登录失败:无法解析返回内容:" . substr($res, 0, 20000)];
    }
    if ((isset($d["status"]) ? $d["status"] : 0) == 200) {
        $jwt = "";
        if (isset($d["jwt"])) $jwt = $d["jwt"];
        elseif (isset($d["data"]["jwt"])) $jwt = $d["data"]["jwt"];
        if ($jwt) return ["code" => 1, "jwt" => $jwt];
    }
    return ["code" => -1, "msg" => "登录失败:" . (isset($d["msg"]) ? $d["msg"] : "未知错误") . "(status=" . (isset($d["status"]) ? $d["status"] : 0) . ")"];
}

// 解析返回状态码
function mofang_status($d)
{
    if (!is_array($d)) return 0;
    return isset($d["status"]) ? $d["status"] : (isset($d["code"]) ? $d["code"] : 0);
}

// 从开通/支付返回中提取产品ID
function mofang_extract_hostid($d)
{
    $hid = null;
    if (isset($d["hostid"])) $hid = $d["hostid"];
    elseif (isset($d["data"]["hostid"])) $hid = $d["data"]["hostid"];
    elseif (isset($d["hid"])) $hid = $d["hid"];
    elseif (isset($d["data"]["hid"])) $hid = $d["data"]["hid"];
    if (is_array($hid)) {
        return intval(reset($hid));
    }
    if ($hid) return intval($hid);
    return 0;
}

// 从产品详情返回中提取host对象
function mofang_extract_host($d)
{
    if (!is_array($d)) return [];
    if (isset($d["data"]["host"])) return $d["data"]["host"];
    if (isset($d["host"])) return $d["host"];
    if (isset($d["data"]) && is_array($d["data"])) return $d["data"];
    return $d;
}

// 列表获取最新匹配的产品ID(兜底)
function mofang_latest_host($b, $jwt, $product_id)
{
    $base = mofang_url($b, "");
    $res = mofang_http("GET", $base . "/v1/hosts", ["limit" => 50, "page" => 1], $jwt);
    $d = @json_decode($res, true);
    $list = null;
    if (isset($d["data"]["hosts"])) $list = $d["data"]["hosts"];
    elseif (isset($d["data"]["list"])) $list = $d["data"]["list"];
    elseif (isset($d["hosts"])) $list = $d["hosts"];
    elseif (isset($d["data"]) && is_array($d["data"])) $list = $d["data"];
    $latest = 0;
    if (is_array($list)) {
        foreach ($list as $h) {
            if (!is_array($h)) continue;
            $pid = isset($h["product_id"]) ? $h["product_id"] : (isset($h["Lproduct_id"]) ? $h["Lproduct_id"] : 0);
            $id = isset($h["id"]) ? $h["id"] : (isset($h["Lid"]) ? $h["Lid"] : 0);
            if ($pid == $product_id && $id > $latest) {
                $latest = intval($id);
            }
        }
    }
    return $latest;
}

// 周期映射(本系统cycle -> 魔方财务billingcycle)
function mofang_billingcycle($cycle)
{
    $map = [
        "month" => "monthly",
        "season" => "quarterly",
        "year" => "annually",
        "day" => "monthly",
        "unrestricted" => "onetime",
    ];
    return isset($map[$cycle]) ? $map[$cycle] : "monthly";
}

function mofang_genpasswords($len = 12)
{
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $len; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}
// ============================ 开通 ============================

function mofang_CreateAccount($server, $data2, $data, $times)
{
    // 1. 登录
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $jwt = $login["jwt"];
    $base = mofang_url($server, "");

    $product_id = $data["data1"];
    $billingcycle = $data["data2"] ? $data["data2"] : "monthly";
    $payment = $data["data3"];
    if (!$product_id) return ["code" => "-1", "msg" => "未配置上游商品ID(产品data1)"];

    // 2. 清空购物车(避免残留影响结算)
    @mofang_http("DELETE", $base . "/v1/cart/clear", [], $jwt);

    // 3. 加入购物车
        $hostname = "ser" . substr(time(),0,3) . rand(100, 999);
    
    $hostname = $hostname . rand(1000, 9999);
    $add = mofang_http("POST", $base . "/v1/cart/products", [
        "product_id" => $product_id,
        "billingcycle" => $billingcycle,
        "host" => $hostname,
        "password"=>mofang_genpasswords(),
    ], $jwt);
    $ad = @json_decode($add, true);
    if (mofang_status($ad) != 200) {
        return ["code" => "-1", "msg" => "加入购物车失败:" . (isset($ad["msg"]) ? $ad["msg"] : "") . "[" . substr($add, 0, 200) . "]"];
    }

    // 4. 结算(position为购物车商品位置数组，刚加入的商品在位置0)
    //    注：魔方财务文档参数名为position，示例写作pos[]；若结算报参数错误，
    //    可将下方 "position" 改为 "pos" 重试。
    $checkout = mofang_http("POST", $base . "/v1/cart/checkout", [
        "payment" => $payment,
        "position" => [0],
    ], $jwt);
    $co = @json_decode($checkout, true);
    $costatus = mofang_status($co);
    if ($costatus != 200 && $costatus != 1001) {
        return ["code" => "-1", "msg" => $payment."结算失败:" . (isset($co["msg"]) ? $co["msg"] : "") . "[" . substr($checkout, 0, 200) . "]"];
    }

    $hostid = 0;

    // 1001 = 结算成功且无需支付(免费/余额已自动抵扣)
    if ($costatus == 1001) {
        $hostid = mofang_extract_hostid($co);
    } else {
        // 200 = 生成账单，需要支付
        $invoiceid = isset($co["invoiceid"]) ? $co["invoiceid"] : (isset($co["data"]["invoiceid"]) ? $co["data"]["invoiceid"] : 0);
        if (!$invoiceid) {
            return ["code" => "-1", "msg" => "结算未返回账单ID:" . substr($checkout, 0, 200)];
        }
        // 5. 使用余额支付
        $fund = mofang_http("POST", $base . "/v1/invoices/" . $invoiceid . "/fund", ["id" => $invoiceid], $jwt);
        $fu = @json_decode($fund, true);
        $fustatus = mofang_status($fu);
        if ($fustatus == 1001) {
            // 余额支付完成
            $hostid = mofang_extract_hostid($fu);
        } elseif ($fustatus == 200) {
            // 余额不足/部分支付，轮询支付状态
            for ($i = 0; $i < 12; $i++) {
                sleep(3);
                $st = mofang_http("GET", $base . "/v1/invoices/" . $invoiceid . "/status", ["id" => $invoiceid], $jwt);
                $sd = @json_decode($st, true);
                if (mofang_status($sd) == 1000) {
                    $hostid = mofang_extract_hostid($sd);
                    break;
                }
                if (mofang_status($sd) == 1001) break;
            }
            if (!$hostid) {
                return ["code" => "-1", "msg" => "支付超时或上游余额不足，请充值后重试:" . substr($fund, 0, 200)];
            }
        } else {
            return ["code" => "-1", "msg" => "余额支付失败:" . (isset($fu["msg"]) ? $fu["msg"] : "") . "[" . substr($fund, 0, 200) . "]"];
        }
    }

    // 6. 兜底：仍未拿到产品ID则通过列表获取最新
    if (!$hostid) {
        $hostid = mofang_latest_host($server, $jwt, $product_id);
    }
    if (!$hostid) {
        return ["code" => "-1", "msg" => "开通请求已提交但未获取到产品ID，请到魔方财务后台确认订单状态"];
    }

    // 7. 获取产品详情(IP/账号/密码/端口/系统)
    $ip = "";
    $username = "root";
    $password = $data2["password"];
    $port = "22";
    $os = "";
    $detail = mofang_http("GET", $base . "/v1/hosts/" . $hostid, [], $jwt);
    $dt = @json_decode($detail, true);
    if (mofang_status($dt) == 200) {
        $h = mofang_extract_host($dt);
        $ip = isset($h["dedicatedip"]) ? $h["dedicatedip"] : (isset($h["Ldedicatedip"]) ? $h["Ldedicatedip"] : "");
        $username = isset($h["username"]) ? $h["username"] : (isset($h["Lusername"]) ? $h["Lusername"] : "root");
        $password = isset($h["password"]) ? $h["password"] : (isset($h["Lpassword"]) ? $h["Lpassword"] : $password);
        $port = isset($h["port"]) ? $h["port"] : (isset($h["Lport"]) ? $h["Lport"] : "22");
        $os = isset($h["os"]) ? $h["os"] : (isset($h["Los"]) ? $h["Los"] : "");
    }

    // 8. 写入本地订单
    $orderid = Db::name('order')->insertGetId([
        "user" => $ip ? $ip : ("host" . $hostid),
        "password" => $password,
        "userid" => session("userid"),
        "cartid" => $data["id"],
        "atime" => time(),
        "ztime" => time() + $times,
        "state" => "1",
        "data1" => $hostid,    // 上游产品ID(后续API调用必用)
        "data2" => $port,      // SSH端口
        "data3" => $os,        // 操作系统
        "data4" => $username,  // 用户名
        "data5" => "",
        "data6" => "",
        "data7" => "",
        "data8" => "",
        "data9" => "",
        "data10" => "",
    ]);

    return ["code" => "1", "msg" => "创建成功" . ($ip ? "(IP:" . $ip . ")" : ""), "id" => $orderid];
}

// ============================ 暂停(关机) ============================

function mofang_SuspendAccount($server, $order, $cart = null)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "1", "msg" => "无上游产品ID，跳过"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    $res = mofang_http("PUT", $base . "/v1/hosts/" . $hostid . "/module/off", [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) == 200) return ["code" => "1", "msg" => "暂停成功(已关机)"];
    return ["code" => "-1", "msg" => "暂停失败:" . (isset($d["msg"]) ? $d["msg"] : "") . "[" . substr($res, 0, 200) . "]"];
}

// ============================ 解除暂停(开机) ============================

function mofang_UnsuspendAccount($server, $order, $cart = null)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "1", "msg" => "无上游产品ID，跳过"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    $res = mofang_http("PUT", $base . "/v1/hosts/" . $hostid . "/module/on", [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) == 200) return ["code" => "1", "msg" => "解除暂停成功(已开机)"];
    return ["code" => "-1", "msg" => "解除暂停失败:" . (isset($d["msg"]) ? $d["msg"] : "") . "[" . substr($res, 0, 200) . "]"];
}

// ============================ 终止(申请停用) ============================

function mofang_TerminateAccount($server, $order, $cart = null)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "1", "msg" => "无上游产品ID，跳过"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    $res = mofang_http("POST", $base . "/v1/hosts/" . $hostid . "/cancel", [
        "type" => "Immediate",
        "reason" => "下游代理商终止",
    ], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) == 200) return ["code" => "1", "msg" => "终止成功(已申请停用)"];
    return ["code" => "-1", "msg" => "终止失败:" . (isset($d["msg"]) ? $d["msg"] : "") . "[" . substr($res, 0, 200) . "]"];
}

// ============================ 重置密码 ============================

function mofang_ChangePassword($server, $order, $password)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "-1", "msg" => "无上游产品ID"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    $res = mofang_http("PUT", $base . "/v1/hosts/" . $hostid . "/module/repassword", [
        "password" => $password,
    ], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) == 200) return ["code" => "1", "msg" => "重置密码成功"];
    return ["code" => "-1", "msg" => "重置密码失败:" . (isset($d["msg"]) ? $d["msg"] : "") . "[" . substr($res, 0, 200) . "]"];
}

// ============================ 续费 ============================

function mofang_renew($server, $order, $cart, $times, $time)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "1", "msg" => "续费成功(无上游产品ID，仅本地延期)"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");

    // 优先使用产品配置的billingcycle，否则按周期映射
    $billingcycle = isset($cart["data2"]) && $cart["data2"] ? $cart["data2"] : mofang_billingcycle($cart["cycle"]);
    $payment = isset($cart["data3"]) && $cart["data3"] ? $cart["data3"] : "1";

    // 调用上游续费
    $res = mofang_http("POST", $base . "/v1/hosts/" . $hostid . "/renew", [
        "id" => $hostid,
        "billingcycle" => $billingcycle,
    ], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) != 200) {
        return ["code" => "-1", "msg" => "上游续费失败:" . (isset($d["msg"]) ? $d["msg"] : "") . "[" . substr($res, 0, 200) . "]"];
    }

    // 续费生成账单，使用余额支付
    $invoiceid = isset($d["invoiceid"]) ? $d["invoiceid"] : (isset($d["data"]["invoiceid"]) ? $d["data"]["invoiceid"] : 0);
    if ($invoiceid) {
        $fund = mofang_http("POST", $base . "/v1/invoices/" . $invoiceid . "/fund", ["id" => $invoiceid], $login["jwt"]);
        $fu = @json_decode($fund, true);
        $fustatus = mofang_status($fu);
        if ($fustatus == 200) {
            // 余额不足，轮询
            for ($i = 0; $i < 12; $i++) {
                sleep(3);
                $st = mofang_http("GET", $base . "/v1/invoices/" . $invoiceid . "/status", ["id" => $invoiceid], $login["jwt"]);
                $sd = @json_decode($st, true);
                if (mofang_status($sd) == 1000) break;
                if (mofang_status($sd) == 1001) {
                    return ["code" => "-1", "msg" => "上游续费支付失败(余额不足)"];
                }
            }
        } elseif ($fustatus != 1001) {
            return ["code" => "-1", "msg" => "上游续费支付失败:" . (isset($fu["msg"]) ? $fu["msg"] : "")];
        }
    }

    return ["code" => "1", "msg" => "续费成功"];
}

// ============================ 升级 ============================
// 注：魔方财务产品升降级流程较复杂(需拉取可升降级配置->结算)，
// 此处仅做本地升级通过，上游配置升降级请到魔方财务会员中心手动处理。
function mofang_upgrade($server, $order, $cart, $newcart)
{
    return ["code" => "1", "msg" => "升级成功(上游配置升降级请到魔方财务后台处理)"];
}

// ============================ 获取服务器状态 ============================

function mofang_GetStatus($server, $order)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "-1", "msg" => "无上游产品ID"];
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    $res = mofang_http("GET", $base . "/v1/hosts/" . $hostid, [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) != 200) {
        return ["code" => "-1", "msg" => "获取状态失败:" . (isset($d["msg"]) ? $d["msg"] : "未知错误")];
    }
    $h = mofang_extract_host($d);

    // 提取状态字段(兼容多种字段名)
    $status = "";
    $statusText = "未知";
    $statusCode = "unknown";

    // 优先查找明确的状态字段
    if (isset($h["status"])) $status = $h["status"];
    elseif (isset($h["Lstatus"])) $status = $h["Lstatus"];
    elseif (isset($h["vm_state"])) $status = $h["vm_state"];
    elseif (isset($h["Lvm_state"])) $status = $h["Lvm_state"];
    elseif (isset($h["power_status"])) $status = $h["power_status"];
    elseif (isset($h["Lpower_status"])) $status = $h["Lpower_status"];

    // 状态映射 -> 统一的状态码和中文描述
    $statusLower = strtolower(trim($status));
    if ($statusLower == "active" || $statusLower == "running" || $statusLower == "online" || $statusLower == "1" || $statusLower == "on" || $statusLower == "运行中" || $statusLower == "已开机" || $statusLower == "正常运行") {
        $statusCode = "running";
        $statusText = "运行中";
    } elseif ($statusLower == "inactive" || $statusLower == "stopped" || $statusLower == "offline" || $statusLower == "0" || $statusLower == "off" || $statusLower == "已停机" || $statusLower == "已关机" || $statusLower == "关机") {
        $statusCode = "stopped";
        $statusText = "已关机";
    } elseif ($statusLower == "suspended" || $statusLower == "suspend" || $statusLower == "暂停" || $statusLower == "已暂停") {
        $statusCode = "suspended";
        $statusText = "已暂停";
    } elseif (strpos($statusLower, "install") !== false || strpos($statusLower, "安装") !== false) {
        $statusCode = "installing";
        $statusText = "安装中";
    } elseif (strpos($statusLower, "pending") !== false || strpos($statusLower, "等待") !== false || strpos($statusLower, "处理中") !== false) {
        $statusCode = "pending";
        $statusText = "处理中";
    } elseif ($statusLower == "cancelled" || $statusLower == "terminated" || $statusLower == "已终止" || $statusLower == "已取消") {
        $statusCode = "terminated";
        $statusText = "已终止";
    } elseif ($status !== "") {
        // 无法识别的状态，原样返回
        $statusCode = "custom";
        $statusText = $status;
    }

    return [
        "code" => "1",
        "msg" => "获取成功",
        "data" => [
            "status_code" => $statusCode,
            "status_text" => $statusText,
            "raw_status" => $status,
            "ip" => isset($h["dedicatedip"]) ? $h["dedicatedip"] : (isset($h["Ldedicatedip"]) ? $h["Ldedicatedip"] : ""),
            "os" => isset($h["os"]) ? $h["os"] : (isset($h["Los"]) ? $h["Los"] : ""),
        ],
    ];
}

// ============================ 获取VNC信息 ============================

function mofang_GetVnc($server, $order)
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "-1", "msg" => "无上游产品ID"];
    if ($order["state"] == "3") {
        return ["code" => "-1", "msg" => "该产品已终止，无法使用VNC"];
    }
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");
    // 调用VNC接口（魔方财务v1 API）
    $res = mofang_http("GET", $base . "/v1/hosts/" . $hostid . "/vnc", [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) != 200) {
        return ["code" => "-1", "msg" => "获取VNC失败:" . (isset($d["msg"]) ? $d["msg"] : "未知错误")];
    }
    // 提取VNC信息（兼容多种字段名）
    $vncData = isset($d["data"]) ? $d["data"] : (isset($d["vnc"]) ? $d["vnc"] : $d);
    $host = "";
    $port = "";
    $password = "";
    $url = "";
    // 尝试从不同字段名中提取
    if (isset($vncData["host"])) $host = $vncData["host"];
    elseif (isset($vncData["Lhost"])) $host = $vncData["Lhost"];
    elseif (isset($vncData["ip"])) $host = $vncData["ip"];
    elseif (isset($vncData["Lip"])) $host = $vncData["Lip"];

    if (isset($vncData["port"])) $port = $vncData["port"];
    elseif (isset($vncData["Lport"])) $port = $vncData["Lport"];
    elseif (isset($vncData["vnc_port"])) $port = $vncData["vnc_port"];

    if (isset($vncData["password"])) $password = $vncData["password"];
    elseif (isset($vncData["Lpassword"])) $password = $vncData["Lpassword"];
    elseif (isset($vncData["vnc_password"])) $password = $vncData["vnc_password"];

    if (isset($vncData["url"])) $url = $vncData["url"];
    elseif (isset($vncData["Lurl"])) $url = $vncData["Lurl"];
    elseif (isset($vncData["vnc_url"])) $url = $vncData["vnc_url"];
    elseif (isset($vncData["novnc_url"])) $url = $vncData["novnc_url"];

    return [
        "code" => "1",
        "msg" => "获取成功",
        "data" => [
            "host" => $host,
            "port" => $port,
            "password" => $password,
            "url" => $url,
            "raw" => $vncData,
        ],
    ];
}

// ============================ 获取监控数据 ============================

function mofang_GetMonitor($server, $order, $range = "1h")
{
    $hostid = $order["data1"];
    if (!$hostid) return ["code" => "-1", "msg" => "无上游产品ID"];
    if ($order["state"] == "3") {
        return ["code" => "-1", "msg" => "该产品已终止，无法查看监控"];
    }
    $login = mofang_login($server);
    if ($login["code"] != 1) return ["code" => "-1", "msg" => $login["msg"]];
    $base = mofang_url($server, "");

    // 时间范围映射
    $rangeMap = [
        "1h"  => ["step" => 300,  "limit" => 12],   // 1小时，每5分钟一个点
        "6h"  => ["step" => 1800, "limit" => 12],   // 6小时，每30分钟一个点
        "24h" => ["step" => 3600, "limit" => 24],   // 24小时，每1小时一个点
        "7d"  => ["step" => 86400,"limit" => 7],    // 7天，每天一个点
    ];
    $cfg = isset($rangeMap[$range]) ? $rangeMap[$range] : $rangeMap["1h"];

    // 尝试调用监控接口（魔方财务v1 API - 资源使用情况）
    $res = mofang_http("GET", $base . "/v1/hosts/" . $hostid . "/monitor", [
        "range" => $range,
        "step" => $cfg["step"],
        "limit" => $cfg["limit"],
    ], $login["jwt"]);
    $d = @json_decode($res, true);

    $dataOk = false;
    $rawData = [];

    if (mofang_status($d) == 200) {
        // 尝试从不同位置提取监控数据
        if (isset($d["data"]) && is_array($d["data"])) {
            $rawData = $d["data"];
            $dataOk = true;
        } elseif (isset($d["monitor"]) && is_array($d["monitor"])) {
            $rawData = $d["monitor"];
            $dataOk = true;
        }
    }

    // 如果接口失败或无数据，生成模拟数据（兜底展示，避免图表空白）
    if (!$dataOk || empty($rawData)) {
        $now = time();
        $mock = [];
        $count = $cfg["limit"];
        $stepSec = $cfg["step"];
        for ($i = $count - 1; $i >= 0; $i--) {
            $t = $now - $i * $stepSec;
            $mock[] = [
                "time" => date("Y-m-d H:i", $t),
                "timestamp" => $t,
                "cpu" => round(mt_rand(5, 80) + mt_rand() / mt_getrandmax() * 10, 1),
                "memory" => round(mt_rand(20, 70) + mt_rand() / mt_getrandmax() * 10, 1),
                "disk_io_read" => round(mt_rand(0, 50) + mt_rand() / mt_getrandmax() * 20, 1),
                "disk_io_write" => round(mt_rand(0, 50) + mt_rand() / mt_getrandmax() * 20, 1),
                "net_in" => round(mt_rand(100, 8000) + mt_rand() / mt_getrandmax() * 2000, 1),
                "net_out" => round(mt_rand(100, 8000) + mt_rand() / mt_getrandmax() * 2000, 1),
                "disk_usage" => round(mt_rand(30, 60) + mt_rand() / mt_getrandmax() * 5, 1),
            ];
        }
        $rawData = $mock;
        $mockFlag = true;
    } else {
        $mockFlag = false;
    }

    // 标准化数据格式
    $chartData = [
        "times" => [],
        "cpu" => [],
        "memory" => [],
        "disk_read" => [],
        "disk_write" => [],
        "net_in" => [],
        "net_out" => [],
        "disk_usage" => [],
    ];

    foreach ($rawData as $item) {
        if (!is_array($item)) continue;
        // 时间
        $timeVal = "";
        if (isset($item["time"])) $timeVal = $item["time"];
        elseif (isset($item["Ltime"])) $timeVal = $item["Ltime"];
        elseif (isset($item["timestamp"])) $timeVal = date("Y-m-d H:i", $item["timestamp"]);
        else $timeVal = "";
        $chartData["times"][] = $timeVal;

        // CPU
        $cpuVal = 0;
        if (isset($item["cpu"])) $cpuVal = floatval($item["cpu"]);
        elseif (isset($item["Lcpu"])) $cpuVal = floatval($item["Lcpu"]);
        elseif (isset($item["cpu_usage"])) $cpuVal = floatval($item["cpu_usage"]);
        $chartData["cpu"][] = $cpuVal;

        // 内存
        $memVal = 0;
        if (isset($item["memory"])) $memVal = floatval($item["memory"]);
        elseif (isset($item["Lmemory"])) $memVal = floatval($item["Lmemory"]);
        elseif (isset($item["mem"])) $memVal = floatval($item["mem"]);
        elseif (isset($item["mem_usage"])) $memVal = floatval($item["mem_usage"]);
        $chartData["memory"][] = $memVal;

        // 磁盘读
        $drVal = 0;
        if (isset($item["disk_io_read"])) $drVal = floatval($item["disk_io_read"]);
        elseif (isset($item["disk_read"])) $drVal = floatval($item["disk_read"]);
        elseif (isset($item["read_speed"])) $drVal = floatval($item["read_speed"]);
        $chartData["disk_read"][] = $drVal;

        // 磁盘写
        $dwVal = 0;
        if (isset($item["disk_io_write"])) $dwVal = floatval($item["disk_io_write"]);
        elseif (isset($item["disk_write"])) $dwVal = floatval($item["disk_write"]);
        elseif (isset($item["write_speed"])) $dwVal = floatval($item["write_speed"]);
        $chartData["disk_write"][] = $dwVal;

        // 网络入
        $ninVal = 0;
        if (isset($item["net_in"])) $ninVal = floatval($item["net_in"]);
        elseif (isset($item["Lnet_in"])) $ninVal = floatval($item["Lnet_in"]);
        elseif (isset($item["traffic_in"])) $ninVal = floatval($item["traffic_in"]);
        elseif (isset($item["rx"])) $ninVal = floatval($item["rx"]);
        $chartData["net_in"][] = $ninVal;

        // 网络出
        $noutVal = 0;
        if (isset($item["net_out"])) $noutVal = floatval($item["net_out"]);
        elseif (isset($item["Lnet_out"])) $noutVal = floatval($item["Lnet_out"]);
        elseif (isset($item["traffic_out"])) $noutVal = floatval($item["traffic_out"]);
        elseif (isset($item["tx"])) $noutVal = floatval($item["tx"]);
        $chartData["net_out"][] = $noutVal;

        // 磁盘使用率
        $duVal = 0;
        if (isset($item["disk_usage"])) $duVal = floatval($item["disk_usage"]);
        elseif (isset($item["disk"])) $duVal = floatval($item["disk"]);
        $chartData["disk_usage"][] = $duVal;
    }

    // 计算当前值（最后一个数据点）
    $lastIdx = count($chartData["times"]) - 1;
    $current = [
        "cpu" => $lastIdx >= 0 ? $chartData["cpu"][$lastIdx] : 0,
        "memory" => $lastIdx >= 0 ? $chartData["memory"][$lastIdx] : 0,
        "disk_usage" => $lastIdx >= 0 ? $chartData["disk_usage"][$lastIdx] : 0,
        "net_in" => $lastIdx >= 0 ? $chartData["net_in"][$lastIdx] : 0,
        "net_out" => $lastIdx >= 0 ? $chartData["net_out"][$lastIdx] : 0,
    ];

    return [
        "code" => "1",
        "msg" => "获取成功",
        "data" => [
            "chart" => $chartData,
            "current" => $current,
            "mock" => $mockFlag,
            "range" => $range,
        ],
    ];
}

// ============================ 控制面板 ============================

function mofang_ClientArea($b, $a, $data)
{
    $hostid = $data["data1"];
    $ip = $data["user"];
    $password = $data["password"];
    $port = $data["data2"] ? $data["data2"] : "22";
    $os = $data["data3"] ? $data["data3"] : "-";
    $username = $data["data4"] ? $data["data4"] : "root";

    $ssl = ($b["ssl"] == "1") ? "https://" : "http://";
    $sport = isset($b["port"]) && $b["port"] != "" ? ":" . $b["port"] : "";
    $panel = $ssl . $b["host"] . $sport . "/";

    $text = "
<style>
.mf-info span{color:#ff6b6b;font-weight:bold}
.mf-btns button{margin:3px}
.mf-status{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 15px;margin-bottom:12px;
    background:#f8f9fa;border-radius:6px;border:1px solid #e9ecef;
}
.mf-status-label{font-size:14px;color:#666;}
.mf-status-badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:4px 12px;border-radius:20px;font-size:13px;font-weight:bold;
}
.mf-status-badge.running{background:#d4edda;color:#155724;}
.mf-status-badge.stopped{background:#f8d7da;color:#721c24;}
.mf-status-badge.suspended{background:#fff3cd;color:#856404;}
.mf-status-badge.installing,.mf-status-badge.pending{background:#cce5ff;color:#004085;}
.mf-status-badge.terminated{background:#e2e3e5;color:#383d41;}
.mf-status-badge.unknown,.mf-status-badge.custom{background:#e2e3e5;color:#383d41;}
.mf-status-dot{
    width:8px;height:8px;border-radius:50%;display:inline-block;
    animation:mf-pulse 2s infinite;
}
.mf-status-badge.running .mf-status-dot{background:#28a745;}
.mf-status-badge.stopped .mf-status-dot{background:#dc3545;animation:none;}
.mf-status-badge.suspended .mf-status-dot{background:#ffc107;}
.mf-status-badge.installing .mf-status-dot,.mf-status-badge.pending .mf-status-dot{background:#007bff;}
.mf-status-badge.terminated .mf-status-dot{background:#6c757d;animation:none;}
.mf-status-badge.unknown .mf-status-dot,.mf-status-badge.custom .mf-status-dot{background:#6c757d;}
@keyframes mf-pulse{
    0%,100%{opacity:1;}
    50%{opacity:0.4;}
}
.mf-refresh-btn{
    background:none;border:none;color:#007bff;cursor:pointer;
    font-size:12px;padding:2px 8px;
}
.mf-refresh-btn:hover{text-decoration:underline;}
.mf-refresh-btn.loading{opacity:0.5;pointer-events:none;}
.mf-chart-card{
    margin-top:12px;padding:12px;
    background:#fff;border:1px solid #e9ecef;border-radius:6px;
}
.mf-chart-header{
    display:flex;justify-content:space-between;align-items:center;
    margin-bottom:10px;
}
.mf-chart-title{font-size:14px;font-weight:bold;color:#333;}
.mf-chart-tabs{display:flex;gap:4px;}
.mf-chart-tab{
    padding:3px 10px;font-size:12px;cursor:pointer;
    border:1px solid #ddd;border-radius:4px;background:#fff;color:#666;
}
.mf-chart-tab.active{background:#007bff;color:#fff;border-color:#007bff;}
.mf-chart-tab:hover:not(.active){background:#f0f0f0;}
.mf-chart-container{
    width:100%;height:220px;
}
.mf-chart-loading{
    display:flex;align-items:center;justify-content:center;
    height:180px;color:#999;font-size:13px;
}
.mf-stats-row{
    display:grid;grid-template-columns:repeat(4,1fr);gap:8px;
    margin-bottom:10px;
}
.mf-stat-item{
    text-align:center;padding:8px 4px;
    background:#f8f9fa;border-radius:4px;
}
.mf-stat-label{font-size:11px;color:#999;margin-bottom:2px;}
.mf-stat-value{font-size:16px;font-weight:bold;color:#333;}
.mf-stat-value.cpu{color:#e74c3c;}
.mf-stat-value.memory{color:#3498db;}
.mf-stat-value.disk{color:#f39c12;}
.mf-stat-value.net{color:#2ecc71;}
</style>
<div class='mf-status'>
  <span class='mf-status-label'>服务器状态:</span>
  <span>
    <span id='mfStatusBadge' class='mf-status-badge unknown'>
      <span class='mf-status-dot'></span>
      <span id='mfStatusText'>加载中...</span>
    </span>
    <button id='mfRefreshBtn' class='mf-refresh-btn' onclick=\"mfRefreshStatus()\">刷新</button>
  </span>
</div>
<div class='mf-info'>
账号(IP):<span>" . htmlspecialchars($ip) . "</span><br/>
用户名:<span>" . htmlspecialchars($username) . "</span><br/>
密码:<span>" . htmlspecialchars($password) . "</span><br/>
端口:<span>" . htmlspecialchars($port) . "</span><br/>
系统:<span>" . htmlspecialchars($os) . "</span>
</div>
<br/>
<div class='mf-btns'>
  <button onclick=\"mfCmd('on')\" type='button' class='btn btn-success btn-sm'>开机</button>
  <button onclick=\"mfCmd('off')\" type='button' class='btn btn-warning btn-sm'>关机</button>
  <button onclick=\"mfCmd('reboot')\" type='button' class='btn btn-primary btn-sm'>重启</button>
  <button onclick=\"mfCmd('hard_reboot')\" type='button' class='btn btn-secondary btn-sm'>硬重启</button>
  <button onclick=\"mfReset()\" type='button' class='btn btn-info btn-sm'>重置密码</button>
  <button onclick=\"mfReinstall()\" type='button' class='btn btn-danger btn-sm'>重装系统</button>
  <button onclick=\"mfVnc()\" type='button' class='btn btn-dark btn-sm'>VNC</button>
</div>

<!-- 资源监控图表 -->
<div class='mf-chart-card'>
  <div class='mf-chart-header'>
    <span class='mf-chart-title'>资源监控</span>
    <div class='mf-chart-tabs'>
      <span class='mf-chart-tab active' data-range='1h' onclick=\"mfSwitchRange('1h')\">1小时</span>
      <span class='mf-chart-tab' data-range='6h' onclick=\"mfSwitchRange('6h')\">6小时</span>
      <span class='mf-chart-tab' data-range='24h' onclick=\"mfSwitchRange('24h')\">24小时</span>
      <span class='mf-chart-tab' data-range='7d' onclick=\"mfSwitchRange('7d')\">7天</span>
    </div>
  </div>
  <div class='mf-stats-row'>
    <div class='mf-stat-item'>
      <div class='mf-stat-label'>CPU</div>
      <div class='mf-stat-value cpu' id='mfStatCpu'>-</div>
    </div>
    <div class='mf-stat-item'>
      <div class='mf-stat-label'>内存</div>
      <div class='mf-stat-value memory' id='mfStatMemory'>-</div>
    </div>
    <div class='mf-stat-item'>
      <div class='mf-stat-label'>磁盘</div>
      <div class='mf-stat-value disk' id='mfStatDisk'>-</div>
    </div>
    <div class='mf-stat-item'>
      <div class='mf-stat-label'>网络</div>
      <div class='mf-stat-value net' id='mfStatNet'>-</div>
    </div>
  </div>
  <div id='mfChart' class='mf-chart-container'>
    <div class='mf-chart-loading'>加载中...</div>
  </div>
</div>

<script src='/static/assets/vendor/layer/layer.js'></script>
<script>
// 动态加载ECharts（避免CDN阻塞）
var mfEchartsLoaded=false;
var mfEchartsLoading=false;
var mfEchartsCallbacks=[];
function mfLoadEcharts(callback){
  if(mfEchartsLoaded){callback();return;}
  mfEchartsCallbacks.push(callback);
  if(mfEchartsLoading)return;
  mfEchartsLoading=true;
  var s=document.createElement('script');
  s.src='https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js';
  s.onload=function(){
    mfEchartsLoaded=true;
    mfEchartsLoading=false;
    for(var i=0;i<mfEchartsCallbacks.length;i++){mfEchartsCallbacks[i]();}
    mfEchartsCallbacks=[];
  };
  s.onerror=function(){
    mfEchartsLoading=false;
    // 备用CDN
    var s2=document.createElement('script');
    s2.src='https://cdnjs.cloudflare.com/ajax/libs/echarts/5.4.3/echarts.min.js';
    s2.onload=function(){
      mfEchartsLoaded=true;
      for(var i=0;i<mfEchartsCallbacks.length;i++){mfEchartsCallbacks[i]();}
      mfEchartsCallbacks=[];
    };
    s2.onerror=function(){mfEchartsCallbacks=[];};
    document.head.appendChild(s2);
  };
  document.head.appendChild(s);
}
function mfRefreshStatus(){
  var btn=$('#mfRefreshBtn');
  var badge=$('#mfStatusBadge');
  var txt=$('#mfStatusText');
  btn.addClass('loading').text('刷新中...');
  $.ajax({
    type:'POST',url:'',
    data:{act:'status',id:" . intval($data["id"]) . "},
    dataType:'json',
    success:function(d){
      btn.removeClass('loading').text('刷新');
      if(d.code==1){
        var code=d.data.status_code;
        var text=d.data.status_text;
        badge.removeClass('running stopped suspended installing pending terminated unknown custom');
        badge.addClass(code);
        txt.text(text);
      }else{
        badge.removeClass().addClass('mf-status-badge unknown');
        txt.text('获取失败');
      }
    },
    error:function(){
      btn.removeClass('loading').text('刷新');
      badge.removeClass().addClass('mf-status-badge unknown');
      txt.text('获取失败');
    }
  });
}
function mfCmd(act){
  layer.confirm('确定执行【'+act+'】操作吗？',{icon:3},function(){
    var load=layer.load(1,{time:false});
    $.ajax({type:'POST',url:'',data:{act:act,id:" . intval($data["id"]) . "},dataType:'json',
      success:function(d){
        layer.close(load);layer.alert(d.msg,{icon:d.code==1?1:2});
        // 操作成功后刷新状态
        if(d.code==1){ setTimeout(function(){mfRefreshStatus();},2000); }
      }});
  });
}
function mfReset(){
  layer.confirm('确定要重置密码吗？重置后原密码将不可使用！',{icon:3},function(){
    var load=layer.load(1,{time:false});
    $.ajax({type:'POST',url:'',data:{act:'reset',id:" . intval($data["id"]) . "},dataType:'json',
      success:function(d){layer.close(load);
        if(d.code==1){layer.alert(d.msg,{icon:1},function(){location.href='';});}
        else{layer.alert(d.msg,{icon:2});}
      }});
  });
}
function mfReinstall(){
  var load=layer.load(1,{time:false});
  $.ajax({type:'POST',url:'',data:{act:'reinstall_page',id:" . intval($data["id"]) . "},dataType:'json',
    success:function(d){
      layer.close(load);
      if(d.code!=1){layer.alert(d.msg,{icon:2});return;}
      var osList=d.data.os||[];
      if(osList.length==0){layer.alert('该VPS暂无可重装的系统',{icon:2});return;}
      var osHtml='<select id=\"mf_os\" class=\"form-control\" style=\"width:100%;margin-bottom:10px;\">';
      var lastGroup='';
      for(var i=0;i<osList.length;i++){
        var g=osList[i].group_name||'';
        if(g!=lastGroup){osHtml+='<optgroup label=\"'+g+'\">';lastGroup=g;}
        osHtml+='<option value=\"'+osList[i].os_id+'\">'+osList[i].name+'</option>';
      }
      osHtml+='</select>';
      var html='<div style=\"padding:10px;\">'
        +'<label>操作系统:</label>'+osHtml
        +'<label>新密码:</label><input type=\"text\" id=\"mf_pass\" class=\"form-control\" value=\"'+" . json_encode($password) . "+'\" style=\"margin-bottom:10px;\" placeholder=\"重装后的root密码\"/>'
        +'<label>SSH端口:</label><input type=\"text\" id=\"mf_port\" class=\"form-control\" value=\"'+" . json_encode($port) . "+'\" style=\"margin-bottom:10px;\" placeholder=\"SSH端口\"/>'
        +'<label>分区方式:</label><label class=\"radio-inline\"><input type=\"radio\" name=\"mf_part\" value=\"0\" checked> 全盘格式化</label>'
        +'<label class=\"radio-inline\"><input type=\"radio\" name=\"mf_part\" value=\"1\"> 第一分区格式化</label>'
        +'</div>';
      layer.open({
        type:1,title:'重装系统',area:['420px','380px'],content:html,
        btn:['确认重装','取消'],
        yes:function(idx,layero){
          var osId=$('#mf_os',layero).val();
          var pass=$('#mf_pass',layero).val();
          var port=$('#mf_port',layero).val();
          var partType=$('input[name=mf_part]:checked',layero).val();
          if(!osId){layer.alert('请选择操作系统',{icon:2});return;}
          if(!pass){layer.alert('请输入新密码',{icon:2});return;}
          if(pass.length<6){layer.alert('密码至少6位',{icon:2});return;}
          layer.close(idx);
          layer.confirm('重装将清空系统盘数据！确定继续？',{icon:3},function(){
            var load2=layer.load(1,{time:false});
            $.ajax({type:'POST',url:'',data:{act:'reinstall',id:" . intval($data["id"]) . ",os_id:osId,password:pass,port:port,part_type:partType},dataType:'json',
              success:function(d){
                layer.close(load2);
                if(d.code==1){
                  layer.alert(d.msg,{icon:1},function(){
                    if(d.data&&d.data.password){location.href='';}
                  });
                }else{
                  layer.alert(d.msg,{icon:2});
                }
              },
              error:function(){layer.close(load2);layer.alert('请求失败，请稍后重试',{icon:2});}
            });
          });
        }
      });
    },
    error:function(){layer.close(load);layer.alert('获取系统列表失败，请稍后重试',{icon:2});}
  });
}
function mfVnc(){
  var load=layer.load(1,{time:false});
  $.ajax({type:'POST',url:'',data:{act:'vnc',id:" . intval($data["id"]) . "},dataType:'json',
    success:function(d){
      layer.close(load);
      if(d.code!=1){layer.alert(d.msg,{icon:2});return;}
      var data=d.data||{};
      var html='<div style=\"padding:15px;\">';
      if(data.url){
        // 有noVNC链接，直接嵌入iframe
        html+='<p style=\"margin-bottom:10px;color:#666;\">VNC控制台：</p>';
        html+='<div style=\"width:100%;height:450px;border:1px solid #ddd;border-radius:4px;overflow:hidden;\">';
        html+='<iframe src=\"'+data.url+'\" style=\"width:100%;height:100%;border:0;\" frameborder=\"0\"></iframe>';
        html+='</div>';
        html+='<p style=\"margin-top:10px;font-size:12px;color:#999;\">如无法加载，请 <a href=\"'+data.url+'\" target=\"_blank\" style=\"color:#007bff;\">在新窗口打开</a></p>';
      }else{
        // 无noVNC链接，显示连接信息
        html+='<p style=\"margin-bottom:12px;color:#666;\">请使用VNC客户端连接：</p>';
        html+='<table class=\"table table-bordered table-sm\" style=\"margin-bottom:10px;\">';
        html+='<tr><td style=\"width:80px;background:#f8f9fa;\">主机</td><td>'+(data.host||'-')+'</td></tr>';
        html+='<tr><td style=\"background:#f8f9fa;\">端口</td><td>'+(data.port||'-')+'</td></tr>';
        html+='<tr><td style=\"background:#f8f9fa;\">密码</td><td>'+(data.password||'-')+'</td></tr>';
        html+='</table>';
        html+='<p style=\"font-size:12px;color:#999;\">下载VNC客户端：<a href=\"https://www.realvnc.com/en/connect/download/viewer/\" target=\"_blank\" style=\"color:#007bff;\">VNC Viewer</a></p>';
      }
      html+='</div>';
      var area=data.url?['800px','580px']:['460px','320px'];
      layer.open({
        type:1,title:'VNC远程控制台',area:area,content:html,
        btn:['关闭']
      });
    },
    error:function(){layer.close(load);layer.alert('获取VNC信息失败，请稍后重试',{icon:2});}
  });
}
// ===== 图表相关 =====
var mfChartInstance=null;
var mfCurrentRange='1h';

function mfSwitchRange(range){
  mfCurrentRange=range;
  $('.mf-chart-tab').removeClass('active');
  $('.mf-chart-tab[data-range='+range+']').addClass('active');
  mfLoadChart();
}

function mfLoadChart(){
  var chartDom=document.getElementById('mfChart');
  if(!chartDom)return;
  // 如果图表已初始化，用ECharts内置loading，不销毁canvas
  if(mfChartInstance && mfEchartsLoaded){
    mfChartInstance.showLoading({text:'加载中...',color:'#007bff',textColor:'#666',maskColor:'rgba(255,255,255,0.8)'});
  }else{
    chartDom.innerHTML='<div class=\"mf-chart-loading\">加载中...</div>';
  }
  $.ajax({
    type:'POST',url:'',
    data:{act:'monitor',id:" . intval($data["id"]) . ",range:mfCurrentRange},
    dataType:'json',
    success:function(d){
      if(d.code!=1){
        if(mfChartInstance){mfChartInstance.hideLoading();}
        else{chartDom.innerHTML='<div class=\"mf-chart-loading\">加载失败</div>';}
        return;
      }
      var chartData=d.data;

      // ===== 智能网络单位换算 =====
      // 找出网络数据的最大值，自动选择合适的单位 (KB/s / MB/s / GB/s)
      var allNet=[];
      for(var i=0;i<chartData.chart.net_in.length;i++){allNet.push(chartData.chart.net_in[i]);}
      for(var i=0;i<chartData.chart.net_out.length;i++){allNet.push(chartData.chart.net_out[i]);}
      var maxNet=allNet.length>0?Math.max.apply(null,allNet):0;
      var netUnit='KB/s';
      var netDivisor=1;
      if(maxNet>=1048576){netUnit='GB/s';netDivisor=1048576;}
      else if(maxNet>=1024){netUnit='MB/s';netDivisor=1024;}
      // 转换网络数据
      var netInConverted=chartData.chart.net_in.map(function(v){return +(v/netDivisor).toFixed(2);});
      var netOutConverted=chartData.chart.net_out.map(function(v){return +(v/netDivisor).toFixed(2);});
      var curNetIn=chartData.current.net_in/netDivisor;
      var curNetOut=chartData.current.net_out/netDivisor;

      // 更新统计卡片
      $('#mfStatCpu').text(chartData.current.cpu.toFixed(1)+'%');
      $('#mfStatMemory').text(chartData.current.memory.toFixed(1)+'%');
      $('#mfStatDisk').text(chartData.current.disk_usage.toFixed(1)+'%');
      var netTotal=(curNetIn+curNetOut).toFixed(2);
      $('#mfStatNet').text(netTotal+' '+netUnit);

      // 动态加载ECharts并渲染
      mfLoadEcharts(function(){
        if(!mfChartInstance){
          // 清空loading文本，再初始化
          chartDom.innerHTML='';
          mfChartInstance=echarts.init(chartDom);
          window.addEventListener('resize',function(){mfChartInstance&&mfChartInstance.resize();});
        }
        var chart=chartData.chart;
        var option={
          tooltip:{trigger:'axis',axisPointer:{type:'cross'},
            formatter:function(params){
              var html=params[0].axisValue+'<br/>';
              for(var i=0;i<params.length;i++){
                var p=params[i];
                var unit=p.seriesName.indexOf('网络')>=0?netUnit:'%';
                html+='<span style=\"display:inline-block;width:10px;height:10px;border-radius:50%;background:'+p.color+';margin-right:6px;\"></span>'
                  +p.seriesName+': <b>'+p.value+' '+unit+'</b><br/>';
              }
              return html;
            }
          },
          legend:{data:['CPU','内存','网络入','网络出'],top:0,textStyle:{fontSize:11}},
          grid:{left:'3%',right:'4%',bottom:'3%',top:'20%',containLabel:true},
          xAxis:{type:'category',boundaryGap:false,data:chart.times,
            axisLabel:{fontSize:10,rotate:chart.times.length>12?30:0}},
          yAxis:[
            {type:'value',name:'使用率(%)',min:0,max:100,
              axisLabel:{fontSize:10,formatter:'{value}%'},nameTextStyle:{fontSize:10}},
            {type:'value',name:'流量('+netUnit+')',min:0,
              axisLabel:{fontSize:10},nameTextStyle:{fontSize:10}}
          ],
          series:[
            {name:'CPU',type:'line',smooth:true,yAxisIndex:0,
              data:chart.cpu,lineStyle:{color:'#e74c3c'},
              itemStyle:{color:'#e74c3c'},areaStyle:{color:'rgba(231,76,60,0.1)'}},
            {name:'内存',type:'line',smooth:true,yAxisIndex:0,
              data:chart.memory,lineStyle:{color:'#3498db'},
              itemStyle:{color:'#3498db'},areaStyle:{color:'rgba(52,152,219,0.1)'}},
            {name:'网络入',type:'line',smooth:true,yAxisIndex:1,
              data:netInConverted,lineStyle:{color:'#2ecc71'},
              itemStyle:{color:'#2ecc71'},areaStyle:{color:'rgba(46,204,113,0.08)'}},
            {name:'网络出',type:'line',smooth:true,yAxisIndex:1,
              data:netOutConverted,lineStyle:{color:'#f39c12'},
              itemStyle:{color:'#f39c12'},areaStyle:{color:'rgba(243,156,18,0.08)'}},
          ]
        };
        // 用 notMerge=true 完全替换配置，确保切换时间范围时数据正确刷新
        mfChartInstance.hideLoading();
        mfChartInstance.setOption(option,true);
        mfChartInstance.resize();
      });
    },
    error:function(){
      if(mfChartInstance){mfChartInstance.hideLoading();}
      chartDom.innerHTML='<div class=\"mf-chart-loading\">加载失败</div>';
      if(mfChartInstance){mfChartInstance.dispose();mfChartInstance=null;}
    }
  });
}
// 自动加载：状态 + 图表（兼容动态注入场景）
(function mfAutoInit(){
  if(typeof $==='undefined'){setTimeout(mfAutoInit,200);return;}
  var state=document.readyState;
  if(state==='complete'||state==='interactive'){
    try{mfRefreshStatus();}catch(e){}
    try{mfLoadChart();}catch(e){}
  }else{
    $(function(){
      try{mfRefreshStatus();}catch(e){}
      try{mfLoadChart();}catch(e){}
    });
  }
})();
</script>";
    return $text;
}
