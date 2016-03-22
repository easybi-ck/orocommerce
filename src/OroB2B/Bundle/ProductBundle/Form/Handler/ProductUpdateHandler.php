<?php

namespace OroB2B\Bundle\ProductBundle\Form\Handler;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\ActionGroupRegistry;
use Oro\Bundle\FormBundle\Model\UpdateHandler;
use Oro\Bundle\UIBundle\Route\Router;

use OroB2B\Bundle\ProductBundle\Entity\Product;

class ProductUpdateHandler extends UpdateHandler
{
    const ACTION_SAVE_AND_DUPLICATE = 'save_and_duplicate';

    /**
     * @var ActionGroupRegistry
     */
    private $actionGroupRegistry;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param ActionGroupRegistry $actionGroupRegistry
     */
    public function setActionGroupRegistry(ActionGroupRegistry $actionGroupRegistry)
    {
        $this->actionGroupRegistry = $actionGroupRegistry;
    }

    /**
     * @param TranslatorInterface $translator
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param FormInterface $form
     * @param Product $entity
     * @param array|callable $saveAndStayRoute
     * @param array|callable $saveAndCloseRoute
     * @param string $saveMessage
     * @param null $resultCallback
     * @return array|RedirectResponse
     */
    protected function processSave(
        FormInterface $form,
        $entity,
        $saveAndStayRoute,
        $saveAndCloseRoute,
        $saveMessage,
        $resultCallback = null
    ) {
        $result = parent::processSave(
            $form,
            $entity,
            $saveAndStayRoute,
            $saveAndCloseRoute,
            $saveMessage,
            $resultCallback
        );

        if ($result instanceof RedirectResponse && $this->isSaveAndDuplicateAction()) {
            $saveMessage = $this->translator->trans('orob2b.product.controller.product.saved_and_duplicated.message');
            $this->session->getFlashBag()->set('success', $saveMessage);
            $actionData = new ActionData();
            if ($actionGroup = $this->actionGroupRegistry->findByName('orob2b_product_duplicate')) {
                $actionData = $actionGroup->execute(new ActionData(['data' => $entity, 'do_redirect' => true]));
            }

            if ($actionData->getRedirectUrl()) {
                return new RedirectResponse($actionData->getRedirectUrl());
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    protected function isSaveAndDuplicateAction()
    {
        return $this->request->get(Router::ACTION_PARAMETER) === self::ACTION_SAVE_AND_DUPLICATE;
    }
}
