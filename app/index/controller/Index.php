<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Index extends Controller {
	 private $web;
	public function _initialize() {
		$this->web=Db::name('web')->where('id',1)->find();
if($this->web["wh"]=="1"){
exit($this->web["whxx"]);
}
       if(session("userid")) {
$judge=Db::name("user")->where("id",session("userid"))->find();
if($judge){
if($judge["state"]=="0"){
session("userid",null);
$this->redirect('/login');
}
			$userstate="1";
}else{
session("userid",null);
			$userstate="0";
}
		} else {
			$userstate="0";
		}
$file=file_exists(PATH."/app/index/view/".$this->web["template"]."/set.php");
if($file){
if($this->web["templateset"]){
$tempset=json_decode($this->web["templateset"],true);
$templateset=array(""=>"");
for($i=0;$i<count($tempset);$i++){
$templateset=array_merge($templateset,array($tempset[$i]["name"]=>$tempset[$i]["value"]));
}
}else{
$templateset=array(""=>"");
}
}else{
$templateset=array(""=>"");
}
		$this->assign([
		            'webname'  => $this->web['name'],
		            'description'  => $this->web['description'],
		            'keywords'  => $this->web['keywords'],
		            'favicon'  => $this->web['favicon'],
		"userstate"=>$userstate,
"templateset"=>$templateset,
		        ]);
	}
public function null(){
return $this->fetch('/'.$this->web["template"].'/index/404',[		
		]);

}




	public function aff($upper) {
$data=Db::name('user')->where('aff',$upper)->find();
if($data){
cookie("upperid",$data["id"]);
	$this->redirect("/index");
}else{
	$this->redirect("/index");
}
}
	public function pwreset() {
	if(Request::instance()->isPost()) {
$act=input("act");
if($act=="validate"){
$user=input("user");
$captcha=input("captcha");
if($user=="" || $captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$data1=Db::name('user')->whereor([
			"user"=>$user,
			])->whereor([
			"mail"=>$user,
			])->find();
if(!$data1){
	$array["code"]="-1";
	$array["msg"]="未找到该账号!";
}else{
session("zhmmzh",$user);
$zhmmyzm=random("4");
session("zhmmyzm",$zhmmyzm);
if($this->web["email"]=="1"){
if($data1["mail"]){
$mailbox=$this->email($data1["mail"],"找回密码通知","你账号:".$data1["user"]."在时间:".date("Y-m-d H:i:s")."在本站找回密码,验证码:".$zhmmyzm."<br/><br/>");
$array["code"]="1";
$array["msg"]="已发送验证码到邮箱!";
}
}else{
$array["code"]="-1";
$array["msg"]="站点未开启邮箱通知!";
}
}
}
}
return $array;
}
// 短信找回：发送验证码（按手机号）
if($act=="smsvalidate"){
if($this->web["smskg"]!="1"){
	$array["code"]="-1";
	$array["msg"]="站点未开启短信功能!";
}else{
$phone=input("phone");
$captcha=input("captcha");
if($phone=="" || $captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else if(!preg_match('/^1[3-9]\\d{9}$/',$phone)){
	$array["code"]="-1";
	$array["msg"]="手机号格式错误!";
}else if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="图形验证码错误!";
}else{
$data1=Db::name('user')->where("phone",$phone)->find();
if(!$data1){
	$array["code"]="-1";
	$array["msg"]="该手机号未绑定账号!";
}else if(session("zhmmsms_time") && (time()-session("zhmmsms_time"))<60){
	$array["code"]="-1";
	$array["msg"]="发送过于频繁,请稍后再试!";
}else{
$smsyzm=random(6);
$res=self::sendSms($phone,$smsyzm);
if($res["code"]=="1"){
session("zhmmsms_phone",$phone);
session("zhmmsms_code",$smsyzm);
session("zhmmsms_time",time());
$array["code"]="1";
$array["msg"]="验证码已发送到手机!";
}else{
$array["code"]="-1";
$array["msg"]=$res["msg"];
}
}
}
}
return $array;
}
// 短信找回：重置密码
if($act=="smssubmit"){
if($this->web["smskg"]!="1"){
	$array["code"]="-1";
	$array["msg"]="站点未开启短信功能!";
}else{
$phone=input("phone");
$code=input("code");
$userpassword=input("userpassword");
$usernewpassword=input("usernewpassword");
if($phone=="" || $code=="" || $userpassword=="" || $usernewpassword==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else if($userpassword!=$usernewpassword){
	$array["code"]="-1";
	$array["msg"]="两次密码不一致!";
}else if(!session("zhmmsms_phone") || !session("zhmmsms_code")){
	$array["code"]="-1";
	$array["msg"]="请先获取短信验证码!";
}else if(session("zhmmsms_phone")!=$phone){
	$array["code"]="-1";
	$array["msg"]="手机号不匹配,请重新获取验证码!";
}else if(session("zhmmsms_time") && (time()-session("zhmmsms_time"))>300){
	$array["code"]="-1";
	$array["msg"]="验证码已过期,请重新获取!";
}else if(session("zhmmsms_code")!=$code){
	$array["code"]="-1";
	$array["msg"]="短信验证码错误!";
}else{
$data1=Db::name('user')->where("phone",$phone)->find();
if(!$data1){
	$array["code"]="-1";
	$array["msg"]="该手机号未绑定账号!";
}else{
Db::name('user')->where("phone",$phone)->update([
"password"=>password_hash($userpassword,PASSWORD_DEFAULT),
]);
session("zhmmsms_phone",null);
session("zhmmsms_code",null);
session("zhmmsms_time",null);
sendNotify($data1["phone"],$data1["mail"],"重置密码通知","你账号:".$data1["user"]."在时间:".date("Y-m-d H:i:s")."在本站通过短信重置密码成功!<br/><br/>","你的账号已通过短信重置密码成功");
$array["code"]="1";
$array["msg"]="已成功重置密码!";
}
}
}
return $array;
}
if($act=="submit"){
$user=input("user");
$code=input("code");
$userpassword=input("userpassword");
$usernewpassword=input("usernewpassword");
if($user=="" || $code=="" || $userpassword=="" || $usernewpassword==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
$data1=Db::name('user')->where([
			"user"=>$user,
			])->whereor([
			"mail"=>$user,
			])->find();
if(!$data1){
	$array["code"]="-1";
	$array["msg"]="未找到该账号!";
}else{
if($userpassword!=$usernewpassword){
	$array["code"]="-1";
	$array["msg"]="两次密码不一致!";
}else{
if(!session("zhmmyzm") || !session("zhmmzh")){
	$array["code"]="-1";
	$array["msg"]="请你重新获取邮箱验证码!";
}else{
if(session("zhmmzh")!=$user){
	$array["code"]="-1";
	$array["msg"]="账号不匹配,请重新获取验证码!";
}else{
if(session("zhmmyzm")==$code){
$data2=Db::name('user')->whereor([
			"user"=>$user,
			])->whereor([
			"mail"=>$user,
			])->update([
"password"=>password_hash($userpassword,PASSWORD_DEFAULT),
]);
sendNotify($data1["phone"],$data1["mail"],"重置密码通知","你账号:".$data1["user"]."在时间:".date("Y-m-d H:i:s")."在本站重置密码成功!<br/><br/>","你的账号已重置密码成功");
	$array["code"]="1";
	$array["msg"]="已成功重置密码!";
}else{
	$array["code"]="-1";
	$array["msg"]="填写的邮箱验证码错误!";
}
}
}
}
}
}
return $array;
}


}
		return $this->fetch('/'.$this->web["template"].'/index/pwreset',[
		"smssz"=>($this->web["smskg"]=="1")?"1":"0",
		]);
	}

	public function notify($id) {
$data1=Db::name('pays')->where('id',$id)->find();
if(!$data1){
exit("<title>出错啦!</title>没有此支付通道!");
}
@include PATH."plugins/pay/".$data1["plugins"]."/notify.php";
}

	public function index() {
$data=Db::name('announcement')->order('id desc')->paginate(5);
$countserver=Db::name('server')->count();
$countuser=Db::name('user')->count();
$countorder=Db::name('order')->count();
$sumpay=Db::name('pay')->where("state",1)->sum("money");
		return $this->fetch('/'.$this->web["template"].'/index/index',[
				"announcement"=>$data,
				"countserver"=>$countserver,
				"countuser"=>$countuser,
				"countorder"=>$countorder,
				"sumpay"=>$sumpay,
		]);
	}

	public function login() {
		if(Request::instance()->isPost()) {
	    $act=input("act");	
	    	
		if($act=="ptdl"){
			$user=input("user");
			$password=input("password");
            $captcha=input("captcha");
if($user=="" || $password=="" || $captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
			$data=Db::name('user')->whereor([
			"user"=>$user,
			])->whereor([
			"mail"=>$user,
			])->find();
if($data){
$data1=password_verify($password,$data["password"]);
			if($data1) {
if($data["state"]=="0"){
	$array["code"]="-1";
    $array["msg"]="账户已被冻结,禁止登录!";
}else{
				session("userid",$data["id"]);
                $db=Db::name('user')->where("id",session("userid"))->find();
sendNotify($db["phone"],$db["mail"],"登录成功通知","你账号:".$db["user"]."在时间:".date("Y-m-d H:i:s")."在本站登录成功,若不是本人所为,请修改密码!<br/><br/>","你的账号".$db["user"]."已登录成功，若非本人操作请修改密码");
				$array["code"]="1";
				$array["msg"]="登录成功!";
}
			} else {
				$array["code"]="-1";
				$array["msg"]="密码错误!";
			}
}else{
				$array["code"]="-1";
				$array["msg"]="账号或账号不存在!";
}

}
}
			return $array;
			}
			
			
			
				
		if($act=="yxvalidate"){
		if($this->web["yxdl"]!="1"){	
					$array["code"]="-1";
					$array["msg"]="未开启邮箱登录!";		
			}else{
         $captcha=input("captcha");
		 $mail=input("mail");
if(!filter_var($mail, FILTER_VALIDATE_EMAIL)){
	$array["code"]="-1";
	$array["msg"]="邮箱格式错误!";
}else{		 
if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$data11=Db::name('user')->where("mail",$mail)->find();
if(!$data11){
	$array["code"]="-1";
	$array["msg"]="邮箱不存在!";
}else{
if($this->web["email"]=="1"){
if($mail){
$yxyzm=random(6);
session("yxyzmyx1",$mail);
session("yxyzm1",$yxyzm);
$mailbox=$this->email($mail,"邮箱登录验证码通知","你邮箱:".$mail."在时间:".date("Y-m-d H:i:s")."在本站登录账户,验证码:".$yxyzm."<br/><br/>");
$array["code"]="1";
$array["msg"]="获取邮箱验证码成功!";
}
}else{
$array["code"]="-1";
$array["msg"]="未开启邮箱通知!";
}
}
}
}
}		
	return $array;	
		
		}	
		
		
		if($act=="yxdl"){
		if($this->web["yxdl"]!="1"){	
					$array["code"]="-1";
					$array["msg"]="未开启邮箱登录!";		
			}else{
	$mail=input("mail");
			$code=input("code");
if($mail=="" || $code==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!filter_var($mail, FILTER_VALIDATE_EMAIL)){
	$array["code"]="-1";
	$array["msg"]="邮箱格式错误!";
}else{
if(!session("yxyzmyx1") || !session("yxyzm1")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(session("yxyzmyx1")!=$mail){
	$array["code"]="-1";
	$array["msg"]="邮箱不匹配,请重新获取邮箱验证码!";

}else{
if(session("yxyzm1")!=$code){
	$array["code"]="-1";
	$array["msg"]="邮箱验证码错误!";
}else{
			$data=Db::name('user')->where([
			"mail"=>$mail,
			])->find();
if($data){
if($data["state"]=="0"){
	$array["code"]="-1";
    $array["msg"]="账户已被冻结,禁止登录!";
}else{
session("yxyzmyx1",null);
session("yxyzm1",null);
				session("userid",$data["id"]);
                $db=Db::name('user')->where("id",session("userid"))->find();
sendNotify($db["phone"],$db["mail"],"邮箱登录成功通知","你邮箱:".$db["mail"]."在时间:".date("Y-m-d H:i:s")."在本站登录成功,若不是本人所为,请修改密码!<br/><br/>","你的账号已通过邮箱登录成功，若非本人操作请修改密码");
				$array["code"]="1";
				$array["msg"]="登录成功!";
}
			
}else{
				$array["code"]="-1";
				$array["msg"]="邮箱不存在!";
}

}
}
}
}
}
}
			return $array;

}			
			
			
		
		// 短信获取验证码
		if($act=="smsvalidate"){
			if($this->web["smskg"]!="1" || $this->web["smsdl"]!="1"){
				$array["code"]="-1";
				$array["msg"]="未开启短信登录!";
			}else{
				$phone=input("phone");
				$captcha=input("captcha");
				if($phone=="" || $captcha==""){
					$array["code"]="-1";
					$array["msg"]="必填参数不可为空!";
				}else if(!preg_match('/^1[3-9]\\d{9}$/',$phone)){
					$array["code"]="-1";
					$array["msg"]="手机号格式错误!";
				}else if(!captcha_check($captcha)){
					$array["code"]="-1";
					$array["msg"]="图形验证码错误!";
				}else{
					$data11=Db::name('user')->where("phone",$phone)->find();
					if(!$data11){
						$array["code"]="-1";
						$array["msg"]="该手机号未绑定账号!";
					}else if(session("smsdl_time") && (time()-session("smsdl_time"))<60){
						$array["code"]="-1";
						$array["msg"]="发送过于频繁,请稍后再试!";
					}else{
						$smsyzm=random(6);
						$res=self::sendSms($phone,$smsyzm);
						if($res["code"]=="1"){
							session("smsdl_phone",$phone);
							session("smsdl_code",$smsyzm);
							session("smsdl_time",time());
							$array["code"]="1";
							$array["msg"]="验证码已发送!";
						}else{
							$array["code"]="-1";
							$array["msg"]=$res["msg"];
						}
					}
				}
			}
			return $array;
		}

		// 短信验证码登录
		if($act=="smsdl"){
			if($this->web["smskg"]!="1" || $this->web["smsdl"]!="1"){
				$array["code"]="-1";
				$array["msg"]="未开启短信登录!";
			}else{
				$phone=input("phone");
				$code=input("code");
				if($phone=="" || $code==""){
					$array["code"]="-1";
					$array["msg"]="必填参数不可为空!";
				}else if(!session("smsdl_phone") || !session("smsdl_code")){
					$array["code"]="-1";
					$array["msg"]="请先获取短信验证码!";
				}else if(session("smsdl_phone")!=$phone){
					$array["code"]="-1";
					$array["msg"]="手机号不匹配,请重新获取验证码!";
				}else if(session("smsdl_time") && (time()-session("smsdl_time"))>300){
					$array["code"]="-1";
					$array["msg"]="验证码已过期,请重新获取!";
				}else if(session("smsdl_code")!=$code){
					$array["code"]="-1";
					$array["msg"]="短信验证码错误!";
				}else{
					$data=Db::name('user')->where("phone",$phone)->find();
					if(!$data){
						$array["code"]="-1";
						$array["msg"]="该手机号未绑定账号!";
					}else if($data["state"]=="0"){
						$array["code"]="-1";
						$array["msg"]="账户已被冻结,禁止登录!";
					}else{
						session("smsdl_phone",null);
						session("smsdl_code",null);
						session("smsdl_time",null);
						session("userid",$data["id"]);
						$array["code"]="1";
						$array["msg"]="登录成功!";
					}
				}
			}
			return $array;
		}
		}
		
		return $this->fetch('/'.$this->web["template"].'/index/login',[
"yxdl"=>$this->web["yxdl"],
"smsdl"=>($this->web["smskg"]=="1" && $this->web["smsdl"]=="1")?"1":"0",
		]);
	}



	public function register() {
	
		if(Request::instance()->isPost()) {
		$act=input("act");
		
		if($act=="ptzc"){
			$name=input("name");
			$user=input("user");
			$password=input("password");
			$repassword=input("repassword");
            $qq=input("qq");
            $captcha=input("captcha");
if($name=="" || $user=="" || $password=="" || $repassword=="" || $qq=="" || $captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
if($password!=$repassword){
	$array["code"]="-1";
	$array["msg"]="两次输入的密码不一样!";
}else{
// 注册短信验证
$phone="";
$smsOn=($this->web["smskg"]=="1" && $this->web["zcsmyz"]=="1");
$smsPass=true;
if($smsOn){
	$phone=input("phone");
	$smscode=input("smscode");
	if($phone=="" || $smscode==""){
		$array["code"]="-1";
		$array["msg"]="请填写手机号和短信验证码!";
		$smsPass=false;
	}else if(!preg_match('/^1[3-9]\\d{9}$/',$phone)){
		$array["code"]="-1";
		$array["msg"]="手机号格式错误!";
		$smsPass=false;
	}else if(!session("zcsms_phone") || !session("zcsms_code")){
		$array["code"]="-1";
		$array["msg"]="请先获取短信验证码!";
		$smsPass=false;
	}else if(session("zcsms_phone")!=$phone){
		$array["code"]="-1";
		$array["msg"]="手机号不匹配,请重新获取验证码!";
		$smsPass=false;
	}else if(session("zcsms_time") && (time()-session("zcsms_time"))>300){
		$array["code"]="-1";
		$array["msg"]="验证码已过期,请重新获取!";
		$smsPass=false;
	}else if(session("zcsms_code")!=$smscode){
		$array["code"]="-1";
		$array["msg"]="短信验证码错误!";
		$smsPass=false;
	}else if(Db::name('user')->where("phone",$phone)->find()){
		$array["code"]="-1";
		$array["msg"]="该手机号已被注册!";
		$smsPass=false;
	}
}
if($smsPass){
			$data=Db::name('user')->where("user",$user)->find();
			if($data) {
				$array["code"]="-1";
				$array["msg"]="账号已存在!";
			} else {
$upperid=cookie("upperid");
if(!$upperid){
$upperid="";
}else{
if(!Db::name('user')->where("id",$upperid)->find()){
$upperid="";
}
}
				$data1=Db::name('user')->insertGetId([
				"name"=>$name,
				"user"=>$user,
				"password"=>password_hash($password,PASSWORD_DEFAULT),
				"mail"=>"",
				"phone"=>$phone,
				"time"=>time(),
                "qq"=>$qq,
                "address"=>"",
                "aff"=>"",
                "upperid"=>$upperid,
                "state"=>"1",
				]);
				if($data1) {
					session("zcsms_phone",null);
					session("zcsms_code",null);
					session("zcsms_time",null);
					$array["code"]="1";
					$array["msg"]="注册成功!";
				} else {
					$array["code"]="-1";
					$array["msg"]="注册失败!";				
}
			}
} // end if($smsPass)


}
}
}
			return $array;
			}
			
			
			
			
			
			if($act=="yxzc"){
	       if($this->web["zcyxyz"]!="1"){
		             $array["code"]="-1";
					$array["msg"]="未开启邮箱注册!";      
			}else{						
			$name=input("name");
			$user=input("user");
			$password=input("password");
			$repassword=input("repassword");
			$mail=input("mail");
            $qq=input("qq");
            $code=input("code");
if($name=="" || $user=="" || $password=="" || $repassword=="" || $mail=="" || $qq=="" || $code==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!session("yxyzmyx") || !session("yxyzm")){
	$array["code"]="-1";
	$array["msg"]="请获取邮箱验证码!";
}else{
if($mail!=session("yxyzmyx")){
	$array["code"]="-1";
	$array["msg"]="邮箱不匹配!";
}else{
if($code!=session("yxyzm")){
	$array["code"]="-1";
	$array["msg"]="邮箱验证码错误!";
}else{
if($password!=$repassword){
	$array["code"]="-1";
	$array["msg"]="两次输入的密码不一样!";
}else{
$data3=filter_var($mail, FILTER_VALIDATE_EMAIL);
if($data3){
			$data=Db::name('user')->where("user",$user)->find();
			if($data) {
				$array["code"]="-1";
				$array["msg"]="账号已存在!";
			} else {
$data2=Db::name('user')->where("mail",$mail)->find();
if($data2){
				$array["code"]="-1";
				$array["msg"]="邮箱已存在!";
}else{
$upperid=cookie("upperid");
if(!$upperid){
$upperid="";
}else{
if(!Db::name('user')->where("id",$upperid)->find()){
$upperid="";
}
}
				$data1=Db::name('user')->insertGetId([
				"name"=>$name,
				"user"=>$user,
				"password"=>password_hash($password,PASSWORD_DEFAULT),
				"mail"=>$mail,
				"time"=>time(),
                "qq"=>$qq,
                "address"=>"",
                "aff"=>"",
                "upperid"=>$upperid,
                "state"=>"1",
				]);
				if($data1) {
				session("yxyzmyx",null);
				session("yxyzm",null);
					$array["code"]="1";
					$array["msg"]="注册成功!";
sendNotify("", $mail,"注册成功通知","时间:".date("Y-m-d H:i:s")."<br/>恭喜你在本站注册成功!<br/>ID:".$data1."<br/>账号:".$name."<br/><br/>","恭喜你注册成功，账号:".$name."，ID:".$data1);
				} else {
					$array["code"]="-1";
					$array["msg"]="注册失败!";
				}
}
			}
}else{
					$array["code"]="-1";
					$array["msg"]="邮箱格式错误!";
}


}
}
}











}
}
			
}			
			return $array;
			
			}
			
			
			
			
			
			
		
		if($act=="yxvalidate"){
		if($this->web["zcyxyz"]!="1"){	
					$array["code"]="-1";
					$array["msg"]="未开启邮箱注册!";		
			}else{
         $captcha=input("captcha");
		 $mail=input("mail");
if(!filter_var($mail, FILTER_VALIDATE_EMAIL)){
	$array["code"]="-1";
	$array["msg"]="邮箱格式错误!";
}else{		 
if(!captcha_check($captcha)){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
if($this->web["email"]=="1"){
if($mail){
$yxyzm=random(6);
session("yxyzmyx",$mail);
session("yxyzm",$yxyzm);
$mailbox=$this->email($mail,"注册验证码通知","你邮箱:".$mail."在时间:".date("Y-m-d H:i:s")."在本站申请账户,验证码:".$yxyzm."<br/><br/>");
$array["code"]="1";
$array["msg"]="获取邮箱验证码成功!";
}
}else{
$array["code"]="-1";
$array["msg"]="未开启邮箱通知!";
}
}
}		
	return $array;	
		}
		}		
		
		
		
		// 注册短信获取验证码
		if($act=="smsvalidate"){
			if($this->web["smskg"]!="1" || $this->web["zcsmyz"]!="1"){
				$array["code"]="-1";
				$array["msg"]="未开启注册短信验证!";
			}else{
				$phone=input("phone");
				$captcha=input("captcha");
				if($phone=="" || $captcha==""){
					$array["code"]="-1";
					$array["msg"]="必填参数不可为空!";
				}else if(!preg_match('/^1[3-9]\\d{9}$/',$phone)){
					$array["code"]="-1";
					$array["msg"]="手机号格式错误!";
				}else if(!captcha_check($captcha)){
					$array["code"]="-1";
					$array["msg"]="图形验证码错误!";
				}else if(Db::name('user')->where("phone",$phone)->find()){
					$array["code"]="-1";
					$array["msg"]="该手机号已被注册!";
				}else if(session("zcsms_time") && (time()-session("zcsms_time"))<60){
					$array["code"]="-1";
					$array["msg"]="发送过于频繁,请稍后再试!";
				}else{
					$smsyzm=random(6);
					$res=self::sendSms($phone,$smsyzm);
					if($res["code"]=="1"){
						session("zcsms_phone",$phone);
						session("zcsms_code",$smsyzm);
						session("zcsms_time",time());
						$array["code"]="1";
						$array["msg"]="验证码已发送!";
					}else{
						$array["code"]="-1";
						$array["msg"]=$res["msg"];
					}
				}
			}
			return $array;
		}
}
		$zcyxyz=$this->web["zcyxyz"];
		return $this->fetch('/'.$this->web["template"].'/index/register',[
		"zcyxyz"=>$zcyxyz,
		"zcsmyz"=>($this->web["smskg"]=="1" && $this->web["zcsmyz"]=="1")?"1":"0",
		]);
	}




	public function cart($id=null) {
		$data=Db::name('product')->where("hide","0")->order("sort","DESC")->select();
		if($id) {
			$data3=Db::name('product')->where("id",$id)->find();
if(!$data3){
$this->redirect('/cart');
}
		$data1=Db::name('cart')->where([
"product"=>$id,
"hide"=>"0",
])->order("sort","DESC")->select();
$productid=$id;
		} else {
			$data2=Db::name('product')->where("hide","0")->order("sort","DESC")->find();
					if($data2){
			$data1=Db::name('cart')->where([
"product"=>$data2['id'],
"hide"=>"0",
])->order("sort","DESC")->select();
			$data3=Db::name('product')->where("id",$data2['id'])->find();
$productid=$data2["id"];
		}else{
$data="";
$data1=Db::name('cart')->where([
"product"=>"",
"hide"=>"0",
])->order("sort","DESC")->select();
$data3="";
$productid="";
}
		}
		return $this->fetch('/'.$this->web["template"].'/index/cart',[
		"product"=>$data,
		"cart"=>$data1,
		"class"=>$data3,
"productid"=>$productid,
		]);
	}

	public function product($id=null) {
		if($id) {
$data=Db::name('cart')->where("id",$id)->find();
if($data){
//获取指定id产品信息
if(Request::instance()->isPost()) {
if(session("userid")){
// 强制实名才能下单
if($this->web["smrzkg"]=="1" && $this->web["smrzqz"]=="1"){
$smuser=Db::name('user')->where('id',session("userid"))->find();
if(!$smuser || $smuser["smrz"]!="1"){
return ["code"=>"-1","msg"=>"请先完成实名认证后再购买产品!"];
}
}
$data1=Db::name('order')->where([
"cartid"=>$id,
"userid"=>session("userid"),
])->find();
if($data["buy"]=="1"){
	$array["code"]="-1";
   $array["msg"]="该产品已设置禁止购买!";
}else{
if($data["inventory"] < 1){
	$array["code"]="-1";
   $array["msg"]="该产品已售完!";
}else{
if($data["limits"]=="1" && $data1){
$array["code"]="-1";
$array["msg"]="该产品只允许订购一次!";
}else{
$data2["user"]=input("user");
$data2["password"]=input("password");
$data2["time"]=input("time");
if($data2["user"]=="" || $data2["password"]=="" || $data2["time"]==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!is_numeric($data2["time"])){
	$array["code"]="-1";
	$array["msg"]="购买时间只能填写数字!";
}else{
if(floor($data2["time"])!=$data2["time"]){
	$array["code"]="-1";
	$array["msg"]="购买时间只能填写整数!";
}else{
if($data2["time"]<1){
	$array["code"]="-1";
	$array["msg"]="购买时间不能小于1!";
}else{
if($data["money"]=="0" && $data2["time"]!="1"){
	$array["code"]="-1";
	$array["msg"]="免费产品的购买时间只能填写1!";
}else{
if($data["cycle"]=="unrestricted" && $data2["time"]!="1"){
	$array["code"]="-1";
	$array["msg"]="永久产品的购买时间只能填写1!";
}else{
$db=Db::name('user')->where('id',session("userid"))->find();
$money=$data["money"]*$data2["time"];
if($db["money"]>=$money || $data["firstmo"]=="1"){
if($data["serverid"]==""){
	$array["code"]="-1";
	$array["msg"]="该产品没有配置服务器!";
}else{
$data3=Db::name('server')->where("id",$data["serverid"])->find();
if($data3["serverplugins"]==""){
	$array["code"]="-1";
	$array["msg"]="该产品的服务器没有配置服务器插件!";
}else{
include PATH."plugins/host/".$data3["serverplugins"]."/".$data3["serverplugins"].".php";
if($data["cycle"]=="month"){
$times="2592000"*$data2["time"];
}
if($data["cycle"]=="season"){
$times="7879680"*$data2["time"];
}
if($data["cycle"]=="year"){
$times="31536000"*$data2["time"];
}
if($data["cycle"]=="day"){
$times="86400"*$data2["time"];
}
if($data["cycle"]=="unrestricted"){
$times="3153600000"*$data2["time"];
}
$function=$data3["serverplugins"]."_"."CreateAccount";
if(function_exists($function)){
$data4=@$function($data3,$data2,$data,$times);
}
if ($data4["code"]=="1"){
if($data["firstmo"]!="1"){
$money1=round($db["money"]-$money,2);
//更新余额
Db::name('user')->where('id',session("userid"))->update(['money' =>$money1]);
}else{
$money="0";
}
//更新库存
Db::name('cart')->where('id',$data["id"])->update(['inventory' =>$data["inventory"]-1]);

//消费记录
$data6=Db::name('transaction')->insertGetId([
"userid"=>session("userid"),
"content"=>"购买产品,ID:".$data4["id"].",时长:".$data2["time"].",消费:".$money,
"time"=>time(),
]);

//aff收益
if($db["upperid"]){
if($money!="0"){
$upper=round($money*$this->web["affdiscount"],2);
$upperuser=Db::name('user')->where('id',$db["upperid"])->find();
$uppermoney=Db::name('user')->where('id',$db["upperid"])->update([
'affmoney' =>round($upperuser["affmoney"]+$upper,2),
]);
$data5=Db::name('affsymoney')->insertGetId([
"information"=>"下级ID:".session("userid")."购买产品",
"money"=>$upper,
"userid"=>$db["upperid"],
"time"=>time(),
]);
}
}


$db=Db::name('user')->where("id",session("userid"))->find();
sendNotify($db["phone"],$db["mail"],"购买产品通知","你账号:".$db["user"]."在时间:".date("Y-m-d H:i:s")."在本站购买产品成功,id:".$data4["id"]."请登录产品管理查看!<br/><br/>","购买产品成功，ID:".$data4["id"]."，请登录管理面板查看");
}
return $data4;
}
}
}else{
	$array["code"]="-1";
	$array["msg"]="账户余额不足!";
}


}
}
}
}
}
}
}
}
}
}else{
	$array["code"]="-1";
	$array["msg"]="请先登录!";	
}
return $array;
}	
}else{
		$this->redirect('/cart');
}
		} else {
			$this->redirect('/cart');
		}
		return $this->fetch('/'.$this->web["template"].'/index/product',[
		"cart"=>$data,
		]);
	}


	public function announcement($id=null) {
if($id){
$data=Db::name('announcement')->where("id",$id)->find();
if($data){
return $this->fetch('/'.$this->web["template"].'/index/announcements',[
		"announcement"=>$data,
		]);
}else{
		$this->redirect('/announcement');
}
}else{
$data=Db::name('announcement')->order('id desc')->paginate(10);
		return $this->fetch('/'.$this->web["template"].'/index/announcement',[
		"announcement"=>$data,
		]);

}
}

public function cron(){
$data=Db::name("order")->select();
$time=time();
for($i=0;$i<count($data);$i++)  
   {
if($data[$i]["ztime"] < $time){
$order=$data[$i];
$data2=Db::name('cart')->where("id",$data[$i]["cartid"])->find();
$data3=Db::name('server')->where("id",$data2["serverid"])->find();
include_once PATH."plugins/host/".$data3["serverplugins"]."/".$data3["serverplugins"].".php";
$cronzz=$this->web["cronzz"]*86400;
if($data[$i]["ztime"]+$cronzz>$time){
//过期三天内暂停
if($order["state"]!="2"){
$data5=Db::name("order")->where("id",$order["id"])->update([
"state"=>"2",
]);
$db=Db::name('user')->where("id",$order["userid"])->find();
sendNotify($db["phone"],$db["mail"],"产品暂停通知","时间:".date("Y-m-d H:i:s")."<br>产品id:".$data[$i]["id"]."<br>产品已到期,自动暂停!请登录产品控制台续费!<br/><br/>","你的产品(ID:".$data[$i]["id"].")已到期暂停，请及时续费");
$function=$data3["serverplugins"]."_"."SuspendAccount";
if(function_exists($function)){
$data4=@$function($data3,$order,$data2);
}
}
}else{
//过期三天后处理
$cronzz=$this->web["cronzz"]*86400;
$cronsc=$this->web["cronsc"]*86400;
if($data[$i]["ztime"]+$cronzz+$cronsc>$time){
//过期六天内终止
if($order["state"]!="3"){
$data5=Db::name("order")->where("id",$order["id"])->update([
"state"=>"3",
]);
//加上库存
$data6=Db::name("cart")->where("id",$order["cartid"])->update([
"inventory"=>$data2["inventory"]+1,
]);
$db=Db::name('user')->where("id",$order["userid"])->find();
sendNotify($db["phone"],$db["mail"],"产品终止通知","时间:".date("Y-m-d H:i:s")."<br>产品id:".$data[$i]["id"]."<br>产品到期,已终止!!<br/><br/>","你的产品(ID:".$data[$i]["id"].")已到期终止");
$function=$data3["serverplugins"]."_"."TerminateAccount";
if(function_exists($function)){
$data4=@$function($data3,$order,$data2);
}
}
}else{
//过期六天后处理
if($order["state"]!="3"){
//加上库存
$data6=Db::name("cart")->where("id",$order["cartid"])->update([
"inventory"=>$data2["inventory"]+1,
]);
$db=Db::name('user')->where("id",$order["userid"])->find();
sendNotify($db["phone"],$db["mail"],"产品终止通知","时间:".date("Y-m-d H:i:s")."<br>产品id:".$data[$i]["id"]."<br>产品到期,已终止!<br/><br/>","你的产品(ID:".$data[$i]["id"].")已到期终止");
$function=$data3["serverplugins"]."_"."TerminateAccount";
if(function_exists($function)){
$data4=@$function($data3,$order,$data2);
}
}
$data5=Db::name("order")->where("id",$order["id"])->delete();
}

}

}

}


//删除5分钟未支付的订单
$data1=Db::name("pay")->select();
for($i=0;$i<count($data1);$i++)  
   {
if($data1[$i]["state"]=="2"){
$paycron=$this->web["paycron"]*60;
if($data1[$i]["time"]+$paycron<time()){
Db::name('pay')->where('id',$data1[$i]["id"])->delete();
}
}
}



//工单超过3天未回复自动关闭
$data6=Db::name("ticket")->select();
for($i=0;$i<count($data6);$i++)  
   {
if($data6[$i]["state"]!="4"){
$json=json_decode($data6[$i]["content"],true);
$content=end($json);
$tickcron=$this->web["tickcron"]*86400;
if($content["time"]+$tickcron<time()){
$data7=Db::name("ticket")->where("id",$data6[$i]["id"])->update([
"state"=>"4",
]);
$db=Db::name('user')->where("id",$data6[$i]["userid"])->find();
sendNotify($db["phone"],$db["mail"],"工单关闭通知","时间:".date("Y-m-d H:i:s")."<br>工单id:".$data6[$i]["id"]."<br>超时未回复,自动关闭!<br/><br/>","你的工单(ID:".$data6[$i]["id"].")超时未回复已自动关闭");
}
}
}





return "任务执行完毕!";
}



//发送邮箱
	

    // 你的email方法
    public static function email($email,$name,$body)
    {
        $web = Db::name('web')->where('id',1)->find();
        $array = [];
        $mail = new PHPMailer(true);

        // 原有配置代码（保留不变）
        $mail->IsSMTP();
        $mail->CharSet = $web["emailchar"];
        $mail->SMTPAuth = $web["emailauth"];
        if($web["emailsecure"]){
            $mail->SMTPSecure = $web["emailsecure"];
        }
        $mail->Port = $web["emailport"];
        $mail->Host = $web["emailhost"];
        $mail->Username = $web["emailname"];
        $mail->Password = $web["emailpass"];
        $mail->From = $web["emailname"];
        $to = $email;
        $mail->AddAddress($to);
        $mail->Subject = $name;
        $mail->Body = "<html><head><title>".$name."</title></head>".$body."</html>";
        $mail->WordWrap = 80;
        $mail->IsHTML(true);

        try {
            $mail->Send();
            $array["code"] = "1";
            $array["msg"] = "邮箱发送成功！";
        } catch (Exception $e) {
            $array["code"] = "-1";
            $array["msg"] = "邮箱发送失败：".$e->errorMessage();
        }

        return $array;
    }

    // 发送短信验证码（复用后台短信配置）
    public static function sendSms($phone, $code)
    {
        $web = Db::name('web')->where('id',1)->find();
        if ($web["smskg"] != "1") {
            return ["code"=>"-1","msg"=>"站点未开启短信功能"];
        }
        $sms = new \sms\Sms([
            'channel'  => $web["smschannel"],
            'id'       => $web["smsid"],
            'key'      => $web["smskey"],
            'sign'     => $web["smssign"],
            'template' => $web["smstemplate"],
            'appid'    => $web["smsappid"],
        ]);
        $res = $sms->send($phone, $code);
        return ["code"=>(string)$res["code"], "msg"=>$res["msg"]];
    }



}