<?php
namespace TCErrorHandler;

use ErrorException;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ErrorHandler;

class Module
{
    public function onBootstrap(MvcEvent $e)
    {
        if (php_sapi_name() == 'cli') {
            return;
        }

        $eventManager = $e->getApplication()->getEventManager();
        // Error catching
        ob_start();
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        $eventManager->attach(
            array(
                MvcEvent::EVENT_BOOTSTRAP
            ),
            array($this, 'startMonitoringErrors')
        );
        $eventManager->attach(
            array(
                MvcEvent::EVENT_DISPATCH
            ),
            array($this, 'checkForNotFoundAction')
        );
        $eventManager->attach(
            array(
                MvcEvent::EVENT_DISPATCH_ERROR,
                MvcEvent::EVENT_RENDER_ERROR,
                MvcEvent::EVENT_FINISH
            ),
            array($this, 'checkForErrors')
        );
        register_shutdown_function(array($this, 'throwFatalError'), $e);
    }

    public function startMonitoringErrors(MvcEvent $e)
    {
        ErrorHandler::start(E_ALL);
    }

    public function checkForNotFoundAction(MvcEvent $e)
    {
        $action = $e->getRouteMatch()->getParam('action');

        if ($action == 'not-found') {
            $events = $e->getApplication()->getEventManager();
            $events->trigger(MvcEvent::EVENT_DISPATCH_ERROR, $e);
        }
    }

    public function checkForErrors(MvcEvent $e)
    {
        try {
            ErrorHandler::stop(true);
        } catch (ErrorException $exception) {
            $this->outputFatalError($exception, $e);
        }

        return;
    }

    public function throwFatalError(MvcEvent $e)
    {
        $error = error_get_last();
        if ($error) {
            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            $this->outputFatalError($exception, $e);
        }
    }

    public function outputFatalError(ErrorException $exception, MvcEvent $e)
    {
        // Clean the buffer from previously badly rendered views
        if (ob_get_level() >= 1) {
            ob_end_clean();
        }

        $sm        = $e->getApplication()->getServiceManager();
        $request   = $e->getRequest();
        $manager   = $sm->get('viewManager');
        $renderer  = $manager->getRenderer();
        $config    = $sm->get('Config');
        $display   = isset($config['view_manager']['display_exceptions']) ? $config['view_manager']['display_exceptions'] : null;
        $layout    = $manager->getLayoutTemplate();
        $template  = isset($config['view_manager']['exception_template']) ? $config['view_manager']['exception_template'] : null;
        $viewType  = get_class($manager->getViewModel());

        // Console
        if ($request instanceof ConsoleRequest || (bool) $display !== true) {
            return;
        }

        // Get layout
        $model     = new $viewType();
        $model->setTemplate($layout);

        // Error page
        if (null !== $template) {
            $content   = new $viewType(
                array(
                    'exception'          => $exception,
                    'display_exceptions' => $display
                )
            );
            $content->setTemplate($template);
            $result    = $renderer->render($content);
            $model->setVariable('content', $result);
        }

        $this->triggerErrorEvent($exception, $e);

        echo $renderer->render($model);
    }

    public function triggerErrorEvent(ErrorException $exception, MvcEvent $e)
    {
        $e->setError(Application::ERROR_EXCEPTION);
        $e->setParam('exception', $exception);

        $events = $e->getApplication()->getEventManager();
        $events->trigger(MvcEvent::EVENT_RENDER_ERROR, $e);

        return;
    }
}
