使用说明：<br/>
=========
#系统环境配置：Ubuntu 16.04.4 LTS<br/>

1.Apache 2.4<br/>
2.PHP-5.6<br/>
3.MySQL 5.5<br/>
4.Redis 5.0<br/>
注意：环境安装完毕请执行，php -m 查看是否正常有redis扩展，phpinfo()也需要查看下。<br/>

#需要安装精秀插件：<br/>

1.把对应版本的jingxiu.so 复制到php的扩展目录中。<br/>
2.在php.ini 中加入extension=jingxiu.so(该文件和redis.so同目录)<br/>
3.配置完成后执行php -m 或者php运行 phpinfo()。查看是否jingxiu插件能正常加载。<br/>
<img src="img/jingxiu.png" /></p>

#一般情况下，程序环境搭建：<br/>
1.导入sql数据库。<br/>
2.修改.env 中的数据库账号密码<br/>
3.修改application/datebase.php中的账号密码。<br/>
4.给public/uploads ,runtime 配置766权限，以便于可以生成缓存文件和二维码图片等东西。<br/>
5.配置网站运行目录于public中运行。<br/>
6.进入后台：网址/gznetceo.php 默认账号密码：jingxiu / mima123 修改入口域名，中转域名，落地域名<br/>
7.填写号精秀id和精秀key获得免登陆接入，省掉一个公众号。<br/>
8.放置授权文件在public目录下,以便于网站正常运行，没有授权文件请沟通精秀特群官方人员。<br/>
9.游戏入口：网址/index.php/index/游戏代号(如币圈为biquan)<br/>
7.自行对接入款平台，积分保存在user表中的point字段中。amount为佣金字段。<br/>
8.游戏收款链接一般跳转到:<br/>
网址/index.php/index/pay.html?fee=1只需对该类进行开发即可对接所有的支付。收款记录于history表中<br/>


#收款系统：<br/>
只需自行开发一个控制器即可 Pay.php,对user的金额存储位point进行修改即可：<br/>

Db::name('user')->where('id='.$this->auth->id)->setInc('point',2);<br/>

具体操作请看Pay.php 控制器<br/>

出款系统：<br/>

出款系统一般默认为精秀企业付款系统，需要找官方人员开户，并获得精秀支付id和key填入后台即可集成零钱到账。<br/>

#带插件系统的开启：
第一步，cd 到 socket目录下<br/>
第二步，运行 php xxx.php restart -d 命令会出项以下窗口则视为正常启动；<br/>
<img src="img/ws.png" /></p>
