<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Oro\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\CatalogBundle\Model\Association;
use Pim\Bundle\CatalogBundle\Entity\AssociationType;
use Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\HasValidCredentials;
use Pim\Bundle\MagentoConnectorBundle\Manager\AssociationTypeManager;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;

/**
 * Magento product processor
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @HasValidCredentials()
 */
class ProductAssociationProcessor extends AbstractProcessor
{
    const MAGENTO_UP_SELL    = 'up_sell';
    const MAGENTO_CROSS_SELL = 'cross_sell';
    const MAGENTO_RELATED    = 'related';
    const MAGENTO_GROUPED    = 'grouped';

    /**
     * @var AssociationTypeManager
     */
    protected $associationTypeManager;

    /**
     * @var string
     */
    protected $pimUpSell;

    /**
     * @var string
     */
    protected $pimCrossSell;

    /**
     * @var string
     */
    protected $pimRelated;

    /**
     * @var string
     */
    protected $pimGrouped;

    /**
     * @param ChannelManager           $channelManager
     * @param WebserviceGuesser        $webserviceGuesser
     * @param ProductNormalizerGuesser $normalizerGuesser
     * @param AssociationTypeManager   $associationTypeManager
     */
    public function __construct(
        ChannelManager $channelManager,
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        AssociationTypeManager $associationTypeManager
    ) {
        parent::__construct($channelManager, $webserviceGuesser, $normalizerGuesser);

        $this->associationTypeManager = $associationTypeManager;
    }

    /**
     * Get pimUpSell
     * @return string
     */
    public function getPimUpSell()
    {
        return $this->pimUpSell;
    }

    /**
     * Set pimUpSell
     * @param string $pimUpSell
     *
     * @return ProductAssociationProcessor
     */
    public function setPimUpSell($pimUpSell)
    {
        $this->pimUpSell = $pimUpSell;

        return $this;
    }

    /**
     * Get pimCrossSell
     * @return string
     */
    public function getPimCrossSell()
    {
        return $this->pimCrossSell;
    }

    /**
     * Set pimCrossSell
     * @param string $pimCrossSell
     *
     * @return ProductAssociationProcessor
     */
    public function setPimCrossSell($pimCrossSell)
    {
        $this->pimCrossSell = $pimCrossSell;

        return $this;
    }

    /**
     * Get pimRelated
     * @return string
     */
    public function getPimRelated()
    {
        return $this->pimRelated;
    }

    /**
     * Set pimRelated
     * @param string $pimRelated
     *
     * @return ProductAssociationProcessor
     */
    public function setPimRelated($pimRelated)
    {
        $this->pimRelated = $pimRelated;

        return $this;
    }

    /**
     * Get pimGrouped
     * @return string
     */
    public function getPimGrouped()
    {
        return $this->pimGrouped;
    }

    /**
     * Set pimGrouped
     * @param string $pimGrouped
     *
     * @return ProductAssociationProcessor
     */
    public function setPimGrouped($pimGrouped)
    {
        $this->pimGrouped = $pimGrouped;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function process($items)
    {
        $this->beforeExecute();

        $processedItems = array();

        $productAssociationCalls = array('remove' => array(), 'create' => array());

        foreach ($items as $product) {
            try {
                $productAssociationCalls['remove'] = array_merge(
                    $productAssociationCalls['remove'],
                    $this->getRemoveCallsForProduct(
                        $product,
                        $this->webservice->getAssociationsStatus($product)
                    )
                );
                $productAssociationCalls['create'] = array_merge(
                    $productAssociationCalls['create'],
                    $this->getCreateCallsForProduct($product)
                );
            } catch (SoapCallException $e) {
                throw new InvalidItemException($e->getMessage(), array($product));
            }
        }

        return $productAssociationCalls;
    }

    /**
     * Get create calls for a given product
     * @param ProductInterface $product
     *
     * @return array
     */
    protected function getCreateCallsForProduct(ProductInterface $product)
    {
        $createAssociationCalls = array();

        foreach ($product->getAssociations() as $productAssociation) {
            $createAssociationCalls = array_merge(
                $createAssociationCalls,
                $this->getCreateCallsForAssociation($product, $productAssociation)
            );
        }

        return $createAssociationCalls;
    }

    /**
     * Get create calls
     * @param ProductInterface $product
     * @param Association      $association
     *
     * @return array
     */
    protected function getCreateCallsForAssociation(ProductInterface $product, Association $association)
    {
        $createAssociationCalls = array();

        $associationType = $association->getAssociationType()->getCode();

        if (in_array($associationType, array_keys($this->getAssociationCodeMapping()))) {
            foreach ($association->getProducts() as $associatedProduct) {
                $createAssociationCalls[] = array(
                    'type'          => $this->getAssociationCodeMapping()[$associationType],
                    'product'       => (string) $product->getIdentifier(),
                    'linkedProduct' => (string) $associatedProduct->getIdentifier()
                );
            }
        }

        return $createAssociationCalls;
    }

    /**
     * Get remove association calls for a given product
     * @param ProductInterface $product
     * @param array            $associationStatus
     *
     * @return return array
     */
    protected function getRemoveCallsForProduct(ProductInterface $product, array $associationStatus)
    {
        $removeAssociationCalls = array();

        foreach ($associationStatus as $associationType => $associatedProducts) {
            foreach ($associatedProducts as $associatedProduct) {
                $removeAssociationCalls[] = array(
                    'type'          => $associationType,
                    'product'       => (string) $product->getIdentifier(),
                    'linkedProduct' => (string) $associatedProduct['sku']
                );
            }
        }

        return $removeAssociationCalls;
    }

    /**
     * Get association code mapping
     * @return array
     */
    protected function getAssociationCodeMapping()
    {
        $associationCodeMapping = array();

        if ($this->getPimUpSell()) {
            $associationCodeMapping[$this->getPimUpSell()] = self::MAGENTO_UP_SELL;
        }

        if ($this->getPimCrossSell()) {
            $associationCodeMapping[$this->getPimCrossSell()] = self::MAGENTO_CROSS_SELL;
        }

        if ($this->getPimRelated()) {
            $associationCodeMapping[$this->getPimRelated()] = self::MAGENTO_RELATED;
        }

        if ($this->getPimGrouped()) {
            $associationCodeMapping[$this->getPimGrouped()] = self::MAGENTO_GROUPED;
        }

        return $associationCodeMapping;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            array(
                'pimUpSell' => array(
                    'type'    => 'choice',
                    'options' => array(
                        'choices' => $this->associationTypeManager->getAssociationTypeChoices()
                    )
                ),
                'pimCrossSell' => array(
                    'type'    => 'choice',
                    'options' => array(
                        'choices' => $this->associationTypeManager->getAssociationTypeChoices()
                    )
                ),
                'pimRelated' => array(
                    'type'    => 'choice',
                    'options' => array(
                        'choices' => $this->associationTypeManager->getAssociationTypeChoices()
                    )
                ),
                'pimGrouped' => array(
                    'type'    => 'choice',
                    'options' => array(
                        'choices' => $this->associationTypeManager->getAssociationTypeChoices()
                    )
                )
            )
        );
    }
}
