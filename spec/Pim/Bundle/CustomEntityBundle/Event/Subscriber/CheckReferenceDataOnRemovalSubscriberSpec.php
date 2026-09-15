<?php

namespace spec\Pim\Bundle\CustomEntityBundle\Event\Subscriber;

use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Doctrine\ORM\EntityManagerInterface;
use PhpSpec\ObjectBehavior;
use Pim\Bundle\CustomEntityBundle\Configuration\Registry;
use Pim\Bundle\CustomEntityBundle\Entity\AbstractCustomEntity;
use Pim\Bundle\CustomEntityBundle\Entity\Repository\AttributeRepository;
use Pim\Bundle\CustomEntityBundle\Event\Subscriber\CheckReferenceDataOnRemovalSubscriber;
use Pim\Bundle\CustomEntityBundle\Remover\NonRemovableEntityException;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderFactoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Query\ProductQueryBuilderInterface;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 */
class CheckReferenceDataOnRemovalSubscriberSpec extends ObjectBehavior
{
    function let(
        AttributeRepository $attributeRepository,
        ProductQueryBuilderFactoryInterface $pqbFactory,
        Registry $configRegistry,
        EntityManagerInterface $em
    ) {
        $this->beConstructedWith($attributeRepository, $pqbFactory, $configRegistry, $em);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(CheckReferenceDataOnRemovalSubscriber::class);
    }

    function it_is_an_event_subscriber()
    {
        $this->shouldHaveType(EventSubscriberInterface::class);
    }

    function it_subscribes_to_pre_remove_events()
    {
        $this->getSubscribedEvents()->shouldHaveKey(StorageEvents::PRE_REMOVE);
        $this->getSubscribedEvents()->shouldHaveCount(1);
    }

    function it_does_not_check_other_entities_than_reference_data(RemoveEvent $event, AbstractCustomEntity $object)
    {
        $event->getSubject()->willReturn(Argument::not($object));
        $this->checkReferenceDataUsage($event)->shouldReturn(null);
    }

    function it_checks_reference_data_usage(
        RemoveEvent $event,
        AbstractCustomEntity $refData,
        AttributeRepository $attributeRepository,
        AttributeInterface $attribute,
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ProductQueryBuilderInterface $pqb,
        \Countable $countable
    ) {
        $event->getSubject()->willReturn($refData);
        $refData->getCode()->willReturn('green');
        $refData->getCustomEntityName()->willReturn('color');
        $attribute->getCode()->willReturn('main_color');
        $attributeRepository->getAttributesByReferenceDataName('color')->willReturn([$attribute]);

        $pqbFactory->create()->willReturn($pqb);
        $pqb->addFilter('main_color', Operators::IN_LIST, ['green'])->shouldBeCalled();
        $pqb->execute()->willReturn($countable);
        $countable->count()->willReturn(0);

        $this->checkReferenceDataUsage($event)->shouldReturn(null);
    }

    function it_throws_an_exception_when_reference_data_is_used_in_at_least_one_product(
        RemoveEvent $event,
        AbstractCustomEntity $refData,
        AttributeRepository $attributeRepository,
        AttributeInterface $attribute,
        ProductQueryBuilderFactoryInterface $pqbFactory,
        ProductQueryBuilderInterface $pqb,
        \Countable $countable
    ) {
        $event->getSubject()->willReturn($refData);
        $refData->getCode()->willReturn('green');
        $refData->getCustomEntityName()->willReturn('color');
        $attribute->getCode()->willReturn('main_color');
        $attributeRepository->getAttributesByReferenceDataName('color')->willReturn([$attribute]);

        $pqbFactory->create()->willReturn($pqb);
        $pqb->addFilter('main_color', Operators::IN_LIST, ['green'])->shouldBeCalled();
        $pqb->execute()->willReturn($countable);

        $countable->count()->willReturn(1);
        $this
            ->shouldThrow(NonRemovableEntityException::class)
            ->during('checkReferenceDataUsage', [$event]);

        $countable->count()->willReturn(5);
        $this
            ->shouldThrow(NonRemovableEntityException::class)
            ->during('checkReferenceDataUsage', [$event]);
    }
}
