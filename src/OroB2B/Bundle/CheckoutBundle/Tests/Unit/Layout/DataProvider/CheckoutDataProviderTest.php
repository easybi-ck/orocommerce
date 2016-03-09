<?php

namespace OroB2B\Bundle\CheckoutBundle\Tests\Unit\Layout\DataProvider;

use Oro\Bundle\WorkflowBundle\Entity\WorkflowStep;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Oro\Component\Layout\LayoutContext;
use Oro\Component\Testing\Unit\EntityTrait;

use OroB2B\Bundle\CheckoutBundle\Entity\Checkout;
use OroB2B\Bundle\CheckoutBundle\Layout\DataProvider\CheckoutDataProvider;

class CheckoutDataProviderTest extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /**
     * @var CheckoutDataProvider
     */
    protected $provider;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|WorkflowManager
     */
    protected $workflowManager;


    public function setUp()
    {
        $this->workflowManager = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\WorkflowManager')
            ->disableOriginalConstructor()
            ->getMock();

        $this->provider = new CheckoutDataProvider($this->workflowManager);
    }

    /**
     * @dataProvider getDataDataProvider
     * @param Checkout $checkout
     * @param WorkflowStep $currentStep
     * @param bool $displayOrdered
     * @param array $steps
     * @param array $expected
     */
    public function testGetData(Checkout $checkout, $currentStep, $displayOrdered, array $steps, array $expected)
    {
        $workflowItem  = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowItem')
            ->disableOriginalConstructor()
            ->getMock();

        $workflowDefinition = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition')
            ->disableOriginalConstructor()
            ->getMock();
        $workflowDefinition->expects($this->once())
            ->method('isStepsDisplayOrdered')
            ->willReturn($displayOrdered);

        $workflow = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\Workflow')
            ->disableOriginalConstructor()
            ->getMock();
        $workflow->expects($this->once())
            ->method('getDefinition')
            ->willReturn($workflowDefinition);

        if ($displayOrdered) {
            $stepManager = $this->getMockBuilder('Oro\Bundle\WorkflowBundle\Model\StepManager')
                ->disableOriginalConstructor()
                ->getMock();
            $stepManager->expects($this->once())
                ->method('getOrderedSteps')
                ->willReturn($steps);
            $workflow->expects($this->once())
                ->method('getStepManager')
                ->willReturn($stepManager);
        } else {
            $workflow->expects($this->once())
                ->method('getPassedStepsByWorkflowItem')
                ->with($workflowItem)
                ->willReturn($steps);
        }

        $workflowItem->expects($this->once())
            ->method('getCurrentStep')
            ->willReturn($currentStep);

        $checkout->setWorkflowItem($workflowItem);

        $this->workflowManager->expects($this->once())
            ->method('getWorkflow')
            ->with($workflowItem)
            ->willReturn($workflow);

        $context = new LayoutContext();
        $context->data()->set('checkout', null, $checkout);

        $result = $this->provider->getData($context);
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public function getDataDataProvider()
    {
        $checkout = $this->getEntity('OroB2B\Bundle\CheckoutBundle\Entity\Checkout', ['id' => 42]);
        $currentStep = $this->getEntity('Oro\Bundle\WorkflowBundle\Entity\WorkflowStep', ['id' => 123]);
        $step1 = $this->getEntity('Oro\Bundle\WorkflowBundle\Model\Step', ['order' => 100]);
        $step2 = $this->getEntity('Oro\Bundle\WorkflowBundle\Model\Step', ['order' => 200]);
        $steps = [$step1, $step2];
        return [
            'displayOrdered' => [
                'checkout' => $checkout,
                'currentStep' => $currentStep,
                'displayOrdered' => true,
                'steps' => $steps,
                'expected' => [
                    'checkout' => $checkout,
                    'steps' => $steps,
                    'currentStep' => $currentStep
                ]
            ],
            'displayUnOrdered' => [
                'checkout' => $checkout,
                'currentStep' => $currentStep,
                'displayOrdered' => false,
                'steps' => $steps,
                'expected' => [
                    'checkout' => $checkout,
                    'steps' => $steps,
                    'currentStep' => $currentStep
                ]
            ],
        ];
    }
}
