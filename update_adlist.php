<?php
echo "开始拉取 Reject-List 广告与恶意域名数据...\n";

$url = "https://raw.githubusercontent.com/Loyalsoldier/v2ray-rules-dat/release/reject-list.txt";
$output_file = __DIR__ . "/reject_adlist.txt";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 放宽超时时间到2分钟，防止 GitHub 节点波动
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
    
    // 丢弃注释和空行
    if (empty($line) || strpos($line, '#') === 0) continue;
    // 丢弃 ROS 无法处理的关键词和正则匹配
    if (strpos($line, 'keyword:') === 0 || strpos($line, 'regexp:') === 0) continue;

    // 清洗域名
    if (strpos($line, 'full:') === 0) {
        $domain = substr($line, 5);
    } else {
        $domain = str_replace('domain:', '', $line);
    }
    
    // 去除规则中可能带有的环境标签（如 @ads）
    $domain = explode('@', $domain)[0];
    $domain = trim($domain);
    
    if (!empty($domain)) {
        // 拼接成标准 Hosts 格式并换行
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
