<?php

namespace Edg\Erp\Logger;

use Edg\Erp\Logger\Handler\HandlerFactory;
use Monolog\Logger;

class PimLogger extends Logger
{
    /**
     * @var array
     */
    protected $defaultHandlerTypes = [
        'error',
        'info',
        'debug'
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct(
        HandlerFactory $handlerFactory,
        $name = 'pimlogger',
        array $handlers = [],
        array $processors = []
    ) {
        foreach ($this->defaultHandlerTypes as $handlerType) {
            if (!array_key_exists($handlerType, $handlers)) {
                $handlers[$handlerType] = $handlerFactory->create($handlerType);
            }
        }
        parent::__construct($name, $handlers, $processors);
    }

}
