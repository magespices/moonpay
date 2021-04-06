<?php
/**
 * Created by Q-Solutions Studio
 * Date: 25.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\AbstractModel;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Data
 * @package Magespices\Moonpay\Helper
 */
class Data extends AbstractHelper
{
    /** @var string */
    public const PAYMENT_MOONPAY_ACTIVE_XPATH = 'payment/moonpay/active';

    /** @var string */
    public const PAYMENT_MOONPAY_TEST_MODE_XPATH = 'payment/moonpay/test_mode';

    /** @var string */
    public const PAYMENT_MOONPAY_PUBLISHABLE_KEY_XPATH = 'payment/moonpay/publishable_key';

    /** @var string */
    public const PAYMENT_MOONPAY_SECRET_KEY_XPATH = 'payment/moonpay/secret_key';

    /** @var string */
    public const PAYMENT_MOONPAY_WEBHOOK_KEY_XPATH = 'payment/moonpay/webhook_key';

    /** @var string */
    public const PAYMENT_MOONPAY_BITCOIN_ADDRESS_XPATH = 'payment/moonpay/bitcoin_address';

    /** @var string */
    public const STAGING_API_URL = 'https://buy-staging.moonpay.com';

    /** @var string */
    public const PRODUCTION_API_URL = 'https://buy.moonpay.com';

    /** @var string[] */
    public const GENERAL_REQUEST_PARAMS = [
        'enabledPaymentMethods' => 'credit_debit_card,apple_pay,google_pay,samsung_pay,sepa_bank_transfer,gbp_bank_transfer,gbp_open_banking_payment',
        'currencyCode' => 'btc',
        'baseCurrencyCode' => 'usd',
        'lockAmount' => 'true',
        'kycAvailable' => 'false',
        'showAllCurrencies' => 'false',
        'showWalletAddressForm' => 'false',
    ];

    /** @var string */
    public const TRANSACTION_API_URL = 'https://api.moonpay.com/v1/transactions/%s?apiKey=%s';

    /** @var string */
    public const TRANSACTION_STATUS_COMPLETED = 'completed';

    /** @var string */
    public const TRANSACTION_STATUS_FAILED = 'failed';

    /** @var string */
    public const TRANSACTION_STATUS_WAITING_PAYMENT = 'waitingPayment';

    /** @var string */
    public const TRANSACTION_STATUS_PENDING = 'pending';

    /** @var string */
    public const TRANSACTION_STATUS_WAITING_AUTHORIZATION = 'waitingAuthorization';

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var Payment
     */
    protected $paymentResourceModel;

    /**
     * Data constructor.
     * @param Context $context
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param Payment $paymentResourceModel
     */
    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        Payment $paymentResourceModel
    ) {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->paymentResourceModel = $paymentResourceModel;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PAYMENT_MOONPAY_ACTIVE_XPATH, ScopeInterface::SCOPE_STORES);
    }

    /**
     * @param string $path
     * @param string $scopeType
     * @param string|null $scopeId
     * @return mixed
     */
    public function getConfigValue(string $path, string $scopeType = ScopeInterface::SCOPE_STORES, string $scopeId = null)
    {
        return $this->scopeConfig->getValue($path, $scopeType, $scopeId);
    }

    /**
     * @return string
     */
    public function getApiUrl(): string
    {
        return (bool)$this->getConfigValue(self::PAYMENT_MOONPAY_TEST_MODE_XPATH) ?
            self::STAGING_API_URL : self::PRODUCTION_API_URL;
    }

    /**
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->_urlBuilder->getUrl('checkout/onepage/success');
    }

    /**
     * @param string $paymentId
     * @param Order $order
     * @return $this
     * @throws Exception
     */
    public function addPaymentIdToOrder(string $paymentId, Order $order): self
    {
        $this->checkOrder($order, true);
        $payment = $order->getPayment();
        if(!$payment->getLastTransId()) {
            $payment->setLastTransId($paymentId);
            $this->savePayment($payment);
        }
        return $this;
    }

    /**
     * @param OrderPaymentInterface|AbstractModel $payment
     * @return $this
     * @throws AlreadyExistsException
     */
    protected function savePayment(OrderPaymentInterface $payment): self
    {
        $this->paymentResourceModel->save($payment);
        return $this;
    }

    /**
     * @param Order $order
     * @return $this
     * @throws Exception
     */
    public function checkAndUpdateOrder(Order $order): self
    {
        $this->checkOrder($order);
        $transactionData = $this->getTransactionData($order->getPayment()->getLastTransId());

        if(isset($transactionData['errors'], $transactionData['message'])) {
            $errors = '';
            foreach($transactionData['errors'] as $error) {
                $errors .= sprintf(' %s (value: %s)',reset($error['constraints']), $error['value']);
            }

            $this->updateOrder($order, sprintf('%s Errors:%s', $transactionData['message'], $errors));
        }


        if(isset($transactionData['status'])) {
            switch ($transactionData['status']) {
                case self::TRANSACTION_STATUS_WAITING_AUTHORIZATION:
                case self::TRANSACTION_STATUS_PENDING:
                case self::TRANSACTION_STATUS_WAITING_PAYMENT:
                    if($order->getState() === Order::STATE_NEW) {
                        $this->updateOrder($order, 'Moonpay payment processing', Order::STATE_PROCESSING);
                    }
                    break;
                case self::TRANSACTION_STATUS_FAILED:
                    if(in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PROCESSING], true)) {
                        $order->cancel();
                        $this->updateOrder($order, 'Moonpay payment failed');
                    }
                    break;
                case self::TRANSACTION_STATUS_COMPLETED:
                    if(in_array($order->getState(), [Order::STATE_NEW, Order::STATE_PROCESSING], true)) {
                        $order->getPayment()->setAmountPaid($order->getBaseGrandTotal());
                        $order->getPayment()->setBaseAmountPaid($order->getBaseGrandTotal());
                        $order->setTotalPaid($order->getBaseGrandTotal());
                        $order->setBaseTotalPaid($order->getBaseGrandTotal());
                        $this->updateOrder($order, 'Moonpay payment complete',Order::STATE_COMPLETE);
                    }
                    break;
                default:
                    break;
            }
        }

        return $this;
    }

    /**
     * @param Order $order
     * @param bool $initialUpdate
     * @return $this
     * @throws Exception
     */
    protected function checkOrder(Order $order, bool $initialUpdate = false): self
    {
        $payment = $order->getPayment();
        if(!$payment || (!$initialUpdate && !$order->getPayment()->getLastTransId())) {
            throw new Exception('Required payment data for order (ID: %1) not found', $order->getEntityId());
        }
        return $this;
    }

    /**
     * @param string $paymentId
     * @return array
     */
    protected function getTransactionData(string $paymentId): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, sprintf(
            self::TRANSACTION_API_URL,
            $paymentId,
            $this->getConfigValue(self::PAYMENT_MOONPAY_PUBLISHABLE_KEY_XPATH)
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * @param int $entityId
     * @param string|null $field
     * @return Order
     */
    public function getOrder(int $entityId, string $field = null): Order
    {
        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $entityId, $field);
        return $order;
    }

    /**
     * @param Order $order
     * @param string $comment
     * @param string|null $state
     * @throws AlreadyExistsException
     */
    protected function updateOrder(Order $order, string $comment, string $state = null): void
    {
        if($state) {
            $order->setState($state)->setStatus($state);
            if($state === Order::STATE_COMPLETE) {
                $order->addStatusToHistory($state, $comment, false);
            }
        }

        if(!$state || $state !== Order::STATE_COMPLETE) {
            $order->addCommentToStatusHistory($comment);
        }

        $this->orderResourceModel->save($order);
    }
}