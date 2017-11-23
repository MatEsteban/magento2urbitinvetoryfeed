<?php

namespace Urbit\InventoryFeed\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection as AttributeCollection;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource as AbstractAttributeSource;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory as EavAttributeFactory;
use Magento\Eav\Model\Entity\Type as EntityType;
use Magento\Eav\Model\Entity\TypeFactory as EntityTypeFactory;

/**
 * Class ProductAttributes
 * @package Urbit\InventoryFeed\Model\Config\Source
 */
class ProductAttributes extends AbstractAttributeSource
{
    /**
     * @var array
     */
    protected static $_attributes;

    /**
     * @var EavAttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var EntityTypeFactory
     */
    protected $eavEntityTypeFactory;

    /**
     * ProductAttributes constructor.
     * @param EavAttributeFactory $attributeFactory
     * @param EntityTypeFactory $typeFactory
     */
    public function __construct(EavAttributeFactory $attributeFactory, EntityTypeFactory $typeFactory)
    {
        $this->attributeFactory = $attributeFactory;
        $this->eavEntityTypeFactory = $typeFactory;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        if (!empty(static::$_attributes)) {
            return static::$_attributes;
        }

        $arr = [];

        /** @var EntityType $entityType */
        $entityType = $this->eavEntityTypeFactory->create()->loadByCode('catalog_product');

        /** @var AttributeCollection $collection */
        $collection = $this->attributeFactory->create()
            ->getCollection()
            ->addFieldToFilter('entity_type_id', $entityType->getId())
            ->setOrder('attribute_code')
        ;

        /** @var Attribute $attribute */
        foreach ($collection as $attribute) {
            if (!$attribute->getIsUserDefined()) {
                continue;
            }

            $code = $attribute->getAttributeCode();

            $arr[$code] = $attribute->getFrontendLabel();
        }

        asort($arr);

        static::$_attributes = array_merge([
            '' => 'Not setted',
        ], $arr);

        return static::$_attributes;
    }

    /**
     * @return array
     */
    public function getAllOptions()
    {
        $arr = $this->toArray();
        $ret = [];

        foreach ($arr as $key => $value) {
            $ret[] = [
                'value' => $key,
                'label' => $value,
            ];
        }

        return $ret;
    }
}