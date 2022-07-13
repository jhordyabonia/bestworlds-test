<?php
namespace Ced\ReferralSystem\Model;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\PageCache\Version;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class Config implements \Ced\ReferralSystem\Api\ConfigInterface
{
    protected $configWriter;
    private $_scopeConfig;
    private $_request;

    public function __construct(
            TypeListInterface $cacheTypeList,
            Pool $cacheFrontendPool,
            WriterInterface $configWriter,
            ScopeConfigInterface $scopeConfig) {

        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->configWriter = $configWriter;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritDoc}
     */
    public function setConfig($config, $value): string
    {
        $this->configWriter->save(\Ced\ReferralSystem\Api\ConfigInterface::PATH.$config, $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,$scopeId=0);
        $this->flushCache();
        if($this->getConfig($config)==$value) {
            return __("Ok, Config guardada");
        }else{
            return __("Error,No se pudo guardar el valor %1",$value);;
        }
    }


    /**
     * {@inheritDoc}
     */
    public function getConfig($config)
    {
        return $this->_scopeConfig ->getValue(\Ced\ReferralSystem\Api\ConfigInterface::PATH.$config);
    }

    public function flushCache()
    {
        $_types = [
            'config',
            'config_integration',
            'config_integration_api',
            'config_webservice'
        ];

        foreach ($_types as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
