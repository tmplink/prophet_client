# Prophet 探针
这是基于 PHP 编写的服务器探针程序，我们尽可能地为源代码添加了注释，以帮助大家理解它的运作原理。  
探针本身不复杂，并且性能优秀，它主要读取了 Linux 系统的几个地方的数据。整理后发送到 API 服务归档。 

* /proc/stat
* /proc/meminfo
* /proc/loadavg
* /proc/net/dev

# 安装 & 运行

请先到[微林](https://vx.link)的**先知**服务中，创建监控点，以获取直接运行对应钥匙。使用key替换如下命令:  
```shell
bash <(curl -s https://raw.githubusercontent.com/tmplink/prophet_client/main/install.sh) -k  <API_KEY>
```

执行完上述脚本，如果无报错，探针服务就已经在运行了。

# 用法

一般用法：**prophet -k your_key**   

后台运行用法：**prophet -k your_key -b**。

停止服务：**prophet --kill**。

重启服务：**prophet --resatrt**。

默认打开了 **debug** 模式，可以通过 **-d 0** 参数关闭；




# 更新
此更新脚本会自动重启正在运行中的探针
```shell
sudo su # obtain root permission
curl -k 'https://raw.githubusercontent.com/tmplink/prophet_client/main/update.sh' | sh
```

这个探针需要运行在 PHP7 以上的版本。