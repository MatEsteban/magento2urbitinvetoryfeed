<?php

namespace Urbit\InventoryFeed\Model\Feed;

use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Store\Api\Data\StoreInterface as MagentoStore;
use Magento\Framework\App\Config\ScopeConfigInterface as MagentoConfig;
use Magento\Directory\Api\CountryInformationAcquirerInterface as MagentoCountryInformation;
use Magento\Catalog\Api\ProductRepositoryInterface as ProductRepository;
use Urbit\InventoryFeed\Model\Collection\Product as ProductCollection;
use Urbit\InventoryFeed\Model\Config\Config;
use Urbit\InventoryFeed\Model\Config\ConfigFactory;

use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class Feed
 * @package Urbit\InventoryFeed\Model
 */
class Feed
{
    const SCHEDULE_INTERVAL_5MIN   = '5MIN';
    const SCHEDULE_INTERVAL_15MIN  = '15MIN';
    const SCHEDULE_INTERVAL_30MIN  = '30MIN';
    const SCHEDULE_INTERVAL_45MIN  = '45MIN';
    const SCHEDULE_INTERVAL_HOURLY = 'HOURLY';


    const FEED_VERSION = '2017-06-28-1';

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var ProductCollection
     */
    protected $_products;

    /**
     * @var FeedInventoryFactory
     */
    protected $_feedInventoryFactory;

    /**
     * @var MagentoStore
     */
    protected $_store;

    /**
     * @var LocaleResolver
     */
    protected $_locale;

    /**
     * @var MagentoConfig
     */
    protected $_scopeConfig;

    /**
     * @var MagentoCountryInformation
     */
    protected $_countryInformation;

    /**
     * @var ProductRepository
     */
    protected $_productRepository;

    /**
     * @var array
     */
    protected $_data = [];

    /**
     * Feed constructor.
     * @param ProductCollection $products
     * @param ConfigFactory $configFactory
     * @param FeedInventoryFactory $feedInventoryFactory
     * @param MagentoStore $store
     * @param LocaleResolver $locale
     * @param MagentoConfig $scopeConfig
     * @param MagentoCountryInformation $countryInformation
     * @param ProductRepository $productRepository
     */
    public function __construct(
        ProductCollection $products,
        ConfigFactory $configFactory,
        FeedInventoryFactory $feedInventoryFactory,
        MagentoStore $store,
        LocaleResolver $locale,
        MagentoConfig $scopeConfig,
        MagentoCountryInformation $countryInformation,
        ProductRepository $productRepository
    ) {
        $this->_products = $products;
        $this->_config   = $configFactory->create();
        $this->_store    = $store;
        $this->_locale   = $locale;
        $this->_scopeConfig = $scopeConfig;
        $this->_countryInformation = $countryInformation;
        $this->_feedInventoryFactory = $feedInventoryFactory;
        $this->_productRepository  = $productRepository;

    }

    /**
     * Process product
     */
    public function process()
    {
        $products = [];

        foreach ($this->_products as $product) {
            //$product = $this->_productRepository->getById($product->getId());

            $feedInventory = $this->_feedInventoryFactory->create([
                'product' => $product,
            ]);

            if ($feedInventory->process()) {
                $products[] = $feedInventory->toArray();
            }
        }

        $this->_data = $products;
    }

    /**
     * @return array
     */
    public function toArray()
    {

        if (empty($this->_data)) {
            $this->process();
        }

        $lang = $this->_locale->getLocale();
        $countryCode = null;

        try {
            $countryID = $this->_scopeConfig->getValue('general/store_information/country_id');
            $country = $this->_countryInformation->getCountryInfo($countryID);
            $countryCode = $country->getTwoLetterAbbreviation() ?: $lang;
        } catch (NoSuchEntityException $e) {
            $countryCode = $lang;
        }

        $version = $this->getFeedVersion();

        return array(
            '$schema' => "https://raw.githubusercontent.com/urbitassociates/urbit-merchant-feeds/master/schemas/inventory/{$version}/inventory.json",
            'content_language' => $lang,
            'attribute_language' => $lang,
            'content_type' => 'products',
            'target_country' => array(
                $countryCode,
            ),
            'version' => $version,
            'feed_format' => array(
                "encoding" => "UTF-8",
            ),
            'schedule' => array(
                'interval' => $this->getIntervalText(),
            ),
            'entities' => $this->_data,
        );
    }

    /**
     * Get schedule interval value
     * @return string
     */
    public function getIntervalText()
    {
        foreach (array(
            5  => self::SCHEDULE_INTERVAL_5MIN,
            15 => self::SCHEDULE_INTERVAL_15MIN,
            30 => self::SCHEDULE_INTERVAL_30MIN,
            45 => self::SCHEDULE_INTERVAL_45MIN,
            60 => self::SCHEDULE_INTERVAL_HOURLY,
        ) as $time => $val) {
            if ($this->_config->cron['cache_duration'] <= $time) {
                return $val;
            }
        }

        return self::SCHEDULE_INTERVAL_HOURLY;
    }

    public function getFeedVersion()
    {
        return static::FEED_VERSION;
    }
}