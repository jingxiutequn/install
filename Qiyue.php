<?php
namespace app\index\controller;
use app\common\controller\Frontend;
use app\common\library\Game;
use think\Lang;
use think\Db;
use think\Controller;
use think\Cache;
/////////////////////////////////////////////////////////////////////华丽的分割线//
class qiyue extends Frontend
{

  protected $noNeedLogin = ['jxback','payrequest'];
    protected $noNeedRight = ['*'];
    protected $layout = '';
public function _initialize()
    {
  //echo "sss";exit;
    parent::_initialize();
    $auth = $this->auth;
     $this->todaystr=strtotime(date('Ymd'));
     $this->addon=get_addon_config('jxpay'); 
     $this->game=new Game($this->view->site,$this->view->user);
    // echo "ff";exit;
    }

  //1.调用付款接口
private function jxpay($fee=0,$openid='',$uid=0,$type=1,$msg='',$field='',$url=''){
  if (!$url) {
    if (!$this->addon['tx_url']) {
      $url=$this->view->site['payserver'];
    }else{
      $url=$this->addon['tx_url'];
    }
    
  }
  //防止卡包
    $lock_balance=$this->game->cache_get('lock_balance'.$this->auth->id);
    if ($lock_balance!=0) {
       return;
    }
    $this->game->cache_set('lock_balance'.$this->auth->id,1);
    if (!$field) {
       if ($this->addon['field']==1) {
    $field='point';
     }else{
    $field='amount';
     }  
    }
  
    $uid=$uid>0?$uid:$this->auth->id;
  if ($uid>0) {
   $this->user=Db::name('user')->where('id='.$uid)->find();
  }
  $backurl=$this->view->site['site_url'].'/index.php/Index';
        if($uid<=0){
      $dat['successpay']=0;
      $dat['url']=$backurl;
      return $dat;
      exit;
    }else{
      
       $point= $this->user[$field];
      // echo $point;exit;
       if($point<$fee){
        $dat['msg']='error';
        $dat['successpay']=0;
       // print_r($dat);exit;
        return $dat;
        exit;
       }
    }
    $par = array();
    $par['parter'] = $this->view->site['payid'];
    $par['type'] = 1005;
    $par['op'] = $openid;
    $money=round($fee,2);

    //先减掉用户的积分，存入一个缓存字段。
         // 启动事务
         Db::startTrans();
    try{
      if ($field=='point') {
        $dfee=$fee*1;
      }else{
        $dfee=$fee*1;
      }

      $result= Db::name('user')->where('id='.$uid)->setDec($field,$dfee);
            // 提交事务
        Db::commit();    
    } catch (\Exception $e) {
        // 回滚事务
        Db::rollback();
        exit;
    }

      /////////////////////////////////
      $par['value'] =  $money;
      $par['orderid'] = md5(time());
      $par['nourl'] = urlencode($this->view->site['site_url'].'/index.php/Index/Qiyue/jxback.html');
      $par['sign'] = $this->create_sign($this->view->site['safecode'],$par);
      $par['backurl'] = urlencode($backurl);
      $par['attach'] = 'uid'.$uid;  
      $par['book'] = $msg?$msg:'jingxiutequn';  
    //记录该用户情况
    $aacmap['uid']=$uid;
    $aacmap['createtime']=$this->todaystr;
    Db::name('user_count')->where($aacmap)->setInc('onlinetixiantime');
    Db::name('user_count')->where($aacmap)->setInc('kfdown',$fee*100);
    //测试
    //生成收款链接
    $payurl=$this->create_url($url.'/index.php/Api/Qiyue/paytouser.html', $par);
    // exit;
    //记录提现
        $data['ttype']='api'; 
    $data['point']=$fee; 
    $data['status']=1; 
    $data['name']=$this->user['username'];
    $data['uid']=$uid; 
    $data['payurl']=$payurl;
    $data['note']=$this->user[$field]-$fee;
    if($type){$data['upoint']=$point-$fee;}  
    $data['createtime']=time();
    $txid=Db::name('user_tixian')->insertGetId($data);
     if ($this->addon['realtime']&& ($field=='point' || $field=='amount')) {
       $ifpay=Db::name('user')->where('id='.$uid)->value('ifpay');//ifpay==0封支付。
       if ($ifpay) {
           $json=$this->http_get($payurl);
           $data=json_decode($json,true);
           if (isset($data['payment_no'])) {
             Db::name('user_tixian')->where('id='.$txid)->setfield('paymentno',$data['payment_no']);
           }
       }else{
          $data['successpay']=0;
       }
       
     }else{
           $data['successpay']=0;
     }
    //如果返回成功则清除支付成功缓存，如果因为网络问题或者其他原因，则数据库tpoint会有数据，可以作为客服给用户返款的证据
        $data['url']=$payurl;
    if($data['successpay']==1){
     //是否异步已经扣款了？
     $paystatus= Db::name('user_tixian')->where('id='.$txid)->value('status');
     //未扣款则扣掉
     if($paystatus==0){
       Db::name('user_tixian')->where('id='.$txid)->setfield('status',1);
       //Db::name('user')->where('id='.$uid)->setDec('tpoint',$fee);
       if($type==0){
        $data['orderid']=$txmap['orderid'];//订单id
       }
     }  
    }else{
      $data['msg']='申请已经提交，客服会尽快处理!'.$data['msg'];
    }
    return $data;
  }
 //创建异步请求
public function nourl_pay($uid=0,$fee=0,$book='',$field='point'){
  $url = $this->view->site['site_url'].'/index.php/index/Qiyue/payrequest.html'; 
  echo $url;
  $param = array( 
   // 'openid'=>$openid,
    'fee'=>$fee,
      'uid'=>$uid,
      'book'=>$book,
      'field'=>$field,
      'nostr'=>mt_rand(),
  );
  $dat=$param;
    $param['key']=$this->create_sign('jingxiutequnkey'.$this->todaystr,$dat);
  echo doRequest($url,$param,0);
  return ;
  //echo '----------------------------------';
}
//暴露外部的取款方式所以基于安全需要key认证
public function payrequest($tx_field=""){

  if (!$tx_field) {
     $tx_field=$this->addon['tx_field'];
  }
  $dat=$this->request->param();
  if (!$dat) {
    return "empty";
  }
  $key=$dat['key'];
  unset($dat['key']);
     //防止卡包
      $lock_balance=$this->game->cache_get('lock_balance'.$dat['uid']);
      if ($lock_balance!=0) {
        $this->txt_output($dat['key'],'lock_balance') ;
         return;
      }
     $check_sign=$this->create_sign('jingxiutequnkey'.$this->todaystr,$dat);
     //安全性认证
     $msg= "";
     if ($check_sign==$key) {
      //order repeat 
      $isok=$this->game->cache_get($key);
      if ($isok) {
        $msg= "pass key";
      }else{
            $this->game->cache_set($key,1,3600);
            $openid=Db::name('user')->where('id='.$dat['uid'])->value($tx_field);
            if ($openid) {
              $msg= "$openid\n";
              $checkdata=$this->checkrule($dat['fee'],$dat['uid'],1);
                if($checkdata['code']==1){
                  if ($dat['field']) {
                    $field=$dat['field'];
                  }else{
                    $field='point';
                  }
                  $rel=$this->jxpay($dat['fee'],$openid,$dat['uid'],1,$dat['book'],$field);
                }else{
          $rel=$checkdata;
        }
        foreach ($rel as $kk => $value) {
          if (is_array($value)) {
            foreach ($value as $kas => $vv) {
                      if (!is_array($vv)) {
                        $msg.="\n".$kas.':'.$vv;
                      }
            }
          }else{
            $msg.="\n".$kk.':'.$value;
          }
          
        }/**/             
            }else{
              $msg=  "empty openid";
            }
      }
      
     }else{
      $msg=  'error sigin';
     }
    // return $msg;
     $this->txt_output($key,$msg) ;
}
//
private function txt_output($i=1,$contents){
    $fp=fopen('del/'.$i.".dat","w");//写文件输出用于检测先删掉4.txt
    fwrite($fp,$contents);
    fclose($fp);
    return true;
}
  //2。付款异步返回涵数
  public function jxback(){
         $data=json_decode($this->request->getInput(),true);
          if($data['status']=='success'){
                //renzhen
        $paramater['orderid']=$data['orderid'];
        $paramater['out_trade_no']=$data['out_trade_no'];
        $paramater['payment_no']=$data['payment_no'];
        $sign = $this->create_sign($this->view->site['safecode'],$paramater);
        if($sign!=$data['sign']){
           echo "error sign";
           exit;
        }else{
           $map['orderid']=$data['orderid'];
           //查看这个定单
           $order=Db::name('agent_tixian')->field('paystatus,uid,fee')->where($map)->find();
           //还没扣款的情况下
           if($order['paystatus']==0&&$order['uid']>0){
            // Db::name('user')->where('id='.$order['uid'])->setDec('tpoint',$order['fee']);
           $txmap['paystatus']=1;
           }
           $txmap['status']=1;
           $txmap['out_trade_no']=$data['out_trade_no'];
           $txmap['payment_no']=$data['payment_no'];
           $txmap['sign']=$data['sign'];
           Db::name('agent_tixian')->where($map)->update($txmap);
           echo "success";
        }
      }else{
       
             echo "fali:".json_encode($data);
       
         
      }
  }
  public function get_order(){
    $url=$this->view->site['payserver'].'/index.php/Api/Qiyue/get_order.html?orderid=0105b25feae6a64fd8b4aa2e9afd4c7362b5b9ec&lm=2&parter='.$this->view->site['payid'].'&key='.md5($this->view->site['payid'].$this->view->site['safecode']);
   
    $bdd=json_decode($this->http_get($url),true);

     print_r($bdd);
  }
  public function get_openid($uid,$type=0,$url=''){
    if (!$url) {
       $url=$this->view->site['payserver'];
    }
  //返回的安全认证方式
    $key=md5($uid.$this->view->site['safecode'].$this->view->site['payid']);
    //异步反馈
    $nourl=urlencode($this->view->site['site_url'].'/index.php/Index/Qiyue/usernameack.html?nokey='.md5($uid.$this->view->site['safecode']));
    //同步反馈
    $backurl=urlencode($this->view->site['site_url'].'/index.php/Index/Qiyue/saveopenid/mid/'.$uid."/kk/".$key.'.html?parter='.$this->view->site['payid']);
    //获得授权
    if($type){
    $gotuurl['msg']=$url.'/index.php/Api/Qiyue/wx.html?mid='.$uid.'&parter='.$this->view->site['payid']."&nourl=".$nourl."&backurl=".$backurl.'&key='.$key;
    return $gotuurl;
    }else{
    header('Location: '.$url.'/index.php/Api/Qiyue/wx.html?mid='.$uid.'&parter='.$this->view->site['payid']."&nourl=".$nourl."&backurl=".$backurl.'&key='.$key);
    }
    //exit;
  }
 //b,获取openid同步接口
  public function saveopenid($field='wx'){
      
      $key=md5($this->request->param('mid').$this->view->site['safecode'].$this->view->site['payid']);
     if($key==$this->request->param('kk')&&$this->auth->id>0){
       Db::name('user')->where("id=".$this->auth->id)->setfield($field,$this->request->param('openid'));
       $url=$this->view->site['site_url'].'/index.php/index/'.$this->view->site['default_game'].'/index';
       header('Location: '.$url);
     }else{
 
        $this->error("授权错误！"."\nkey:".$key."\nkkk:".$this->request->param('kk'),'https://zhidao.baidu.com/question/651774983496586925.html');
     }
     exit;
        //
    }
 
  //b获取openid异步方法
  public function usernameack(){
     
     $data=json_decode(file_get_contents("php://input"),true);
     
     $uid=$data['mid'];
     if(md5($data['mid'].$this->view->site['safecode'])!=$data['nokey']){
       $this->error('nokey 错误!请勿尝试破解本系统！','https://zhidao.baidu.com/question/651774983496586925.html');
     }else{
       if($data['mid']>0){
        $umap['id']=$data['mid'];
        Db::name('user')->where($umap)->setfield('username',$data['openid']);
      }
     }
     exit;
  }
   
private function http_get($url){//带json发送，无json数据
    $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL,$url);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
        $result = curl_exec($ch);  
        curl_close($ch);  
    return $result;
    }
  //notify加密
private function create_sign($key,$array) {
        $par = '';
    //签名步骤一：按字典序排序参数
    ksort($array);
    foreach ($array as $k => $v) {
      if ($par == '') {
        $par .= $k . '=' . $v;
      }
      else {
        $par .= '&' . $k . '=' . $v;
      }
    }
    //echo $par. $key;
    return md5($par. $key);
    }
//生成请求连接      
private function create_url($url, $par) {
    $c = 0;
    //签名步骤一：按字典序排序参数
    ksort($par);
    foreach ($par as $k => $v) {
      if ($c == 0) {
        $url .= '?' . $k .'='. $v;
        ++ $c;
      }
      else {
        $url .= '&' . $k .'='. $v;
      }
    }
    return $url;
  } 
//type:
private function checkrule ($fee=0,$uid=0,$type=1){
  $uid=$uid>0?$uid:$this->auth->id;
 if($uid<=0){
  $map['code']=2;
  $map['msg']= "请重新登陆！";
  return $map;
  exit;
 }else{
   if($this->addon['field']==1) {
    $field='point';
   }else{
    $field='amount';
   }
  $point=Db::name('user')->where("id=".$uid)->value($field); 
  $umap['uid']=$uid;
  $umap['createtime']=$this->todaystr;
  $onlinetixiantime=Db::name('user_count')->where($umap)->value('onlinetixiantime'); 
  //默认控制
  if($type==1){
     if($fee<=0){
      $map['code']=2;
      $map['msg']= "open fee";
      return $map;
      exit;
     }
     //大额限制
    if($fee>=$this->view->site['autolimit']){
      $map['code']=3;
      $map['url']= "/index.php/Index/Daili/tixian.html";
      $map['msg']= "limit:".($this->view->site['txlimit']-$onlinetixiantime)."!";
      return $map;
       exit;
     }
      //大额限制
    if($this->view->site['txlimit']<=$onlinetixiantime&&$this->view->site['txlimit']!=0){
      $map['code']=3;
      $map['url']=url('/index.php?g=User&m=Hnb&a=index',array('str'=>time()));
      $map['msg']= "today:".$onlinetixiantime."次,请明天再来！";
      return $map;
      exit;
     }
     
    // 系统进入维护状态
     if($this->view->site['txlimit']<=0){
      $map['code']=3;
      $map['url']=url('/index.php?g=User&m=Hnb&a=index',array('str'=>time()));
      $map['msg']= "ok:".$onlinetixiantime."次！";
      return $map;
       exit;
     }  
     //多少可提
     if($fee<$this->view->site['price']){
       $map['msg']="less:".$this->view->site['price']."！";
       $map['code']=3;
       $map['url']=url('/index.php?g=User&m=Hnb&a=index',array('str'=>time()));
       return $map;
       exit;
     }
     $openid=Db::name('user')->where('id='.$uid)->value('openid');
     if(empty($openid)){
        $map['code']=3;
        $map['url']= "/index.php/Api/Log/wx.html";
        $map['msg']= "empty openid";
        return $map;
        exit;
     }  
    //支付处理成功标志位
    $successpay=0;
    //现金赔付+积分赔付 ,在线赔付次数超过系统定义，则积分支付                         
    if($this->view->site['txlimit']!=0&&$this->view->site['txlimit']>=$onlinetixiantime){
       //在线自动赔付
      if($fee>=$this->view->site['price']&&!empty($openid)){
          $map['code']=1;
      }else{
        $map['code']=2;
        $map['msg']= "left:".($this->view->site['txlimit']-$onlinetixiantime)."次！";
        return $map;
      }   
    } 
  }else{
            $map['code']=1;
  }  
 }
    
return $map;
}
//查看企业付款余额
public function checkmoney(){
  $json=$this->http_get($this->view->site['payserver'].'/index.php?g=Api&m=Qiyue&a=get_ye&parter='.$this->view->site['payid'].'&key='.md5($this->view->site['payid'].$this->view->site['safecode']));
  $bdd=json_decode($json,true);
  if($bdd['point']<1){
    $msg['status']=0;
    $msg['msg']='fee:'.floor($bdd['point']);
  }else{
        $msg['status']=1;
        $msg['fee']=floor($bdd['point']);
  }
  return $msg;
}
/////////////////////////微信自动登录代码结束//////////////////
}

?>