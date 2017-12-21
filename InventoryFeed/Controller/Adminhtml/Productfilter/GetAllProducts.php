<?php

namespace Urbit\InventoryFeed\Controller\Adminhtml\Productfilter;

use Magento\Backend\App\Action;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Urbit\InventoryFeed\Model\Config\ConfigFactory;
use Magento\Framework\App\Response\Http\Interceptor as HttpResponse;

use Magento\Catalog\Model\Product\Attribute\Source\Status as MagentoProductStatus;
use Magento\Catalog\Model\Product\Visibility as MagentoProductVisibility;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Store\Api\Data\StoreInterface as Store;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;

/**
 * Class GetAllProducts
 * @package Urbit\ProductFeed\Controller\Admin
 */
class GetAllProducts extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productsCollectionFactory;

    /**
     * @var MagentoProductStatus
     */
    protected $_productStatus;

    /**
     * @var MagentoProductVisibility
     */
    protected $_productVisibility;

    /**
     * @var StockHelper
     */
    protected $_stockHelper;

    /**
     * @var Store
     */
    protected $_store;

    /**
     * @var AttributeCollection
     */
    protected $_attributeCollection;

    /**
     * GetAllProducts constructor.
     * @param Action\Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param MagentoProductStatus $productStatus
     * @param MagentoProductVisibility $productVisibility
     */
    public function __construct(
        Action\Context $context,
        CollectionFactory $productCollectionFactory,
        MagentoProductStatus $productStatus,
        MagentoProductVisibility $productVisibility,
        AttributeCollection $attributeCollection,
        StockHelper $stockHelper,
        Store $store
    ) {
        $this->_productsCollectionFactory = $productCollectionFactory;
        $this->_productStatus = $productStatus;
        $this->_productVisibility = $productVisibility;
        $this->_stockHelper = $stockHelper;
        $this->_attributeCollection = $attributeCollection;
        $this->_store = $store;
        parent::__construct($context);
    }

    public function execute()
    {
        $filter = [
            'category' => $this->_request->getParam('categoryFiler'),
            'tag_name' => $this->_request->getParam('tagFilterName'),
            'tag_value' => $this->_request->getParam('tagFilterValue'),
        ];

        $products = [];

        /** @var Product $product */
        foreach ($this->_getProducts($filter) as $product) {
            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
            ];
        }

        /** @var HttpResponse $response */
        $response = $this->getResponse();

        $response
            ->setHeader("Content-type", "text/json", true)
            ->setBody(\Zend_Json::encode($products))
            ->send()
        ;
    }

    /**
     * @param array $filter
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function _getProducts($filter = [])
    {
        $productsCollection = $this
            ->_productsCollectionFactory
            ->create()
            ->addAttributeToSelect('name')
        ;

        $statuses    = $this->_productStatus->getVisibleStatusIds();
        $visibilites = $this->_productVisibility->getVisibleStatusIds();

        // filtering products with available stock only
        $this->_stockHelper->addInStockFilterToCollection($productsCollection);

        // filtration for active products
        if ($statuses && !empty($statuses)) {
            $productsCollection->addAttributeToFilter('status', [
                'in' => $statuses,
            ]);
        }

        // filtration for visible products
        if ($visibilites && !empty($visibilites)) {
            $productsCollection->setVisibility($visibilites);
        }

        // filtration by current store
        if ($this->_store->getId()) {
            $productsCollection->setStore($this->_store->getId());
        }

        // filtering by category
        if ($filter['category'] && !empty($filter['category'])) {
            $productsCollection->addCategoriesFilter([
                'in' => $filter['category']
            ]);
        }

        // filtering by tag
        if ($filter['tag_name'] && $filter['tag_value']) {
            /** @var Attribute $attribute */
            $attribute = $this->_attributeCollection->getItemByColumnValue('attribute_code', $filter['tag_name']);

            $options = $attribute->getFrontend()->getSelectOptions() ?: [];

            foreach ($options as $option) {
                if (strtolower($option['label']) === strtolower($filter['tag_value'])) {
                    $filter['tag_value'] = $option['value'];
                    break;
                }
            }

            $productsCollection->addAttributeToFilter($filter['tag_name'], [
                'eq' => $filter['tag_value'],
            ]);
        }

        return $productsCollection;
    }
}