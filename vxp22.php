<?php
/**
 * Project 22
 * Version: 0
 * Date: 24/05/2019
 * 
 * 收集 Linux 系统的各项状态指标，然后提交到云端。
 */

$vxp22 = new vxp22();
$vxp22->main();

class vxp22 {

    private $api_url = 'http://vx.link/';

    public function main(){
        var_dump($this->get_net());
    }

    /**
     * 计算 CPU 最近1秒的使用率
     */
    private function get_cpu(){
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
            'user' => ceil($calcu['user'] / $calcu['total']*10000),
            'nice' => ceil($calcu['nice'] / $calcu['total']*10000),
            'system' => ceil($calcu['system'] / $calcu['total']*10000),
            'idle' => ceil($calcu['idle'] / $calcu['total']*10000),
            'iowait' => ceil($calcu['iowait'] / $calcu['total']*10000),
            'irq' => ceil($calcu['irq'] / $calcu['total']*10000),
            'softirq' => ceil($calcu['softirq'] / $calcu['total']*10000),
            'steal' => ceil($calcu['steal'] / $calcu['total']*10000),
            'guest' => ceil($calcu['guest'] / $calcu['total']*10000),
            'guest_nice' => ceil($calcu['guest_nice'] / $calcu['total']*10000),
        ];
    }

    /**
     * 获取 CPU 信息
     * @return array
     * 
    */
    private function get_cpu_info(){
        $cpu_stat = explode(' ',preg_replace("/\s(?=\s)/", "\\1", trim(file('/proc/stat')[0])));
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
    private function get_load(){
        $load_avg = explode(' ',preg_replace("/\s(?=\s)/", "\\1", trim(file('/proc/loadavg')[0])));
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
    private function get_mem(){
        //先获取内存数据
        $proc_meminfo = file('/proc/meminfo');
        $return = [];
        foreach($proc_meminfo as $meminfo){
            $meminfo = explode(' ',preg_replace("/\s(?=\s)/", "\\1", $meminfo));
            //提取指标
            if($meminfo[0]==='MemTotal:'){
                $return['total'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='MemFree:'){
                $return['free'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='MemAvailable:'){
                $return['available'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='Buffers:'){
                $return['buffers'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='Cached:'){
                $return['cached'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='SwapTotal:'){
                $return['swap_total'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='SwapFree:'){
                $return['swap_free'] = $meminfo[1]*1024;
            }
            if($meminfo[0]==='SwapCached:'){
                $return['swap_cached'] = $meminfo[1]*1024;
            }
        }
        return $return;
    }

    /**
     * 获取磁盘使用率信息
     */
    private function get_disk(){
        //先获取磁盘数据
        exec('df',$df_diskinfo);
        $disk_usage = [];
        //处理数据
        foreach($df_diskinfo as $mount){
            //只处理 /dev/ 下的数据
            if(strpos($mount,'/dev/')!==false){
                $mount = explode(' ',preg_replace("/\s(?=\s)/", "\\1", $mount));
                $disk_usage[$mount[0]] = [
                    'free' => $mount[3]*1024,
                    'used' => $mount[2]*1024,
                    'path' => $mount[5],
                ];
            }
        }
        //返回磁盘的使用率
        return $disk_usage;
    }

    private function get_net(){

        //先获取网络数据t1
        $t1 = $this->get_net_info();

        //休息1秒
        sleep(1);

        //获取网络数据t2
        $t2 = $this->get_net_info();

        //计算各项参数
        $return = [];
        foreach($t1 as $key => $value){
            $return[$key] = [
                'recv_packets' => ($t2[$key]['recv_packets']-$t1[$key]['recv_packets']),
                'recv_bytes' => ($t2[$key]['recv_bytes']-$t1[$key]['recv_bytes']),
                'send_packets' => ($t2[$key]['send_packets']-$t1[$key]['send_packets']),
                'send_bytes' => ($t2[$key]['send_bytes']-$t1[$key]['send_bytes']),
            ];
        }

        //移除数据为0的项目
        foreach($return as $key => $value){
            if($value['recv_packets']===0 && $value['recv_bytes']===0 && $value['send_packets']===0 && $value['send_bytes']===0){
                unset($return[$key]);
            }
        }

        return $return;
    }

    private function get_net_info(){
        //先获取网络数据
        $proc_netinfo = file('/proc/net/dev');
        array_shift($proc_netinfo);
        array_shift($proc_netinfo);
        //先检查哪些设备是有效的，排除无数据的网络设备，排除 lo ，排除网桥
        $net = [];
        foreach ($proc_netinfo as $line) {
            //排除无数据的网络设备
            $line = explode(' ',preg_replace("/\s(?=\s)/", "\\1", $line));
            if(count($line)===18){
                $dev_name = substr($line[1],0,-1);
                //排除 lo ，排除网桥
                if(strpos($dev_name,'lo')===false && strpos($dev_name,'ifb')===false){
                    //获取发送和接收的数据包数量以及字节数
                    $net[$dev_name] = [
                        'recv_packets' => $line[2],
                        'recv_bytes' => $line[3],
                        'send_packets' => $line[10],
                        'send_bytes' => $line[11],
                    ];
                }
            }
        }
        return $net;
    }
}