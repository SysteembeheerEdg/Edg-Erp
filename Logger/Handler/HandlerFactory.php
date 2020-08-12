<?php
/**
 * HandlerFactory
 *
 * @copyright Copyright © 2017 Bold Commerce BV. All rights reserved.
 * @author    dev@boldcommerce.nl
 */

namespace Bold\PIM\Logger\Handler;

use Bold\PIM\Logger\Handler\HandlerAbstract as ObjectType;
use InvalidArgumentException;
use Magento\Framework\ObjectManagerInterface;

class HandlerFactory
{
    /**
     * Object Manager instance
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected $instanceTypeNames = [
        'error' => '\\Bold\\PIM\\Logger\\Handler\\Error',
        'info' => '\\Bold\\PIM\\Logger\\Handler\\Info',
        'debug' => '\\Bold\\PIM\\Logger\\Handler\\Debug',
    ];

    /**
     * Factory constructor
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create corresponding class instance
     *
     * @param $type
     * @param array $data
     * @return ObjectType
     */
    public function create($type, array $data = array())
    {
        if (empty($this->instanceTypeNames[$type])) {
            throw new InvalidArgumentException('"' . $type . ': isn\'t allowed');
        }

        $resultInstance = $this->objectManager->create($this->instanceTypeNames[$type], $data);
        if (!$resultInstance instanceof ObjectType) {
            throw new InvalidArgumentException(get_class($resultInstance) . ' isn\'t instance of \Bold\PIM\Logger\Handler\HandlerAbstract');
        }

        return $resultInstance;
    }
}