<?php
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

namespace Icinga\Protocol\Commandpipe;

/**
 *  Container class to modify a few monitoring attributes at oncee
 *
 */
class PropertyModifier
{
    /**
     *  Set an attribute to be enabled in the command
     */
    const STATE_ENABLE = 1;

    /**
     *  Set an attribute to be disabled in the command
     */
    const STATE_DISABLE = 0;

    /**
     *  Set an attribute to not be modified in the command
     */
    const STATE_KEEP = -1;

    /**
     *  Template for enabling/disabling flap detection
     */
    const FLAPPING = "%s_FLAP_DETECTION";

    /**
     *  Template for enabling/disabling active checks
     */
    const ACTIVE = "%s_CHECK";

    /**
     *  Template for enabling/disabling passive checks
     */
    const PASSIVE = "PASSIVE_%s_CHECKS";

    /**
     *  Template for enabling/disabling notification
     */
    const NOTIFICATIONS = "%s_NOTIFICATIONS";

    /**
     *  Template for enabling/disabling freshness checks
     */
    const FRESHNESS = "%s_FRESHNESS_CHECKS";

    /**
     *  Template for enabling/disabling event handler
     */
    const EVENTHANDLER = "%s_EVENT_HANDLER";

    /**
     * The state that will be applied when fetching this container for an object
     *
     * @var array
     */
    private $flags = array(
        self::FLAPPING => self::STATE_KEEP,
        self::ACTIVE => self::STATE_KEEP,
        self::PASSIVE => self::STATE_KEEP,
        self::NOTIFICATIONS => self::STATE_KEEP,
        self::FRESHNESS => self::STATE_KEEP,
        self::EVENTHANDLER => self::STATE_KEEP
    );

    /**
     * Create a new PropertyModified object using the given flags
     *
     * @param array $flags      Flags to enable/disable/keep different monitoring attributes
     */
    public function __construct(array $flags)
    {
        foreach ($flags as $type => $value) {
            if (isset($this->flags[$type])) {
                $this->flags[$type] = $value;
            }
        }
    }

    /**
     * Return this object as a template for the given object type
     *
     * @param $type         Either CommandPipe::TYPE_HOST or CommandPipe::TYPE_SERVICE
     * @return array        An array of external command templates for the given type representing the containers state
     */
    public function getFormatString($type)
    {
        $cmd = array();
        foreach ($this->flags as $cmdTemplate => $setting) {
            if ($setting == self::STATE_KEEP) {
                continue;
            }
            $commandString = ($setting == self::STATE_ENABLE ? "ENABLE_" : "DISABLE_");
            $targetString = $type;
            if ($type == CommandPipe::TYPE_SERVICE && $cmdTemplate == self::FRESHNESS) {
                // the external command definition is inconsistent here..
                $targetString = "SERVICE";
            }
            $commandString .= sprintf($cmdTemplate, $targetString);
            $cmd[] = $commandString;
        }
        return $cmd;
    }
}
