<?php
echo "开始拉取 APNIC 中国大陆 IP 数据...\n";

$url = "https://ftp.apnic.net/stats/apnic/delegated-apnic-latest";
$output_file = __DIR__ . "/cn_cache.rsc";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$data = curl_exec($ch);
curl_close($ch);

if (empty($data)) {
    die("错误：APNIC 数据下载失败！\n");
}

$lines = explode("\n", $data);
$ipv4_count = 0;
$ipv6_count = 0;

$rsc_content = "/ip firewall address-list remove [find list=\"CN_IP\"]\n";
$rsc_content .= "/ipv6 firewall address-list remove [find list=\"CN_IPV6\"]\n";

foreach ($lines as $line) {
    if (strpos($line, 'apnic|CN|ipv4|') === 0) {
        $parts = explode('|', $line);
        $ip = $parts[3];
        $count = (int)$parts[4];
        $mask = 32 - log($count, 2);
        $rsc_content .= "/ip firewall address-list add list=CN_IP address={$ip}/{$mask}\n";
        $ipv4_count++;
    } elseif (strpos($line, 'apnic|CN|ipv6|') === 0) {
        $parts = explode('|', $line);
        $ip = $parts[3];
        $mask = $parts[4];
        $rsc_content .= "/ipv6 firewall address-list add list=CN_IPV6 address={$ip}/{$mask}\n";
        $ipv6_count++;
    }
}

if (file_put_contents($output_file, $rsc_content) !== false) {
    echo "成功！已生成 {$ipv4_count} 条 IPv4 段，以及 {$ipv6_count} 条 IPv6 段。\n";
} else {
    echo "错误：写入文件失败！\n";
}
?>
