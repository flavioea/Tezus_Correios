<?php

namespace Tezus\Correios\Logger;

use Monolog\Logger as MonologLogger;
use Magento\Framework\Logger\Handler\Base as LoggerHandlerBase;

class Handler extends LoggerHandlerBase {
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = MonologLogger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = "/var/log/tezus_correios.log";
}