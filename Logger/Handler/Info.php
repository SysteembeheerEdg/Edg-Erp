<?php
/**
 * Info
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Logger\Handler;

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
