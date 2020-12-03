<?php

namespace Concrete\Controller\Backend;

use Concrete\Core\Command\Task\Input\InputFactory;
use Concrete\Core\Command\Task\Output\OutputFactory;
use Concrete\Core\Command\Task\Response\HttpResponseFactory;
use Concrete\Core\Controller\AbstractController;
use Concrete\Core\Entity\Automation\Task;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Validation\CSRF\Token;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\JsonResponse;

class Tasks extends AbstractController
{

    /**
     * @var ErrorList
     */
    protected $errorList;

    /**
     * @var Token
     */
    protected $token;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var HttpResponseFactory
     */
    protected $httpResponseFactory;

    public function __construct(
        ErrorList $errorList,
        Token $token,
        EntityManager $entityManager,
        HttpResponseFactory $httpResponseFactory
    ) {
        parent::__construct();
        $this->errorList = $errorList;
        $this->token = $token;
        $this->entityManager = $entityManager;
        $this->httpResponseFactory = $httpResponseFactory;
    }

    public function execute()
    {
        if (!$this->token->validate('execute', $this->request->request->get('ccm_token'))) {
            $this->errorList->add($this->token->getErrorMessage());
        }

        $page = \Concrete\Core\Page\Page::getByPath('/dashboard/system/automation/tasks');
        $checker = new Checker($page);
        if (!$checker->canViewPage()) {
            $this->errorList->add(t('You do not have permissions to run tasks.'));
        }

        $task = null;
        if ($this->request->request->has('id')) {
            /**
             * @var $task Task
             */
            $task = $this->entityManager->find(Task::class, $this->request->request->get('id'));
        }

        if (!$task) {
            $this->errorList->add(t('Invalid task object.'));
        }

        if ($this->errorList->has()) {
            return new JsonResponse($this->errorList);
        } else {
            /**
             * @var $inputFactory InputFactory
             * @var $outputFactory OutputFactory
             */
            $inputFactory = $this->app->make(InputFactory::class);
            $outputFactory = $this->app->make(OutputFactory::class);
            $input = $inputFactory->createFromRequest($this->request, $task->getController()->getInputDefinition());
            $runner = $task->getController()->getTaskRunner($task, $input);
            $handler = $this->app->make($runner->getTaskRunnerHandler());
            $handler->boot($runner);

            $output = $outputFactory->createDashboardOutput($runner);

            $handler->start($runner, $output);
            $handler->run($runner, $output);
            $completedResponse = $handler->complete($runner, $output);
            return $this->httpResponseFactory->createResponse($completedResponse);
        }
    }


}