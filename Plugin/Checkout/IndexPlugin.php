<?php
declare(strict_types=1);

namespace TuVo\AutoAddToCart\Plugin\Checkout;

use Magento\Checkout\Controller\Index\Index;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\UrlInterface;
use TuVo\AutoAddToCart\Model\CartFiller;
use TuVo\AutoAddToCart\Model\Config;
use TuVo\AutoAddToCart\Model\LinkParser;

/**
 * /checkout/?products=SKU:qty,... -> fill cart -> 302 to a clean /checkout/.
 *
 * Requests without the parameter go straight to the core controller, so the
 * normal cart/checkout flow is untouched.
 */
class IndexPlugin
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var LinkParser
     */
    private $linkParser;

    /**
     * @var CartFiller
     */
    private $cartFiller;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var MessageManager
     */
    private $messageManager;

    /**
     * @var UrlInterface
     */
    private $url;

    public function __construct(
        Config $config,
        LinkParser $linkParser,
        CartFiller $cartFiller,
        RedirectFactory $redirectFactory,
        MessageManager $messageManager,
        UrlInterface $url
    ) {
        $this->config = $config;
        $this->linkParser = $linkParser;
        $this->cartFiller = $cartFiller;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->url = $url;
    }

    /**
     * @param Index $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
    public function aroundExecute(Index $subject, callable $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        $raw = $subject->getRequest()->getParam($this->config->getQueryParam());
        if (!is_string($raw) || trim($raw) === '') {
            return $proceed();
        }

        $items = $this->linkParser->parse($raw);
        $result = $items ? $this->cartFiller->fill($items) : ['added' => [], 'skipped' => []];

        if ($result['skipped']) {
            // Count only: SKUs come from the URL and must not be echoed back to the page
            $this->messageManager->addNoticeMessage(
                __('%1 product(s) from the link are unavailable and were not added.', count($result['skipped']))
            );
        }

        $redirect = $this->redirectFactory->create();

        // Nothing valid -> homepage, never an error page
        return $result['added']
            ? $redirect->setPath('checkout')
            : $redirect->setUrl($this->url->getBaseUrl());
    }
}
