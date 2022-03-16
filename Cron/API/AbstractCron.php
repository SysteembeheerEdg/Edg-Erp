<?php

namespace Edg\Erp\Cron\API;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Mail\EmailMessage;
use Monolog\Logger;
use Zend\Log\Writer\Stream;

abstract class AbstractCron
{
    protected $loglevels = [
        Logger::EMERGENCY,
        Logger::ALERT,
        Logger::CRITICAL,
        Logger::ERROR,
        Logger::WARNING,
        Logger::NOTICE,
        Logger::INFO,
        Logger::DEBUG
    ];

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Edg\Erp\Helper\Data
     */
    protected $helper;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var EmailMessage
     */
    protected $email;

    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $storeManager;


    protected $_exportDir = null;
    protected $_importDir = null;
    protected $_debugDir = null;
    protected $_stockmutationsDir = null;
    protected $settings;

    /**
     * @var bool
     */
    protected $_logOutputEnabled = false;

    public function __construct(
        \Edg\Erp\Helper\Data $helper,
        DirectoryList $directoryList,
        ConfigInterface $config,
        EmailMessage $message,
        \Magento\Store\Model\StoreManager $storeManager,
        $settings = []
    ) {
        $this->logger = new Logger();
        $this->helper = $helper;
        $this->config = $config;
        $this->email = $message;
        $this->storeManager = $storeManager;

        $this->_exportDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/orderupload";
        $this->_importDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/orderstatus";
        $this->_stockmutationsDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/stockmutations";
        $this->_debugDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/debug";

        $this->settings = $this->initDefaultSettings($settings);

        $this->_checkPrerequisites();
    }

    protected function initDefaultSettings($settings)
    {
        return array_merge([
            'force_order_upload' => false/** @see \Edg\Erp\Cron\API\OrderExport */,
            'id_prefix' => null,
            'order_id' => null
        ], $settings);
    }

    /**
     * Make sure all logging directories are present
     */
    protected function _checkPrerequisites()
    {

        $dirs = [
            $this->_exportDir,
            $this->_importDir,
            $this->_stockmutationsDir,
            $this->_debugDir
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                $this->moduleLog(printf('Creating output directory "%s"', $dir));
                mkdir($dir, 0777, true);
            }
        }


        return $this;
    }

    /**
     * write to the main log file of this Magento extension
     *
     * @param $msg
     * @param bool $debug
     * @return $this
     */
    protected function moduleLog($msg, $debug = false)
    {
        if ($this->_logOutputEnabled === true) {
            if (php_sapi_name() !== 'cli') {
                $msg = htmlspecialchars($msg);
            }

            echo $msg . PHP_EOL;
        }

        $this->helper->log($msg, $debug);
        return $this;
    }

    public abstract function execute();

    /**
     *
     */
    public function enabledLogOutput()
    {
        $this->_logOutputEnabled = true;
        return $this;
    }

    /**
     * Retrieve email to send error report to
     */
    public function getErrorEmail()
    {
        return $this->helper->getLoggingSetting('erremail');
    }

    /**
     * #192: ERP koppeling - Rate limiter maken voor exception-emails
     * @param String $email
     * @param String $subject
     * @param String $content
     * @return $this
     */
    public function sendErrorMail($email, $subject, $content)
    {
        $lastSentErrorEmail = $this->helper->getSystemConfigSetting('bold/bold_release/last_sent_error_email');

        // Skip procedure if last sent error email is sent more then one hour ago (60 sec * 60 min)
        if (($lastSentErrorEmail + (60 * 60)) > time()) {
            $this->moduleLog('Email exception suppressed.');
            return $this;
        }

        $mail = $this->email;
        $mail
            ->setMessageType(EmailMessage::TYPE_TEXT)
            ->addTo($email)
            ->setFrom(
                $this->helper->getSystemConfigSetting('trans_email/ident_general/email'),
                $this->helper->getSystemConfigSetting('trans_email/ident_general/name')
            )->setBodyText($content)
            ->setSubject($subject);

        try {
            $mail->send();
        } catch (\Exception $e) {
            $this->moduleLog('unable to send PIM error email ' . $e->getMessage() . ', ' . $subject . ', ' . $content,
                Logger::ERROR);
        }

        $this->config->saveConfig('bold/bold_release/last_sent_error_email', time(), 'default', 0);

        $this->storeManager->getStore()->resetConfig();
        return $this;
    }

    /**
     * write to an api service specific log file.
     *
     * writes log message to to a file. first call addLogStreamToServiceLogger to set a log writer.
     * If no log writer is set, STDERR will be used to log messages.
     *
     * @param $message
     * @param int $priority
     * @param array $params
     */
    protected function serviceLog($message, $priority = Logger::INFO, $params = [])
    {
        if (!in_array($priority, $this->loglevels)) {
            $priority = Logger::INFO;
        }

        if (!$this->zendLogger->getWriters()) {
            $this->addLogStreamToServiceLogger('php://stderr');
        }

        $this->zendLogger->log($priority, $message, $params);
    }

    protected function addLogStreamToServiceLogger($path)
    {
        $writer = new Stream($path);
        $this->zendLogger->addWriter($writer);
        return $this;
    }
}
