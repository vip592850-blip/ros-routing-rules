-----

# RouterOS v7 极客网络分流与防污染规则引擎

[](https://www.google.com/search?q=https://github.com/vip592850-blip/ros-routing-rules/actions)

本项目依托 GitHub Actions 全自动化 CI/CD 流水线，每天定时（北京时间凌晨 04:00）从全球权威的上游数据源抓取最新规则，并自动清洗、编译为 **RouterOS v7 (ROS)** 可直接挂载或导入的高效脚本文件。

借助 GitHub 全球 CDN 分发，实现零服务器成本维护，专为极客与高端玩家打造极致纯净、极速分流的网络体验。

-----

## 📦 核心功能模块与规则库

本项目每日自动编译并提供以下 3 个核心规则文件：

### 1\. 🛡️ 智能 DNS 防污染 (`proxy_dns.rsc`)

  * **上游数据源**：`Loyalsoldier/v2ray-rules-dat` (`proxy-list.txt`)
  * **规则数量**：约 2.5 万条被墙或体验极差的海外泛域名。
  * **运作原理**：利用 ROS v7 原生的 `match-subdomain=yes` 泛域名特性，将这些黑名单域名精准转发（FWD）至无污染的海外 DNS（如 `8.8.8.8`）进行解析，从根源解决 DNS 污染、连接重置与 CDN 跨国调度异常。

### 2\. 🚀 国内 IP 极速直连 (`cn_cache.rsc`)

  * **上游数据源**：APNIC 官方统计数据 (`delegated-apnic-latest`)
  * **规则数量**：约 8800 条 IPv4 及 2000+ 条 IPv6 中国大陆地址段。
  * **运作原理**：生成标准 ROS `address-list` 格式，可用于配置策略路由（PBR）或动态/静态路由表的精准分流，确保访问国内业务（如淘宝、B站）始终走本地最优出口，延迟最低。

### 3\. 🚫 全局广告与恶意域名黑洞 (`reject_adlist.txt`)

  * **上游数据源**：`Loyalsoldier/v2ray-rules-dat` (`reject-list.txt`)
  * **规则数量**：约 17 万条已知广告、隐私追踪器及恶意挖矿脚本域名。
  * **运作原理**：输出为极简的 `0.0.0.0 domain.com` 标准 Hosts 格式。完美适配 ROS v7.14+ 原生引入的 `/ip dns adlist` 引擎，由底层 C 语言极速解析，实现无感知的全屋网络去广告。

-----

## 🔗 获取订阅链接 (API 地址)

**方案 A：官方 GitHub 直连（推荐海外或网络环境良好的环境使用）**

  * DNS 转发规则：`https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/proxy_dns.rsc`
  * 国内 IP 地址簿：`https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/cn_cache.rsc`
  * 全局去广告规则：`https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/reject_adlist.txt`

**方案 B：国内代理加速版（解决 GitHub Raw 被墙或连接超时的痛点）**

> 感谢网友提供的高速转换代理。如果方案 A 在你的 ROS 内无法下载 (`fetch` 报错)，请直接在原始链接前加上 `https://listapp.linuxiarz.pl/convert?url=` 即可。

  * ⚡ DNS 转发规则 (加速)：`https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/proxy_dns.rsc`
  * ⚡ 国内 IP 地址簿 (加速)：`https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/cn_cache.rsc`
  * ⚡ 全局去广告规则 (加速)：`https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/reject_adlist.txt`

*(下文部署教程中，请根据自身网络情况，将 `$apiUrl` 变量替换为方案 A 或 B 的链接)*

-----

## 🚀 RouterOS v7 详细部署指南

为保护路由器 NAND Flash 闪存寿命并确保系统稳定，强烈建议采用\*\*“业务脚本 (Script) 与触发器 (Scheduler) 物理隔离”\*\*的显式调用规范。

### 一、 挂载去广告引擎 (Adlist)

*适用版本：ROS v7.14 及以上*
去广告模块无需编写复杂的下载脚本，ROS 原生支持极速远程挂载。请在 ROS 终端执行：

```routeros
/ip dns adlist
add url="https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/reject_adlist.txt" ssl-verify=no
```

### 二、 部署智能 DNS 防污染脚本

此脚本负责下载、清理旧规则并自动导入，自带容错机制。在 ROS 终端执行添加：

```routeros
/system script
add name=UpdateProxyDNS policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive source=" \
    :local apiUrl \"https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/proxy_dns.rsc\";\r\
    :local fileName \"proxy_dns.rsc\";\r\
    :log info \"[ProxyDNS] 开始获取最新 DNS 防污染规则...\";\r\
    \r\
    :do {\r\
        /tool fetch url=\$apiUrl mode=https dst-path=\$fileName;\r\
        :delay 3s;\r\
        \r\
        :if ([:len [/file find name=\$fileName]] > 0) do={\r\
            :log info \"[ProxyDNS] 下载成功，开始将其导入 DNS 缓存表...\";\r\
            /import file-name=\$fileName;\r\
            :delay 2s;\r\
            /file remove \$fileName;\r\
            :log info \"[ProxyDNS] 导入完成，临时文件已清理！\";\r\
        } else={\r\
            :log error \"[ProxyDNS] 规则下载失败，请检查 URL 代理连通性！\";\r\
        }\r\
    } on-error={\r\
        :log error \"[ProxyDNS] HTTPS 请求彻底失败，请检查路由器公网连接或上游 DNS！\";\r\
    }\
"
```

### 三、 部署国内 IP 直连脚本

在 ROS 终端执行添加：

```routeros
/system script
add name=UpdateCNIP policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive source=" \
    :local apiUrl \"https://listapp.linuxiarz.pl/convert?url=https://raw.githubusercontent.com/vip592850-blip/ros-routing-rules/main/cn_cache.rsc\";\r\
    :local fileName \"cn_cache.rsc\";\r\
    :log info \"[CN-IP] 开始获取最新国内 IP 地址段...\";\r\
    \r\
    :do {\r\
        /tool fetch url=\$apiUrl mode=https dst-path=\$fileName;\r\
        :delay 3s;\r\
        \r\
        :if ([:len [/file find name=\$fileName]] > 0) do={\r\
            :log info \"[CN-IP] 下载成功，开始导入地址列表...\";\r\
            /import file-name=\$fileName;\r\
            :delay 2s;\r\
            /file remove \$fileName;\r\
            :log info \"[CN-IP] 导入完成，临时文件已清理！\";\r\
        } else={\r\
            :log error \"[CN-IP] 下载失败！\";\r\
        }\r\
    } on-error={\r\
        :log error \"[CN-IP] HTTPS 请求彻底失败！\";\r\
    }\
"
```

### 四、 设定全自动定时任务 (Scheduler)

通过显式调用 `/system script run`，设定在每日凌晨网络闲时自动更新规则。

```routeros
/system scheduler
add name=Schedule_UpdateProxyDNS \
    on-event="/system script run UpdateProxyDNS" \
    policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive \
    start-time=04:00:00 interval=1d

add name=Schedule_UpdateCNIP \
    on-event="/system script run UpdateCNIP" \
    policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive \
    start-time=04:10:00 interval=1d
```

*(注：两个定时任务刻意错开 10 分钟执行，避免瞬间高负载导致 CPU 飙升。)*

-----

## 🛠️ 测试与排错指北

部署完毕后，建议您：

1.  前往 ROS `System` -\> `Scripts`，选中刚建好的脚本点击 **Run Script** 进行手动冷启动测试。
2.  打开 `Log` 窗口观察执行日志。若看到绿色的 `导入完成` 提示，即代表系统已稳定运行。
3.  请确保您的 ROS 已配置了稳定的大陆常用全局 DNS（如 `223.5.5.5`，`119.29.29.29`）。

-----
