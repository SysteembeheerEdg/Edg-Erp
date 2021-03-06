<?php

namespace Edg\Erp\Logger\Handler;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;

class HandlerAbstract extends Base
{
    /**
     * HandlerAbstract constructor.
     *
     * Set default filePath for PimLogger logs folder
     *
     * @param DriverInterface $filesystem
     * @param null|string $filePath
     */
    public function __construct(
        DriverInterface $filesystem,
        $filePath = BP . '/var/log/bold_pim/'
    ) //@codingStandardsIgnoreLine
    {
        parent::__construct($filesystem, $filePath);
    }
}
