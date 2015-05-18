<?php

namespace OroB2B\Bundle\ProductBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Oro\Bundle\EntityConfigBundle\Metadata\Annotation\Config;

/**
 * @ORM\Table(name="orob2b_product_unit")
 * @ORM\Entity
 * @Config(
 *      defaultValues={
 *          "entity"={
 *              "icon"="icon-edit"
 *          }
 *      }
 * )
 */
class ProductUnit
{
    /**
     * @var string
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=255)
     * @ORM\GeneratedValue(strategy="NONE")
     */
    protected $code;

    /**
     * @var integer
     *
     * @ORM\Column(name="default_precision", type="integer")
     */
    protected $defaultPrecision;

    /**
     * Set code
     *
     * @param string $code
     * @return ProductUnit
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set defaultPrecision
     *
     * @param integer $defaultPrecision
     * @return ProductUnit
     */
    public function setDefaultPrecision($defaultPrecision)
    {
        $this->defaultPrecision = $defaultPrecision;

        return $this;
    }

    /**
     * Get defaultPrecision
     *
     * @return integer
     */
    public function getDefaultPrecision()
    {
        return $this->defaultPrecision;
    }
}
