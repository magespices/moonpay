<?php
/**
 * Created by Q-Solutions Studio
 * Date: 25.01.2021
 *
 * @category    Magescpies
 * @package     Magescpies_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Magespices\Moonpay\Api\TransactionInterface;
use Magespices\Moonpay\Helper\Data;

/**
 * Class Transaction
 * @package Magespices\Moonpay\Model
 */
class Transaction implements TransactionInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var UrlInterface
     */
    protected $url;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    protected $sortOrderBuilder;

    /**
     * Transaction constructor.
     * @param Data $helper
     * @param QuoteFactory $quoteFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param UrlInterface $url
     * @param RequestInterface $request
     * @param OrderRepository $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     */
    public function __construct(
        Data $helper,
        QuoteFactory $quoteFactory,
        QuoteResourceModel $quoteResourceModel,
        UrlInterface $url,
        RequestInterface $request,
        OrderRepository $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->helper = $helper;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->url = $url;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * @return string
     */
    public function save(): string
    {
        if($this->validSignature()) {
            $bodyParams = $this->request->getBodyParams();
            $order = $this->getOrderByCustomerId($bodyParams['data']['externalCustomerId']);
            if($order) {
                $this->helper->addPaymentIdToOrder($bodyParams['data']['id'], $order);
            }
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public function redirect(int $quoteId): ?string
    {
        $quote = $this->getQuote($quoteId);
        if(!$quote->isObjectNew()) {
            $params = array_merge(Data::GENERAL_REQUEST_PARAMS, [
                'apiKey' => $this->helper->getConfigValue(Data::PAYMENT_MOONPAY_PUBLISHABLE_KEY_XPATH),
                'walletAddress' => $this->helper->getConfigValue(Data::PAYMENT_MOONPAY_BITCOIN_ADDRESS_XPATH),
                'redirectURL' => $this->helper->getRedirectUrl(),
                'baseCurrencyAmount' => $quote->getBaseGrandTotal(),
                'email' => $quote->getCustomerEmail(),
                'externalCustomerId' => $quote->getCustomerId()
            ]);

            if(!$this->helper->getConfigValue(Data::PAYMENT_MOONPAY_TEST_MODE_XPATH)) {
                $params['signature'] = base64_encode(
                    hash_hmac(
                        'sha256',
                        sprintf('?%s', http_build_query($params)),
                        $this->helper->getConfigValue(Data::PAYMENT_MOONPAY_SECRET_KEY_XPATH),
                        true
                    )
                );
            }

            $params = http_build_query($params);

            return sprintf('%s?%s', $this->helper->getApiUrl(), $params);
        }
        return null;
    }

    /**
     * @param int $quoteId
     * @return Quote
     */
    protected function getQuote(int $quoteId): Quote
    {
        $quote = $this->quoteFactory->create();
        $this->quoteResourceModel->load($quote, $quoteId);
        return $quote;
    }

    /**
     * @param int $customerId
     * @return OrderInterface|null
     */
    protected function getOrderByCustomerId(int $customerId): ?OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('customer_id', $customerId)
            ->addSortOrder(
                $this->sortOrderBuilder->setField('entity_id')->setDescendingDirection()->create()
            )->create();
        return current($this->orderRepository->getList($searchCriteria)->getItems());
    }

    /**
     * @return bool
     */
    protected function validSignature(): bool
    {
        $webhookKey = $this->helper->getConfigValue(Data::PAYMENT_MOONPAY_WEBHOOK_KEY_XPATH);
        if(!$webhookKey) {
            return false;
        }

        $moonpayHeader = explode(',', $this->request->getHeader('Moonpay-Signature-V2'));
        $signedPayloadArray = [];
        foreach($moonpayHeader as $item) {
            $array = explode('=', $item);
            $signedPayloadArray[$item[0]] = $array[1];
        }

        $bodyParams = $this->request->getBodyParams();

        $signedPayload = hash_hmac(
            'sha256',
            $signedPayloadArray['t'] . '.' . json_encode($bodyParams, JSON_UNESCAPED_SLASHES),
            $webhookKey
        );

        return $signedPayload === $signedPayloadArray['s'];
    }
}