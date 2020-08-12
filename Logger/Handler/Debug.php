<?php

namespace Edg\Erp\Logger\Handler;

use Monolog\Logger;

class Debug extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = 'debug.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
