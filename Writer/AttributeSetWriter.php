<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\FamilyMappingManager;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;

/**
 * Magento attribute set writer
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeSetWriter extends AbstractWriter
{
    const FAMILY_CREATED = 'Families created';
    const FAMILY_ALREADY = 'Family already in magento';

    /**
     * @var FamilyMappingManager
     */
    protected $familyMappingManager;

    /**
     * @var AttributeMappingManager
     */
    protected $attributeMappingManager;

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
        $this->familyMappingManager = $familyMappingManager;
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items)
    {
        $this->beforeExecute();
        foreach ($items as $item) {
            try {
                $this->handleNewFamily($item);
            } catch (SoapCallException $e) {
                $this->stepExecution->incrementSummaryInfo(self::FAMILY_ALREADY);
            }
        }
    }

    /**
     * Handle family creation
     * @param array $item
     * @throws InvalidItemException
     */
    protected function handleNewFamily(array $item)
    {
        if (isset($item['create'])) {
            $pimFamily       = $item['family'];
            $magentoFamilyId = $this->webservice->createAttributeSet($item['create']['attributeSetName']);
            $magentoUrl      = $this->soapUrl;
            $this->familyMappingManager->registerFamilyMapping(
                $pimFamily,
                $magentoFamilyId,
                $magentoUrl
            );
            $this->stepExecution->incrementSummaryInfo(self::FAMILY_CREATED);
        }
    }
}
