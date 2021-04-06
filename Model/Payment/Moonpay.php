<?php
/**
 * Created by Q-Solutions Studio
 * Date: 25.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Class Moonpay
 * @package Magespices\Moonpay\Model\Payment
 */
class Moonpay extends AbstractMethod
{
    /** @var string */
    public const PAYMENT_CODE = 'moonpay';

    /**
     * @var string
     */
    protected $_code = self::PAYMENT_CODE;

    /**
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(CartInterface $quote = null): bool
    {
        if((boolean)$this->getConfigData('test_mode', $quote->getStoreId())
            && $quote->getBaseGrandTotal() > 200) {
            return false;
        }
        return parent::isAvailable($quote);
    }
}

