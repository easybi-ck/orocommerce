<?php

namespace Oro\Bundle\ShippingBundle\Form\Type;

use Oro\Bundle\ShippingBundle\Entity\ShippingRuleMethodTypeConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShippingRuleMethodTypeConfigType extends AbstractType
{
    const BLOCK_PREFIX = 'oro_shipping_rule_method_type_config';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['is_grouped']) {
            $builder->add('enabled', CheckboxType::class, [
                'mapped' => false,
            ]);
        }
        $builder->add('type', HiddenType::class);
        $builder->add('options', $options['options_type']);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ShippingRuleMethodTypeConfig::class,
            'options_type' => HiddenType::class,
            'is_grouped' => false,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::BLOCK_PREFIX;
    }
}
