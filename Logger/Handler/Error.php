<?php

namespace Edg\Erp\Logger\Handler;

use Monolog\Logger;

class Error extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/bold_pim/error.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::ERROR;
}
