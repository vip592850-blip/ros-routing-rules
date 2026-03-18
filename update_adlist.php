<?php
echo "开始拉取 Reject-List 广告与恶意域名数据...\n";

$url = "https://raw.githubusercontent.com/Loyalsoldier/v2ray-rules-dat/release/reject-list.txt";
$output_file = __DIR__ . "/reject_adlist.txt";

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
$out_content = "";

foreach ($lines as $line) {
    $line = trim($line);
    
    if (empty($line) || strpos($line, '#') === 0) continue;
    if (strpos($line, 'keyword:') === 0 || strpos($line, 'regexp:') === 0) continue;

    $line = explode('@', $line)[0];
    $line = trim($line);
    if (empty($line)) continue;

    $domain = "";
    
    if (strpos($line, 'full:') === 0) {
        $domain = substr($line, 5);
    } elseif (strpos($line, 'domain:') === 0) {
        $domain = substr($line, 7);
    } else {
        if (strpos($line, ':') === false) {
            $domain = $line;
        }
    }
    
    if (!empty($domain)) {
        $out_content .= "0.0.0.0 {$domain}\n";
        $rule_count++;
    }
}

if (file_put_contents($output_file, $out_content) !== false) {
    echo "成功！已生成 {$rule_count} 条广告拦截规则。\n";
} else {
    echo "错误：写入文件失败！\n";
}
?>
