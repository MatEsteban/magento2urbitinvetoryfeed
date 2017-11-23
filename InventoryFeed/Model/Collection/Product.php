<?php

namespace Urbit\InventoryFeed\Model\Collection;

use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status as MagentoProductStatus;
use Magento\Catalog\Model\Product\Visibility as MagentoProductVisibility;
use Magento\Catalog\Model\ProductFactory as MagentoProductFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as MagentoProductCollection;
use Exception;
use IteratorAggregate;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Store\Api\Data\StoreInterface as Store;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;

/**
 * Class Product
 * @package Urbit\InventoryFeed\Model\Collection
 */
class Product implements IteratorAggregate
{
    /**
     * @var array
     */
    protected $_filterDefault = [
        'category' => [],
        'attribute_name'  => false,
        'attribute_value' => false,
    ];

    /**
     * @var array
     */
    protected $_filter = [];

    /**
     * @var MagentoProductCollection
     */
    protected $_products;

    /**
     * @var MagentoProduct
     */
    protected $_productModel;

    /**
     * @var Store
     */
    protected $_store;

    /**
     * @var AttributeCollection
     */
    protected $_attributeCollection;

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
     * Product Collection constructor.
     * @param array $filter
     * @param MagentoProductFactory $productFactory
     * @param MagentoProductStatus $productStatus
     * @param MagentoProductVisibility $productVisibility
     * @param AttributeCollection $attributeCollection
     * @param Store $store
     * @param StockHelper $stockHelper
     */
    public function __construct(
        $filter = [],
        MagentoProductFactory $productFactory,
        MagentoProductStatus $productStatus,
        MagentoProductVisibility $productVisibility,
        AttributeCollection $attributeCollection,
        Store $store,
        StockHelper $stockHelper
    ) {
        $this->setFilter($filter);
        $this->_productModel = $productFactory->create();
        $this->_productStatus = $productStatus;
        $this->_productVisibility = $productVisibility;
        $this->_store = $store;
        $this->_attributeCollection = $attributeCollection;
        $this->_stockHelper = $stockHelper;
    }

    /**
     * Fetching data for iteration by IteratorAggregate interface
     * @return MagentoProductCollection
     */
    public function getIterator()
    {
        return $this->getProducts();
    }

    /**
     * Load products data to current block by filter
     * @param bool $reload
     * @return MagentoProductCollection
     */
    protected function loadProducts($reload = false)
    {
        if ($this->_products && !$reload) {
            return $this->_products;
        }

        /** @var MagentoProductCollection $collection */
        $collection = $this->_productModel->getCollection()
            ->addAttributeToSelect('*')
        ;

        /** @var array $filter */
        $filter = $this->getFilter();

        $statuses     = $this->_productStatus->getVisibleStatusIds();
        $visibilities = $this->_productVisibility->getVisibleStatusIds();

        // filtering products with available stock only
        $this->_stockHelper->addInStockFilterToCollection($collection);

        // filtration for active products
        if ($statuses && !empty($statuses)) {
            $collection->addAttributeToFilter('status', [
                'in' => $statuses,
            ]);
        }

        // filtration for visible products
        if ($visibilities && !empty($visibilities)) {
            $collection->setVisibility($visibilities);
        }

        // filtration by current store
        if ($this->_store->getId()) {
            $collection->setStore($this->_store->getId());
        }

        // filtering by category
        if ($filter['category'] && !empty($filter['category'])) {
            $collection->addCategoriesFilter([
                'in' => explode(',', $filter['category'])
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

            $collection->addAttributeToFilter($filter['tag_name'], [
                'eq' => $filter['tag_value'],
            ]);
        }

        $this->_products = $collection;

        return $this->_products;
    }


    /**
     * Get product collection
     * @return MagentoProductCollection
     */
    public function getCollection()
    {
        return $this->loadProducts();
    }

    /**
     * @return MagentoProductCollection
     */
    public function getProducts()
    {
        return $this->getCollection()->load();
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->_filter;
    }

    /**
     * @param array $filter
     * @return $this
     * @throws Exception
     */
    public function setFilter($filter)
    {
        if (!is_array($filter)) {
            throw new Exception("Product feed filter should be an array");
        }

        $this->_filter = $filter + $this->_filterDefault;

        return $this;
    }
}