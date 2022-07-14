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
 * @author      CedCommerce Core Team <connect@cedcommerce.com>
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
use Magento\Store\Model\StoreManagerInterface;
Class CheckSignup implements ObserverInterface
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    protected $request;
    protected $_scopeConfig;
    const SIGNUP_STATUS_DISABLE = 0;
    const SIGNUP_STATUS_ENABLE = 1;
    const TRANSACTION_TYPE_CREDIT =1;
    const TRANSACTION_TYPE_DEBIT =2;
    const USED = 1;
    const UNSED = 2;

    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager,
                                \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                RequestInterface $request, \Magento\Framework\Stdlib\DateTime\DateTime $date,
                                Encryptor $hashpas,
                                CustomerRepositoryInterface $customerRepository,
                                CustomerInterfaceFactory $customerDataFactory,
                                DataObjectHelper $dataObjectHelper,
                                \Magento\Framework\Message\ManagerInterface $messageManager,
                                StoreManagerInterface $storeManager,
                                \Magento\Customer\Model\Customer\Mapper $customerMapper,
                                \Ced\ReferralSystem\Helper\Data $data)
    {
        $this->request = $request;
        $this->_scopeConfig = $scopeConfig;
        $this->_date = $date;
        $this->_objectManager = $objectManager;
        $this->encryptor =$hashpas;
        $this->_customerRepository = $customerRepository;
        $this->_customerMapper = $customerMapper;
        $this->_customerDataFactory = $customerDataFactory;
        $this->_dataObjectHelper = $dataObjectHelper;
        $this->_messageManager = $messageManager;
        $this->storeManager = $storeManager;

        $this->_data = $data;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {

        try{
            $customer = $observer->getCustomer();
            $customer_id = $customer->getId();
            $referral_id = '';
            $this->saveInvitationCode($customer_id);
            $referral_code = $this->request->getPost('referral_code');
            $referal_source = 'email';
            $referal_source = $this->request->getPost('referal_source');
            if($referral_code!=""){
                $referral_id = $this->getCustomerIdByReferralCode($referral_code);
            }

            if ($referal_source!="" && $referral_id!="") {
                $referSource = $this->_objectManager->create('Ced\ReferralSystem\Model\Refersource');
                try{
                    $referSource->setData('customer_id', $referral_id);
                    $referSource->setData('referred_email',$customer->getEmail());
                    $referSource->setData('source', $referal_source);
                    $referSource->save();
                }catch(\Exception $e){
                    echo $e->getMessage();die("error1");
                }
            }

            $transaction=$this->_objectManager->create('Ced\ReferralSystem\Model\Transaction');
            $signup_bonus=$this->_scopeConfig->getValue('referral/system/signup_bonus');
            $referral_reward=$this->_scopeConfig->getValue('referral/system/referral_reward');

            if($referral_id!=''){
                $transaction->setData('customer_id', $customer_id);
                $transaction->setData('description', "Joining Bonus");
                $transaction->setData('earned_amount', $signup_bonus);
                $transaction->setData('transaction_type', self::TRANSACTION_TYPE_CREDIT);
                $transaction->setData('creation_date', $this->_date->gmtDate());
                $transaction->save();
                $signupBonusCreated = $this->createDiscountCoupon($customer);

                $message =__('Discount coupon code (%1) by $%2, Received for joining bonus on becoming our member.',$signupBonusCreated['coupon_code'],$signupBonusCreated['coupon_amount']);
                $message1 = __('Thank you for registering with %1. Congratulations! Here’s your. %2 joining bonus on becoming our member.', $this->storeManager->getStore()->getFrontendName(), $signup_bonus);
                $this->_data->sendCoupon($customer_id,$message1."<br/>".$message,__('Discount coupon code Received for referring,'),$signupBonusCreated['coupon_code']);

                if($signupBonusCreated && is_array($signupBonusCreated)){
                $this->_messageManager->addSuccessMessage($message1);
                }
            }

            if($referral_id !=''){
                $referred_friends_model = $this->_objectManager->create('Ced\ReferralSystem\Model\ReferList');
                $referred_friends = $referred_friends_model->getCollection()
                    ->addFieldToFilter('referred_email', $customer->getEmail())
                    ->addFieldToFilter('customer_id', $referral_id)
                    ->getFirstItem();

                if($referred_friends && $referred_friends->getId()){
                    $referred_friends_model->load($referred_friends->getId());
                    $referred_friends_model->setData('signup_status', self::SIGNUP_STATUS_ENABLE);
                    $referred_friends_model->setData('signup_date', $this->_date->gmtDate());
                    $referred_friends_model->setData('amount', $referral_reward);
                    $referred_friends_model->save();
                }
            }
        }catch(\Exception $e){
            echo $e->getMessage();die("error2");
        }
    }

    public function saveInvitationCode($customer_id){
        $customerData = $this->request->getParams();
        $customerId=$customer_id ;
        $savedCustomerData = $this->_customerRepository->getById($customerId);
        $customerm = $this->_customerDataFactory->create();
        $customerData = array_merge($this->_customerMapper->toFlatArray($savedCustomerData), $customerData);
        $customerData['id'] = $customerId;
        $this->_dataObjectHelper->populateWithArray(
            $customerm,
            $customerData,
            '\Magento\Customer\Api\Data\CustomerInterface'
        );
        try{
            $this->_customerRepository->save($customerm);
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

    public function createDiscountCoupon($currentCustomer) {
        $amount = $this->_scopeConfig ->getValue('referral/system/signup_bonus');
        $customer = $this->_objectManager->create('Magento\Customer\Model\Customer')->load($currentCustomer->getId());
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
        $promo_name = __('Discount coupon code Received for Signup ');
        $uniqueId = $this->generatePromoCode($couponlength);
        $rule = $this->_objectManager->create('Magento\SalesRule\Model\Rule');
        $rule->setName($promo_name);
        $rule->setDescription(__('Signup Bonus For Customer %1', $currentCustomer->getEmail()));
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
        $labels[1] = __('Signup Bonus For Customer ').$currentCustomer->getEmail();
        $rule->setStoreLabels($labels);
        try{
            $rule->save();
        }catch(\Exception $e){
            echo $e->getMessage();die("error3");
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
