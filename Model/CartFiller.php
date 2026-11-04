<?php
declare(strict_types=1);

namespace TuVo\AutoAddToCart\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Adds [sku => qty] to the current visitor's quote.
 *
 * Unknown, disabled or out-of-stock SKUs are skipped; the rest are still added.
 * Child SKUs of configurable products are added through their parent with the
 * matching super attributes, so the cart shows the right name, options and price.
 */
class CartFiller
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Configurable
     */
    private $configurableType;

    /**
     * @var StockStateInterface
     */
    private $stockState;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository,
        Configurable $configurableType,
        StockStateInterface $stockState,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->configurableType = $configurableType;
        $this->stockState = $stockState;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param int[] $items keyed by SKU
     * @return array{added: int[], skipped: string[]} added: sku => qty, skipped: sku => reason
     */
    public function fill(array $items): array
    {
        $added = [];
        $skipped = [];

        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $websiteId = (int)$store->getWebsiteId();
        $quote = $this->checkoutSession->getQuote();

        foreach ($items as $sku => $qty) {
            $sku = (string)$sku;

            try {
                /** @var Product $product */
                $product = $this->productRepository->get($sku, false, $storeId);
            } catch (NoSuchEntityException $e) {
                $skipped[$sku] = 'not found';
                continue;
            }

            if (!$this->isAvailable($product, $websiteId)) {
                $skipped[$sku] = 'disabled or not in this website';
                continue;
            }

            if (!$product->isSalable()
                || !$this->stockState->checkQty((int)$product->getId(), $qty, $websiteId)
            ) {
                $skipped[$sku] = 'out of stock';
                continue;
            }

            $buyRequest = new DataObject(['qty' => $qty]);
            $productToAdd = $product;

            $parent = $this->getConfigurableParent($product, $storeId, $websiteId);
            if ($parent !== null) {
                $superAttribute = $this->getSuperAttribute($parent, $product);
                if ($superAttribute !== null) {
                    $buyRequest->setData('super_attribute', $superAttribute);
                    $productToAdd = $parent;
                }
            }

            try {
                $result = $quote->addProduct($productToAdd, $buyRequest);
                if (is_string($result)) {
                    $skipped[$sku] = $result;
                    continue;
                }
            } catch (LocalizedException $e) {
                $skipped[$sku] = $e->getMessage();
                continue;
            }

            $added[$sku] = $qty;
        }

        if ($added) {
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $this->checkoutSession->replaceQuote($quote);
        }

        if ($skipped) {
            $this->logger->info('TuVo_AutoAddToCart: skipped SKUs from checkout link', $skipped);
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    private function isAvailable(ProductInterface $product, int $websiteId): bool
    {
        return (int)$product->getStatus() === Status::STATUS_ENABLED
            && in_array($websiteId, array_map('intval', $product->getWebsiteIds()), true);
    }

    /**
     * @return Product|null
     */
    private function getConfigurableParent(ProductInterface $child, int $storeId, int $websiteId)
    {
        if ($child->getTypeId() !== ProductType::TYPE_SIMPLE) {
            return null;
        }

        foreach ($this->configurableType->getParentIdsByChild($child->getId()) as $parentId) {
            try {
                $parent = $this->productRepository->getById((int)$parentId, false, $storeId);
            } catch (NoSuchEntityException $e) {
                continue;
            }
            if ($this->isAvailable($parent, $websiteId)) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * Builds [attribute_id => option_id] for the child inside its configurable parent.
     *
     * @return array|null null when the child is missing one of the parent's options
     */
    private function getSuperAttribute(Product $parent, Product $child)
    {
        $superAttribute = [];

        foreach ($this->configurableType->getConfigurableAttributesAsArray($parent) as $attribute) {
            $value = $child->getData($attribute['attribute_code']);
            if ($value === null || $value === '') {
                return null;
            }
            $superAttribute[$attribute['attribute_id']] = $value;
        }

        return $superAttribute ?: null;
    }
}
