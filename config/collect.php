<?php

return [
    'enable' => true,
	/**
	 * 指定 ChannelServer 的地址
	 *
	 * 如：127.0.0.1:2206
	 * 不指定 channel_server 则 Collector 组件内部会自动启动一个 ChannelServer。
	 * 多个服务依赖 ChannelServer 时建议启动单独的 ChannelServer 并指定。
	 */
    'channel_server' => null,
    'timeout' => 5
];