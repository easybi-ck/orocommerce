<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Form\EventSubscriber;

use Oro\Bundle\LayoutBundle\Model\ThemeImageType;
use Oro\Bundle\ProductBundle\Entity\ProductImage;
use Oro\Bundle\ProductBundle\Entity\ProductImageType;
use Oro\Bundle\ProductBundle\Form\EventSubscriber\ProductImageTypesSubscriber;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;
use Symfony\Component\Form\FormEvent;

class ProductImageTypesSubscriberTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var array
     */
    protected $typeLabels = [
        ProductImageType::TYPE_MAIN => 'Main',
        ProductImageType::TYPE_LISTING => 'Listing',
        ProductImageType::TYPE_ADDITIONAL => 'Additional',
    ];

    /**
     * @var ProductImageTypesSubscriber
     */
    protected $productImageTypesSubscriber;

    protected function setUp(): void
    {
        $this->productImageTypesSubscriber = new ProductImageTypesSubscriber([
            new ThemeImageType(
                ProductImageType::TYPE_MAIN,
                $this->typeLabels[ProductImageType::TYPE_MAIN],
                [],
                1
            ),
            new ThemeImageType(
                ProductImageType::TYPE_ADDITIONAL,
                $this->typeLabels[ProductImageType::TYPE_ADDITIONAL],
                [],
                null
            ),
            new ThemeImageType(
                ProductImageType::TYPE_LISTING,
                $this->typeLabels[ProductImageType::TYPE_LISTING],
                [],
                2
            )
        ]);
    }

    public function testPostSetData()
    {
        $productImage = new ProductImage();
        $productImage->addType(ProductImageType::TYPE_MAIN);

        $form = $this->prophesize('Symfony\Component\Form\FormInterface');
        $form->getData()->willReturn($productImage);
        $this->addFormAddExpectation($form, ProductImageType::TYPE_MAIN, RadioType::class, true);
        $this->addFormAddExpectation($form, ProductImageType::TYPE_ADDITIONAL, CheckboxType::class, false);
        $this->addFormAddExpectation($form, ProductImageType::TYPE_LISTING, CheckboxType::class, false);

        $event = new FormEvent($form->reveal(), null);

        $this->productImageTypesSubscriber->postSetData($event);
    }

    public function testPreSubmit()
    {
        $data = [
            'image' => 'some data',
            ProductImageType::TYPE_MAIN => 1,
            ProductImageType::TYPE_ADDITIONAL => 1
        ];

        $form = $this->prophesize('Symfony\Component\Form\FormInterface');
        $event = new FormEvent($form->reveal(), $data);

        $this->productImageTypesSubscriber->preSubmit($event);

        $this->assertEquals(
            array_merge($data, ['types' => [ProductImageType::TYPE_MAIN, ProductImageType::TYPE_ADDITIONAL]]),
            $event->getData()
        );
    }

    /**
     * @param ObjectProphecy $form
     * @param string $name
     * @param string $type
     * @param bool $data
     */
    private function addFormAddExpectation(ObjectProphecy $form, $name, $type, $data)
    {
        $form->add($name, $type, [
            'label' => $this->typeLabels[$name],
            'value' => 1,
            'mapped' => false,
            'data' => $data
        ])->shouldBeCalled();
    }
}
