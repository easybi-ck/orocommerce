<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\ORM;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\PricingBundle\Entity\CombinedPriceList;
use Oro\Bundle\PricingBundle\Entity\CombinedProductPrice;
use Oro\Bundle\PricingBundle\Entity\Repository\CombinedProductPriceRepository;
use Oro\Bundle\PricingBundle\ORM\TempTableManipulator;
use Oro\Bundle\PricingBundle\ORM\Walker\TempTableOutputResultModifier;
use Oro\Bundle\PricingBundle\Sharding\ShardManager;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadCombinedPriceLists;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadCombinedProductPrices;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPrices;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Symfony\Bridge\Doctrine\ManagerRegistry;

class TempTableManipulatorTest extends WebTestCase
{
    /**
     * @var TempTableManipulator
     */
    private $tempTableManipulator;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var ShardManager
     */
    private $shardManager;

    protected function setUp(): void
    {
        $this->initClient();
        $this->loadFixtures(
            [
                LoadCombinedPriceLists::class,
                LoadProductPrices::class,
                LoadCombinedProductPrices::class,
            ]
        );
        $this->registry = $this->getContainer()->get('doctrine');
        $this->connection = $this->getContainer()->get('doctrine')->getConnection();
        $this->tempTableManipulator = $this->getContainer()->get('oro_pricing.orm.temp_table_manipulator');
        $this->shardManager = $this->getContainer()->get('oro_pricing.shard_manager');
    }

    public function testGetTableNameForEntity()
    {
        $tableName = $this->tempTableManipulator->getTableNameForEntity(CombinedProductPrice::class);
        $this->assertEquals('oro_price_product_combined', $tableName);
    }

    public function testGetTempTableNameForEntity()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');

        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );
        $this->assertEquals('oro_price_product_combined_tmp_' . $cpl->getId(), $tempTableName);
    }

    public function testCreateTempTableForEntity()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );

        $this->tempTableManipulator->createTempTableForEntity(CombinedProductPrice::class, $cpl->getId());

        $this->assertTableRecordsCount(0, $tempTableName);
    }

    /**
     * @depends testCreateTempTableForEntity
     */
    public function testInsertData()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );

        $this->insertPricesFromCplToTemp();
        $this->assertTableRecordsCount(2, $tempTableName);
    }

    /**
     * @depends testInsertData
     *
     * @covers TempTableOutputResultModifier::walkFromClause
     * @covers TempTableOutputResultModifier::walkSubselectFromClause
     * @covers TempTableOutputResultModifier::walkJoin
     */
    public function testTempTableUsageInQb()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );

        /** @var CombinedProductPriceRepository $repo */
        $repo = $this->registry->getRepository(CombinedProductPrice::class);
        $qb = $repo->createQueryBuilder('cpp');
        $qb->where(
            $qb->expr()->eq('cpp.priceList', ':cpl')
        );
        $qb->setParameter('cpl', $cpl);

        $subQuery = $repo->createQueryBuilder('cpp_temp1')
            ->innerJoin(
                CombinedProductPrice::class,
                'cpp_temp2',
                Join::WITH,
                'cpp_temp1.priceList = cpp_temp2.priceList'
            )
            ->where('cpp_temp1.product = cpp.product AND cpp_temp1.priceList = cpp.priceList');
        $qb->andWhere($qb->expr()->not($qb->expr()->exists($subQuery->getDQL())));

        $query = $qb->getQuery();
        $query->setHint(
            TempTableOutputResultModifier::ORO_TEMP_TABLE_ALIASES,
            [
                'cpp_temp1' => $tempTableName,
                'cpp_temp2' => $tempTableName
            ]
        );

        $this->assertStringContainsString('FROM ' . $tempTableName, $query->getSQL());
        $this->assertStringContainsString('JOIN ' . $tempTableName, $query->getSQL());
        $result = $query->getResult();
        $this->assertEmpty($result);

        $qb2 = $repo->createQueryBuilder('cpp');
        $query2 = $qb2->getQuery();
        $query2->setHint(
            TempTableOutputResultModifier::ORO_TEMP_TABLE_ALIASES,
            ['cpp' => $tempTableName]
        );
        $this->assertStringContainsString('FROM ' . $tempTableName, $query2->getSQL());
        $this->assertCount(2, $query2->getResult(AbstractQuery::HYDRATE_ARRAY));
    }

    /**
     * @depends testTempTableUsageInQb
     */
    public function testTruncateTempTableForEntity()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );
        $this->tempTableManipulator->truncateTempTableForEntity(CombinedProductPrice::class, $cpl->getId());
        $this->assertTableRecordsCount(0, $tempTableName);
    }

    /**
     * @depends testTruncateTempTableForEntity
     */
    public function testMoveDataFromTemplateTableToEntityTable()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );
        $this->insertPricesFromCplToTemp();

        $this->assertTableRecordsCount(2, $tempTableName);

        /** @var CombinedProductPriceRepository $repo */
        $repo = $this->registry->getRepository(CombinedProductPrice::class);
        $repo->deleteCombinedPrices($cpl);

        $prices = $repo->findByPriceList($this->shardManager, $cpl, []);
        $this->assertEmpty($prices);

        $this->tempTableManipulator->moveDataFromTemplateTableToEntityTable(
            CombinedProductPrice::class,
            $cpl->getId(),
            [
                'product',
                'unit',
                'priceList',
                'productSku',
                'quantity',
                'value',
                'currency',
                'mergeAllowed',
                'originPriceId',
                'id'
            ]
        );
        $prices = $repo->findByPriceList($this->shardManager, $cpl, []);
        $this->assertCount(2, $prices);
        $this->assertTableRecordsCount(0, $tempTableName);
    }

    /**
     * @depends testMoveDataFromTemplateTableToEntityTable
     */
    public function testDropTempTableForEntity()
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );
        $this->tempTableManipulator->dropTempTableForEntity(CombinedProductPrice::class, $cpl->getId());

        $this->expectException(TableNotFoundException::class);
        $this->assertTableRecordsCount(0, $tempTableName);
    }

    /**
     * @param int $count
     * @param string $tableName
     * @throws \Doctrine\DBAL\DBALException
     */
    private function assertTableRecordsCount(int $count, string $tableName): void
    {
        $records = $this->connection->fetchColumn('SELECT COUNT(*) FROM ' . $tableName);
        $this->assertEquals($count, $records);
    }

    /**
     * @return string
     */
    private function insertPricesFromCplToTemp(): string
    {
        /** @var CombinedPriceList $cpl */
        $cpl = $this->getReference('2t_3t');
        $tempTableName = $this->tempTableManipulator->getTempTableNameForEntity(
            CombinedProductPrice::class,
            $cpl->getId()
        );

        /** @var CombinedProductPriceRepository $repo */
        $repo = $this->registry->getRepository(CombinedProductPrice::class);
        $qb = $repo->createQueryBuilder('pp');
        $qb
            ->select(
                'IDENTITY(pp.product)',
                'IDENTITY(pp.unit)',
                'IDENTITY(pp.priceList)',
                'pp.productSku',
                'pp.quantity',
                'pp.value',
                'pp.currency',
                sprintf('CAST(%d as boolean)', 1),
                'pp.originPriceId',
                'UUID()'
            )
            ->where($qb->expr()->eq('pp.priceList', ':currentPriceList'))
            ->setParameter('currentPriceList', $cpl);

        $this->tempTableManipulator->insertData(
            $tempTableName,
            CombinedProductPrice::class,
            $cpl->getId(),
            [
                'product',
                'unit',
                'priceList',
                'productSku',
                'quantity',
                'value',
                'currency',
                'mergeAllowed',
                'originPriceId',
                'id'
            ],
            $qb,
            false
        );

        return $tempTableName;
    }
}
