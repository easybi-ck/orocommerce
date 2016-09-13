<?php

namespace Oro\Bundle\ShippingBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionDispatcher;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\ShippingBundle\Form\Type\ShippingRuleType;
use Oro\Bundle\ShippingBundle\Entity\ShippingRule;

class ShippingRuleController extends Controller
{
    /**
     * @Route("/", name="oro_shipping_rule_index")
     * @Template
     * @AclAncestor("oro_shipping_rule_view")
     *
     * @return array
     */
    public function indexAction()
    {
        return [
            'entity_class' => $this->container->getParameter('oro_shipping.entity.shipping_rule.class')
        ];
    }

    /**
     * @Route("/create", name="oro_shipping_rule_create")
     * @Template("OroShippingBundle:ShippingRule:update.html.twig")
     * @Acl(
     *     id="oro_shipping_rule_create",
     *     type="entity",
     *     permission="CREATE",
     *     class="OroShippingBundle:ShippingRule"
     * )
     *
     * @return array
     */
    public function createAction()
    {
        return $this->update(new ShippingRule());
    }

    /**
     * @Route("/view/{id}", name="oro_shipping_rule_view", requirements={"id"="\d+"})
     * @Template
     * @Acl(
     *      id="oro_shipping_rule_view",
     *      type="entity",
     *      class="OroShippingBundle:ShippingRule",
     *      permission="VIEW"
     * )
     *
     * @param ShippingRule $shippingRule
     *
     * @return array
     */
    public function viewAction(ShippingRule $shippingRule)
    {
        return [
            'entity' => $shippingRule,
        ];
    }

    /**
     * @param ShippingRule $entity
     *
     * @Route("/update/{id}", name="oro_shipping_rule_update", requirements={"id"="\d+"})
     * @Template
     * @Acl(
     *     id="oro_shipping_rule_update",
     *     type="entity",
     *     permission="EDIT",
     *     class="OroShippingBundle:ShippingRule"
     * )
     * @return array
     */
    public function updateAction(ShippingRule $entity)
    {
        return $this->update($entity);
    }

    /**
     * @param ShippingRule $entity
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function update(ShippingRule $entity)
    {
        $form = $this->createForm(ShippingRuleType::class, $entity);
        return $this->get('oro_form.model.update_handler')->update(
            $entity,
            $form,
            $this->get('translator')->trans('oro.shipping.controller.rule.saved.message')
        );
    }

    /**
     * @Route("/{gridName}/massAction/{actionName}", name="oro_status_shipping_rule_massaction")
     * @Acl(
     *     id="oro_shipping_rule_update",
     *     type="entity",
     *     permission="EDIT",
     *     class="OroShippingBundle:ShippingRule"
     * )
     * @param string $gridName
     * @param string $actionName
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function markMassAction($gridName, $actionName, Request $request)
    {
        /** @var MassActionDispatcher $massActionDispatcher */
        $massActionDispatcher = $this->get('oro_datagrid.mass_action.dispatcher');

        $response = $massActionDispatcher->dispatchByRequest($gridName, $actionName, $request);

        $data = [
            'successful' => $response->isSuccessful(),
            'message' => $response->getMessage()
        ];

        return new JsonResponse(array_merge($data, $response->getOptions()));
    }
}
