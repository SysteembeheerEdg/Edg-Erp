<?php
/**
 * Debug
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Logger\Handler;

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
