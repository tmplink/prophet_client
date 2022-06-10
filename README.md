# Prophet 探针
这是基于 PHP 编写的服务器探针程序，我们尽可能地为源代码添加了注释，以帮助大家理解它的运作原理。  
探针本身不复杂，并且性能优秀，它主要读取了 Linux 系统的几个地方的数据。整理后发送到 API 服务归档。 

* /proc/stat
* /proc/meminfo
* /proc/loadavg
* /proc/net/dev

# 安装
```shell
curl -k 'https://raw.githubusercontent.com/tmplink/prophet_client/main/install.sh' | sh
```

# 运行
可以到微林(https://vx.link)的先知服务中，创建监控点后获取直接运行对应钥匙的启动命令。  
一般用法：**prophet -k your_key**   
默认打开了 **debug** 模式，可以通过 **-d 0** 参数关闭。

# 更新
此更新脚本会自动重启正在运行中的探针
```shell
curl -k 'https://raw.githubusercontent.com/tmplink/prophet_client/main/update.sh' | sh
```

这个探针需要运行在 PHP7 以上的版本。