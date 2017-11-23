<?php

namespace Urbit\InventoryFeed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;

use Urbit\InventoryFeed\Model\Config\Config;
use Urbit\InventoryFeed\Model\Config\ConfigFactory;
use Urbit\InventoryFeed\Model\Feed\FeedFactory;
use Urbit\InventoryFeed\Model\Feed\Feed as FeedModel;
use Urbit\InventoryFeed\Model\Collection\Product as ProductCollection;

/**
 * Class Feed
 * @package Urbit\InventoryFeed\Helper
 */
class Feed extends AbstractHelper
{
    /**
     * retrieving various paths
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * retrieving various paths
     * @var DirectoryList
     */
    protected $_directoryList;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var FeedFactory
     */
    protected $_feedFactory;

    /**
     * Feed Helper constructor.
     * @param StoreManagerInterface $storeManager
     * @param DirectoryList $directory_list
     * @param Config $config
     * @param FeedFactory $feedFactory
     * @internal param ConfigFactory $configFactory
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        DirectoryList $directory_list,
        Config $config,
        FeedFactory $feedFactory
    ) {
        $this->_storeManager  = $storeManager;
        $this->_directoryList = $directory_list;
        $this->_config        = $config;
        $this->_feedFactory   = $feedFactory;
    }

    /**
     * Feed file generation
     * @param ProductCollection $collection
     * @return string
     */
    public function generateFeed(ProductCollection $collection)
    {
        $this->_touch();

        /** @var FeedModel $feedProcessor */
        $feedProcessor = $this->_feedFactory->create([
            'products' => $collection,
        ]);

        $products = $feedProcessor->toArray();

        $json = json_encode($products, JSON_PRETTY_PRINT);

        $this->setDataJson($json);

        return $this;
    }

    /**
     * Check cache on expire. True if it still valid
     * @return bool
     */
    public function checkCache()
    {
        $cacheDuration = $this->_config->get("cron/cache_duration");

        $filePath = $this->getCacheFilePath();

        return file_exists($filePath) && (time() - filemtime($filePath)) < ($cacheDuration * 60);
    }

    /**
     * Get feed cache file path
     * @return string
     */
    public function getCacheFilePath()
    {
        $storeID = $this->_getStoreID();

        return $this->_getCacheDir() . "inventoryfeed_{$storeID}.json";
    }

    /**
     * Get data from cache file
     * @return mixed
     */
    public function getData()
    {
        return json_decode($this->getDataJson(), true);
    }

    /**
     * Get plain json from cache file
     * @return string
     */
    public function getDataJson()
    {
        return file_get_contents($this->getCacheFilePath());
    }

    /**
     * Set data to cache file
     * @param mixed $data
     * @return bool|int
     */
    public function setData($data)
    {
        return $this->setDataJson(json_encode($data));
    }

    /**
     * Set json data to cache file
     * @param string $json
     * @return bool|string
     */
    public function setDataJson($json)
    {
        return file_put_contents($this->getCacheFilePath(), $json);
    }

    /**
     * Get file system caching directory (if file system caching is used)
     * @return string
     */
    protected function _getCacheDir()
    {
        return $this->_directoryList->getPath('cache');
    }

    /**
     * Get store identifier
     * @return integer
     */
    protected function _getStoreID()
    {
        return (int) $this->_storeManager->getStore()->getStoreId(); 
    }

    /**
     * Touch (update "last modify" time) cache file to prevent updating data by other process
     * (e.g. next cron task if feed generation get a lot of time)
     */
    protected function _touch()
    {
        touch($this->getCacheFilePath());
    }
}