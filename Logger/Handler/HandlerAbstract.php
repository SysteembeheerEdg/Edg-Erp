<?php

namespace Edg\Erp\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;

class HandlerAbstract extends Base
{
    /**
     * HandlerAbstract constructor.
     * @param DriverInterface $filesystem
     */
    public function __construct(
        DriverInterface $filesystem
    ) //@codingStandardsIgnoreLine
    {
        parent::__construct($filesystem);
    }
}