<?php

namespace Urbit\InventoryFeed\Model\Feed;

use Magento\Directory\Model\Currency;
use Magento\Store\Api\Data\StoreInterface as Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\CatalogInventory\Api\StockStateInterface as StockManager;
use Magento\CatalogInventory\Model\Stock\Item as StockItem;
use Magento\CatalogInventory\Model\Stock\StockItemRepository as StockRepository;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Magento\Catalog\Model\Product\Type\AbstractType as ProductType;
use Magento\Catalog\Model\Product\Type\Simple as ProductTypeSimple;
use Urbit\InventoryFeed\Model\Config\ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Calculation\Rate;

use Exception;

/**
 * Class FeedProduct
 * Working and process with Magento Product
 * @package Urbit\InventoryFeed\Model\Feed
 *
 * Special properties:
 * @property $isSimple
 *
 * Field properties (for feed $data property):
 * @property string $id
 * @property array $prices
 * @property array $inventory
 */
class FeedInventory
{
    /**
     * Magento product object
     * @var MagentoProduct
     */
    protected $_product;

    /**
     * Array with product fields
     * @var array
     */
    protected $_data = [];

    /**
     * @var StoreManagerInterface
     */
    protected $_store;

    /**
     * @var Currency
     */
    protected $_currency;

    /**
     * @var StockManager
     */
    protected $_stockManager;

    /**
     * @var StockRepository
     */
    protected $_stockRepository;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var ScopeConfig
     */
    protected $_scopeConfig;

    /**
     * @var TaxCalculation
     */
    protected $_taxCalculation;

    /**
     * @var TaxCalculationRate
     */
    protected $_taxCalculationRate;

    /**
     * FeedProduct constructor.
     * @param MagentoProduct $product
     * @param ProductRepository $productRepository
     * @param StoreManagerInterface $store
     * @param Currency $currency
     * @param StockManager $stockManager
     * @param StockRepository $stockRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param Calculation $taxCalculation
     * @param Rate $taxCalculationRate
     */
    public function __construct(
        MagentoProduct $product,
        ProductRepository $productRepository,
        StoreManagerInterface $store,
        Currency $currency,
        StockManager $stockManager,
        StockRepository $stockRepository,
        ConfigFactory $configFactory,
        ScopeConfigInterface $scopeConfig,
        Calculation $taxCalculation,
        Rate $taxCalculationRate
    ) {
        $this->_product = $productRepository->getById($product->getId());
        $this->_store = $store;
        $this->_currency = $currency;
        $this->_stockManager = $stockManager;
        $this->_stockRepository = $stockRepository;
        $this->_config = $configFactory->create();
        $this->_scopeConfig = $scopeConfig;
        $this->_taxCalculation = $taxCalculation;
        $this->_taxCalculationRate = $taxCalculationRate;

        $this->_product->setStoreId($this->_getStore()->getId());
    }

    /**
     * Get product data for feed
     * @return array
     */
    public function toArray()
    {
        if (empty($this->_data)) {
            $this->process();
        }

        return $this->_data;
    }

    /**
     * Get feed product data fields
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->_data[$name])) {
            return $this->_data[$name];
        }

        if (stripos($name, 'is') === 0 && method_exists($this, $name)) {
            return $this->{$name}();
        }

        $getMethod = "get{$name}";

        if (method_exists($this, $getMethod)) {
            return $this->{$getMethod}();
        }

        return null;
    }

    /**
     * Set feed product data fields
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $setMethod = "set{$name}";

        if (method_exists($this, $setMethod)) {
            $this->{$setMethod}($value);

            return;
        }

        $this->_data[$name] = $value;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return $this|mixed|null
     * @throws Exception
     */
    public function __call($name, $arguments)
    {
        $property = strtolower(preg_replace("/^unset/", '', $name));
        $propertyExist = isset($this->_data[$property]);

        if ($propertyExist) {
            if (stripos($name, 'unset') === 0) {
                unset($this->_data[$property]);

                return $this;
            }

            if (stripos($name, 'get') === 0) {
                return $this->{$property};
            }

            if (stripos($name, 'set') === 0 && isset($arguments[0])) {
                $this->{$property} = $arguments[0];

                return $this;
            }
        }

        throw new Exception("Unknown method {$name}");
    }

    /**
     * Process Magento Product
     * @return bool
     */
    public function process()
    {
        if (!$this->isSimple()) {
            return false;
        }

        $product = $this->_product;

        $this->id = (string)$product->getId();

        $this->processPrices();
        $hasStock = $this->processInventory();

        return $hasStock;
    }

    /**
     * Process product prices
     */
    protected function processPrices()
    {
        $prices = [];
        $product = $this->_product;
        $currency = $this->_getCurrency();
        $priceInfo = $product->getPriceInfo();

        //get tax country from config
        $configTaxCountry = $this->_config->get("taxes")['tax_country'];

        //get default shop's country
        $countryCode = $this->_scopeConfig->getValue('general/country/default');
        $productTaxClassId = $product->getTaxClassId();

        $taxCol = $this->_taxCalculation->getResourceCollection();

        $taxCol->addFieldtoFilter('product_tax_class_id', $productTaxClassId);

        $rates = $taxCol->load()->getData();

        $productTax = null;
        $defaultTax = null;

        if ($rates) {
            foreach ($rates as $rate) {
                $rateInfo = $this->_taxCalculationRate->load($rate['tax_calculation_rate_id'])->getData();

                $countryId = $rateInfo['tax_country_id'];

                if ($countryId == $configTaxCountry) {
                    $productTax = $rateInfo['rate'];
                } elseif ($countryId == $countryCode) {
                    $defaultTax = $rateInfo['rate'];
                }
            }
        }

        $regularPrice = $priceInfo->getPrice('regular_price');
        $finalPrice = $priceInfo->getPrice('final_price');

        $regularPriceValue = $regularPrice->getValue();
        $finalPriceValue = $finalPrice->getValue();

        if ($regularPriceValue) {
            $prices[] = [
                "currency" => $currency,
                "value"    => $this->getPriceWithTax($regularPriceValue, $productTax, $defaultTax) * 100,
                "type"     => "regular",
                "vat"      => $this->getFormattedVat($productTax, $defaultTax),
            ];
        }

        if ($finalPriceValue && $finalPriceValue !== $regularPriceValue) {
            $prices[] = [
                "currency" => $currency,
                "value"    => $this->getPriceWithTax($finalPriceValue, $productTax, $defaultTax) * 100,
                "type"     => "sale",
                "vat"      => $this->getFormattedVat($productTax, $defaultTax),
            ];
        }

        $this->prices = $prices;
    }

    protected function getPriceWithTax($priceValue, $tax, $defaultTax)
    {
        return $priceValue + ($tax ? ($priceValue * (float)$tax / 100) : ($defaultTax ? $priceValue * (float)$defaultTax / 100 : 0));
    }

    protected function getFormattedVat($tax, $defaultTax)
    {
        return $tax ? ((float)$tax * 100) : ($defaultTax ? (float)$defaultTax * 100 : null);
    }

    /**
     * Process product inventory
     * @return bool
     */
    protected function processInventory()
    {
        $product = $this->_product;

        /** @var StockItem $stock */
        $stock = $this->_stockRepository->get($product->getId());

        if (!$stock || !$stock['qty']) {
            return false;
        }

        $this->inventory = [[
            'location' => $stock['stock_id'],
            'quantity' => (int)$stock['qty'],
        ]];

        return true;
    }

    /**
     * Check if product have simple type
     * @return bool
     */
    public function isSimple()
    {
        /** @var ProductType $type */
        $type = $this->_product->getTypeInstance();

        return $type instanceof ProductTypeSimple;
    }

    /**
     * @return Store
     */
    protected function _getStore()
    {
        return $this->_store->getStore();
    }

    /**
     * Helper function
     * Get store currency
     * @return string
     */
    protected function _getCurrency()
    {
        return $this->_getStore()->getCurrentCurrencyCode();
    }
}
