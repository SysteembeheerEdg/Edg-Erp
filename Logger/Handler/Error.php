<?php
/**
 * Error
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Logger\Handler;

use Monolog\Logger;

class Error extends HandlerAbstract
{
    /**
     * @var string
     */
    protected $fileName = 'error.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::ERROR;
}
