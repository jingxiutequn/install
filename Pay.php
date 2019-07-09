<?php
namespace app\index\controller;
use app\common\controller\Frontend;
use app\common\library\Token;
use think\Db;
class Pay extends Frontend
{

    protected $noNeedLogin = ['callback'];//需要登陆
    protected $noNeedRight = '*';//需要认证
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();
        //$this->game=new Game($this->view->site,$this->view->user);
        $this->todaystr=strtotime(date('Ymd'));
        $this->addon=get_addon_config('Pay');
        $this->appid='这里写apid';
        $this->appkey='这里写appkey';
        $this->game_name='biquan';//项目名字
    }


public function pay()
    {  
 
    $ptype=$this->request->param('ptype')?$this->request->param('ptype'):1;
    $money=intval($this->request->param('aa'))>=5?intval($this->request->param('aa')):0.01;
    $xmoney=$money;
    //避免生成大量废单
    $time_str=time();
    $datas['uid']=$this->auth->id;
    $datas['cash_fee']=$xmoney;
    $datas['status']=0;
    $iforder=Db::name('history')->where($datas)->count();
    if ($iforder) {
        $dat=Db::name('history')->where($datas)->find();
        $idx= $dat['id'];
        $orderid= $dat['out_trade_no'];
    }else{
        $orderid=md5(time().$this->auth->id.$xmoney);
        $datas['out_trade_no']=$orderid;
        $datas['trade_type']='jingxiupay';
        $datas['total_fee']=$xmoney;
        $datas['createtime']=time();
        $datas['status']=0;
        $idx=Db::name('history')->insert($datas);
    }

    $returnurl=$this->view->site['site_url']."/index.php/index/".$this->game_name."/index.html";
   if($xmoney>0){ 
      $data = array(
        "uid" => $this->appid,//你的支付ID
        "money" => number_format(floatval($money),2,".",""),//金额100元
        "notify_url" => $this->view->site['site_url']."/index.php/Index/Pay/callback.html",
        "return_url"=>$returnurl,//跳转地址
        "order_id" => $orderid,
        "remark" => 'id:'.$this->auth->id.'充值'.$money.'元',
        "key" => '',
      ); //构造需要传递的参数
      //print_r($data);
      $sign =$this->create_sign($this->appkey,$data);
      $data['key']=$sign;
      $return = $this->phpPost("http://pay.xlqxw.cn/index/paic/make", $data);
      $result = json_decode($return, true);
      if($result['msg']!='success'){
        echo $return;exit;
        //$this->success($result['msg'],$returnurl);
      } 
            //  echo $return;
      $this->assign('returnurl',$returnurl);
      $this->assign($result);
      return $this->fetch("game/mapay/pay.html"); 
 
    }
  }

public function macheck(){
  $orderid=$this->request->param('orderid');
   $where['uid']=$this->auth->id;
  $where['out_trade_no']=$orderid;
  $status=db::name('history')->where($where)->value('status');
    return $status;
}

public function callback(){
   
   
  $datas=array();
  $dat=$this->request->param();
  $msg='';
  $map['out_trade_no']=!empty($dat['orderid'])?$dat['orderid']:'empty';
  $map['cash_fee']=$dat['money'];
  $map['transaction_id']=array('eq','');//返回空
  $map['status']=0;
  $idx=Db::name('history')->where($map)->count();//查询订单是否存在
  if ($idx>0) {
      $data = [
        'uid' => $dat['uid'],//$_REQUEST['uid'], //商户id
        'money' => $dat['money'],//$_REQUEST['money'], //原支付金额
        'xmoney' => $dat['xmoney'],//$_REQUEST['real_money'], //实际支付金额
        'orderid' => $dat['orderid'],//$_REQUEST['order_id'], //你的订单号
        'token' => $dat['token'],//$_REQUEST['orderno'], //平台订单号
      ];

      //print_r($data);exit;
      $key=$this->appkey;
      $sign = $this->create_sign($key,$data);
      if (!isset($dat['key'])) {
         print_r($dat);
         echo "error";exit;
      }
        if($sign != $dat['key']){
           $msg= 'fail error key';
        }else{
            //更新订单
              $hmap['transaction_id']=$dat['token'];
              $hmap['note']=$dat['orderid'];
              $hmap['attach']=$dat['key'];
              Db::name('history')->where($map)->update($hmap);  
              $uid=Db::name('history')->where($map)->value('uid');
              // $this->txt_output('del/callback'.time().$uid,json_encode($this->request->param()));
              if ($uid>0) {
                 db::name('user')->where('id='.$uid)->setInc('point',$dat['money']);//上分
                 Db::name('history')->where($map)->setfield('status',1);
                 //记录收入
                 $tomap['createtime']=$this->todaystr;
                 db::name('run_count')->where($tomap)->setInc('srpay',intval($dat['money']*100));
                 $tomap['uid']=$uid;
                 db::name('user_count')->where($tomap)->setInc('srpay',intval($dat['money']*100));
              }  
              $msg='success';
        }
  }else{
      $msg='faill';
  }
 
  echo $msg;
  exit;
}

private function phpPost($url, $data = array()) {
  $curl = curl_init(); // 启动一个CURL会话
  curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
  //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 1); // 从证书中检查SSL加密算法是否存在
  curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
  curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
  curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
  curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
  curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); // Post提交的数据包
  curl_setopt($curl, CURLOPT_TIMEOUT, 5); // 设置超时限制防止死循环
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/x-www-form-urlencoded')
  );
  curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
  $res = curl_exec($curl);
  curl_close($curl);
  return $res;

}

private function create_sign($this->appkey,$data){
      $sign = ''; //初始化需要签名的字符为空
      $signPars = ''; //初始化URL参数为空
      ksort($data);
      foreach ($data as $k => $v) {
        if ('' != $v && 'sign' != $k) {
          $signPars .= $k . '=' . $v . '&';
        }
      }
      $signPars .= 'key=' . $this->appkey;
      $sign = md5($signPars);
      return $sign;
}
 

}
