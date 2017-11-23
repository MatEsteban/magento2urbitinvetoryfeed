<?php

namespace Urbit\InventoryFeed\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Response\Http\Interceptor as HttpResponse;
use Magento\Framework\Controller\Result\JsonFactory;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Urbit\InventoryFeed\Model\Collection\Product as ProductCollection;
use Urbit\InventoryFeed\Model\Collection\ProductFactory as ProductCollectionFactory;
use Urbit\InventoryFeed\Model\Config\Config;
use Urbit\InventoryFeed\Model\Config\ConfigFactory;
use Urbit\InventoryFeed\Helper\Feed as FeedHelper;

class Json extends Action
{
    /**
     * @var JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @var Config
     */
    protected $_config;

    /**
     * @var ProductCollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var FeedHelper
     */
    protected $_helper;

    /**
     * @var RemoteAddress
     */
    protected $_remoteAddress;

    /**
     * Json Controller constructor.
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ConfigFactory $configFactory
     * @param FeedHelper $helper
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductCollectionFactory $productCollectionFactory,
        ConfigFactory $configFactory,
        FeedHelper $helper,
        RemoteAddress $remoteAddress
    ) {
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        $this->_config = $configFactory->create();
        $this->_helper = $helper;
        $this->_remoteAddress = $remoteAddress;

        parent::__construct($context);
    }

    /**
     * Get feed for current store
     */
    public function execute()
    {
        /** @var ProductCollection $productCollection */
        $productCollection = $this->_productCollectionFactory->create([
            'filter' => $this->_config->filter,
        ]);

        $feedHelper = $this->_helper;

        if (!$feedHelper->checkCache()) {
            $feedHelper->generateFeed($productCollection);
        }

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        $response
            ->setHeader("Content-type", "text/json", true)
            ->setBody($feedHelper->getDataJson())
            ->send()
        ;
    }
}
