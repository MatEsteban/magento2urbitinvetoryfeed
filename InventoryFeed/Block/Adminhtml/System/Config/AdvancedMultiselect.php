<?php

namespace Urbit\InventoryFeed\Block\Adminhtml\System\Config;

/**
 * Class AdvancedMultiselect
 * @package Urbit\InventoryFeed\Block\Adminhtml\System\Config
 */
class AdvancedMultiselect extends \Magento\Config\Block\System\Config\Form\Field
{
    const PRODUCT_FILTER_CONFIG_KEY = 'inventoryfeed_config/filter/products';

    /**
     * Template path
     *
     * @var string
     */
    protected $_template = 'Urbit_InventoryFeed::advanced-multiselect.phtml';

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productColectionFactory;

    /**
     * AdvancedMultiselect constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection,
        array $data = []
    ) {
        $this->_productColectionFactory = $productCollection;
        parent::__construct($context, $data);
    }

    /**
     * @return $this|\Magento\Framework\Data\Collection\AbstractDb
     */
    public function getSelectedProducts()
    {
        $productIds = $this
            ->_scopeConfig
            ->getValue(self::PRODUCT_FILTER_CONFIG_KEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        ;

        return $this
            ->_productColectionFactory
            ->create()
            ->addAttributeToSelect('name')
            ->addStoreFilter()
            ->addFieldToFilter('entity_id', ['in' => explode(',', $productIds)])
        ;
    }

    /**
     * Render fieldset html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $columns = $this->getRequest()->getParam('website') || $this->getRequest()->getParam('store') ? 5 : 4;
        return $this->_decorateRowHtml($element, "<td colspan='{$columns}'>" . $this->toHtml() . '</td>');
    }
}