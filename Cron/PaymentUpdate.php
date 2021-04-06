<?php
/**
 * Created by Q-Solutions Studio
 * Date: 26.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Cron;

use Exception;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magespices\Moonpay\Helper\Data;
use Magespices\Moonpay\Model\Payment\Moonpay;
use Psr\Log\LoggerInterface;

/**
 * Class PaymentUpdate
 * @package Magespices\Moonpay\Cron
 */
class PaymentUpdate
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * PaymentUpdate constructor.
     * @param Data $helper
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param OrderRepository $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Data $helper,
        OrderCollectionFactory $orderCollectionFactory,
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        OrderRepository $orderRepository,
        LoggerInterface $logger
    ) {
        $this->helper = $helper;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $orders = [];

        try {
            $orders = $this->getOrders();
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }

        foreach($orders as $order) {
            try {
                $this->helper->checkAndUpdateOrder($order);
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * @return Order[]
     * @throws InputException
     * @throws NoSuchEntityException
     */
    protected function getOrders(): array
    {
        $orders = [];
        $ordersCollection = $this->orderCollectionFactory->create()
            ->addFieldToSelect(Order::ENTITY_ID)
            ->addFieldToFilter(Order::STATE, ['nin' => [Order::STATE_COMPLETE, Order::STATE_CANCELED]]);

        $ordersCollection->getSelect()
            ->join(
                ['sop' => $ordersCollection->getConnection()->getTableName('sales_order_payment')],
                'main_table.entity_id = sop.parent_id',
                []
            )
            ->where('sop.method = ?', Moonpay::PAYMENT_CODE );

        foreach($ordersCollection->getItems() as $order) {
            $orders[] = $this->orderRepository->get($order->getId());
        }

        return $orders;
    }
}