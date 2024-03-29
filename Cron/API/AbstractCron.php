<?php

namespace Edg\Erp\Cron\API;

use Edg\Erp\Helper\Data;
use Exception;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Logger\Monolog;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

abstract class AbstractCron
{
    const ERROR_EMAIL_TEMPLATE = 'error_email';

    /**
     * @var array
     */
    protected array $loglevels = [
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
     * @var Monolog
     */
    protected Monolog $monolog;

    /**
     * @var Data
     */
    protected Data $helper;

    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @var TransportBuilder
     */
    protected TransportBuilder $transportBuilder;

    /**
     * @var StoreManager
     */
    protected StoreManager $storeManager;

    /**
     * @var string|null
     */
    protected ?string $_exportDir = null;

    /**
     * @var string|null
     */
    protected ?string $_importDir = null;

    /**
     * @var string|null
     */
    protected ?string $_debugDir = null;

    /**
     * @var string|null
     */
    protected ?string $_stockmutationsDir = null;

    /**
     * @var array
     */
    protected array $settings;

    /**
     * @var bool
     */
    protected bool $_logOutputEnabled = false;

    /**
     * @param Data $helper
     * @param DirectoryList $directoryList
     * @param Monolog $monolog
     * @param ConfigInterface $config
     * @param TransportBuilder $transportBuilder
     * @param StoreManager $storeManager
     * @param array $settings
     * @throws FileSystemException
     */
    public function __construct(
        Data $helper,
        DirectoryList $directoryList,
        Monolog $monolog,
        ConfigInterface $config,
        TransportBuilder $transportBuilder,
        StoreManager $storeManager,
        array $settings = []
    )
    {
        $this->helper = $helper;
        $this->config = $config;
        $this->monolog = $monolog;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;

        $this->_exportDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/orderupload";
        $this->_importDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/orderstatus";
        $this->_stockmutationsDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/stockmutations";
        $this->_debugDir = $directoryList->getPath(DirectoryList::VAR_DIR) . "/webservice/debug";

        $this->settings = $this->initDefaultSettings($settings);

        $this->_checkPrerequisites();
    }

    protected function initDefaultSettings($settings): array
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
    protected function _checkPrerequisites(): AbstractCron
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
    protected function moduleLog($msg, bool $debug = false): AbstractCron
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
     * Retrieve email to send error report to
     */
    public function getErrorEmail()
    {
        return $this->helper->getLoggingSetting('erremail');
    }

    /**
     * #192: ERP koppeling - Rate limiter maken voor exception-emails
     * @param String $email
     * @param string $subject
     * @param String $content
     * @return $this
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws MailException
     */
    public function sendErrorMail(string $email, string $subject, string $content): AbstractCron
    {
        $lastSentErrorEmail = $this->helper->getSystemConfigSetting('bold/bold_release/last_sent_error_email');

        // Skip procedure if last sent error email is sent more then one hour ago (60 * 60 sec)
        if (($lastSentErrorEmail + (60 * 60)) > time()) {
            $this->moduleLog('Email exception suppressed.');
            return $this;
        }

            $storeId = $this->storeManager->getStore()->getId();

            $from = [
                'email' => $this->helper->getSystemConfigSetting('trans_email/ident_general/email'),
                'name' => $this->helper->getSystemConfigSetting('trans_email/ident_general/name')
            ];

            $templateOptions = [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId
            ];

        $transport = $this->transportBuilder->setTemplateIdentifier(self::ERROR_EMAIL_TEMPLATE)
            ->setTemplateOptions($templateOptions)
            ->setFrombyScope($from)
            ->addTo($email)
            ->setTemplateVars(
                [
                    'message' => $content,
                    'subject' => $subject
                ])
            ->getTransport();

            try {
                $transport->sendMessage();
            } catch (Exception $e) {
                $this->moduleLog('unable to send PIM error email ' . $e->getMessage() . ', ' . $subject . ', ' . $content,
                Logger::ERROR);
        }


        $this->config->saveConfig('bold/bold_release/last_sent_error_email', time(), 'default', 0);

        $this->storeManager->getStore()->resetConfig();

        return $this;
    }

    /**
     * @param $stream
     * @param $message
     * @param int $priority
     * @param array $params
     * @return void
     */
    protected function serviceLog($stream, $message, int $priority = Logger::INFO, array $params = [])
    {
        if (!in_array($priority, $this->loglevels)) {
            $priority = Logger::INFO;
        }

        if (!$stream) {
            $stream = $this->addLogStreamToServiceLogger('php://stderr');
        }

        $stream->log($priority, $message, $params);
    }

    /**
     * @param $path
     * @return Logger
     */
    protected function addLogStreamToServiceLogger($path): Logger
    {
        $logger = new Logger('app');

        $logger->pushHandler(new StreamHandler($path));

        return $logger;
    }

}
