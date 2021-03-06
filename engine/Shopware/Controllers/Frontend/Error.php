<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */
use Shopware\Components\CSRFWhitelistAware;

/**
 */
class Shopware_Controllers_Frontend_Error extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * Disable front plugins
     */
    public function init()
    {
        $this->Front()->Plugins()->ScriptRenderer()->setRender(false);
        $this->Front()->Plugins()->ViewRenderer()->setNoRender(false);
        $this->Front()->Plugins()->Json()->setRenderer(false);
    }

    /**
     * Load correct template
     */
    public function preDispatch()
    {
        if ($this->Request()->getActionName() !== 'service') {
            $templateModule = 'frontend';
            if ($this->Request()->getModuleName() == 'backend') {
                $templateModule = 'backend';
                $this->enableBackendTheme();
            }

            if (strpos($this->Request()->getHeader('Content-Type'), 'application/json') === 0) {
                $this->Front()->Plugins()->Json()->setRenderer();
                $this->View()->assign('success', false);
            } elseif ($this->Request()->isXmlHttpRequest() || !Shopware()->Container()->initialized('Db')) {
                $this->View()->loadTemplate($templateModule . '/error/exception.tpl');
            } elseif (isset($_ENV['SHELL']) || php_sapi_name() == 'cli') {
                $this->View()->loadTemplate($templateModule . '/error/cli.tpl');
            } elseif (empty($_SERVER['SERVER_NAME'])) {
                $this->View()->loadTemplate($templateModule . '/error/ajax.tpl');
            } else {
                $this->View()->loadTemplate($templateModule . '/error/index.tpl');
            }
        }
    }

    public function cliAction()
    {
        $this->view->setTemplate();

        $response = new Enlight_Controller_Response_ResponseCli();
        $response->appendBody(strip_tags($this->View()->exception) . "\n");

        $this->front->setResponse($response);
    }

    /**
     * Controller action that handles all error rendering
     * either by itself or by delegating specific scenarios to other actions
     */
    public function errorAction()
    {
        $error = $this->Request()->getParam('error_handler');

        if (!empty($error)) {
            $code = $error->exception->getCode();
            switch ($code) {
                case Enlight_Controller_Exception::Controller_Dispatcher_Controller_Not_Found:
                case Enlight_Controller_Exception::Controller_Dispatcher_Controller_No_Route:
                case Enlight_Controller_Exception::ActionNotFound:
                case 404:
                    $this->forward('pageNotFoundError');
                    break;
                case 401:
                    $this->forward('genericError', null, null, array('code' => $code));
                    break;
                default:
                    $this->forward('genericError', null, null, array('code' => 503));
                    break;
            }
        }
    }

    /**
     * Handles "Page Not Found" errors
     */
    public function pageNotFoundErrorAction()
    {
        $response = $this->Response();

        $targetEmotionId = Shopware()->Config()->get('PageNotFoundDestination');
        $targetErrorCode = Shopware()->Config()->get('PageNotFoundCode', 404);

        $response->setHttpResponseCode($targetErrorCode);

        switch ($targetEmotionId) {
            case -2:
            case null:
                $response->unsetExceptions();
                $this->forward(
                    Shopware()->Front()->Dispatcher()->getDefaultAction(),
                    Shopware()->Front()->Dispatcher()->getDefaultControllerName()
                );
                break;
            case -1:
                $this->forward('genericError', null, null, array('code' => $targetErrorCode));
                break;
            default:
                $response->unsetExceptions();
                $this->forward('index', 'campaign', 'frontend', array('emotionId' => $targetEmotionId));
        }
    }

    /**
     * Generic error handling controller action
     */
    public function genericErrorAction()
    {
        $response = $this->Response();
        $errorCode = $this->Request()->getParam('code', 503);
        $response->setHttpResponseCode($errorCode);

        $error = $this->Request()->getParam('error_handler');

        /**
         * If the system is configured to display the exception data, we need
         * to pass it to the template
        */
        if ($this->Front()->getParam('showException') || $this->Request()->getModuleName() == 'backend') {
            $paths = array(Shopware()->DocPath());
            $replace = array('');

            $exception = $error->exception;
            $error_file = $exception->getFile();
            $error_file = str_replace($paths, $replace, $error_file);

            $error_trace = $error->exception->getTraceAsString();
            $error_trace = str_replace($paths, $replace, $error_trace);
            $this->View()->assign(array(
                'exception' => $exception,
                'error' => $exception->getMessage(),
                'error_message' => $exception->getMessage(),
                'error_file' => $error_file,
                'error_trace' => $error_trace
            ));
        } else {
            /**
             * Prevent sending error code 503 because of an exception,
             * if it's not configured that way
             */
            $response->unsetExceptions();
        }

        if ($this->View()->getAssign('success') !== null) {
            $this->Response()->setHttpResponseCode(200);
            $this->View()->clearAssign('exception');
            $this->View()->assign('message', $error->exception->getMessage());
        }
    }

    public function serviceAction()
    {
        $this->Response()->setHttpResponseCode(503);
    }

    /**
     * Ensure the backend theme is enabled.
     * This is important in cases when a backend request uses the storefront context eg. "$shop->registerResources($this)".
     */
    private function enableBackendTheme()
    {
        $directory = Shopware()->Container()->get('theme_path_resolver')->getExtJsThemeDirectory();
        Shopware()->Container()->get('template')->setTemplateDir(
            array(
                'backend' => $directory,
                'include_dir' => '.'
            )
        );
    }

    /**
     * Returns a list with actions which should not be validated for CSRF protection
     *
     * @return string[]
     */
    public function getWhitelistedCSRFActions()
    {
        return [
            'error',
            'cli',
            'pageNotFoundError',
            'genericError',
            'service'
        ];
    }
}
