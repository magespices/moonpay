# Moonpay Payment Module

### Configuration
1. Log into the admin panel
1. Go to *Stores -> Configuration*
1. Go to *Sales -> Payment Methods*
1. Expand *Moonpay*
1. Set up the following fields:
   1. Enabled - Yes 
   1. Title - Method name you wish to be displayed in checkout
   1. New Order Status - order status after payment is completed
   1. Payment from Applicable Countries - whether the method should be available to selected countries
   1. Payment from Specific Countries - countries in which the payment method should be available
   1. Test mode - this settings allows you to use Moonpay in test mode
   1. Publishable key - public key available in Moonpay dashboard (*Developers* tab)
   1. Secret key - private key available in Moonpay dashboard (*Developers* tab)
   1. Bitcoin Address - BTC wallet address to receive the payments
1. Save configuration
1. Create a webhook:
   1. Log into Moonpay dashboard
   1. Go to *Developers* tab
   1. Go to *Webhooks* tab
   1. Click "Add Endpoint" (on the right)
   1. Type in the address: https://your_store_url/rest/default/V1/moonpay/transaction/save
   1. Check "Transaction created"
   1. Save the webhook

1. Get the Webhook key
    1. Log into Moonpay dashboard
    1. Go to *Developers* tab
    1. Copy the Webhook key

1. Configure the Webhook key
   1. Go to Magento admin panel
   1. Go to *Stores > Configuration*
   1. Go to *Sales > Payment Methods*
   1. Expand *Moonpay* section
   1. Fill in *Webhook Key* field with the key you just created in the Moonpay dashboard


### Additional information
* Minimum order amount is $20
* Maximum order amount is $12000
* Maximum order amount in test mode is $200
* While redirecting to Moonpay, following data is sent:
  * cryptocurrency code (BTC)
  * currency code (USD)
  * configured BTC wallet address
  * order amount in USD
  * customer e-mail address
  * customer ID from Magento
  * configured public API key
  * confirmation that the amount transferred has been blocked
* Order status is updated every 5 minutes via cron.
* In test mode public and private key differ from the production ones. Before switching mode, make sure the correct one is set in Moonpay dashboard.
