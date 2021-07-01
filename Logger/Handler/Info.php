<?php

namespace Edg\Erp\Logger\Handler;

use Monolog\Logger;

class Info extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/bold_pim/info.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;
}
