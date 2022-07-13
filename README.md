# bestworlds-test
Test to new developers
##Background of the exam:
<br/>
We bought this extension from CED Commerce (https://cedcommerce.com/magento-2-extensions/refer-a-friend). We paid for the specific customization that we need for installing on one of our clients but the module is not working as expected.
<br/>
### Requisites:
Install a demo Magento 2.2.8 with sample data
Install the module provided on the zip file attached to the task
Flow Requested
<br/>
### Main Scenario:
User john@doe.com registers to the site.
He sends Jane Winter a referral code
Jane Winter registers to the site (email jane@winter.com) using the referral code that john@doe.com sent her.
She gets a $5 coupon discount, just for registering using the referral code.
She makes a new order where the subtotal amount is equal or higher than $10.
John Doe gets a $5 discount coupon due to Jane Winter's order.
Jane Winter makes several new orders but John Doe won't get a coupon for next orders.
<br/>
### Another possible scenario:
User will@smith.com registers to the site using a referral code sent by john@doe.com
The first one gets a $5 coupon discount just for registering using the referral code.
He makes an order where subtotal is lower than $10.
John Doe doesn't get any coupon for this order
Will Smith makes several new orders (lower and higher than $10) but John Doe doesn't get any coupon for those.
<br/>
The only possible scenario where referral is getting a coupon is on the referrer's first order and if the subtotal is equal or higher than $10.
<br/>
>Apply the necessary fixes in order to get the requested flow working as it was described above. 
<br/>
>That $10 limit should be configurable through the Magento Admin panel by adding a new configuration option on the module.
<br/>
>reate a new REST API Endpoint that allows changing that same limit configuration value. You can use Postman or any other REST API application in order to test the results.
<br/>
Here is a screenshot of the actual configuration that we have on our client's staging site:
http://screen1.me/moduleConfig_2281ED2F.png
<br/>
If you find that different configurations are needed in order to achieve the requested flow, please add some screenshots within the zip containing the module changes along with everything you consider necessary.
<br/>
CED Module Zip => https://drive.google.com/file/d/1WgfHmlza61DVRFqE6Dizzt7Fb76c-vc9/view?usp=sharing
