/**
 * Created by Q-Solutions Studio
 * Date: 25.01.2021
 *
 * @category    Magespices
 * @package     Magespices_Moonpay
 * @author      Maciej Buchert <maciej@qsolutionsstudio.com>
 */
define(
    [
        'jquery',
        'mage/translate',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/quote',
    ],
    function ($, $t, Component, url, quote) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Magespices_Moonpay/payment/moonpay',
            },
            redirectAfterPlaceOrder: false,

            afterPlaceOrder: function () {
                $.ajax({
                    type: 'POST',
                    url: url.build(`/rest/default/V1/moonpay/transaction/redirect/${quote.getQuoteId()}`),
                }).done(function (data) {
                    if(data) {
                        window.location.replace(data);
                    }
                });
            },
        });
    }
);
