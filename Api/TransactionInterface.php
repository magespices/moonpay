<?php
/**
 * Created by Q-Solutions Studio
 * Date: 25.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */

namespace Magespices\Moonpay\Api;

/**
 * Interface TransactionInterface
 * @package Magespices\Moonpay\Api
 */
interface TransactionInterface
{
    /**
     * @param int $quoteId
     * @return string|null
     */
    public function redirect(int $quoteId): ?string;

    /**
     * @return string
     */
    public function save(): string;
}