<?php
echo "开始拉取 Proxy-List 域名数据...\n";

$url = "https://raw.githubusercontent.com/Loyalsoldier/v2ray-rules-dat/release/proxy-list.txt";
$output_file = __DIR__ . "/proxy_dns.rsc";
$forward_dns = "8.8.8.8";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
$data = curl_exec($ch);
curl_close($ch);

if (empty($data)) {
    die("错误：下载失败！\n");
}

$lines = explode("\n", $data);
$rule_count = 0;

$rsc_content = "/ip dns static remove [find forward-to=\"{$forward_dns}\" type=\"FWD\"]\n";

foreach ($lines as $line) {
    $line = trim($line);
    
    if (empty($line) || strpos($line, '#') === 0) continue;
    if (strpos($line, 'keyword:') === 0 || strpos($line, 'regexp:') === 0) continue;

    $line = explode('@', $line)[0];
    $line = trim($line);
    if (empty($line)) continue;

    if (strpos($line, 'full:') === 0) {
        $domain = substr($line, 5);
        $rsc_content .= "/ip dns static add forward-to={$forward_dns} match-subdomain=no name=\"{$domain}\" type=FWD\n";
        $rule_count++;
    } elseif (strpos($line, 'domain:') === 0) {
        $domain = substr($line, 7);
        $rsc_content .= "/ip dns static add forward-to={$forward_dns} match-subdomain=yes name=\"{$domain}\" type=FWD\n";
        $rule_count++;
    } else {
        if (strpos($line, ':') === false) {
            $rsc_content .= "/ip dns static add forward-to={$forward_dns} match-subdomain=yes name=\"{$line}\" type=FWD\n";
            $rule_count++;
        }
    }
}

if (file_put_contents($output_file, $rsc_content) !== false) {
    echo "成功！已生成 {$rule_count} 条 DNS 转发规则。\n";
} else {
    echo "错误：写入文件失败！\n";
}
?>
