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
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Conftool\Icinga2;

class Icinga2Dependency extends Icinga2ObjectDefinition
{
    protected $type = 'Dependency';

    protected $v1AttributeMap = array(
        //
    );

    protected $v1RejectedAttributeMap = array(
        'hostgroup_name',
        'inherits_parent'
    );

    protected function convertDependency_period($value) {
        $this->period = "\"".$value."\"";
    }

    protected function convertExecution_failure_criteria($value) {
        $this->states = "[ OK ]"; //FIXME

        $this->disable_checks = "true"; //FIXME
    }

    protected function convertNotification_failure_criteria($value) {
        $this->disable_notifications = "true"; //FIXME
    }

    //parent host
    protected function convertHost_name($value) {
        $this->parent_host_name = "\"" . $value . "\"";
    }

    //parent service
    protected function convertService_description($value) {
        $this->parent_service_name = "\"" . $value . "\"";
    }

    //child host
    protected function convertDependent_host_name($value) {
        $arr = $this->splitComma($value);

        //single host does not require apply
        if (count($arr) == 1) {
            $this->child_host_name = $this->migrateLegacyString($value);
            return;
        }

        $this->is_apply = true;

        foreach ($arr as $hostname) {
            if (substr($hostname, 0, 1) === '!') {
                $hostname = substr($hostname, 1);
                $this->ignoreWhere('host.name == '.$this->migrateLegacyString($hostname));
            } else {
                $this->assignWhere('host.name == '.$this->migrateLegacyString($hostname));
            }
        }
    }

    //child hostgroup
    protected function convertDependent_hostgroup_name($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;

        foreach ($arr as $hostgroupname) {
            if (substr($hostgroupname, 0, 1) === '!') {
                $hostgroupname = substr($hostgroupname, 1);
                $this->ignoreWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            } else {
                $this->assignWhere($this->migrateLegacyString($hostgroupname) . ' in host.groups');
            }
        }
    }

    //child service
    protected function convertDependent_service_description($value) {
        $arr = $this->splitComma($value);

        //single service does not require apply
        if (count($arr) == 1) {
            if (array_key_exists('dependent_host_name', $this->properties)) {
                $this->child_host_name = $this->migrateLegacyString($this->dependent_host_name);
            }
            $this->child_service_name = $this->migrateLegacyString($value);
            return;
        }

        $this->is_apply = true;
        $this->apply_target = "Service";

        //TODO: build (h1 || h2) && (s1 || s2 || s3)
        foreach ($arr as $servicename) {
            if (substr($servicename, 0, 1) === '!') {
                $servicename = substr($servicename, 1);
                $this->ignoreWhere('service.name == '.$this->migrateLegacyString($servicename));
            } else {
                $this->assignWhere('service.name == '.$this->migrateLegacyString($servicename));
            }
        }
    }

    //child servicegroup
    protected function convertDependent_servicegroup_name($value) {
        $arr = $this->splitComma($value);
        $this->is_apply = true;
        $this->apply_target = "Service";

        foreach ($arr as $servicegroupname) {
            if (substr($servicegroupname, 0, 1) === '!') {
                $servicegroupname = substr($servicegroupname, 1);
                $this->ignoreWhere($this->migrateLegacyString($servicegroupname) . ' in service.groups');
            } else {
                $this->assignWhere($this->migrateLegacyString($servicegroupname) . ' in service.groups');
            }
        }
    }
}