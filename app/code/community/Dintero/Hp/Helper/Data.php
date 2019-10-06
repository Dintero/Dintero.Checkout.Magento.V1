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

    /*
     * Logo type
     */
    const XPATH_LOGO_TYPE = 'payment/dintero/logo_type';

    /*
     * Logo width
     */
    const XPATH_LOGO_WIDTH = 'payment/dintero/logo_width';

    /*
     * Logo color
     */
    const XPATH_LOGO_COLOR = 'payment/dintero/logo_color';

    /*
     * Default logo width
     */
    const DEFAULT_LOGO_WIDTH = 420;

    /*
     * Default logo color
     */
    const DEFAULT_LOGO_COLOR = '#c4c4c4';

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

    /**
     * Retrieving logo type
     *
     * @return string
     */
    public function getLogoType()
    {
        return Mage::getStoreConfigFlag(self::XPATH_LOGO_TYPE) ? 'mono' : 'colors';
    }

    /**
     * Retrieving logo color
     *
     * @return string
     */
    public function getLogoColor()
    {
        $value = Mage::getStoreConfig(self::XPATH_LOGO_COLOR);
        return $value ?: self::DEFAULT_LOGO_COLOR;
    }

    /**
     * Retrieving logo width
     *
     * @return int
     */
    public function getLogoWidth()
    {
        $value = Mage::getStoreConfig(self::XPATH_LOGO_WIDTH);
        return $value ?: self::DEFAULT_LOGO_WIDTH;
    }

    /**
     * Retrieving footer logo url
     *
     * @return string
     */
    public function getFooterLogoUrl()
    {
        $baseUrl = Dintero_Hp_Model_Api_Client::CHECKOUT_API_BASE_URL;
        $pattern = '%s/branding/logos/visa_mastercard_vipps_swish_instabank/'
            . 'variant/%s/colors/color/%s/width/%d/dintero_left_frame.svg';

        if (Mage::getStoreConfigFlag(self::XPATH_LOGO_TYPE)) {
            $pattern = '%s/branding/logos/visa_mastercard_vipps_swish_instabank/'
                . 'variant/%s/color/%s/width/%d/dintero_left_frame.svg';
        }

        return sprintf(
            $pattern,
            $baseUrl,
            $this->getLogoType(),
            str_replace('#', '', $this->getLogoColor()),
            $this->getLogoWidth()
        );
    }

    /**
     * Retrieving checkout logo url
     *
     * @return string
     */
    public function getCheckoutLogoUrl()
    {
        $baseUrl = Dintero_Hp_Model_Api_Client::CHECKOUT_API_BASE_URL;
        $pattern = '%s/branding/profiles/%s/'
            . 'variant/%s/color/%s/width/%d/dintero_left_frame.svg';

        return sprintf(
            $pattern,
            $baseUrl,
            $this->getProfileId(),
            $this->getLogoType(),
            str_replace('#', '', $this->getLogoColor()),
            $this->getLogoWidth()
        );
    }
}