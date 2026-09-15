<?php

namespace Pim\Bundle\CustomEntityBundle\Event\Subscriber;

use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\ORM\EntityManagerInterface;
use Pim\Bundle\CustomEntityBundle\Configuration\Registry;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;
use Pim\Bundle\CustomEntityBundle\Entity\Repository\AttributeRepository;
use Pim\Bundle\CustomEntityBundle\Remover\NonRemovableEntityException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks if a reference data can be removed or not
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 */
class CheckReferenceDataOnRemovalSubscriber implements EventSubscriberInterface
{
    /** @var AttributeRepository */
    protected $attributeRepository;

    /** @var ProductQueryBuilderFactoryInterface */
    protected $pqbFactory;

    /** @var Registry */
    protected $configRegistry;

    /** @var EntityManagerInterface */
    protected $em;

    /**
     * @param AttributeRepository $attributeRepository
     * @param ProductQueryBuilderFactoryInterface $pqbFactory
     * @param Registry $configRegistry
     * @param EntityManagerInterface $em
     */
    public function __construct(
        AttributeRepository $attributeRepository,
        ProductQueryBuilderFactoryInterface $pqbFactory,
        Registry $configRegistry,
        EntityManagerInterface $em
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->pqbFactory = $pqbFactory;
        $this->configRegistry = $configRegistry;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            StorageEvents::PRE_REMOVE => 'checkReferenceDataUsage',
        ];
    }

    /**
     * Checks if the reference data is used in a product
     *
     * @param RemoveEvent $event
     *
     * @return null
     */
    public function checkReferenceDataUsage(RemoveEvent $event)
    {
        $referenceData = $event->getSubject();
        if (!$referenceData instanceof AbstractCustomEntity) {
            return;
        }

        $referenceDataName = $referenceData->getCustomEntityName();
        $attributes = $this->attributeRepository->getAttributesByReferenceDataName($referenceDataName);
        $this->checkProductLink($attributes, [$referenceData->getCode()]);
    }

    /**
     * Checks if a reference data is linked to a product
     *
     * @param array $attributes
     * @param string[] $referenceDataCode
     *
     * @throws NonRemovableEntityException
     */
    protected function checkProductLink($attributes, array $referenceDataCode)
    {
        foreach ($attributes as $attribute) {
            $pqb = $this->pqbFactory->create();
            $pqb->addFilter($attribute->getCode(), Operators::IN_LIST, $referenceDataCode);
            $count = $pqb->execute()->count();

            if (0 !== $count) {
                throw new NonRemovableEntityException(
                    sprintf(
                        'Reference data cannot be removed. It is linked to %s product(s) with the attribute "%s"',
                        $count,
                        $attribute->getCode()
                    )
                );
            }
        }
    }
}
