<?php

use Workerman\Worker;

use function Amp\delay;
use function Amp\defer;

use Wind\Beanstalk\BeanstalkClient;
use Wind\Beanstalk\BeanstalkException;

require __DIR__.'/common.php';

$worker = new Worker();
$worker->reusePort = false;
$worker->onWorkerStart = function() {
    defer(function() {
        $client = new BeanstalkClient('192.168.4.2', 11300, [
            'autoReconnect' => true,
            'reconnectDelay' => 5,
            'concurrent' => true
        ]);
        $client->debug = true;

        try {
            $client->connect();
            echo "Producer connect success.\n";

            $client->useTube('test');

            for ($i=0; $i<100; $i++) {
                delay(1000);
                echo "--producer ";
                $id = $client->put("Hello World");
                echo $id."--\n";
            }

            echo "Put finished.\n";
        } catch (\Throwable $e) {
            echo dumpException($e);
        }
    });

    defer(function() {
        $client = new BeanstalkClient('192.168.4.2', 11300, [
            'autoReconnect' => true,
            'reconnectDelay' => 5,
            'concurrent' => true
        ]);
        $client->debug = true;

        /*
        defer(function() use ($client) {
            delay(2000);
            echo "Close..\n";
            $client->close();
        });
        */

        try {
            echo "Start connect\n";
            $client->connect();
            echo "Connect success.\n";

            echo "Start watch\n";
            // $client->watch('test');

            try {
                $client->watch('test');
                echo "Watched\n";
            } catch (BeanstalkException $e) {
                echo "WatchError: " . $e->getMessage() . "\n";
            }

            try {
                echo "Start ignore\n";
                // $client->ignore('default');
                $client->ignore('default');
                echo "Ignored\n";
            } catch (BeanstalkException $e) {
                echo "IgnoreError: " . $e->getMessage() . "\n";
            }

            while ($data = $client->reserve()) {
                print_r($data);
                $client->delete($data['id']);
                delay(10);
            }
        } catch (\Exception $e) {
            echo dumpException($e);
        }
    });
};

Worker::runAll();
