<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\DataGrid\EventListener;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datasource\ResultRecord;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\ProductBundle\DataGrid\EventListener\FrontendProductCleanUpListener;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Oro\Bundle\SearchBundle\Datagrid\Event\SearchResultAfter;
use Oro\Bundle\SearchBundle\Query\SearchQueryInterface;

class FrontendProductCleanUpListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject */
    private $doctrineHelper;

    /** @var FrontendProductCleanUpListener */
    private $listener;

    /** @var SearchResultAfter */
    private $event;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->listener = new FrontendProductCleanUpListener($this->doctrineHelper);
        /** @var $datagridMock DatagridInterface|\PHPUnit_Framework_MockObject_MockObject */
        $datagridMock = $this->createMock(DatagridInterface::class);
        /** @var $queryMock SearchQueryInterface|\PHPUnit_Framework_MockObject_MockObject */
        $queryMock = $this->createMock(SearchQueryInterface::class);
        $this->event = new SearchResultAfter($datagridMock, $queryMock, []);
    }

    public function testOnSearchResultAfterNoRecords()
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getEntityRepository');

        $this->listener->onSearchResultAfter($this->event);
        $this->assertEmpty($this->event->getRecords());
    }

    public function testOnSearchResultAfterNoDeletedEntities()
    {
        $record = new ResultRecord(['id' => 1]);
        $existingRecord2 = new ResultRecord(['id' => 2]);
        $this->event->setRecords([$record, $existingRecord2]);

        $this->configureQueryBuilder([['id' => 1], ['id' => 2]]);

        $this->listener->onSearchResultAfter($this->event);
        $this->assertCount(2, $this->event->getRecords());
    }

    public function testOnSearchResultAfterOneDeletedEntity()
    {
        $record = new ResultRecord(['id' => 1]);
        $deletedRecord = new ResultRecord(['id' => 2]);
        $this->event->setRecords([$record, $deletedRecord]);

        $this->configureQueryBuilder([['id' => 1]]);

        $this->listener->onSearchResultAfter($this->event);
        $this->assertCount(1, $this->event->getRecords());
    }

    /**
     * @param array $expectedResult
     */
    private function configureQueryBuilder(array $expectedResult): void
    {
        /** @var $repository ProductRepository|\PHPUnit\Framework\MockObject\MockObject */
        $repository = $this->createMock(ProductRepository::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);

        $repository->expects($this->once())
            ->method('getProductsQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('p.id')
            ->willReturn($queryBuilder);

        $query = $this->getMockBuilder(AbstractQuery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getArrayResult')
            ->willReturn($expectedResult);

        $this->doctrineHelper->expects($this->once())
            ->method('getEntityRepository')
            ->willReturn($repository);
    }
}
