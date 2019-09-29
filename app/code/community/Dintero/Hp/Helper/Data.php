<?php

/**
 * Class Dintero_Hp_Helper_Config
 */
class Dintero_Hp_Helper_Data extends Mage_Core_Helper_Data
{
    /*
     * XPATH Check if the payment method is active
     */
    const XPATH_IS_ACTIVE = 'payment/dintero/active';

    /*
     * XPATH for client id
     */
    const XPATH_CLIENT_ID = 'payment/dintero/client_id';

    /*
     * XPATH for client secret
     */
    const XPATH_CLIENT_SECRET = 'payment/dintero/client_secret';

    /*
     * XPATH for account id
     */
    const XPATH_ACCOUNT_ID = 'payment/dintero/account_id';

    /*
     * XPATH for environment
     */
    const XPATH_ENVIRONMENT = 'payment/dintero/environment';

    /*
     * XPATH for profile id
     */
    const XPATH_PROFILE_ID = 'payment/dintero/checkout_profile_id';

    /*
     * Payment action
     */
    const XPATH_PAYMENT_ACTION = 'payment/dintero/payment_action';

    /**
     * Checking whether module is active or not
     *
     * @return bool
     */
    public function isActive()
    {
        return Mage::getStoreConfigFlag(self::XPATH_IS_ACTIVE);
    }

    /**
     * Retrieving payment session url
     *
     * @return string
     */
    public function getPlaceOrderUrl()
    {
        return $this->_getUrl('dintero/payment/place');
    }

    /**
     * Retrieving client id from configuration
     *
     * @return string
     */
    public function getClientId()
    {
        return Mage::getStoreConfig(self::XPATH_CLIENT_ID);
    }

    /**
     * Retrieving client secret from configuration
     *
     * @return string
     */
    public function getClientSecret()
    {
        return Mage::getStoreConfig(self::XPATH_CLIENT_SECRET);
    }

    /**
     * Retrieving account id from configuration
     *
     * @return string
     */
    public function getAccountId()
    {
        return $this->decrypt(Mage::getStoreConfig(self::XPATH_ACCOUNT_ID));
    }

    /**
     * Retrieving environment
     *
     * @return string
     */
    public function getEnvironment()
    {
        return Mage::getStoreConfigFlag(self::XPATH_ENVIRONMENT) ? 'T' : 'P';
    }

    /**
     * Retrieving account id with environment prefix
     *
     * @return string
     */
    public function getFullAccountId()
    {
        return $this->getEnvironment() . $this->getAccountId();
    }

    /**
     * Retrieving callback url
     *
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->_getUrl('dintero/payment/response');
    }

    /**
     * Retrieving checkout profile id
     *
     * @return string
     */
    public function getProfileId()
    {
        return Mage::getStoreConfig(self::XPATH_PROFILE_ID);
    }

    /**
     * Retrieving return url
     *
     * @return string
     */
    public function getReturnUrl()
    {
        return $this->_getUrl('dintero/payment/success');
    }

    /**
     * Can auto-capture
     *
     * @return bool
     */
    public function canAutoCapture()
    {
        return Mage::getStoreConfig(self::XPATH_PAYMENT_ACTION)
            == Dintero_Hp_Model_Dintero::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Get controller name
     *
     * @return string
     */
    public function getControllerName()
    {
        return Mage::app()->getFrontController()->getRequest()->getControllerName();
    }
}