<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2014 Icinga Development Team
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
 * @copyright  2014 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Web\Session;

use Icinga\Logger\Logger;
use \Icinga\Exception\ConfigurationError;

/**
 * Session implementation in PHP
 */
class PhpSession extends Session
{
    /**
     * The namespace prefix
     *
     * Used to differentiate between standard session keys and namespace identifiers
     */
    const NAMESPACE_PREFIX = 'ns.';

    /**
     * Whether the session has already been closed
     *
     * @var bool
     */
    private $hasBeenTouched = false;

    /**
     * Name of the session
     *
     * @var string
     */
    private $sessionName = 'Icingaweb2';

    /**
     * Configuration for cookie options
     *
     * @var array
     */
    private static $defaultCookieOptions = array(
        'use_trans_sid'             => false,
        'use_cookies'               => true,
        'cookie_httponly'           => true,
        'use_only_cookies'          => true,
        'hash_function'             => true,
        'hash_bits_per_character'   => 5,
    );

    /**
     * Create a new PHPSession object using the provided options (if any)
     *
     * @param   array   $options    An optional array of ini options to set
     *
     * @throws  ConfigurationError
     * @see     http://php.net/manual/en/session.configuration.php
     */
    public function __construct(array $options = null)
    {
        if ($options !== null) {
            $options = array_merge(self::$defaultCookieOptions, $options);
        } else {
            $options = self::$defaultCookieOptions;
        }

        if (array_key_exists('test_session_name', $options)) {
            $this->sessionName = $options['test_session_name'];
            unset($options['test_session_name']);
        }

        foreach ($options as $sessionVar => $value) {
            if (ini_set("session." . $sessionVar, $value) === false) {
                Logger::warning(
                    'Could not set php.ini setting %s = %s. This might affect your sessions behaviour.',
                    $sessionVar,
                    $value
                );
            }
        }

        if (!is_writable(session_save_path())) {
            throw new ConfigurationError('Can\'t save session');
        }

        $this->read();
    }

    /**
     * Open a PHP session
     */
    private function open()
    {
        session_name($this->sessionName);

        if ($this->hasBeenTouched) {
            $cacheLimiter = ini_get('session.cache_limiter');
            ini_set('session.use_cookies', false);
            ini_set('session.use_only_cookies', false);
            ini_set('session.cache_limiter', null);
        }

        session_start();

        if ($this->hasBeenTouched) {
            ini_set('session.use_cookies', true);
            ini_set('session.use_only_cookies', true);
            ini_set('session.cache_limiter', $cacheLimiter);
        }
    }

    /**
     * Read all values written to the underling session and make them accessible.
     */
    public function read()
    {
        $this->clear();
        $this->open();

        foreach ($_SESSION as $key => $value) {
            if (strpos($key, self::NAMESPACE_PREFIX) === 0) {
                $namespace = new SessionNamespace($this);
                $namespace->setAll($value);
                $this->namespaces[substr($key, strlen(self::NAMESPACE_PREFIX))] = $namespace;
            } else {
                $this->set($key, $value);
            }
        }

        session_write_close();
        $this->hasBeenTouched = true;
    }

    /**
     * Write all values of this session object to the underlying session implementation
     */
    public function write()
    {
        $this->open();

        foreach ($this->removed as $key) {
            unset($_SESSION[$key]);
        }
        foreach ($this->values as $key => $value) {
            $_SESSION[$key] = $value;
        }
        foreach ($this->removedNamespaces as $identifier) {
            unset($_SESSION[self::NAMESPACE_PREFIX . $identifier]);
        }
        foreach ($this->namespaces as $identifier => $namespace) {
            $_SESSION[self::NAMESPACE_PREFIX . $identifier] = $namespace->getAll();
        }

        session_write_close();
        $this->hasBeenTouched = true;
    }

    /**
     * Delete the current session, causing all session information to be lost
     */
    public function purge()
    {
        $this->open();
        $_SESSION = array();
        $this->clear();
        session_destroy();
        $this->clearCookies();
        session_write_close();
        $this->hasBeenTouched = true;
    }

    /**
     * Remove session cookies
     */
    private function clearCookies()
    {
        if (ini_get('session.use_cookies')) {
            Logger::debug('Clear session cookie');
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
    }

    /**
     * Assign a new sessionId to the currently active session
     */
    public function refreshId()
    {
        $this->open();
        session_regenerate_id();
        session_write_close();
    }
}
