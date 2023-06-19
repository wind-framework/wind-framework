<?php
/**
 * Wind Framework Merged Process
 */
namespace Wind\Process;

use function Amp\asyncCall;

/**
 * Merged Process
 *
 * Merged Process can run multiple process's code in one process.
 */
abstract class MergedProcess extends Process
{

    /**
     * Multiple process class names
     *
     * @var array
     */
    public $processes = [];

    /**
     * 进程执行代码，支持协程
     * @return void|\Generator|\Amp\Promise
     */
    public function run()
    {
        $app = getApp();

        foreach ($this->processes as $class) {
            /* @var $process Process */
            $process = $app->container->make($class);

            for ($i=0; $i<$process->count; $i++) {
                asyncCall([$process, 'run']);
            }
        }
    }

}
