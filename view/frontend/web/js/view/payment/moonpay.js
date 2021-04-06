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
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'moonpay',
                component: 'Magespices_Moonpay/js/view/payment/method-renderer/moonpay-method'
            }
        );
        return Component.extend({});
    }
);