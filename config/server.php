<?php

return [
    /**
     * 内置 Server 配置
     */
    'servers' => [
        /**
         * Http 服务器
         */
        [
            'listen' => '0.0.0.0:2345',
            'worker_num' => 2,
            'type' => 'http'
        ],
        /**
         * Channel 通信服务器
         */
        [
		    'enable' => true,
	    	'listen' => '127.0.0.1:2206',
		    'type' => 'channel'
	    ]
    ],
    'static_file' => [
        'document_root' => BASE_DIR.'/static',
        'enable_negotiation_cache' => true
    ],
    /**
     * Task Worker 配置
     */
    'task_worker' => [
        /**
         * Task Worker 进程数量
         * 为 0 时将不启动任何 Task Worker 进程
         */
        'worker_num' => 2,
        /**
         * Task Worker 通信 Channel 地址
         * 为 null 时则默认使用本地地址，即 127.0.0.1:2206
         */
        'channel_server' => env('CHANNEL_SERVER')
    ]
];