<?php
declare(strict_types=1);

namespace TuVo\AutoAddToCart\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const XML_PATH_ENABLED = 'tuvo_auto_add_to_cart/general/enabled';
    const XML_PATH_QUERY_PARAM = 'tuvo_auto_add_to_cart/general/query_param';
    const DEFAULT_QUERY_PARAM = 'products';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getQueryParam(): string
    {
        $param = trim((string)$this->scopeConfig->getValue(self::XML_PATH_QUERY_PARAM, ScopeInterface::SCOPE_STORE));

        return $param !== '' ? $param : self::DEFAULT_QUERY_PARAM;
    }
}
