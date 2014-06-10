<?php
// @codeCoverageIgnoreStart
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Application;

require_once __DIR__ . '/ApplicationBootstrap.php';

use Icinga\Authentication\Manager as AuthenticationManager;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotReadableError;
use Icinga\Logger\Logger;
use Icinga\Web\Request;
use Icinga\Web\View;
use Icinga\Web\Session\Session as BaseSession;
use Icinga\Web\Session;
use Icinga\User;
use Icinga\Util\Translator;
use Icinga\Util\DateTimeFactory;
use DateTimeZone;
use Exception;
use Zend_Layout;
use Zend_Paginator;
use Zend_View_Helper_PaginationControl;
use Zend_Controller_Action_HelperBroker;
use Zend_Controller_Router_Route;
use Zend_Controller_Front;

/**
 * Use this if you want to make use of Icinga functionality in other web projects
 *
 * Usage example:
 * <code>
 * use Icinga\Application\EmbeddedWeb;
 * EmbeddedWeb::start();
 * </code>
 */
class Web extends ApplicationBootstrap
{
    /**
     * View object
     *
     * @var View
     */
    private $viewRenderer;

    /**
     * Zend front controller instance
     *
     * @var Zend_Controller_Front
     */
    private $frontController;

    /**
     * Request object
     *
     * @var Request
     */
    private $request;

    /**
     * Session object
     *
     * @var BaseSession
     */
    private $session;

    /**
     * User object
     *
     * @var User
     */
    private $user;

    /**
     * Identify web bootstrap
     *
     * @var bool
     */
    protected $isWeb = true;

    /**
     * Initialize all together
     *
     * @return self
     */
    protected function bootstrap()
    {
        return $this
            ->setupLogging()
            ->setupErrorHandling()
            ->loadConfig()
            ->setupResourceFactory()
            ->setupSession()
            ->setupUser()
            ->setupTimezone()
            ->setupLogger()
            ->setupInternationalization()
            ->setupRequest()
            ->setupZendMvc()
			->setupFormNamespace()
            ->setupModuleManager()
            ->loadEnabledModules()
            ->setupRoute()
            ->setupPagination();
    }

    /**
     * Prepare routing
     *
     * @return self
     */
    private function setupRoute()
    {
        $this->frontController->getRouter()->addRoute(
            'module_javascript',
            new Zend_Controller_Router_Route(
                'js/components/:module_name/:file',
                array(
                    'controller' => 'static',
                    'action'     => 'javascript'
                )
            )
        );

        return $this;
    }

    /**
     * Getter for frontController
     *
     * @return Zend_Controller_Front
     */
    public function getFrontController()
    {
        return $this->frontController;
    }

    /**
     * Getter for view
     *
     * @return View
     */
    public function getViewRenderer()
    {
        return $this->viewRenderer;
    }

    /**
     * Dispatch public interface
     */
    public function dispatch()
    {
        $this->frontController->dispatch();
    }

    /**
     * Prepare Zend MVC Base
     *
     * @return self
     */
    private function setupZendMvc()
    {
        // TODO: Replace Zend_Application:
        Zend_Layout::startMvc(
            array(
                'layout'     => 'layout',
                'layoutPath' => $this->getApplicationDir('/layouts/scripts')
            )
        );

        $this->setupFrontController();
        $this->setupViewRenderer();

        return $this;
    }

    /**
     * Create user object
     *
     * @return  self
     */
    private function setupUser()
    {
        $authenticationManager = AuthenticationManager::getInstance();

        if ($authenticationManager->isAuthenticated() === true) {
            $this->user = $authenticationManager->getUser();
        }

        return $this;
    }

    /**
     * Initialize a session provider
     *
     * @return  self
     */
    private function setupSession()
    {
        $this->session = Session::create();
        return $this;
    }

    /**
     * Inject dependencies into request
     *
     * @return self
     */
    private function setupRequest()
    {
        $this->request = new Request();

        if ($this->user instanceof User) {
            $this->request->setUser($this->user);
        }

        return $this;
    }

    /**
     * Instantiate front controller
     *
     * @return self
     */
    private function setupFrontController()
    {
        $this->frontController = Zend_Controller_Front::getInstance();

        $this->frontController->setRequest($this->request);

        $this->frontController->setControllerDirectory($this->getApplicationDir('/controllers'));

        $this->frontController->setParams(
            array(
                'displayExceptions' => true
            )
        );

        return $this;
    }

    /**
     * Register helper paths and views for renderer
     *
     * @return self
     */
    private function setupViewRenderer()
    {
        /** @var \Zend_Controller_Action_Helper_ViewRenderer $view */
        $view = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view->setView(new View());

        $view->view->addHelperPath($this->getApplicationDir('/views/helpers'));

        $view->view->setEncoding('UTF-8');
        $view->view->headTitle()->prepend(
            $this->config->global !== null ? $this->config->global->get('project', 'Icinga') : 'Icinga'
        );

        $view->view->headTitle()->setSeparator(' :: ');

        $this->viewRenderer = $view;

        return $this;
    }

    /**
     * Configure pagination settings
     *
     * @return self
     */
    private function setupPagination()
    {

        Zend_Paginator::addScrollingStylePrefixPath(
            'Icinga_Web_Paginator_ScrollingStyle',
            'Icinga/Web/Paginator/ScrollingStyle'
        );

        Zend_Paginator::setDefaultScrollingStyle('SlidingWithBorder');
        Zend_View_Helper_PaginationControl::setDefaultViewPartial(
            array('mixedPagination.phtml', 'default')
        );

        return $this;
    }

    /**
     * Setup user timezone if set and valid, otherwise global default timezone
     *
     * @return  self
     * @see     ApplicationBootstrap::setupTimezone
     */
    protected function setupTimezone()
    {
        if ($this->user !== null && $this->user->getPreferences() !== null) {
            $userTimezone = $this->user->getPreferences()->get('app.timezone');
        } else {
            $userTimezone = null;
        }

        try {
            DateTimeFactory::setConfig(array('timezone' => $userTimezone));
            date_default_timezone_set($userTimezone);
        } catch (ConfigurationError $e) {
            return parent::setupTimezone();
        }

        return $this;
    }

    /**
     * Setup internationalization using gettext
     *
     * Uses the preferred user language or the configured default and system default, respectively.
     *
     * @return  self
     */
    protected function setupInternationalization()
    {
        parent::setupInternationalization();
        if ($this->user !== null && $this->user->getPreferences() !== null
            && ($locale = $this->user->getPreferences()->get('app.language') !== null)
        ) {
            try {
                Translator::setupLocale($locale);
            } catch (Exception $error) {
                Logger::warning(
                    'Cannot set locale "' . $locale . '" configured in ' .
                    'preferences of user "' . $this->user->getUsername() . '"'
                );
            }
        }
        return $this;
    }

    /**
     * Setup an autoloader namespace for Icinga\Form
     *
     * @return  self
     */
    private function setupFormNamespace()
    {
        $this->getLoader()->registerNamespace(
            'Icinga\\Form',
            $this->getApplicationDir('forms')
        );
        return $this;
    }
}
// @codeCoverageIgnoreEnd
