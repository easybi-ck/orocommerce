<?php

namespace OroB2B\Bundle\PricingBundle\Tests\Unit\SystemConfig;

use OroB2B\Bundle\PricingBundle\Entity\PriceList;
use OroB2B\Bundle\PricingBundle\SystemConfig\PriceListConfig;
use OroB2B\Bundle\PricingBundle\SystemConfig\PriceListConfigConverter;

class PriceListConfigConverterTest extends \PHPUnit_Framework_TestCase
{
    use ConfigsGeneratorTrait;

    public function testConvertBeforeSave()
    {
        $converter = new PriceListConfigConverter($this->getRegistryMock(), '\PriceList');
        $testData = $this->createConfigs(2);

        $expected = [
            ['priceList' => 1, 'priority' => 100],
            ['priceList' => 2, 'priority' => 200]
        ];

        $actual = $converter->convertBeforeSave($testData);
        $this->assertSame($expected, $actual);
    }

    public function testConvertFromSaved()
    {
        $registry = $this->getRegistryMockWithRepository();
        $converter = new PriceListConfigConverter($registry, '\PriceList');

        $configs = [
            ['priceList' => 1, 'priority' => 100],
            ['priceList' => 2, 'priority' => 200]
        ];

        $actual = $converter->convertFromSaved($configs);
        $this->assertEquals(array_reverse($this->createConfigs(2)), $actual);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Price list record with id 5 not found, while reading
     */
    public function testConvertFromSavedInvalidData()
    {
        $registry = $this->getRegistryMockWithRepository();
        $converter = new PriceListConfigConverter($registry, '\PriceList');

        $configs = [
            ['priceList' => 1, 'priority' => 100],
            ['priceList' => 5, 'priority' => 500]
        ];

        $converter->convertFromSaved($configs);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Symfony\Bridge\Doctrine\RegistryInterface
     */
    protected function getRegistryMock()
    {
        return $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Symfony\Bridge\Doctrine\RegistryInterface
     */
    protected function getRegistryMockWithRepository()
    {
        $priceListConfigs = $this->createConfigs(2);
        $priceLists = array_map(function ($item) {
            /** @var PriceListConfig $item */
            return $item->getPriceList();
        }, $priceListConfigs);


        $repository = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $repository->expects($this->once())
            ->method('findBy')
            ->willReturn($priceLists);

        $manager = $this->getMockBuilder('\Doctrine\Common\Persistence\ObjectManager')
            ->disableOriginalConstructor()
            ->getMock();

        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $registry = $this->getRegistryMock();

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($manager);

        return $registry;
    }
}
