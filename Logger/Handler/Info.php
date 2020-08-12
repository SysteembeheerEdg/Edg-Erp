<?php

namespace Edg\Erp\Logger\Handler;

use Monolog\Logger;

class Info extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = 'info.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;
}
