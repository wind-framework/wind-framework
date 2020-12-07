# workerman-amphp

基于 Workerman + Amphp 实现纯 PHP 协程框架。

- Workerman 提供了 Socket 服务器、客户端，进程管理，Channel 等基础组件。
- Amphp 提供了纯 PHP 的协程实现，以及协程的 MySQL、Http 客户端等等。

---

运行环境：PHP 7.2 及以上 \
推荐扩展：event（建议生产环境安装此扩展）

---

目前框架拥有以下组件：

- HTTP 服务器（支持基于控制器路由的动态程序和静态文件）
- 依赖注入
- 缓存（实现 PSR-16 SimpleCache 的协程缓存）
- 进程信息收集组件
- 定时任务组件
- 协程 MySQL 客户端、支持连接池、查询构造器
- 日志组件（基于 MonoLog，支持异步写入）
- 自定义进程组件
- 异步消息队列组件（支持 Redis、Beanstalk 作为驱动）
- 协程 Redis 客户端
- TaskWorker（可将同步调用发到其它进程为异步调用）
- 视图组件（支持 Twig 等多种实现）

---

解放 PHP 的能力！