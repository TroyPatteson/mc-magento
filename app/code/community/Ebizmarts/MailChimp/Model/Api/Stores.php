<?php

/**
 * mailchimp-lib Magento Component
 *
 * @category  Ebizmarts
 * @package   mailchimp-lib
 * @author    Ebizmarts Team <info@ebizmarts.com>
 * @copyright Ebizmarts (http://ebizmarts.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Ebizmarts_MailChimp_Model_Api_Stores
{
    private $_helper;


    public function __construct()
    {
        $this->_helper = Mage::helper('mailchimp');
    }

    /**
     * Create MailChimp store.
     *
     * @param  $mailChimpStoreId
     * @param  null             $listId
     * @param  $scope
     * @param  $scopeId
     * @throws Exception
     */
    public function createMailChimpStore($mailChimpStoreId, $listId = null, $scopeId, $scope)
    {
        $api = $this->_helper->getApi($scopeId, $scope);
        if ($api) {
            if (!$listId) {
                $listId = $this->_helper->getGeneralList($scopeId, $scope);
            }

            if ($listId != null && $listId != "") {
                $storeName = $this->_helper->getMCStoreName($scopeId, $scope);
                $storeEmail = $this->_helper->getConfigValueForScope('trans_email/ident_general/email', $scopeId, $scope);
                $storeDomain = $this->_helper->getStoreDomain($scopeId, $scope);
                if (strpos($storeEmail, 'example.com') !== false) {
                    $storeEmail = null;
                    throw new Exception('Please, change the general email in Store Email Addresses/General Contact');
                }

                $currencyCode = $this->_helper->getConfigValueForScope(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_DEFAULT, $scopeId, $scope);
                $isSyncing = true;
                $this->_helper->logDebug("Creating mailchimp store $mailChimpStoreId name $storeName email $storeEmail domain $storeDomain for list ID $listId for scope ".$this->_helper->getScopeDescription($scopeId, $scope), $scopeId, $scope);
                $response = $api->ecommerce->stores->add($mailChimpStoreId, $listId, $storeName, $currencyCode, $isSyncing, 'Magento', $storeDomain, $storeEmail);
                return $response;
            } else {
                throw new Exception('You don\'t have any lists configured in MailChimp');
            }
        } else {
            throw new Exception('You must provide a MailChimp API key');
        }
    }

    /**
     * Delete MailChimp store.
     *
     * @param $mailchimpStoreId
     * @param $scopeId
     * @param $scope
     */
    public function deleteMailChimpStore($mailchimpStoreId, $scopeId, $scope)
    {
        try {
            $this->_helper->logDebug("Deleting mailchimp store $mailchimpStoreId for ", $scopeId, $scope);
            $api = $this->_helper->getApi($scopeId, $scope);
            $api->ecommerce->stores->delete($mailchimpStoreId);
        } catch (MailChimp_Error $e) {
            $this->_helper->logError($e->getFriendlyMessage(), $scopeId, $scope);
        } catch (Exception $e) {
            $this->_helper->logError($e->getMessage(), $scopeId, $scope);
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $resource = Mage::getResourceModel('mailchimp/synchbatches');
        $connection->update($resource->getMainTable(), array('status' => 'canceled'), "status = 'pending'");
    }

    /**
     * Edit MailChimp store name for given scope.
     *
     * @param $name
     * @param $scopeId
     * @param $scope
     */
    public function modifyName($name, $scopeId, $scope)
    {
        try {
            $api = $this->_helper->getApi($scopeId, $scope);
            $mailchimpStoreId = $this->_helper->getMCStoreId($scopeId, $scope);
            $this->_helper->logDebug("Setting mailchimp store $mailchimpStoreId name to $name for scope ".$this->_helper->getScopeDescription($scopeId, $scope), $scopeId, $scope);
            $api->ecommerce->stores->edit($mailchimpStoreId, $name);
        } catch (MailChimp_Error $e) {
            $this->_helper->logError($e->getFriendlyMessage(), $scopeId, $scope);
        } catch (Exception $e) {
            $this->_helper->logError($e->getMessage(), $scopeId, $scope);
        }
    }

    /**
     * Returns URL from MailChimp store data
     *
     * @param  $scopeId
     * @param  $scope
     * @return mixed
     */
    public function getMCJsUrl($scopeId, $scope)
    {
        try {
            $api = $this->_helper->getApi($scopeId, $scope);
            $mailchimpStoreId = $this->_helper->getMCStoreId($scopeId, $scope);
            $this->_helper->logDebug("Getting mailchimp store $mailchimpStoreId connected sitre URL for scope ".$this->_helper->getScopeDescription($scopeId, $scope), $scopeId, $scope);
            $response = $api->ecommerce->stores->get($mailchimpStoreId, 'connected_site');
            if (isset($response['connected_site']['site_script']['url'])) {
                $url = $response['connected_site']['site_script']['url'];
                $configValues = array(array(Ebizmarts_MailChimp_Model_Config::ECOMMERCE_MC_JS_URL, $url));
                $this->_helper->saveMailchimpConfig($configValues, $scopeId, $scope);
                return $url;
            }
        } catch (MailChimp_Error $e) {
            $this->_helper->logError($e->getFriendlyMessage(), $scopeId, $scope);
        } catch (Exception $e) {
            $this->_helper->logError($e->getMessage(), $scopeId, $scope);
        }
    }

    /**
     * Set is_syncing value for the given scope.
     *
     * @param $mailchimpApi
     * @param $isSincingValue
     * @param $mailchimpStoreId
     * @param $magentoStoreId
     */
    public function editIsSyncing($mailchimpApi, $isSincingValue, $mailchimpStoreId, $magentoStoreId)
    {
        $mailchimpApi->ecommerce->stores->edit($mailchimpStoreId, null, null, null, $isSincingValue);
        $scopeToEdit = $this->_helper->getMailChimpScopeByStoreId($magentoStoreId);
        $configValue = array(array(Ebizmarts_MailChimp_Model_Config::GENERAL_MCISSYNCING, (int)$isSincingValue));
        $this->_helper->saveMailchimpConfig($configValue, $scopeToEdit['scope_id'], $scopeToEdit['scope']);
    }
}