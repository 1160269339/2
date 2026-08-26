<?php
/**
 * 魔方财务 控制面板 AJAX 处理
 * 由 app/index/controller/User.php 的 order() 方法 include
 * 可用变量: $act(动作) $data(订单) $a(产品cart) $b(服务器) $id(订单ID)
 */

use think\Db;

// 防御性初始化(正常由控制器注入，此处兜底避免未定义变量告警)
if (!isset($act))      $act = "";
if (!isset($id))       $id = 0;
if (!isset($data))     $data = [];
if (!isset($a))        $a = [];
if (!isset($b))        $b = [];

// ---------------- 获取监控数据 ----------------
if ($act == "monitor") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    $range = isset($_POST["range"]) ? trim($_POST["range"]) : "1h";
    $result = mofang_GetMonitor($b, $data, $range);
    exit(json_encode($result));
}

// ---------------- 获取VNC信息 ----------------
if ($act == "vnc") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    $result = mofang_GetVnc($b, $data);
    exit(json_encode($result));
}

// ---------------- 获取服务器状态 ----------------
if ($act == "status") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    $result = mofang_GetStatus($b, $data);
    exit(json_encode($result));
}

// ---------------- 开机 ----------------
if ($act == "on") {
    $array = mofang_client_action($b, $data, "on", "开机");
    exit(json_encode($array));
}

// ---------------- 关机 ----------------
if ($act == "off") {
    $array = mofang_client_action($b, $data, "off", "关机");
    exit(json_encode($array));
}

// ---------------- 重启 ----------------
if ($act == "reboot") {
    $array = mofang_client_action($b, $data, "reboot", "重启");
    exit(json_encode($array));
}

// ---------------- 硬重启 ----------------
if ($act == "hard_reboot") {
    $array = mofang_client_action($b, $data, "hard_reboot", "硬重启");
    exit(json_encode($array));
}

// ---------------- 重置密码 ----------------
if ($act == "reset") {
    if ($data["state"] == "3") {
        $array = ["code" => "-2", "msg" => "该产品已终止,禁止重置密码！"];
    } else {
        include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
        $password = mofang_genpassword();
        $function = $b["serverplugins"] . "_" . "ChangePassword";
        $data1 = $function($b, $data, $password);
        if ($data1["code"] == "1") {
            Db::name('order')->where([
                'id' => $id,
                "userid" => session("userid"),
            ])->update([
                'password' => $password,
            ]);
            $array = ["code" => "1", "msg" => "重置密码成功！新密码:" . $password];
        } else {
            $array = ["code" => "-1", "msg" => isset($data1["msg"]) ? $data1["msg"] : json_encode($data1)];
        }
    }
    exit(json_encode($array));
}

// ---------------- 刷新产品信息(同步IP/密码) ----------------
if ($act == "sync") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    $login = mofang_login($b);
    if ($login["code"] != 1) {
        exit(json_encode(["code" => "-1", "msg" => $login["msg"]]));
    }
    $base = mofang_url($b, "");
    $hostid = $data["data1"];
    if (!$hostid) {
        exit(json_encode(["code" => "-1", "msg" => "无上游产品ID"]));
    }
    $detail = mofang_http("GET", $base . "/v1/hosts/" . $hostid, [], $login["jwt"]);
    $dt = @json_decode($detail, true);
    if (mofang_status($dt) != 200) {
        exit(json_encode(["code" => "-1", "msg" => "获取产品信息失败"]));
    }
    $h = mofang_extract_host($dt);
    $up = [];
    if (isset($h["dedicatedip"]) && $h["dedicatedip"]) $up["user"] = $h["dedicatedip"];
    if (isset($h["password"]) && $h["password"]) $up["password"] = $h["password"];
    if (isset($h["port"])) $up["data2"] = $h["port"];
    if (isset($h["os"])) $up["data3"] = $h["os"];
    if (isset($h["username"])) $up["data4"] = $h["username"];
    if ($up) {
        Db::name('order')->where(['id' => $id, "userid" => session("userid")])->update($up);
    }
    exit(json_encode(["code" => "1", "msg" => "同步成功"]));
}

// ---------------- 重装系统 - 获取可用系统列表 ----------------
if ($act == "reinstall_page") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    if ($data["state"] == "3") {
        exit(json_encode(["code" => "-1", "msg" => "该产品已终止，禁止重装系统"]));
    }
    $hostid = $data["data1"];
    if (!$hostid) {
        exit(json_encode(["code" => "-1", "msg" => "无上游产品ID"]));
    }
    $login = mofang_login($b);
    if ($login["code"] != 1) {
        exit(json_encode(["code" => "-1", "msg" => $login["msg"]]));
    }
    $base = mofang_url($b, "");
    $res = mofang_http("GET", $base . "/v1/hosts/" . $hostid . "/module/reinstall", [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) != 200) {
        $msg = isset($d["msg"]) ? $d["msg"] : "获取系统列表失败";
        exit(json_encode(["code" => "-1", "msg" => $msg]));
    }
    // 提取系统列表(API返回os数组，每项含os_id/name/group_name)
    $osList = [];
    if (isset($d["data"]["os"]) && is_array($d["data"]["os"])) {
        $osList = $d["data"]["os"];
    } elseif (isset($d["os"]) && is_array($d["os"])) {
        $osList = $d["os"];
    }
    // 标准化字段名(兼容L前缀格式)
    $cleanList = [];
    foreach ($osList as $item) {
        if (!is_array($item)) continue;
        $osId = isset($item["os_id"]) ? $item["os_id"] : (isset($item["Los_id"]) ? $item["Los_id"] : "");
        $name = isset($item["name"]) ? $item["name"] : (isset($item["Lname"]) ? $item["Lname"] : "未知系统");
        $group = isset($item["group_name"]) ? $item["group_name"] : (isset($item["Lgroup_name"]) ? $item["Lgroup_name"] : "");
        if ($osId !== "") {
            $cleanList[] = [
                "os_id" => $osId,
                "name" => $name,
                "group_name" => $group,
            ];
        }
    }
    exit(json_encode(["code" => "1", "msg" => "成功", "data" => ["os" => $cleanList]]));
}

// ---------------- 重装系统 - 执行重装 ----------------
if ($act == "reinstall") {
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    if ($data["state"] == "3") {
        exit(json_encode(["code" => "-1", "msg" => "该产品已终止，禁止重装系统"]));
    }
    $hostid = $data["data1"];
    if (!$hostid) {
        exit(json_encode(["code" => "-1", "msg" => "无上游产品ID"]));
    }
    $osId = isset($_POST["os_id"]) ? trim($_POST["os_id"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";
    $port = isset($_POST["port"]) ? trim($_POST["port"]) : "";
    $partType = isset($_POST["part_type"]) ? trim($_POST["part_type"]) : "0";

    if (!$osId) {
        exit(json_encode(["code" => "-1", "msg" => "请选择操作系统"]));
    }
    if (strlen($password) < 6) {
        exit(json_encode(["code" => "-1", "msg" => "密码至少6位"]));
    }

    $login = mofang_login($b);
    if ($login["code"] != 1) {
        exit(json_encode(["code" => "-1", "msg" => $login["msg"]]));
    }
    $base = mofang_url($b, "");

    // 构建重装参数
    $params = [
        "os_id" => $osId,
        "password" => $password,
    ];
    if ($port) $params["port"] = $port;
    if ($partType !== "") $params["part_type"] = $partType;

    $res = mofang_http("PUT", $base . "/v1/hosts/" . $hostid . "/module/reinstall", $params, $login["jwt"]);
    $d = @json_decode($res, true);

    if (mofang_status($d) == 200) {
        // 重装成功，更新本地密码/端口
        $up = ["password" => $password];
        if ($port) $up["data2"] = $port;

        // 尝试获取选中的系统名称写入data3
        if (isset($d["data"]["os"]) && is_array($d["data"]["os"])) {
            // 无需处理
        }

        Db::name('order')->where([
            'id' => $id,
            "userid" => session("userid"),
        ])->update($up);

        exit(json_encode([
            "code" => "1",
            "msg" => "重装系统成功！新密码:" . $password . ($port ? "，端口:" . $port : ""),
            "data" => ["password" => $password],
        ]));
    }

    // 检查是否需要二次验证
    if (isset($d["data"]["second_verify"]) && $d["data"]["second_verify"]) {
        $sv = $d["data"]["second_verify"];
        $svType = isset($sv["type"]) ? $sv["type"] : "";
        $svAccount = isset($sv["account"]) ? $sv["account"] : "";
        exit(json_encode(["code" => "-1", "msg" => "需要二次验证(" . $svType . ":" . $svAccount . ")，请到魔方财务面板操作重装"]));
    }

    $msg = isset($d["msg"]) ? $d["msg"] : "重装系统失败";
    exit(json_encode(["code" => "-1", "msg" => $msg]));
}

/**
 * 执行开机/关机/重启等模块命令
 */
function mofang_client_action($b, $data, $cmd, $name)
{
    include_once PATH . "plugins/host/" . $b["serverplugins"] . "/" . $b["serverplugins"] . ".php";
    $hostid = $data["data1"];
    if (!$hostid) {
        return ["code" => "-1", "msg" => "无上游产品ID"];
    }
    if ($data["state"] == "3") {
        return ["code" => "-1", "msg" => "该产品已终止"];
    }
    $login = mofang_login($b);
    if ($login["code"] != 1) {
        return ["code" => "-1", "msg" => $login["msg"]];
    }
    $base = mofang_url($b, "");
    $res = mofang_http("PUT", $base . "/v1/hosts/" . $hostid . "/module/" . $cmd, [], $login["jwt"]);
    $d = @json_decode($res, true);
    if (mofang_status($d) == 200) {
        return ["code" => "1", "msg" => $name . "成功"];
    }
    $msg = isset($d["msg"]) ? $d["msg"] : "未知错误";
    return ["code" => "-1", "msg" => $name . "失败:" . $msg];
}

/**
 * 生成随机密码(字母+数字)
 */
function mofang_genpassword($len = 12)
{
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $len; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}
