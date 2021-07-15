<?php

namespace Edg\Erp\Logger\Handler;

use Monolog\Logger;

class Debug extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/bold_pim/debug.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
