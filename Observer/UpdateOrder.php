<?php
/**
 * Created by Q-Solutions Studio
 * Date: 26.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Observer;

use Exception;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magespices\Moonpay\Helper\Data;
use Psr\Log\LoggerInterface;

/**
 * Class UpdateOrder
 * @package Magespices\Moonpay\Observer
 */
class UpdateOrder implements ObserverInterface
{
    /** @var string */
    public const TRANSACTION_ID_PARAM = 'transactionId';

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * UpdateOrder constructor.
     * @param RequestInterface $request
     * @param Data $helper
     * @param LoggerInterface $logger
     */
    public function __construct(RequestInterface $request, Data $helper, LoggerInterface $logger)
    {
        $this->request = $request;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getData('order');
            if(!$order) {
                $orderId = $observer->getEvent()->getData('order_ids');
                $order = $this->helper->getOrder(reset($orderId));
            }
            $paymentId = $this->request->getParam(self::TRANSACTION_ID_PARAM);
            if($paymentId) {
                $this->helper->addPaymentIdToOrder($paymentId, $order)->checkAndUpdateOrder($order);
            }
        } catch (Exception $exception) {
            $this->logger->debug($exception->getMessage());
        }
    }
}