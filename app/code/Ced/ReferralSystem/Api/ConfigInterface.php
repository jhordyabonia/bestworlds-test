<?php
namespace Ced\ReferralSystem\Api;

use Magento\Framework\Data\ObjectFactory;

interface ConfigInterface
{

    const PATH = 'referral/system/';
    /**
     * @api
     * @param string $path = 'signup_bonus|referral_reward|referral_order_minimum_amount|referral_reward_denomination_range|default_message
     *                referral_reward_max_denomination_range|discount_code_expiration_days|support_email|default_email_subject  '
     * @param string $value = ''
     * @return string
     */
    public function setConfig($config, $value);

    /**
     * @api
     * @param string  $path = 'signup_bonus|referral_reward|referral_order_minimum_amount|referral_reward_denomination_range|default_message
     *                referral_reward_max_denomination_range|discount_code_expiration_days|support_email|default_email_subject  '
     * @return string
     */
    public function getConfig($config);
}
