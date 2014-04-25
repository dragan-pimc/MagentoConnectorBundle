<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\CatalogBundle\Entity\Attribute;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\FamilyMappingManager;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;
use Pim\Bundle\CatalogBundle\Entity\Family;

/**
 * Magento attribute writer
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeWriter extends AbstractWriter
{
    const ATTRIBUTE_UPDATE_SIZE = 2;
    const ATTRIBUTE_UPDATED     = 'Attributes updated';
    const ATTRIBUTE_CREATED     = 'Attributes created';
    const ATTRIBUTE_ALREADY     = 'Attribute already in magento';

    /**
     * @var AttributeMappingManager
     */
    protected $attributeMappingManager;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var FamilyMappingManager
     */
    protected $familyMappingManager;

    /**
     * Constructor
     *
     * @param WebserviceGuesser       $webserviceGuesser
     * @param FamilyMappingManager    $familyMappingManager
     * @param AttributeMappingManager $attributeMappingManager
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        FamilyMappingManager $familyMappingManager,
        AttributeMappingManager $attributeMappingManager
    ) {
        parent::__construct($webserviceGuesser);

        $this->attributeMappingManager = $attributeMappingManager;
        $this->familyMappingManager    = $familyMappingManager;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $attributes)
    {
        $this->beforeExecute();

        foreach ($attributes as $attribute) {
            try {
                $this->attribute = $attribute[0];
                $this->handleAttribute($attribute[1]);
            } catch (SoapCallException $e) {
                throw new InvalidItemException($e->getMessage(), array(json_encode($attribute)));
            }
        }
    }

    /**
     * Handle attribute creation and update
     * @param array $attribute
     * @throws InvalidItemException
     */
    protected function handleAttribute(array $attribute)
    {
        if (count($attribute) === self::ATTRIBUTE_UPDATE_SIZE) {
            $this->webservice->updateAttribute($attribute);

            $magentoAttributeId = $this->attributeMappingManager
                ->getIdFromAttribute($this->attribute, $this->getSoapUrl());

            $this->addAttributeToAttributeSet($magentoAttributeId);
            $this->stepExecution->incrementSummaryInfo(self::ATTRIBUTE_UPDATED);
        } else {
            $magentoAttributeId = $this->webservice->createAttribute($attribute);

            $this->addAttributeToAttributeSet($magentoAttributeId);

            $this->stepExecution->incrementSummaryInfo(self::ATTRIBUTE_CREATED);
            $magentoUrl = $this->soapUrl;

            $this->attributeMappingManager->registerAttributeMapping(
                $this->attribute,
                $magentoAttributeId,
                $magentoUrl
            );
        }
    }

    /**
     * Add attribute to corresponding attribute sets
     * @param int $magentoAttributeId ID of magento attribute
     *
     * @return void
     */
    protected function addAttributeToAttributeSet($magentoAttributeId)
    {
        $families = $this->attribute->getFamilies();
        foreach ($families as $family) {
            $familyMagentoId = $this->familyMappingManager->getIdFromFamily($family, $this->soapUrl);
            try {
                $this->webservice->addAttributeToAttributeSet($magentoAttributeId, $familyMagentoId);
            } catch (SoapCallException $e) {
                $this->stepExecution->incrementSummaryInfo(self::ATTRIBUTE_ALREADY);
            }
        }
    }
}
