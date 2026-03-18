***

# RouterOS v7 极客分流与防污染规则全自动生成器

本项目利用 GitHub Actions 的全自动化 CI/CD 流程，每天定时从上游权威数据源拉取最新规则，并自动清洗、编译为 RouterOS v7 可直接导入的 `.rsc` 脚本文件。
完全依靠 GitHub 全球 CDN 分发，**零服务器成本，极速更新**。

## 📦 生成的规则文件

每天自动抓取并生成以下两个核心文件：

1. **`proxy_dns.rsc` (防 DNS 污染黑名单)**
   * **数据源**：`Loyalsoldier/v2ray-rules-dat` (`proxy-list.txt`)
   * **作用**：提取约 2.5 万条被墙或体验不佳的海外泛域名，利用 ROS v7 的 `match-subdomain=yes` 特性，将其强制转发（FWD）给安全 DNS（如 `8.8.8.8`）进行解析，从根源解决 DNS 污染与 CDN 乱跑问题。
2. **`cn_cache.rsc` (国内 IP 明细路由)**
   * **数据源**：APNIC 官方统计数据
   * **作用**：生成最新的中国大陆 IPv4 / IPv6 地址段，用于配置本地路由直连或策略路由分流。

---

## 🚀 RouterOS v7 详细部署流程 (最佳实践)

为了保障系统稳定性及保护路由器 Flash 闪存寿命，强烈建议采用**“业务脚本 (Script) 与 触发器 (Scheduler) 物理隔离”**的显式调用架构。

### 第一阶段：获取你的专属直链 (RAW URL)
部署前，请先进入你的 GitHub 仓库，点击生成的 `.rsc` 文件，再点击右上角的 **Raw** 按钮，获取真实的下载链接。
格式如下：
* `https://raw.githubusercontent.com/[你的用户名]/[仓库名]/main/proxy_dns.rsc`
* `https://raw.githubusercontent.com/[你的用户名]/[仓库名]/main/cn_cache.rsc`

### 第二阶段：配置核心下载与导入脚本 (System Script)

该脚本负责文件下载、自动清理旧规则、导入新规则以及自动删除临时文件，内建完整的 Try-Catch 错误拦截机制。

在 ROS 终端中依次执行以下命令添加脚本：

**1. 添加 DNS 防污染更新脚本**
> ⚠️ **注意：** 请将 `apiUrl` 变量的值替换为你自己的 GitHub Raw 链接！

```routeros
/system script
add name=UpdateProxyDNS policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive source=" \
    :local apiUrl \"https://raw.githubusercontent.com/你的用户名/仓库名/main/proxy_dns.rsc\";\r\
    :local fileName \"proxy_dns.rsc\";\r\
    :log info \"[ProxyDNS] 开始从 GitHub 拉取最新 DNS 黑名单规则...\";\r\
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
            :log info \"[ProxyDNS] 导入完成，清理本地临时文件成功！\";\r\
        } else={\r\
            :log error \"[ProxyDNS] 文件未生成，请检查 GitHub URL 是否正确！\";\r\
        }\r\
    } on-error={\r\
        :log error \"[ProxyDNS] HTTPS 请求彻底失败，请检查路由器公网连接或 DNS 解析！\";\r\
    }\
"
```

**2. 添加国内 IP 段更新脚本（如果需要通过 RSC 导入路由）**
> 💡 **进阶建议：** 对于小内存路由器（如 hAP ax lite），建议通过 BIRD 等动态路由协议（BGP）下发 IP 段至内存，以减少 Flash 擦写。如确需静态导入，可使用以下脚本。

```routeros
/system script
add name=UpdateCNIP policy=ftp,reboot,read,write,policy,test,password,sniff,sensitive source=" \
    :local apiUrl \"https://raw.githubusercontent.com/你的用户名/仓库名/main/cn_cache.rsc\";\r\
    :local fileName \"cn_cache.rsc\";\r\
    :log info \"[CN-IP] 开始从 GitHub 拉取最新国内 IP 段...\";\r\
    \r\
    :do {\r\
        /tool fetch url=\$apiUrl mode=https dst-path=\$fileName;\r\
        :delay 3s;\r\
        \r\
        :if ([:len [/file find name=\$fileName]] > 0) do={\r\
            :log info \"[CN-IP] 下载成功，开始导入路由表...\";\r\
            /import file-name=\$fileName;\r\
            :delay 2s;\r\
            /file remove \$fileName;\r\
            :log info \"[CN-IP] 导入完成，清理本地临时文件成功！\";\r\
        } else={\r\
            :log error \"[CN-IP] 文件未生成！\";\r\
        }\r\
    } on-error={\r\
        :log error \"[CN-IP] HTTPS 请求彻底失败！\";\r\
    }\
"
```

### 第三阶段：配置定时任务 (System Scheduler)

通过显式调用 `/system script run [任务名称]` 的方式触发脚本，避免权限环境错乱。
为了保护路由器 NAND Flash 闪存寿命，定时任务设定为每天凌晨 4 点（网络闲时）执行一次。

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
*(注：两个定时任务建议错开 10 分钟执行，避免同时高负载下载和导入导致 CPU 飙升。)*

---

## 🛠️ 运维与测试建议

1. **手动测试机制**：部署完成后，可前往 ROS `System` -> `Scripts` 菜单，选中对应的脚本点击 **Run Script**，随后观察 `Log` 面板是否有成功的绿色日志输出。
2. **DNS 配置前置要求**：在使用 `proxy_dns.rsc` 前，请确保 ROS 的 `/ip dns` 菜单中已经配置了可靠的上游全局 DNS（如 `223.5.5.5` 等），且路由器的网络能够正常访问海外 `raw.githubusercontent.com` 节点。

***
