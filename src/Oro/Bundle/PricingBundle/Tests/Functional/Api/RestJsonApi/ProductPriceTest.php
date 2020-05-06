<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\Api\RestJsonApi;

use Oro\Bundle\ApiBundle\Tests\Functional\RestJsonApiTestCase;
use Oro\Bundle\PricingBundle\Async\Topics;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Model\PriceListTriggerFactory;
use Oro\Bundle\PricingBundle\ORM\Walker\PriceShardOutputResultModifier;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPricesWithRules;
use Oro\Bundle\PricingBundle\Tests\Functional\Entity\EntityListener\MessageQueueTrait;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Symfony\Component\HttpFoundation\Response;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class ProductPriceTest extends RestJsonApiTestCase
{
    use MessageQueueTrait;

    /**
     * {@inheritDoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->loadFixtures([LoadProductPricesWithRules::class]);
    }

    public function testGetList()
    {
        $response = $this->cget(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => ['@price_list_1->id']], 'sort' => 'product']
        );

        $this->assertResponseContains('product_price/get_list.yml', $response);
    }

    public function testGetListWithTotalCount()
    {
        $response = $this->cget(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => ['@price_list_1->id']], 'page' => ['size' => 1], 'sort' => 'product'],
            ['HTTP_X-Include' => 'totalCount']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    [
                        'type' => 'productprices',
                        'id'   => '<(implode("-", [@product_price_with_rule_1->id, @price_list_1->id]))>',
                    ]
                ]
            ],
            $response
        );
        self::assertEquals(2, $response->headers->get('X-Include-Total-Count'));
    }

    public function testTryToGetListWithoutPriceListFilter()
    {
        $response = $this->cget(
            ['entity' => 'productprices'],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'filter constraint',
                'detail' => 'The "priceList" filter is required.'
            ],
            $response
        );
    }

    public function testGetListWhenPriceListFilterContainsIdOfNotExistingPriceList()
    {
        $response = $this->cget(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => '9999']]
        );

        $this->assertResponseContains(['data' => []], $response);
    }

    public function testTryToGetListWhenPriceListFilterContainsNotIntegerValue()
    {
        $response = $this->cget(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => 'invalid']],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'filter constraint',
                'detail' => 'Expected integer value. Given "invalid".',
                'source' => ['parameter' => 'filter[priceList]']
            ],
            $response
        );
    }

    public function testCreate()
    {
        $this->cleanScheduledMessages();

        $response = $this->post(
            ['entity' => 'productprices'],
            'product_price/create.yml'
        );

        $productPrice = $this->getProductPrice('price_list_3');
        self::assertNotNull($productPrice);

        self::assertEquals(
            $productPrice->getId() . '-' . $productPrice->getPriceList()->getId(),
            $this->getResourceId($response)
        );

        $this->assertMessagesSentForCreateRequest('price_list_3');
    }

    public function testTryToCreateDuplicate()
    {
        $this->post(
            ['entity' => 'productprices'],
            'product_price/create.yml'
        );

        $response = $this->post(
            ['entity' => 'productprices'],
            'product_price/create.yml',
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'unique entity constraint',
                'detail' => 'Product has duplication of product prices.'
                    . ' Set of fields "PriceList", "Quantity" , "Unit" and "Currency" should be unique.'
            ],
            $response
        );
    }

    public function testTryToCreateEmptyValue()
    {
        $data = $this->getRequestData('product_price/create.yml');
        $data['data']['attributes']['value'] = '';
        $response = $this->post(
            ['entity' => 'productprices'],
            $data,
            [],
            false
        );

        $this->assertResponseContainsValidationError(
            [
                'title'  => 'not blank constraint',
                'detail' => 'Price value should not be blank.',
                'source' => ['pointer' => '/data/attributes/value']
            ],
            $response
        );
    }

    public function testTryToCreateEmptyCurrency()
    {
        $data = $this->getRequestData('product_price/create.yml');
        $data['data']['attributes']['currency'] = '';
        $response = $this->post(
            ['entity' => 'productprices'],
            $data,
            [],
            false
        );

        $this->assertResponseContainsValidationError(
            [
                'title'  => 'not blank constraint',
                'detail' => 'This value should not be blank.',
                'source' => ['pointer' => '/data/attributes/currency']
            ],
            $response
        );
    }

    public function testTryToCreateWrongValue()
    {
        $data = $this->getRequestData('product_price/create.yml');
        $data['data']['attributes']['value'] = 'test';
        $response = $this->post(
            ['entity' => 'productprices'],
            $data,
            [],
            false
        );

        $this->assertResponseContainsValidationError(
            [
                'title'  => 'type constraint',
                'detail' => 'This value should be of type numeric.',
                'source' => ['pointer' => '/data/attributes/value']
            ],
            $response
        );
    }

    public function testTryToCreateWrongCurrency()
    {
        $data = $this->getRequestData('product_price/create.yml');
        $data['data']['attributes']['currency'] = 'EUR';
        $response = $this->post(
            ['entity' => 'productprices'],
            $data,
            [],
            false
        );

        $this->assertResponseContainsValidationError(
            [
                'title'  => 'product price currency constraint',
                'detail' => 'Currency "EUR" is not valid for current price list.',
                'source' => ['pointer' => '/data/attributes/currency']
            ],
            $response
        );
    }

    public function testTryToCreateWrongProductUnit()
    {
        $data = $this->getRequestData('product_price/create.yml');
        $data['data']['relationships']['unit']['data']['id'] = '<toString(@product_unit.liter->code)>';
        $response = $this->post(
            ['entity' => 'productprices'],
            $data,
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'product price allowed units constraint',
                'detail' => 'Unit "liter" is not allowed for product "product-5".',
                'source' => ['pointer' => '/data/relationships/unit/data']
            ],
            $response
        );
    }

    public function testCreateTogetherWithPriceList()
    {
        $response = $this->post(
            ['entity' => 'productprices'],
            'product_price/create_with_priceList.yml'
        );

        $content = self::jsonToArray($response->getContent());
        $priceListId = (int)$content['data']['relationships']['priceList']['data']['id'];
        $productPrice = $this->getProductPrice($priceListId);
        self::assertNotNull($productPrice);

        self::assertEquals(
            $productPrice->getId() . '-' . $productPrice->getPriceList()->getId(),
            $this->getResourceId($response)
        );

        $this->assertMessagesSentForCreateRequest($priceListId);
    }

    public function testDeleteList()
    {
        $this->cleanScheduledMessages();

        $priceList = $this->getReference('price_list_1');
        $priceListId = $priceList->getId();
        $product1Id = $this->getReference('product-1')->getId();
        $product2Id = $this->getReference('product-2')->getId();

        $this->cdelete(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => (string)$priceListId]]
        );

        self::assertSame(
            0,
            $this->getEntityManager()->getRepository(ProductPrice::class)->countByPriceList(
                self::getContainer()->get('oro_pricing.shard_manager'),
                $priceList
            )
        );

        $message = self::getSentMessage(Topics::RESOLVE_COMBINED_PRICES);
        self::assertIsArray($message);
        self::assertArrayHasKey('product', $message);
        self::assertArrayHasKey($priceListId, $message['product']);
        $productIds = $message['product'][$priceListId];
        sort($productIds);
        self::assertEquals([$product1Id, $product2Id], $productIds);
    }

    public function testDeleteListWhenPriceListFilterContainsIdOfNotExistingPriceList()
    {
        $this->cleanScheduledMessages();

        $this->cdelete(
            ['entity' => 'productprices'],
            ['filter' => ['priceList' => '9999']]
        );

        self::assertEmptyMessages(Topics::RESOLVE_COMBINED_PRICES);
    }

    public function testTryToDeleteListWithoutPriceListFilter()
    {
        $response = $this->cdelete(
            ['entity' => 'productprices'],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'filter constraint',
                'detail' => 'At least one filter must be provided.'
            ],
            $response
        );
    }

    public function testTryToGetWithoutPriceListInId()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPrice()->getId()],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testTryToGetWithWrongPriceListId()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId('price_list_2')],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testTryToGetWhenProductListDoesNotExist()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPrice()->getId() . '-9999'],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testTryToGetWhenProductPriceIdIsNotGuid()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => 'invalid-' . $this->getReference('price_list_1')->getId()],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testTryToGetWhenProductListIdIsNotInteger()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPrice()->getId() . '-invalid'],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testTryToGetWhenIdIsInvalid()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => 'invalid'],
            [],
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'not found http exception',
                'detail' => 'An entity with the requested identifier does not exist.'
            ],
            $response,
            Response::HTTP_NOT_FOUND
        );
    }

    public function testGet()
    {
        $response = $this->get(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()]
        );

        $this->assertResponseContains('product_price/get.yml', $response);
    }

    public function testUpdate()
    {
        $this->cleanScheduledMessages();

        $response = $this->patch(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()],
            'product_price/update.yml'
        );

        $productPrice = $this->getProductPrice('price_list_1');
        self::assertNotNull($productPrice);

        self::assertEquals(
            $productPrice->getId() . '-' . $productPrice->getPriceList()->getId(),
            $this->getResourceId($response)
        );

        $this->assertMessagesSentForCreateRequest('price_list_1');
    }

    public function testTryToUpdateWithPriceList()
    {
        $productPriceId = LoadProductPricesWithRules::PRODUCT_PRICE_1;
        $priceListId = $this->getReference($productPriceId)->getPriceList()->getId();
        $response = $this->patch(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()],
            [
                'data' => [
                    'type'          => 'productprices',
                    'id'            => $this->getFirstProductPriceApiId(),
                    'relationships' => [
                        'priceList' => [
                            'data' => ['type' => 'pricelists', 'id' => '<toString(@price_list_3->id)>']
                        ]
                    ]
                ]
            ]
        );

        $data['data']['relationships']['priceList']['data']['id'] = (string)$priceListId;
        $this->assertResponseContains($data, $response);
        self::assertSame(
            $priceListId,
            $this->getReference($productPriceId)->getPriceList()->getId()
        );
    }

    public function testTryToUpdateDuplicate()
    {
        $response = $this->patch(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()],
            'product_price/update_duplicate.yml',
            [],
            false
        );

        $this->assertResponseValidationError(
            [
                'title'  => 'unique entity constraint',
                'detail' => 'Product has duplication of product prices.'
                    . ' Set of fields "PriceList", "Quantity" , "Unit" and "Currency" should be unique.'
            ],
            $response
        );
    }

    public function testUpdateResetPriceRule()
    {
        $this->cleanScheduledMessages();

        $this->patch(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()],
            'product_price/update_reset_rule.yml'
        );

        $productPrice = $this->findProductPriceByUniqueKey(
            5,
            'USD',
            $this->getReference('price_list_1'),
            $this->getReference('product-1'),
            $this->getReference('product_unit.liter')
        );

        self::assertNull($productPrice->getPriceRule());
    }

    public function testDelete()
    {
        $this->cleanScheduledMessages();

        $this->delete(
            ['entity' => 'productprices', 'id' => $this->getFirstProductPriceApiId()]
        );

        $productPrice = $this->findProductPriceByUniqueKey(
            5,
            'USD',
            $this->getReference('price_list_1'),
            $this->getReference('product-1'),
            $this->getReference('product_unit.liter')
        );

        self::assertNull($productPrice);

        self::assertMessageSent(
            Topics::RESOLVE_COMBINED_PRICES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $this->getReference('price_list_1')->getId() => [$this->getReference('product-1')->getId()]
                ]
            ]
        );
    }

    /**
     * @param int|string $priceListIdOrReference
     *
     * @return ProductPrice|null
     */
    private function getProductPrice($priceListIdOrReference): ?ProductPrice
    {
        $em = $this->getEntityManager(ProductPrice::class);
        /** @var PriceList $priceList */
        $priceList = is_string($priceListIdOrReference)
            ? $this->getReference($priceListIdOrReference)
            : $em->find(PriceList::class, $priceListIdOrReference);

        $queryBuilder = $em->getRepository(ProductPrice::class)->createQueryBuilder('price');
        $queryBuilder
            ->andWhere('price.quantity = :quantity')
            ->andWhere('price.value = :value')
            ->andWhere('price.currency = :currency')
            ->andWhere('price.priceList = :priceList')
            ->andWhere('price.product = :product')
            ->andWhere('price.unit = :unit')
            ->setParameter('quantity', 250)
            ->setParameter('value', 150)
            ->setParameter('currency', 'CAD')
            ->setParameter('priceList', $priceList)
            ->setParameter('product', $this->getReference('product-5'))
            ->setParameter('unit', $this->getReference('product_unit.milliliter'));

        $query = $queryBuilder->getQuery();
        $query->setHint('priceList', $priceList->getId());
        $query->setHint(
            PriceShardOutputResultModifier::ORO_PRICING_SHARD_MANAGER,
            self::getContainer()->get('oro_pricing.shard_manager')
        );

        return $query->getOneOrNullResult();
    }

    /**
     * @param int         $quantity
     * @param string      $currency
     * @param PriceList   $priceList
     * @param Product     $product
     * @param ProductUnit $unit
     *
     * @return ProductPrice|null
     */
    private function findProductPriceByUniqueKey(
        int $quantity,
        string $currency,
        PriceList $priceList,
        Product $product,
        ProductUnit $unit
    ) {
        $queryBuilder = $this->getEntityManager()
            ->getRepository(ProductPrice::class)
            ->createQueryBuilder('price');

        $queryBuilder
            ->andWhere('price.quantity = :quantity')
            ->andWhere('price.currency = :currency')
            ->andWhere('price.priceList = :priceList')
            ->andWhere('price.product = :product')
            ->andWhere('price.unit = :unit')
            ->setParameter('quantity', $quantity)
            ->setParameter('currency', $currency)
            ->setParameter('priceList', $priceList)
            ->setParameter('product', $product)
            ->setParameter('unit', $unit);

        $query = $queryBuilder->getQuery();
        $query->useQueryCache(false);
        $query->setHint('priceList', $this->getReference('price_list_3')->getId());
        $query->setHint(
            PriceShardOutputResultModifier::ORO_PRICING_SHARD_MANAGER,
            self::getContainer()->get('oro_pricing.shard_manager')
        );

        return $query->getOneOrNullResult();
    }

    /**
     * @param int|string $priceListIdOrReference
     */
    private function assertMessagesSentForCreateRequest($priceListIdOrReference)
    {
        $productId = $this->getReference('product-5')->getId();
        $priceListId = is_string($priceListIdOrReference)
            ? $this->getReference($priceListIdOrReference)->getId()
            : $priceListIdOrReference;

        self::assertMessageSent(
            Topics::RESOLVE_COMBINED_PRICES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceListId => [$productId],
                ]
            ]
        );
        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                PriceListTriggerFactory::PRODUCT => [
                    $priceListId => [$productId],
                ]
            ]
        );
    }

    /**
     * @param string $priceListReference
     *
     * @return string
     */
    private function getFirstProductPriceApiId($priceListReference = 'price_list_1')
    {
        return $this->getFirstProductPrice()->getId() . '-' . $this->getReference($priceListReference)->getId();
    }

    /**
     * @return ProductPrice
     */
    private function getFirstProductPrice()
    {
        return $this->getReference(LoadProductPricesWithRules::PRODUCT_PRICE_1);
    }
}
