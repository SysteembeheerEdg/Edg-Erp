<?php

namespace Edg\Erp\Logger\Handler;

use Edg\Erp\Logger\Handler\HandlerAbstract as ObjectType;
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
        'error' => '\\Edg\\Erp\\Logger\\Handler\\Error',
        'info' => '\\Edg\\Erp\\Logger\\Handler\\Info',
        'debug' => '\\Edg\\Erp\\Logger\\Handler\\Debug',
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
            throw new InvalidArgumentException(get_class($resultInstance) . ' isn\'t instance of \Edg\Erp\Logger\Handler\HandlerAbstract');
        }

        return $resultInstance;
    }
}