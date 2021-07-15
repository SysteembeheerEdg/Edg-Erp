<?php

namespace Edg\Erp\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;

class HandlerAbstract extends Base
{
    /**
     * HandlerAbstract constructor.
     * @param DriverInterface $filesystem
     * @param string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        $filePath = '/var/log/bold_pim/'
    ) //@codingStandardsIgnoreLine
    {
        parent::__construct($filesystem, $filePath);
    }
}