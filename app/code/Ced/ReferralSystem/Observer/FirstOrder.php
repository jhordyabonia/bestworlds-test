<?php

/**
 * CedCommerce
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the End User License Agreement (EULA)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://cedcommerce.com/license-agreement.txt
 *
 * @category    Ced
 * @package     Ced_ReferralSystem
 * @author 		CedCommerce Core Team <connect@cedcommerce.com>
 * @copyright   Copyright CedCommerce (http://cedcommerce.com/)
 * @license      http://cedcommerce.com/license-agreement.txt
 */

namespace Ced\ReferralSystem\Observer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Encryption\EncryptorInterface as Encryptor;
Class FirstOrder implements ObserverInterface
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    protected $request;
    const SIGNUP_STATUS_DISABLE = 0;
    const SIGNUP_STATUS_ENABLE = 1;
    const TRANSACTION_TYPE_CREDIT =1;
    const TRANSACTION_TYPE_DEBIT =2;
    const USED = 1;
    const UNSED = 2;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        RequestInterface $request,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Customer\Model\Session $session
    )
    {
        $this->request = $request;
        $this->_date = $date;
        $this->_scopeConfig = $scopeConfig;
        $this->_customerSession = $session;
        $this->_objectManager = $objectManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try{
            $order = $observer->getOrder()->getId();
            $customerId = $observer->getOrder()->getCustomerId();
            $customerOrders = $this->_objectManager->create('Magento\Sales\Model\Order')->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('grand_total',['gteq'=>10])
                ->count();

            if($customerOrders==1){
                $customer = $this->_customerSession->getCustomer();
                $referral_code = $customer->getReferralCode();
                $referral_id = $this->getCustomerIdByReferralCode($referral_code);

                $transaction=$this->_objectManager->create('Ced\ReferralSystem\Model\Transaction');
                $referral_reward=$this->_scopeConfig->getValue('referral/system/referral_reward');

                if($referral_id!=''){
                    $transaction = $this->_objectManager->create('Ced\ReferralSystem\Model\Transaction');
                    $transaction->setData('customer_id', $referral_id);
                    $transaction->setData('description', "Referral Reward For-".$customer->getEmail());
                    $transaction->setData('creation_date', $this->_date->gmtDate());
                    $transaction->setData('earned_amount', $referral_reward);
                    $transaction->setData('transaction_type', self::TRANSACTION_TYPE_CREDIT);
                    $transaction->save();
                }
            }
        }catch(\Exception $e){
            echo $e->getMessage();
        }

    }

    public function getCustomerIdByReferralCode($referral_code){
        $code=$this->_objectManager->get('Magento\Customer\Model\Customer')->getCollection()->addAttributeToFilter('invitation_code', $referral_code)->getData();
        foreach ($code as $key => $value) {
            $customerId= $value['entity_id'];
        }
        if(isset($customerId)){
            return $customerId;
        }
    }


    public function createDiscountCoupon($referral_id) {

        $currentCustomer = $this->_customerSession->getCustomer();
        $amount = $this->_scopeConfig ->getValue('referral/system/referral_reward');
        $customer = $this->_objectManager->create('Magento\Customer\Model\Customer')->load($referral_id);
        $customer_Id = $customer->getId ();
        $customer_Email = $customer->getEmail();
        $discountType = "cart_fixed";
        $discountAmount =  $amount;
        $percoupon = 1;
        $percustomer = 1;
        $email = $customer_Email;

        $minpurchase = 0;
        $expireDays = 0;
        $expireDays = $this->_scopeConfig->getValue('referral/system/discount_code_expiration_days');
        $today = date_create($this->_date->date('Y-m-d H:i:s'));

        $next = date_format(date_add($today, date_interval_create_from_date_string($expireDays." days")),"Y-m-d H:i:s");
        $couponlength=8;
        $promo_name = __('Discount coupon code Received for referring '). $currentCustomer->getEmail();
        $uniqueId = $this->generatePromoCode($couponlength);
        $rule = $this->_objectManager->create('Magento\SalesRule\Model\Rule');
        $rule->setName($promo_name);
        $rule->setDescription(__('Discount coupon code Received for referring %1', $currentCustomer->getEmail()));
        $rule->setCouponCode($uniqueId);
        $rule->setFromDate($this->_date->date('Y-m-d H:i:s'));
        $rule->setToDate($next);
        $rule->setUsesPerCoupon($percoupon);
        $rule->setUsesPerCustomer($percustomer);
        $customerGroups = $this->_objectManager->get('Magento\Customer\Model\Group')->getCollection();
        $groups = [];
        foreach ($customerGroups as $group){
            $groups[] = $group->getId();
        }

        $conditions =
            [
                '1' =>
                    [
                        'type' => 'Magento\SalesRule\Model\Rule\Condition\Combine',
                        'aggregator' => 'all',
                        'value' => '1',
                        'new_child' => ''
                    ],

                '1--1' =>
                    [
                        'type' => 'Magento\SalesRule\Model\Rule\Condition\Address',
                        'attribute' => 'base_subtotal',
                        'operator' => '>=',
                        'value' => $minpurchase
                    ]

            ];
        $rule->setData('conditions' , $conditions);
        $rule->setCustomerGroupIds($groups);
        $rule->setIsActive(1);
        $rule->setStopRulesProcessing(1);
        $rule->setIsRss(0);
        $rule->setIsAdvanced(1);
        $rule->setSortOrder(0);
        $rule->setSimpleAction($discountType);
        $rule->setDiscountAmount($discountAmount);
        $rule->setDiscountQty(0);
        $rule->setDiscountStep(0);
        $rule->setSimpleFreeShipping(0);
        $rule->setApplyToShipping(0);
        $rule->setWebsiteIds(array(1));
        $rule->loadPost($rule->getData());
        $rule->setCouponType(2);
        $labels = [];
        $labels[1] = __('Discount coupon code Received for referring ').$currentCustomer->getEmail();
        $rule->setStoreLabels($labels);
        try{
            $rule->save();
        }catch(\Exception $e){
            $e->getMessage();
        }

        $couponModel = $this->_objectManager->get('Ced\ReferralSystem\Model\Coupon');
        $couponModel->setData('customer_id', $customer_Id);
        $couponModel->setData('email_id', $customer_Email);
        $couponModel->setData('coupon_code', $uniqueId);
        $couponModel->setData('status', self::UNSED);
        $couponModel->setData('expiration_date', $next);
        $couponModel->setData('amount', $discountAmount);
        $couponModel->setData('cart_amount', $minpurchase);
        try{
            $couponModel->save();
        }catch(\Exception $e){
            return false;
        }
        return ['success'=>true, 'coupon_code'=>$uniqueId, 'coupon_amount'=>$discountAmount];
    }

    private function generatePromoCode($length = null)
    {
        $rndId = md5(uniqid(rand(),1));
        $rndId = strip_tags(stripslashes($rndId));
        $rndId = str_replace(array(".", "$"),"",$rndId);
        $rndId = strrev(str_replace("/","",$rndId));
        if (!is_null($rndId)){
            return strtoupper(substr($rndId, 0, $length));
        }
        return strtoupper($rndId);
    }
}
