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

use \Exception;
use \ArrayIterator;
use \IteratorAggregate;

/**
 * Container for session values
 */
class SessionNamespace implements IteratorAggregate
{
    /**
     * The session this namespace is associated to
     *
     * @var Session
     */
    protected $session;

    /**
     * The actual values stored in this container
     *
     * @var array
     */
    protected $values = array();

    /**
     * The names of all values removed from this container
     *
     * @var array
     */
    protected $removed = array();

    /**
     * Create a new session namespace
     *
     * @param   Session     $session    The session this namespace is associated to
     */
    public function __construct(Session $session = null)
    {
        $this->session = $session;
    }

    /**
     * Return an iterator for all values in this namespace
     *
     * @return  ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->getAll());
    }

    /**
     * Set a session value by property access
     *
     * @param   string  $key    The value's name
     * @param   mixed   $value  The value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Return a session value by property access
     *
     * @param   string  $key    The value's name
     *
     * @return  mixed           The value
     * @throws  Exception       When the given value-name is not found
     */
    public function __get($key)
    {
        if (!array_key_exists($key, $this->values)) {
            throw new Exception('Cannot access non-existent session value "' . $key . '"');
        }

        return $this->get($key);
    }

    /**
     * Return whether the given session value is set
     *
     * @param   string  $key    The value's name
     * @return  bool
     */
    public function __isset($key)
    {
        return isset($this->values[$key]);
    }

    /**
     * Unset the given session value
     *
     * @param   string  $key    The value's name
     */
    public function __unset($key)
    {
        $this->delete($key);
    }

    /**
     * Setter for session values
     *
     * @param   string      $key        Name of value
     * @param   mixed       $value      Value to set
     *
     * @return  self
     */
    public function set($key, $value)
    {
        $this->values[$key] = $value;

        if (in_array($key, $this->removed)) {
            unset($this->removed[array_search($key, $this->values)]);
        }

        return $this;
    }

    /**
     * Getter for session values
     *
     * @param   string  $key        Name of the value to return
     * @param   mixed   $default    Default value to return
     *
     * @return  mixed
     */
    public function get($key, $default = null)
    {
        return isset($this->values[$key]) ? $this->values[$key] : $default;
    }

    /**
     * Delete the given value from the session
     *
     * @param   string  $key    The value's name
     */
    public function delete($key)
    {
        $this->removed[] = $key;
        unset($this->values[$key]);
    }

    /**
     * Getter for all session values
     *
     * @return array
     */
    public function getAll()
    {
        return $this->values;
    }

    /**
     * Put an array into the session
     *
     * @param   array   $values     Values to set
     * @param   bool    $overwrite  Overwrite existing values
     */
    public function setAll(array $values, $overwrite = false)
    {
        foreach ($values as $key => $value) {
            if ($this->get($key, $value) !== $value && !$overwrite) {
                continue;
            }
            $this->set($key, $value);
        }
    }

    /**
     * Save the session this namespace is associated to
     */
    public function write()
    {
        if (!$this->session) {
            throw new Exception('Cannot save, session not set');
        }

        $this->session->write();
    }
}
