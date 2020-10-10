<?php
namespace Framework\Channel;

use Workerman\Worker;

/**
 * Channel server.
 */
class Server
{
    /**
     * Worker instance.
     * @var Worker
     */
    protected $_worker = null;

    /**
     * Queues
     * @var Queue[]
     */
    protected $_queues = array();

    /**
     * Construct.
     * @param string $ip
     * @param int $port
     */
    public function __construct($ip = '0.0.0.0', $port = 2206)
    {
        $worker = new Worker("frame://$ip:$port");
        $worker->count = 1;
        $worker->name = 'ChannelServer';
        $worker->channels = array();
        $worker->onMessage = array($this, 'onMessage') ;
        $worker->onClose = array($this, 'onClose');
        $this->_worker = $worker;
    }

    /**
     * onClose
     * @return void
     */
    public function onClose($connection)
    {
        if (!empty($connection->channels)) {
	        foreach ($connection->channels as $channel) {
		        unset($this->_worker->channels[$channel][$connection->id]);
		        if (empty($this->_worker->channels[$channel])) {
			        unset($this->_worker->channels[$channel]);
		        }
	        }
        }

        if (!empty($connection->watchs)) {
        	foreach ($connection->watchs as $channel) {
        		if (isset($this->_queues[$channel])) {
        			$this->_queues[$channel]->removeWatch($connection);
        			if ($this->_queues[$channel]->isEmpty()) {
        				unset($this->_queues[$channel]);
			        }
		        }
	        }
        }
    }

    /**
     * onMessage.
     * @param \Workerman\Connection\TcpConnection $connection
     * @param string $data
     */
    public function onMessage($connection, $data)
    {
        if(!$data)
        {
            return;
        }
        $worker = $this->_worker;
        $data = unserialize($data);
        $type = $data['type'];
        switch($type)
        {
            case 'subscribe':
                foreach($data['channels'] as $channel)
                {
                    $connection->channels[$channel] = $channel;
                    $worker->channels[$channel][$connection->id] = $connection;
                }
                break;
            case 'unsubscribe':
                foreach($data['channels'] as $channel) {
                    if (isset($connection->channels[$channel])) {
                        unset($connection->channels[$channel]);
                    }
                    if (isset($worker->channels[$channel][$connection->id])) {
                        unset($worker->channels[$channel][$connection->id]);
                        if (empty($worker->channels[$channel])) {
                            unset($worker->channels[$channel]);
                        }
                    }
                }
                break;
            case 'publish':
                foreach ($data['channels'] as $channel) {
                    if (empty($worker->channels[$channel])) {
                        continue;
                    }
                    $buffer = serialize(array('type' => 'event', 'channel' => $channel, 'data' => $data['data']))."\n";
                    foreach ($worker->channels[$channel] as $connection) {
                        $connection->send($buffer);
                    }
                }
                break;
            case 'watch':
            	foreach ($data['channels'] as $channel) {
		            $this->getQueue($channel)->addWatch($connection);
	            }
                break;
            case 'unwatch':
	            foreach ($data['channels'] as $channel) {
		            if (isset($this->_queues[$channel])) {
			            $this->_queues[$channel]->removeWatch($connection);
			            if ($this->_queues[$channel]->isEmpty()) {
				            unset($this->_queues[$channel]);
			            }
		            }
	            }
                break;
            case 'enqueue':
            	foreach ($data['channels'] as $channel) {
		            $this->getQueue($channel)->enqueue($data['data']);
	            }
                break;
            case 'reserve':
				if (isset($connection->watchs)) {
					foreach ($connection->watchs as $channel) {
						if (isset($this->_queues[$channel])) {
							$this->_queues[$channel]->addConsumer($connection);
						}
					}
				}
                break;
        }
    }

    private function getQueue($channel)
    {
        if (isset($this->_queues[$channel])) {
            return $this->_queues[$channel];
        }
        return ($this->_queues[$channel] = new Queue($channel));
    }

}
