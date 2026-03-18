<?php
// 文件名: update_cn.php
// 作用: 拉取 APNIC 数据，同时生成 IPv4 和 IPv6 的 ROS 脚本

$apnic_url = "http://ftp.apnic.net/apnic/stats/apnic/delegated-apnic-latest";
$cache_file = __DIR__ . '/cn_cache.rsc';

echo "开始拉取 APNIC 数据...\n";
$data = file_get_contents($apnic_url);

if (!$data) {
    die("错误：无法获取 APNIC 数据！\n");
}

$lines = explode("\n", $data);

// 准备 IPv4 和 IPv6 的 ROS 命令头部
$rsc_ipv4 = "/ip firewall address-list remove [find list=CN_V4]\n/ip firewall address-list\n";
$rsc_ipv6 = "/ipv6 firewall address-list remove [find list=CN_V6]\n/ipv6 firewall address-list\n";

$count_v4 = 0;
$count_v6 = 0;

foreach ($lines as $line) {
    // 1. 处理 IPv4
    if (strpos($line, 'apnic|CN|ipv4|') === 0) {
        $parts = explode('|', $line);
        $ip = $parts[3];
        $count = (int)$parts[4];
        $mask = 32 - log($count, 2); // 算出掩码
        $rsc_ipv4 .= "add list=CN_V4 address={$ip}/{$mask}\n";
        $count_v4++;
    }
    // 2. 处理 IPv6 (无需计算掩码，第5列直接是前缀)
    elseif (strpos($line, 'apnic|CN|ipv6|') === 0) {
        $parts = explode('|', $line);
        $ip = $parts[3];
        $prefix = $parts[4]; // 直接获取前缀长度
        $rsc_ipv6 .= "add list=CN_V6 address={$ip}/{$prefix}\n";
        $count_v6++;
    }
}

// 拼接最终的脚本文本
$final_rsc = "# IPv4 路由分流表 ({$count_v4}条)\n" . $rsc_ipv4 . "\n\n";
$final_rsc .= "# IPv6 路由分流表 ({$count_v6}条)\n" . $rsc_ipv6 . "\n";

// 写入缓存文件
file_put_contents($cache_file, $final_rsc);

echo "成功！已生成 {$count_v4} 条 IPv4 段，以及 {$count_v6} 条 IPv6 段。\n";
?>