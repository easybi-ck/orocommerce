<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Api\Frontend\RestJsonApi;

use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Oro\Bundle\CustomerBundle\Tests\Functional\Api\Frontend\DataFixtures\LoadAdminCustomerUserData;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\FrontendBundle\Tests\Functional\Api\FrontendRestJsonApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class ProductTest extends FrontendRestJsonApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // guard
        self::assertEquals(
            ['in_stock', 'out_of_stock'],
            $this->getConfigManager()->get('oro_product.general_frontend_product_visibility')
        );

        $this->loadFixtures([
            LoadAdminCustomerUserData::class,
            '@OroProductBundle/Tests/Functional/Api/Frontend/DataFixtures/product.yml',
            '@OroProductBundle/Tests/Functional/Api/Frontend/DataFixtures/product_prices.yml'
        ]);
    }

    /**
     * @return bool
     */
    private function isPostgreSql(): bool
    {
        return $this->getEntityManager()->getConnection()->getDatabasePlatform() instanceof PostgreSqlPlatform;
    }

    public function testGetList()
    {
        $response = $this->cget(
            ['entity' => 'products'],
            ['page[size]' => 100]
        );

        $this->assertResponseContains('cget_product.yml', $response);
    }

    public function testGetListFilterBySeveralSkus()
    {
        $response = $this->cget(
            ['entity' => 'products'],
            ['filter' => ['sku' => 'PSKU1,PSKU2,PSKU3']]
        );

        $this->assertResponseContains('cget_product_filter_by_sku.yml', $response);
    }

    public function testGetListFilterBySeveralInventoryStatuses()
    {
        $response = $this->cget(
            ['entity' => 'products'],
            ['filter' => ['inventoryStatus' => 'out_of_stock,discontinued']]
        );

        $this->assertResponseContains(
            [
                'data' => [
                    [
                        'type'          => 'products',
                        'id'            => '<toString(@product3->id)>',
                        'relationships' => [
                            'inventoryStatus' => [
                                'data' => [
                                    'type' => 'productinventorystatuses',
                                    'id'   => '<toString(@out_of_stock->id)>'
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetListFilterByVariants()
    {
        $response = $this->cget(
            ['entity' => 'products'],
            ['filter' => ['variants' => '0']]
        );

        $this->assertResponseContains(
            [
                'data' => [
                    ['type' => 'products', 'id' => '<toString(@product1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@product3->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product2->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3->id)>']
                ]
            ],
            $response
        );
    }

    public function testGetListFilterByVariantsWithYesValue()
    {
        $response = $this->cget(
            ['entity' => 'products'],
            ['filter' => ['variants' => '1'], 'fields[products]' => 'id', 'page[size]' => 100]
        );

        $this->assertResponseContains(
            [
                'data' => [
                    ['type' => 'products', 'id' => '<toString(@product1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@product3->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product2->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product1_variant2->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product2_variant1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product2_variant2->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3_variant1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3_variant2->id)>']
                ]
            ],
            $response
        );
    }

    public function testGet()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>']
        );

        $this->assertResponseContains('get_product.yml', $response);

        // test that product attributes do not exposed as separate members
        $responseData = self::jsonToArray($response->getContent());
        $attributes = $responseData['data']['attributes'] ?? [];
        $relationships = $responseData['data']['relationships'] ?? [];
        $attributeNames = [
            'testAttrInvisible',
            'testAttrString',
            'testAttrBoolean',
            'testAttrInteger',
            'testAttrFloat',
            'testAttrMoney',
            'testAttrDateTime',
            'testAttrEnum',
            'testAttrMultiEnum',
            'testAttrMultiEnum' . ExtendHelper::ENUM_SNAPSHOT_SUFFIX,
            'testAttrManyToOne',
            'testToOneId',
            'testAttrManyToMany',
            'testToManyId',
            ExtendConfigDumper::DEFAULT_PREFIX . 'testAttrManyToMany'
        ];
        foreach ($attributeNames as $name) {
            self::assertFalse(array_key_exists($name, $attributes), $name . ' attribute must not exist');
        }
        foreach ($attributeNames as $name) {
            self::assertFalse(array_key_exists($name, $relationships), $name . ' relationship must not exist');
        }
    }

    public function testGetForAnotherLocalization()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            [],
            ['HTTP_X-Localization-ID' => $this->getReference('es')->getId()]
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'type'       => 'products',
                    'id'         => '<toString(@product1->id)>',
                    'attributes' => [
                        'name'             => 'Product 1 Spanish Name',
                        'shortDescription' => 'Product 1 Spanish Short Description',
                        'description'      => 'Product 1 Spanish Description',
                        'metaTitle'        => 'Product 1 Spanish Meta Title',
                        'metaDescription'  => 'Product 1 Spanish Meta Description',
                        'metaKeywords'     => 'Product 1 Spanish Meta Keywords',
                        'url'              => '/product1_slug_es',
                        'urls'             => [
                            ['url' => '/product1_slug_en_CA', 'localizationId' => '<toString(@en_CA->id)>']
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetOnlyUrlsAndUrlForProductOnlyWithDefaultUrl()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product3->id)>'],
            ['fields[products]' => 'url,urls']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'attributes' => [
                        'url'  => '/product3_slug_default',
                        'urls' => [
                            ['url' => '/product3_slug_default', 'localizationId' => '<toString(@en_CA->id)>'],
                            ['url' => '/product3_slug_default', 'localizationId' => '<toString(@es->id)>']
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetOnlyUpcomingAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            ['fields[products]' => 'upcoming']
        );

        $this->assertResponseContains(['data' => ['attributes' => ['upcoming' => true]]], $response);
    }

    public function testGetOnlyAvailabilityDateAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            ['fields[products]' => 'availabilityDate']
        );

        $this->assertResponseContains(
            ['data' => ['attributes' => ['availabilityDate' => '2119-01-20T20:30:00Z']]],
            $response
        );
    }

    public function testGetOnlyLowInventoryAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            ['fields[products]' => 'lowInventory']
        );

        $this->assertResponseContains(['data' => ['attributes' => ['lowInventory' => true]]], $response);
    }

    public function testGetOnlyUpcomingAttributeOnNonUpcomingProduct()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product3->id)>'],
            ['fields[products]' => 'upcoming']
        );

        $this->assertResponseContains(['data' => ['attributes' => ['upcoming' => false]]], $response);
    }

    public function testGetOnlyAvailabilityDateAttributeOnNonUpcomingProduct()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product3->id)>'],
            ['fields[products]' => 'availabilityDate']
        );

        $this->assertResponseContains(
            ['data' => ['attributes' => ['availabilityDate' => null]]],
            $response
        );
    }

    public function testGetOnlyLowInventoryAttributeOnNonHighlightRequiredProduct()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product3->id)>'],
            ['fields[products]' => 'lowInventory']
        );

        $this->assertResponseContains(['data' => ['attributes' => ['lowInventory' => false]]], $response);
    }

    public function testGetOnlyUnitPrecisionsAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            ['fields[products]' => 'unitPrecisions']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'attributes' => [
                        'unitPrecisions' => [
                            ['unit' => 'item', 'precision' => 0, 'conversionRate' => 1, 'default' => true],
                            ['unit' => 'set', 'precision' => 1, 'conversionRate' => 10, 'default' => false]
                        ]
                    ]
                ]
            ],
            $response
        );
        // test that unit precision with "sell" === false is not returned
        $responseData = self::jsonToArray($response->getContent());
        self::assertCount(2, $responseData['data']['attributes']['unitPrecisions']);
    }

    public function testGetOnlyProductAttributes()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            ['fields[products]' => 'productAttributes']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'attributes' => [
                        'productAttributes' => [
                            'testAttrString'     => 'string attribute',
                            'testAttrBoolean'    => true,
                            'testAttrFloat'      => 1.23,
                            'testAttrMoney'      => '1.2300',
                            'testAttrDateTime'   => '2010-06-15T20:20:30Z',
                            'testAttrMultiEnum'  => [
                                [
                                    'id'          => '@productAttrMultiEnum_option1->id',
                                    'targetValue' => '@productAttrMultiEnum_option1->name'
                                ],
                                [
                                    'id'          => '@productAttrMultiEnum_option2->id',
                                    'targetValue' => '@productAttrMultiEnum_option2->name'
                                ]
                            ],
                            'testAttrManyToOne'  => [
                                'id'          => '<toString(@customer1->id)>',
                                'targetValue' => 'Company 1'
                            ],
                            'testToOneId'        => [
                                'id'          => '<toString(@country.usa->iso2Code)>',
                                'targetValue' => '<toString(@country.usa->iso2Code)>'
                            ],
                            'testAttrManyToMany' => [
                                ['id' => '<toString(@customer_user1->id)>', 'targetValue' => 'John Edgar Doo'],
                                ['id' => '<toString(@customer_user2->id)>', 'targetValue' => 'Amanda Cole']
                            ],
                            'testToManyId'       => [
                                [
                                    'id'          => '<toString(@country.mexico->iso2Code)>',
                                    'targetValue' => '<toString(@country.mexico->iso2Code)>'
                                ],
                                [
                                    'id'          => '<toString(@country.germany->iso2Code)>',
                                    'targetValue' => '<toString(@country.germany->iso2Code)>'
                                ]
                            ],
                            'wysiwyg'            => '<style type="text/css">.test {color: red}</style>'
                                . 'Product 1 WYSIWYG Text. Twig Expr: "test".',
                            'wysiwygAttr'        => '<style type="text/css">.test {color: red}</style>'
                                . 'Product 1 WYSIWYG Attr Text. Twig Expr: "test".'
                        ]
                    ]
                ]
            ],
            $response
        );
        $responseData = self::jsonToArray($response->getContent());
        self::assertCount(12, $responseData['data']['attributes']['productAttributes']);
    }

    public function testGetAttributesWithEmptyValues()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product3->id)>'],
            ['fields[products]' => 'productAttributes']
        );

        // this is a workaround for a known PDO driver issue not saving null to nullable boolean field
        // for PostgreSQL, see https://github.com/doctrine/dbal/issues/2580 for details
        $emptyBooleanValue = null;
        if ($this->isPostgreSql()) {
            $emptyBooleanValue = false;
        }

        $this->assertResponseContains(
            [
                'data' => [
                    'attributes' => [
                        'productAttributes' => [
                            'testAttrString'     => null,
                            'testAttrBoolean'    => $emptyBooleanValue,
                            'testAttrMoney'      => null,
                            'testAttrDateTime'   => null,
                            'testAttrMultiEnum'  => [],
                            'testAttrManyToOne'  => [
                                'id'          => '<toString(@customer2->id)>',
                                'targetValue' => 'Company 2'
                            ],
                            'testToOneId'        => null,
                            'testAttrManyToMany' => [],
                            'testToManyId'       => [],
                            'wysiwyg'            => null,
                            'wysiwygAttr'        => null
                        ]
                    ]
                ]
            ],
            $response
        );
        $responseData = self::jsonToArray($response->getContent());
        self::assertCount(12, $responseData['data']['attributes']['productAttributes']);
    }

    public function testGetConfigurableProduct()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product3->id)>']
        );

        $this->assertResponseContains('get_configurable_product.yml', $response);
    }

    public function testGetConfigurableProductVariantWithInvisibleVariantAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>']
        );

        $this->assertResponseContains('get_variant_product_with_invisible.yml', $response);
    }

    public function testGetConfigurableProductVariantWithoutInvisibleVariantAttribute()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product2_variant1->id)>']
        );

        $this->assertResponseContains('get_variant_product_without_invisible.yml', $response);

        $responseData = self::jsonToArray($response->getContent());
        self::assertArrayNotHasKey('testAttrEnum', $responseData['data']['attributes']['productAttributes']);
    }

    public function testGetOnlyVariantProducts()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product3->id)>'],
            ['fields[products]' => 'variantProducts']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'relationships' => [
                        'variantProducts' => [
                            'data' => [
                                ['type' => 'products', 'id' => '<toString(@configurable_product3_variant1->id)>'],
                                ['type' => 'products', 'id' => '<toString(@configurable_product3_variant2->id)>'],
                                ['type' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>']
                            ]
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetWithIncludeVariantProducts()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product1->id)>'],
            ['include' => 'variantProducts']
        );

        $this->assertResponseContains('get_configurable_product_with_variants.yml', $response);
    }

    public function testGetOnlyParentProducts()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>'],
            ['fields[products]' => 'parentProducts']
        );

        $this->assertResponseContains(
            [
                'data' => [
                    'relationships' => [
                        'parentProducts' => [
                            'data' => [
                                ['type' => 'products', 'id' => '<toString(@configurable_product1->id)>'],
                                ['type' => 'products', 'id' => '<toString(@configurable_product3->id)>']
                            ]
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetWithIncludeParentProducts()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>'],
            ['include' => 'parentProducts']
        );

        $this->assertResponseContains('get_product_with_parent_products.yml', $response);
    }

    public function testTryToGetDisabled()
    {
        $response = $this->get(
            ['entity' => 'products', 'id' => '<toString(@product2->id)>'],
            [],
            [],
            false
        );
        $this->assertResponseValidationError(
            [
                'title'  => 'access denied exception',
                'detail' => 'No access to the entity.'
            ],
            $response,
            Response::HTTP_FORBIDDEN
        );
    }

    public function testTryToUpdate()
    {
        $data = [
            'data' => [
                'type'       => 'products',
                'id'         => '<toString(@product1->id)>',
                'attributes' => [
                    'name' => 'Updated Product Name'
                ]
            ]
        ];

        $response = $this->patch(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            $data,
            [],
            false
        );

        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToCreate()
    {
        $data = [
            'data' => [
                'type'       => 'products',
                'attributes' => [
                    'name' => 'New Product'
                ]
            ]
        ];

        $response = $this->post(
            ['entity' => 'products'],
            $data,
            [],
            false
        );

        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToDelete()
    {
        $response = $this->delete(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>'],
            [],
            [],
            false
        );

        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToDeleteList()
    {
        $response = $this->cdelete(
            ['entity' => 'products'],
            ['filter' => ['id' => '<toString(@product1->id)>']],
            [],
            false
        );

        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testGetSubresourceForProductFamily()
    {
        $response = $this->getSubresource(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>', 'association' => 'productFamily']
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type'       => 'productfamilies',
                    'id'         => '<toString(@default_product_family->id)>',
                    'attributes' => [
                        'name'      => 'Default',
                        'createdAt' => '@default_product_family->createdAt->format("Y-m-d\TH:i:s\Z")',
                        'updatedAt' => '@default_product_family->updatedAt->format("Y-m-d\TH:i:s\Z")'
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetRelationshipForProductFamily()
    {
        $response = $this->getRelationship(
            ['entity' => 'products', 'id' => '<toString(@product1->id)>', 'association' => 'productFamily']
        );
        $this->assertResponseContains(
            [
                'data' => [
                    'type' => 'productfamilies',
                    'id'   => '<toString(@default_product_family->id)>'
                ]
            ],
            $response
        );
    }

    public function testTryToUpdateRelationshipForProductFamily()
    {
        $response = $this->patchRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@product1->id)>',
                'association' => 'productFamily'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testGetSubresourceForVariantProducts()
    {
        $response = $this->getSubresource([
            'entity'      => 'products',
            'id'          => '<toString(@configurable_product3->id)>',
            'association' => 'variantProducts'
        ]);
        $this->assertResponseContains(
            [
                'data' => [
                    [
                        'type'       => 'products',
                        'id'         => '<toString(@configurable_product1_variant1->id)>',
                        'attributes' => [
                            'sku' => 'CVPSKU1'
                        ]
                    ],
                    [
                        'type'       => 'products',
                        'id'         => '<toString(@configurable_product3_variant1->id)>',
                        'attributes' => [
                            'sku' => 'CVPSKU5'
                        ]
                    ],
                    [
                        'type'       => 'products',
                        'id'         => '<toString(@configurable_product3_variant2->id)>',
                        'attributes' => [
                            'sku' => 'CVPSKU6'
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetRelationshipForVariantProducts()
    {
        $response = $this->getRelationship([
            'entity'      => 'products',
            'id'          => '<toString(@configurable_product3->id)>',
            'association' => 'variantProducts'
        ]);
        $this->assertResponseContains(
            [
                'data' => [
                    ['type' => 'products', 'id' => '<toString(@configurable_product1_variant1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3_variant1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3_variant2->id)>']
                ]
            ],
            $response
        );
    }

    public function testTryToUpdateRelationshipForVariantProducts()
    {
        $response = $this->patchRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product3->id)>',
                'association' => 'variantProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToAddRelationshipForVariantProducts()
    {
        $response = $this->postRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product3->id)>',
                'association' => 'variantProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToDeleteRelationshipForVariantProducts()
    {
        $response = $this->deleteRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product3->id)>',
                'association' => 'variantProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testGetSubresourceForParentProducts()
    {
        $response = $this->getSubresource([
            'entity'      => 'products',
            'id'          => '<toString(@configurable_product1_variant1->id)>',
            'association' => 'parentProducts'
        ]);
        $this->assertResponseContains(
            [
                'data' => [
                    [
                        'type'       => 'products',
                        'id'         => '<toString(@configurable_product1->id)>',
                        'attributes' => [
                            'sku' => 'CPSKU1'
                        ]
                    ],
                    [
                        'type'       => 'products',
                        'id'         => '<toString(@configurable_product3->id)>',
                        'attributes' => [
                            'sku' => 'CPSKU3'
                        ]
                    ]
                ]
            ],
            $response
        );
    }

    public function testGetRelationshipForParentProducts()
    {
        $response = $this->getRelationship([
            'entity'      => 'products',
            'id'          => '<toString(@configurable_product1_variant1->id)>',
            'association' => 'parentProducts'
        ]);
        $this->assertResponseContains(
            [
                'data' => [
                    ['type' => 'products', 'id' => '<toString(@configurable_product1->id)>'],
                    ['type' => 'products', 'id' => '<toString(@configurable_product3->id)>']
                ]
            ],
            $response
        );
    }

    public function testTryToUpdateRelationshipForParentProducts()
    {
        $response = $this->patchRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product1_variant1->id)>',
                'association' => 'parentProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToAddRelationshipForParentProducts()
    {
        $response = $this->postRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product1_variant1->id)>',
                'association' => 'parentProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }

    public function testTryToDeleteRelationshipForParentProducts()
    {
        $response = $this->deleteRelationship(
            [
                'entity'      => 'products',
                'id'          => '<toString(@configurable_product1_variant1->id)>',
                'association' => 'parentProducts'
            ],
            [],
            [],
            false
        );
        self::assertMethodNotAllowedResponse($response, 'OPTIONS, GET');
    }
}
