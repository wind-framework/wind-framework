<?php

return [
    'servers' => [
        'web' => [
            'listen' => '0.0.0.0:2345',
            'worker_num' => 2,
            'type' => 'http'
        ],
	    'channel' => [
		    'enable' => true,
	    	'listen' => '127.0.0.1:2206',
		    'type' => 'channel'
	    ]
    ]
];