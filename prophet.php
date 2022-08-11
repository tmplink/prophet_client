#!/usr/bin/php
<?php

/**
 * Prophet Client
 * Version: 5
 * Date: 2022-08-12
 * 
 * 收集 Linux 系统的各项状态指标，然后提交到云端。
 */

$Prophet = new Prophet();
$Prophet->main();

class Prophet
{
    //API 服务器位置
    private $api_url = 'https://vx.link/openapi/service/prophet';

    //请在这里填写微林 API 服务的 KEY
    private $api_key = 'vxp22';

    //是否启用日志
    private $debug = true;

    private $path_pid = '/var/run/prophet.pid';
    private $path_log = '/var/log/prophet.log';
    private $path_key = '/var/log/prophet.key';
    private $path_syslog = '/var/log/prophet_sys.log';

    //是否在后台运行
    private $daemon = false;

    //版本号
    private $version = '5';

    public function main()
    {
        $params = getopt('hvbd:k:', ['kill']);

        if (isset($params['h'])) {
            $this->help();
        }

        if (isset($params['b'])) {
            $this->daemon = true;
        }

        if (isset($params['v'])) {
            $this->version();
        }

        if (isset($params['d'])) {
            if ($params['d'] == 0) {
                $this->debug = false;
            } else {
                $this->debug = true;
            }
        }

        if (isset($params['kill'])) {
            $this->kill();
        }

        if (isset($params['restart'])) {
            $this->restart();
        }

        if (isset($params['k'])) {
            $this->set_key($params['k']);
        }else{
            $this->read_key();
        }

        $this->processor_main();
    }

    /**
     * 读取钥匙
     */
    private function read_key(){
        $key = file_get_contents($this->path_key);
        if ($key) {
            $this->api_key = $key;
        }
    }

    /**
     * 设置钥匙
     */
    private function set_key($key){
        $old_key = $this->read_key();
        //如果新设置的钥匙与旧的钥匙不一致，则采用新的钥匙
        if ($key != $old_key&&!empty($key)) {
            file_put_contents($this->path_key, $key);
            $this->api_key = $key;
        }else{
            $this->api_key = $old_key;
        }
    }

    /**
     * 帮助信息
     */
    private function help()
    {
        echo "Usage: prophet [options]\n";
        echo "Options:\n";
        echo "  -h        Print this help message\n";
        echo "  -b        Run in background\n";
        echo "  -v        Print version information\n";
        echo "  -d        Enable debug mode\n";
        echo "  -k        Set the API key\n";
        echo "  --resatrt Restart\n";
        echo "  --kill    Kill the running process\n";
        exit;
    }

    /**
     * 版本信息
     */
    private function version()
    {
        echo "Prophet v{$this->version}\n";
        exit;
    }

    /**
     * 查找进程并结束
     */
    private function kill()
    {
        $pid = file_get_contents($this->path_pid);
        if ($pid) {
            posix_kill($pid, 9);
            unlink($this->path_pid);
            echo "Prophet is killed.\n";
        } else {
            echo "Prophet is not running.\n";
        }
        exit;
    }

    /**
     * 重启进程
     */
    private function restart(){
        //结束此前进程
        $pid = file_get_contents($this->path_pid);
        if ($pid) {
            posix_kill($pid, 9);
            unlink($this->path_pid);
        }
        //使用重启命令时，会直接将新进程设置到后台运行
        $this->daemon = true;
        //使用重启命令时，关闭 Debug
        $this->debug = false;
        //启动新的守护进程
        $this->processor_main();
    }

    /**
     * 
     */
    public function processor_main()
    {
        //如果不在后台运行，则直接运行
        if (!$this->daemon) {
            error_reporting(E_ALL);
            echo "Prophet v{$this->version}\n";
            while (true) {
                $this->processor_collect();
            }
            exit;
        }
        //创建背景进程并退出
        $pid = pcntl_fork();
        if ($pid > 0) {
            echo "Prophet v{$this->version}\n";
            echo "Process start，pid:{$pid}.\n";
            $this->debug('Prophet process start，pid:' . $pid);
            file_put_contents($this->path_pid, $pid);
            exit;
        } else {
            //子进程处理
            cli_set_process_title("prophet-main");
            umask(0);
            posix_setsid();
            fclose(STDIN);
            fclose(STDOUT);
            fclose(STDERR);

            //设置后台运行时的日志
            if ($this->debug) {
                ini_set('error_reporting', E_ALL);
                ini_set('log_errors', 'on');
                ini_set('display_errors', 'off');
                ini_set('error_log', $this->path_syslog);
            }else{
                error_reporting(0);
            }


            //启动收集程序
            while (true) {
                $this->processor_collect();
            }
        }
    }

    public function processor_collect()
    {
        //收集数据并发送到 API 服务器
        $data = [];
        $data['cpu'] = $this->get_cpu(); //停留1秒
        $data['mem'] = $this->get_mem();
        $data['disk'] = $this->get_disk();
        $data['net'] = $this->get_net(); //停留1秒
        $data['load'] = $this->get_load();

        //发送
        $send_status = $this->post_data($this->api_url, [
            'action' => 'collect',
            'key' => $this->api_key,
            'data' => $data
        ]);

        //debug
        if ($send_status) {
            $log_cpu = ($data['cpu']['user'] + $data['cpu']['system'] + $data['cpu']['iowait']) / 100 . '%';
            $log_mem_free = $this->bytes_fomat($data['mem']['free']);
            $net_rx = $this->bytes_fomat($data['net'][0]['recv_bytes']);
            $net_tx = $this->bytes_fomat($data['net'][0]['send_bytes']);
            $net_interface = $data['net'][0]['interface'];
            $this->debug("Prophet collect data：CPU[{$log_cpu}] MEM Free[{$log_mem_free}] NETWORK[{$net_interface}|rx:{$net_rx}|tx:{$net_tx}]");
        }
    }

    /**
     * 计算 CPU 最近1秒的使用率
     */
    private function get_cpu()
    {
        //先获取 t1 时刻的数据
        $t1 = $this->get_cpu_info();
        //等待一秒
        sleep(1);
        //计算 t2 时刻的数据
        $t2 = $this->get_cpu_info();
        //计算 t1 和 t2 之间的数据
        $calcu =  [
            'user' => $t2['user'] - $t1['user'],
            'nice' => $t2['nice'] - $t1['nice'],
            'system' => $t2['system'] - $t1['system'],
            'idle' => $t2['idle'] - $t1['idle'],
            'iowait' => $t2['iowait'] - $t1['iowait'],
            'irq' => $t2['irq'] - $t1['irq'],
            'softirq' => $t2['softirq'] - $t1['softirq'],
            'steal' => $t2['steal'] - $t1['steal'],
            'guest' => $t2['guest'] - $t1['guest'],
            'guest_nice' => $t2['guest_nice'] - $t1['guest_nice'],
            'total' => $t2['total'] - $t1['total'],
        ];
        //计算各项参数，格式化成整数
        return [
            'user' => ceil($calcu['user'] / $calcu['total'] * 10000),
            'nice' => ceil($calcu['nice'] / $calcu['total'] * 10000),
            'system' => ceil($calcu['system'] / $calcu['total'] * 10000),
            'idle' => ceil($calcu['idle'] / $calcu['total'] * 10000),
            'iowait' => ceil($calcu['iowait'] / $calcu['total'] * 10000),
            'irq' => ceil($calcu['irq'] / $calcu['total'] * 10000),
            'softirq' => ceil($calcu['softirq'] / $calcu['total'] * 10000),
            'steal' => ceil($calcu['steal'] / $calcu['total'] * 10000),
            'guest' => ceil($calcu['guest'] / $calcu['total'] * 10000),
            'guest_nice' => ceil($calcu['guest_nice'] / $calcu['total'] * 10000),
        ];
    }

    /**
     * 获取 CPU 信息
     * @return array
     * 
     */
    private function get_cpu_info()
    {
        $cpu_stat = explode(' ', preg_replace("/\s(?=\s)/", "\\1", trim(file('/proc/stat')[0])));
        return [
            'user' => $cpu_stat[1],
            'nice' => $cpu_stat[2],
            'system' => $cpu_stat[3],
            'idle' => $cpu_stat[4],
            'iowait' => $cpu_stat[5],
            'irq' => $cpu_stat[6],
            'softirq' => $cpu_stat[7],
            'steal' => $cpu_stat[8],
            'guest' => $cpu_stat[9],
            'guest_nice' => $cpu_stat[10],
            'total' => array_sum(array_slice($cpu_stat, 1))
        ];
    }

    /**
     * 获取系统负载信息
     * @return array
     */
    private function get_load()
    {
        $load_avg = explode(' ', preg_replace("/\s(?=\s)/", "\\1", trim(file('/proc/loadavg')[0])));
        return [
            '1' => $load_avg[0],
            '5' => $load_avg[1],
            '15' => $load_avg[2],
        ];
    }

    /**
     * 获取内存信息
     * @return array
     */
    private function get_mem()
    {
        //先获取内存数据
        $proc_meminfo = file('/proc/meminfo');
        $return = [];
        foreach ($proc_meminfo as $meminfo) {
            $meminfo = explode(' ', preg_replace("/\s(?=\s)/", "\\1", $meminfo));
            //提取指标
            if ($meminfo[0] === 'MemTotal:') {
                $return['total'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'MemFree:') {
                $return['free'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'MemAvailable:') {
                $return['available'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'Buffers:') {
                $return['buffers'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'Cached:') {
                $return['cached'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'SwapTotal:') {
                $return['swap_total'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'SwapFree:') {
                $return['swap_free'] = $meminfo[1] * 1024;
            }
            if ($meminfo[0] === 'SwapCached:') {
                $return['swap_cached'] = $meminfo[1] * 1024;
            }
        }
        return $return;
    }

    /**
     * 获取磁盘使用率信息
     */
    private function get_disk()
    {
        //先获取磁盘数据
        exec('df', $df_diskinfo);
        $disk_usage = [];
        //处理数据
        foreach ($df_diskinfo as $mount) {
            //只处理 /dev/ 下的数据
            if (strpos($mount, '/dev/') !== false) {
                $mount = explode(' ', preg_replace("/\s(?=\s)/", "\\1", $mount));
                $disk_usage[$mount[0]] = [
                    'free' => $mount[3] * 1024,
                    'used' => $mount[2] * 1024,
                    'path' => $mount[5],
                ];
            }
        }
        //返回磁盘的使用率
        return $disk_usage;
    }

    private function get_net()
    {

        //先获取网络数据t1
        $t1 = $this->get_net_info();

        //休息1秒
        sleep(1);

        //获取网络数据t2
        $t2 = $this->get_net_info();

        //计算各项参数
        $return = [];
        foreach ($t1 as $key => $value) {
            if ($value['recv_packets'] === 0 && $value['recv_bytes'] === 0 && $value['send_packets'] === 0 && $value['send_bytes'] === 0) {
                continue;
            }
            $return[] = [
                'interface' => $key,
                'recv_total_packets' => $t2[$key]['recv_packets'],
                'recv_total_bytes' => $t2[$key]['recv_bytes'],
                'send_total_packets' => $t2[$key]['send_packets'],
                'send_total_bytes' => $t2[$key]['send_bytes'],
                'recv_packets' => ($t2[$key]['recv_packets'] - $t1[$key]['recv_packets']),
                'recv_bytes' => ($t2[$key]['recv_bytes'] - $t1[$key]['recv_bytes']),
                'send_packets' => ($t2[$key]['send_packets'] - $t1[$key]['send_packets']),
                'send_bytes' => ($t2[$key]['send_bytes'] - $t1[$key]['send_bytes']),
            ];
        }

        return $return;
    }

    private function get_net_info()
    {
        //先获取网络数据
        $proc_netinfo = file('/proc/net/dev');
        array_shift($proc_netinfo);
        array_shift($proc_netinfo);
        //先检查哪些设备是有效的，排除无数据的网络设备，排除 lo ，排除网桥
        $net = [];
        foreach ($proc_netinfo as $line) {
            //排除无数据的网络设备
            $line = explode(' ', preg_replace("/\s(?=\s)/", "\\1", $line));
            if (count($line) === 18) {
                $dev_name = substr($line[1], 0, -1);
                //排除 lo ，排除网桥
                if (strpos($dev_name, 'lo') === false && strpos($dev_name, 'ifb') === false) {
                    //获取发送和接收的数据包数量以及字节数
                    $net[$dev_name] = [
                        'recv_packets' => (int)$line[3],
                        'recv_bytes' => (int)$line[2],
                        'send_packets' => (int)$line[11],
                        'send_bytes' => (int)$line[10],
                    ];
                }
            }
        }
        return $net;
    }

    private function post_data($url, $data)
    {
        $last = 'ok';
        $send = http_build_query($data);
        $size = strlen($send);
        $retry = 3;
        $retry_count = 0;
        $opts = array(
            'http' => array(
                'method' => "POST",
                'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Content-length:" . $size . "\r\n" .
                    "\r\n",
                'content' => $send,
            )
        );
        $cxContext = stream_context_create($opts);
        $sFile = file_get_contents($url, false, $cxContext);

        while ($sFile === false) {
            //发送失败，重试
            $sFile = file_get_contents($url, false, $cxContext);
            if ($sFile !== false) {
                $last = 'ok';
                break;
            }
            $retry_count++;
            if ($retry_count >= $retry) {
                $last = 'error';
                break;
            }
            $this->debug("Sending data:{$url},Size:{$size} ... failed,retry...");
            sleep(3);
        }

        //重试三次还是失败就跳出
        $this->debug("Sending data:{$url},Size:{$size} {$last}.");

        if ($last === 'error') {
            return false;
        }

        //检查返回值是否正常
        $rdata = json_decode($sFile, true);
        if ($rdata['status'] != 1) {
            $this->debug("===========Debug============");
            $this->debug("{$sFile}");
            $this->debug("===========Debug============");
        }

        return true;
    }

    private function bytes_fomat($size, $digits = 2)
    {
        if (empty($size)) {
            return '0 B';
        }
        $unit = array('', 'K', 'M', 'G', 'T', 'P');
        $base = 1024;
        $i = floor(log($size, $base));
        $n = count($unit);
        if ($i >= $n) {
            $i = $n - 1;
        }
        return round($size / pow($base, $i), $digits) . ' ' . $unit[$i] . 'B';
    }

    private function debug($msg)
    {
        $this->debug;
        if ($this->debug) {
            file_put_contents($this->path_log, date('Y-m-d H:i:s') . " {$msg}\n", FILE_APPEND);
        }
        //如果在前台运行，则直接打印日志
        if ($this->daemon === false) {
            echo $msg . "\n";
        }
    }
}
