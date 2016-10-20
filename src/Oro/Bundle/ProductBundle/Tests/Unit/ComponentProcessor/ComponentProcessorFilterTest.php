<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\ComponentProcessor;

use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\ProductBundle\ComponentProcessor\ComponentProcessorFilter;
use Oro\Bundle\ProductBundle\Entity\Manager\ProductManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Search\ProductRepository;
use Oro\Bundle\ProductBundle\Storage\ProductDataStorage;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result\Item;
use Oro\Bundle\SearchBundle\Query\SearchQueryInterface;

class ComponentProcessorFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ComponentProcessorFilter|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $filter;

    /**
     * @var ProductManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $productManager;

    /**
     * @var ProductRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $productRepository;

    protected function setUp()
    {
        $this->filter = new ComponentProcessorFilter(
            $this->getProductManager(),
            $this->getProductRepository()
        );
    }

    protected function tearDown()
    {
        unset($this->filter, $this->productManager, $this->productRepository);
    }

    /**
     * @return ProductManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getProductManager()
    {
        if (!$this->productManager) {
            $this->productManager = $this->getMockBuilder(ProductManager::class)
                ->disableOriginalConstructor()
                ->getMock();
        }
        return $this->productManager;
    }

    /**
     * @return ProductRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getProductRepository()
    {
        if (!$this->productRepository) {
            $this->productRepository = $this
                ->getMockBuilder(ProductRepository::class)
                ->disableOriginalConstructor()
                ->getMock();
        }
        return $this->productRepository;
    }

    public function testFilterData()
    {
        $skus           = ['visibleSku1', 'invisibleSku1', 'visibleSku2'];
        $data           = [
            ProductDataStorage::ENTITY_ITEMS_DATA_KEY => [
                ['productSku' => $skus[0]],
                ['productSku' => $skus[1]],
                ['productSku' => $skus[2]],
            ],
        ];
        $dataParameters = [];

        $searchQuery = $this->getMockBuilder(SearchQueryInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getResult', 'toArray'])
            ->getMockForAbstractClass();
        $searchQuery->expects($this->once())
            ->method('getResult')
            ->willReturnSelf();
        $searchQuery->expects($this->once())
            ->method('toArray')
            ->willReturnCallback(function () use ($skus) {
                $filteredSkus = [];
                $objectManager = $this->getMockBuilder(ObjectManager::class)
                    ->disableOriginalConstructor()->getMock();
                foreach ($skus as $index => $sku) {
                    if (strpos($sku, 'invisibleSku') === false) {
                        $filteredSkus[] = new Item(
                            $objectManager,
                            Product::class,
                            $index,
                            null,
                            null,
                            [
                                'sku'           => $sku,
                                'sku_uppercase' => strtoupper($sku)
                            ]
                        );
                    }
                }
                return $filteredSkus;
            });
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()->getMock();
        $searchQuery->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->getProductRepository()->expects($this->once())
            ->method('getFilterSkuQuery')
            ->with(array_map('strtoupper', $skus))
            ->willReturn($searchQuery);

        $this->getProductManager()->expects($this->once())
            ->method('restrictSearchQuery')
            ->with($query)
            ->willReturn($searchQuery);

        $filteredData = $this->filter->filterData($data, $dataParameters);

        $this->assertInternalType('array', $filteredData);
        $this->assertCount(2, $filteredData[ProductDataStorage::ENTITY_ITEMS_DATA_KEY]);
        $this->assertEquals(
            $data[ProductDataStorage::ENTITY_ITEMS_DATA_KEY][0],
            $filteredData[ProductDataStorage::ENTITY_ITEMS_DATA_KEY][0]
        );
        $this->assertEquals(
            $data[ProductDataStorage::ENTITY_ITEMS_DATA_KEY][2],
            $filteredData[ProductDataStorage::ENTITY_ITEMS_DATA_KEY][1]
        );
    }
}
