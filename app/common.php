<?php
function random($length = 8,$chars = null){
  if(empty($chars)){
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  }
  $count = strlen($chars) - 1;
  $code = '';
  while( strlen($code) < $length){
    $code .= substr($chars,rand(0,$count),1);
  }
  return $code;
}

function userrandom(){
$rand="a".rand(100000,999999);
return $rand;
}

//获取目录下的子目录
function my_dir($dir) {
    $files = array();
    if(@$handle = opendir($dir)) { //注意这里要加一个@，不然会有warning错误提示：）
        while(($file = readdir($handle)) !== false) {
            if($file != ".." && $file != ".") { //排除根目录；
               $files[] = $file; 
            }
        }
        closedir($handle);
        return $files;
    }
}

function generateRand($m, $n)
{
    if ($m > $n) {
        $numMax = $m;
        $numMin = $n;
    } else {
        $numMax = $n;
        $numMin = $m;
    }
    /**
     * 生成$numMin和$numMax之间的随机浮点数，保留2位小数
     */
    $rand = $numMin + mt_rand() / mt_getrandmax() * ($numMax - $numMin);
    return floatval(number_format($rand,2));
}

//判断是否是HTTPS
function isHTTPS()
{
    if (defined('HTTPS') && HTTPS) return true;
    if (!isset($_SERVER)) return FALSE;
    if (!isset($_SERVER['HTTPS'])) return FALSE;
    if ($_SERVER['HTTPS'] === 1) {  //Apache
        return TRUE;
    } elseif ($_SERVER['HTTPS'] === 'on') { //IIS
        return TRUE;
    } elseif ($_SERVER['SERVER_PORT'] == 443) { //其他
        return TRUE;
    }
    return FALSE;
}


function judge($a,$b){
    if(in_array($b,$a)){
     return "1";
    }else{
return "2";
}
}

function getLen($num)
{
         $arr = explode('.',$num);
     $str=array_pop($arr);
if($str==$num){
$len="0";
}else{
     $len=strlen($str);
}
         return $len;
}

/**
 * 统一消息提醒函数
 * 逻辑：根据后台「消息提醒」设置的渠道发送通知
 * - auto（自动）：优先短信，短信未开启或不成功则降级用邮箱，邮箱也未开启则不发
 * - sms（仅短信）：短信开启就发，不开启就不发
 * - email（仅邮箱）：邮箱开启就发，不开启就不发
 *
 * @param string $phone       收件人手机号（为空则跳过短信）
 * @param string $email       收件人邮箱（为空则跳过邮箱）
 * @param string $title       邮件标题 / 短信摘要
 * @param string $body        邮件HTML正文
 * @param string $smsContent  短信文本内容（为空时自动从body去标签生成）
 * @return array ["code"=>"1/-1", "msg"=>"..."]
 */
function sendNotify($phone, $email, $title, $body, $smsContent = '')
{
    $web = \think\Db::name('web')->where('id', 1)->find();
    $channel = isset($web['notify_channel']) ? $web['notify_channel'] : 'auto';

    // 短信内容为空时从邮件正文提取纯文本
    if (!$smsContent) {
        $smsContent = trim(strip_tags($body));
    }

    $smsSent = false; // 标记短信是否已成功发送

    // ---- 短信渠道 ----
    if (($channel == 'auto' || $channel == 'sms') && $web['smskg'] == '1' && $phone) {
        $tzTmpl = isset($web['smstztemplate']) && $web['smstztemplate'] ? $web['smstztemplate'] : $web['smstemplate'];
        try {
            $sms = new \sms\Sms([
                'channel'  => $web['smschannel'],
                'id'       => $web['smsid'],
                'key'      => $web['smskey'],
                'sign'     => $web['smssign'],
                'template' => $tzTmpl,
                'appid'    => $web['smsappid'],
            ]);
            $res = $sms->send($phone, $smsContent);
            if (isset($res['code']) && $res['code'] == 1) {
                $smsSent = true;
            }
        } catch (\Exception $e) {
            $smsSent = false;
        }
    }

    // 短信发送成功，直接返回
    if ($smsSent) {
        return ['code' => '1', 'msg' => '短信提醒发送成功'];
    }

    // 仅短信模式，不降级到邮箱
    if ($channel == 'sms') {
        return ['code' => '-1', 'msg' => '短信未发送成功'];
    }

    // ---- 邮箱渠道（auto 降级 或 email 直发）----
    if (($channel == 'auto' || $channel == 'email') && $web['email'] == '1' && $email) {
        return sendEmail($email, $title, $body);
    }

    return ['code' => '-1', 'msg' => '未开启任何通知功能'];
}

/**
 * 发送邮件（从控制器中提取的公共方法）
 */
function sendEmail($email, $name, $body)
{
    $web = \think\Db::name('web')->where('id', 1)->find();
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    $mail->IsSMTP();
    $mail->CharSet = $web['emailchar'];
    $mail->SMTPAuth = $web['emailauth'];
    if ($web['emailsecure']) {
        $mail->SMTPSecure = $web['emailsecure'];
    }
    $mail->Port = $web['emailport'];
    $mail->Host = $web['emailhost'];
    $mail->Username = $web['emailname'];
    $mail->Password = $web['emailpass'];
    $mail->From = $web['emailname'];
    $mail->AddAddress($email);
    $mail->Subject = $name;
    $mail->Body = '<html><head><title>' . $name . '</title></head>' . $body . '</html>';
    $mail->WordWrap = 80;
    $mail->IsHTML(true);

    try {
        $mail->Send();
        return ['code' => '1', 'msg' => '邮箱发送成功！'];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['code' => '-1', 'msg' => '邮箱发送失败：' . $e->errorMessage()];
    }
}