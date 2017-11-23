<?php

namespace Urbit\InventoryFeed\Model\Config\Source;

use Magento\Catalog\Api\CategoryManagementInterface;
use Magento\Framework\Option\ArrayInterface;

/**
 * Class Category
 * @package Urbit\InventoryFeed\Model\Config\Source
 */
class Category implements ArrayInterface
{
    /**
     * @var array
     */
    protected $options = null;

    /**
     * @var string
     */
    protected $separator;

    /**
     * @var CategoryManagementInterface
     */
    protected $categoryManagement;

    /**
     * Category constructor.
     * @param CategoryManagementInterface $categoryManagement
     */
    public function __construct(CategoryManagementInterface $categoryManagement)
    {
        $this->categoryManagement = $categoryManagement;
    }

    /**
     * @return array|null
     */
    public function toOptionArray()
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $this->options = [];

        $categories = $this->categoryManagement->getTree();

        foreach ($categories->getChildrenData() as $category) {
            $this->separator = '';

            $this->options[] = [
                'value' => $category->getId(),
                'label' => $category->getName(),
            ];

            if ($category->getChildrenData()) {
                $this->addSubcategories($category->getChildrenData());
            }
        }

        return $this->options;
    }

    /**
     * @param $subcategories
     * @return $this
     */
    public function addSubcategories($subcategories)
    {
        $this->separator .= '-';
        foreach ($subcategories as $subcategory) {
            $this->options[] = [
                'value' => $subcategory->getId(),
                'label' => $this->separator.' '.$subcategory->getName(),
            ];
            if ($subcategory->getChildrenData()) {
                $this->addSubcategories($subcategory->getChildrenData());
            }
        }
        $this->separator = substr($this->separator, 0, -1);

        return $this;
    }
}