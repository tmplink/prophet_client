# Prophet 探针
这是基于 PHP 编写的服务器探针程序，我们尽可能地为源代码添加了注释，以帮助大家理解它的运作原理。  
探针本身不复杂，并且性能优秀，它主要读取了 Linux 系统的几个地方的数据。整理后发送到 API 服务归档。 

* /proc/stat
* /proc/meminfo
* /proc/loadavg
* /proc/net/dev

另外，通过 **df** 命令，可以获取到磁盘的使用情况。
这个探针需要运行在 PHP7 以上的版本。

一般用法：**php prophet.php -k your_key**   
默认打开了 **debug** 模式，可以通过 **-d 0** 参数关闭。