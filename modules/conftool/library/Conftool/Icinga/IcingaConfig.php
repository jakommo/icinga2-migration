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

namespace Icinga\Module\Conftool\Icinga;

class IcingaConfig
{
    protected $base_config;
    protected $config_file;

    protected $allDefinitions = array();
    protected $templates = array();

    protected $definition_files = array();
    protected $definitions = array();
    protected $host_index = array();
    protected $group_members = array();
    protected $host_groups = array();

    public function __construct($file)
    {
        $this->config_file = $file;
    }

    public function getDefinitions($type = null)
    {
        if ($type === null) {
            return $this->definitions;
        } else {
            if (! array_key_exists($type, $this->definitions)) {
                // TODO: Better create all types in constructor and fail for
                //       invalid types
                return array();
            }
            return $this->definitions[$type];
        }
    }

    public function getTemplates()
    {
        return $this->templates;
    }

    public function dump()
    {
        foreach ($this->definitions as $type => $definitions) {
            foreach ($definitions as $d) {
                $d->dump();
            }
        }
    }

    public function getCommandList()
    {
        $list = array();
        if (! isset($this->definitions['command'])) return $list;
        foreach ($this->definitions['command'] as $d) {
            if ($d->isTemplate()) continue;
            if (! $d instanceof IcingaCommand) {
                throw new IcingaConfigException('A command has to be a command');
            }
            if (isset($list[$d->command_name])) {
                throw new IcingaConfigException('A command cannot be defined twice');
            }
            $list[$d->command_name] = $d->command_line;
        }
        return $list;
    }

    public function getHostList()
    {
        $liste = array();
        if (! isset($this->definitions['host'])) return $liste;

        foreach ($this->definitions['host'] as $d) {
            if ($d->isTemplate()) continue;
            if (! $d instanceof IcingaHost) {
                throw new IcingaConfigException('A host has to be a host');
            }
            $ip = ip2long($d->address);
            if ($ip === false) {
                continue;
            }
            $liste[$d->address] = $d->host_name;
        }
        return $liste;
    }

    public function getIpList()
    {
        $liste = array();
        if (! isset($this->definitions['host'])) return $liste;

        foreach ($this->definitions['host'] as $d) {
            if ($d->isTemplate()) continue;
            if (! $d instanceof IcingaHost) continue;
            $ip = ip2long($d->address);
            if ($ip === false) {
                continue;
            }
            $liste[$ip] = $d->address;
        }
        return $liste;
    }

    public function store()
    {
    }

    public function refresh()
    {
        if (! is_readable($this->config_file)) {
            throw new IcingaConfigException(sprintf(
                'Cannot read config file: %s',
                $this->config_file
            ));
        }
        $content = file_get_contents($this->config_file);
        $content = preg_replace('~\#.*$~m', '', $content);
        $content = preg_replace('~\s+\;.*$~m', '', $content);
        $this->base_config = (object) array();
        $this->base_config->cfg_file = array();
        $this->base_config->cfg_dir = array();
        $this->base_config->broker_module = array();
        $this->base_config->log_file = array(); // TODO: really? multiple entries?
        foreach (preg_split('~\n~', $content, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            list($k, $v) = preg_split('~\s*=\s*~', $line, 2);
            if (property_exists($this->base_config, $k)) {
                if (is_array($this->base_config->$k)) {
                    array_push($this->base_config->$k, $v);
                } else {
                    throw new IcingaConfigException(sprintf(
                        'Trying to set config param twice: %s',
                        $k
                    ));
                }
            } else {
                $this->base_config->$k = $v;
            }
        }
        foreach ($this->base_config->cfg_file as $file) {
            $this->addDefinitionFile($file);
        }
        foreach ($this->base_config->cfg_dir as $dir) {
            if (! is_dir($dir)) {
                throw new IcingaConfigException(sprintf(
                    'Trying to add invalid definition dir: %s',
                    $dir
                ));
            }
            $this->discoverDefinitionFiles($dir);
        }
        $this->parseDefinitionFiles();
        $this->createDefinitionIndexes();
        $this->resolveParents();
        $this->resolveServices();
    }

    public function parseDefinitionFiles()
    {
        foreach ($this->definition_files as $file) {
            // echo "Parsing $file\n";
            if (! is_readable($file)) {
                throw new IcingaConfigException(sprintf(
                    'Unable to read definition file: %s',
                    $file
                ));
            }
            $content = file_get_contents($file);
            $this->parseDefinitions($content, $file);
        }
    }

    public function parseDefinitions($content, $file)
    {
        $content = preg_replace('~^\s*[\#\;].*$~m', '', $content);
        $content = preg_replace('~([^\\\])\;.*$~m', '$1', $content);
        $open = false;
        $vals = null;
        $current_type = null;
        $buffer = '';
        foreach (preg_split('~\r?\n~', $content, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            if (preg_match('~^\s*$~', $line)) continue;
            if ($buffer !== '') {
                $line = $buffer . $line;
                $buffer = '';
            }
            if (! $open) {
                if (preg_match('~^\s*define\s+([a-z]+)\s*\{~', $line, $match)) {
                    $current_type = $match[1];
                    $open = true;
                    $vals = (object) array();
                } elseif (preg_match('~^\s*define\s+([a-z]+)\s*$~', $line, $match)) {
                    $buffer .= $line;
                } else {
                    throw new IcingaConfigException(sprintf('Cannot parse config line: "%s" in file "%s"', $line, $file));
                }
                continue;
            }
            if (preg_match('~\}\s*$~', $line)) {
                $line = preg_replace('~\}\s*$~', '', $line);
                $open = false;
            }
            $kv = preg_split('~\s+~', $line, 2, PREG_SPLIT_NO_EMPTY);
            if (! empty($kv)) {
                if (! isset($kv[1])) {
                    // Skip illegal lines. TODO: Shall we show a notice?
                    continue;
                }
                $vals->$kv[0] = rtrim($kv[1]);
            }

            if ($open !== false) {
                continue;
            }
            // TODO: How to handle missing key?
            //if ($current_type == 'hostescalation') continue;
            //if ($current_type == 'serviceescalation') continue;
            if ($current_type == 'hostextinfo') continue;
            if ($current_type == 'serviceextinfo') continue;
            //if ($current_type == 'hostdependency') continue;
            //if ($current_type == 'servicedependency') continue;
            $this->addDefinition(
                IcingaObjectDefinition::factory($current_type, $vals)
            );
        }
    }

    protected function splitComma($string)
    {
        return preg_split('/\s*,\s*/', $string, null, PREG_SPLIT_NO_EMPTY);
    }

    protected function addHostGroupMapping($host, $group)
    {
        $group_members = array();
        $host_groups = array();
        if (! isset($this->group_members[$group])) {
            $this->group_members[$group] = array();
        }
        if (! isset($this->host_groups[$host])) {
            $this->host_groups[$host] = array();
        }
        if (! in_array($host, $this->group_members[$group])) {
            $this->group_members[$group][] = $host;
        }
        if (! in_array($group, $this->host_groups[$host])) {
            $this->host_groups[$host][] = $group;
        }
    }

    public function getHostgroupToHostMappings()
    {
        return $this->group_members;
    }

    public function getHostToHostgroupMappings()
    {
        return $this->host_groups;
    }

    public function listGroups()
    {
        return array_keys($this->group_members);
    }

    public function getGroupsForHost($hostname)
    {
        if (! array_key_exists($hostname, $this->host_groups)) return array();
        return $this->host_groups[$hostname];
    }

    public function hostHasGroup($host, $group)
    {
        if (! array_key_exists($host, $this->host_groups)) return false;
        return in_array($group, $this->host_groups[$host]);
    }

    public function addDefinition(IcingaObjectDefinition $definition)
    {
        $this->allDefinitions[] = $definition;
        if ($definition->isTemplate()) {
            $this->templates[(string) $definition] = $definition;
        }

        return $this;
    }

    protected function resolveParents()
    {
        foreach ($this->allDefinitions as $definition) {
            //print_r("definition:".$definition." ".var_dump($definition));
            //skip if no templates defined
            if (! $definition->use) {
                continue;
            }
            $uses = $this->splitComma($definition->use);
            foreach ($uses as $use) {
                if (! array_key_exists($use, $this->templates)) {
                    //there may still be an object used instead
                    print("//Template does not exist. Trying a real object.\n");
                    if (! array_key_exists($use, $this->allDefinitions)) {
                        print("//ERROR: Template '".$use."' does not exist. Fix your configuration.\n");
                        continue;
                        //throw new IcingaDefinitionException(
                        //   sprintf('Object inherits from unknown template "%s"', $use) . print_r($definition)
                        //);
                    }
                }
                $definition->addParent($this->templates[$use]);
            }
            //print_r("object templates:\n");
            //var_dump($definition->getParents());
        }
    }

    protected function createDefinitionIndexes()
    {
        foreach ($this->allDefinitions as $definition) {
            $this->createDefinitionIndex($definition);
        }
    }

    protected function createDefinitionIndex($definition)
    {
	    /* service objects cannot be indexed as they are not unique
	     * service templates must be, and can just be indexed */
        if ($definition instanceof IcingaService && ! $definition->isTemplate()) {
            return;
        }

        //use the unique name as id
        try {
            $id = (string) $definition;
        } catch(Exception $e) {
            echo 'Exception: ',  $e->getMessage(), '\n';
        }

        $type = $definition->getDefinitionType();
        if (isset($this->definitions[$type][$id])) {
            throw new IcingaConfigException(sprintf(
                'Got duplicate definition : %s',
                $id
            ));
        }
        $this->definitions[$type][$id] = $definition;

        if ($definition->isTemplate()) {
            return;
        }

        if ($definition instanceof IcingaHostgroup) {
            $members = $this->splitComma($definition->members);
            foreach ($members as $member) {
                $this->addHostGroupMapping($member, (string) $definition);
            }
        }
        if ($definition instanceof IcingaHost) {
            if ($definition->hostgroups)  {
                $members = $this->splitComma($definition->hostgroups);
                foreach ($members as $member) {
                    $this->addHostGroupMapping((string) $definition, $member);
                }
            }
            $this->host_index[strtolower($definition->address)] = $id;
            $this->host_index[strtolower($definition->host_name)] = $id;
            $this->host_index[strtolower($definition->alias)] = $id;
        }
    }

    protected function resolveServices()
    {
        foreach ($this->allDefinitions as $definition) {
            if ($definition instanceof IcingaService) {
                $this->resolveService($definition);
            }
            if ($definition instanceof IcingaHost) {
                $this->resolveHost($definition);
            }
        }
    }

    protected function resolveHost(IcingaHost $host)
    {
        if ($host->isTemplate()) {
            return;
        }

        //get the check command from the template tree
        //otherwise we cannot migrate it later
        if (! $host->check_command) {
            $check_command = $this->getObjectAttributeRecursive($host, 'check_command');

            if (! $check_command) {
                print("//ERROR: Host ".$host." without valid check_command attribute (also not in templates). Assigning 'dummy'.\n");
                $host->check_command = "dummy";
            } else {
                $host->check_command = $check_command;
            }
        }
    }


    //TODO this only works if the object has 'host_name' or 'hostgroup_name'
    //but not if that attribute is hidden in the template tree
    protected function resolveService(IcingaService $service)
    {
        //TODO service templates can be linked too? - NO
        if ($service->isTemplate()) {
            return;
        }

        //get the check command from the template tree
        //otherwise we cannot migrate it later
        if (! $service->check_command) {
            $check_command = $this->getObjectAttributeRecursive($service, 'check_command');

            if (! $check_command) {
                print("//ERROR: Service ".$service." without valid check_command attribute (also not in templates). Assigning 'dummy'.\n");
                $service->check_command = "dummy";
            } else {
                $service->check_command = $check_command;
            }
        }

        $hostgroups = $service->hostgroup_name
                    ? $this->splitComma($service->hostgroup_name)
                    : array();
        $hosts = $service->host_name
               ? $this->splitComma($service->host_name)
               : array();

        //check if there are attributes hidden in the template tree
        //we need to directly assign host_name and hostgroup_name here
        //v2 cannot easily resolve these attributes
        if (empty($hosts)) {
            //print("parents: ".$service."\n");
            //var_dump($service_tmpl); //FIXME lookup the attributes in the template tree?
            $hosts = $this->getObjectAttributeRecursive($service, 'host_name');
            if (! $hosts) {
                $hosts = array();
            } else {
                print_r("Found host_name attribute in template tree: ".$hosts);
                $service->_hosts = $hosts; //store them for later
            }
            //var_dump($hosts);
        }
        if (empty($hostgroups)) {
            $hostgroups = $this->getObjectAttributeRecursive($service, 'hostgroup_name');
            if (! $hostgroups) {
                $hostgroups = array();
            } else {
                $service->_hostgroups = $hostgroups; //store them for later
            }
            //var_dump($hostgroups);
        }

        if (empty($hosts) && empty($hostgroups) && !$service->isTemplate()) {
            print("Could not find any host or hostgroup_name attribute. Skipping invalid object.");
            return;
        }

        if (empty($hosts) && empty($hostgroups) && $service->isTemplate()) {
            return;
        }

        $assigned = false;
        foreach (array_unique($hosts) as $host) {
            if (isset($this->definitions['host'][$host])) {
                $assigned = true;
                if (! $this->definitions['host'][$host]->hasService($service)) {
                    //relation must be force on migration task! see MigrateCommand.php (ugly TODO)
                    //$service->host_name = (string) $host;
                    $this->definitions['host'][$host]->addService($service);
                }
            } elseif (substr($host, 0, 1) === '!' && isset($this->definitions['host'][substr($host, 1)])) {
                $assigned = true;
                $host = substr($host, 1);
                if (! $this->definitions['host'][$host]->hasBlacklistedService($service)) {
                    $this->definitions['host'][$host]->blacklistService($service);
                }
            } elseif (substr($host, 0, 1) === '*') {
                //assign service to all hosts
                foreach ($this->definitions['host'] as $config_host) {
                    if (! $config_host->hasService($service)) {
                        $config_host->addService($service);
                        $assigned = true;
                        //TODO: this is ugly as f*ck but
                        //skip further hosts, we'll deal with that rule later in v2
                        break;
                    }
                }
            } else {
                printf('//ERROR: Cannot assign service "%s" to host "%s"\n"', $service, $host);
            }
        }
        foreach (array_unique($hostgroups) as $hostgroup) {
            if (isset($this->definitions['hostgroup'][$hostgroup])) {
                $assigned = true;
                try {
                    $this->definitions['hostgroup'][$hostgroup]->addService($service);
                } catch (Exception $e) {
                    echo 'Exception: ',  $e->getMessage(), ' for hostgroup ', $hostgroup, '\n';
                }
            } elseif (substr($hostgroup, 0, 1) === '!' && isset($this->definitions['hostgroup'][substr($hostgroup, 1)])) {
                $assigned = true;
                $hostgroup = substr($hostgroup, 1);
                if (! $this->definitions['hostgroup'][$hostgroup]->hasBlacklistedService($service)) {
                    $this->definitions['hostgroup'][$hostgroup]->blacklistService($service);
                }
            } else {
                printf('//ERROR: Cannot assign service "%s" to hostgroup "%s"', $service, $hostgroup);
            }
        }
        if (! $assigned) {
            echo "Unassigned service: '" . (string) $service . "'.";
        }
    }

    //required for host_name, hostgroup_name, contact{,_group}s lookups
    protected function getObjectAttributeRecursive($object, $attr)
    {
        if ($object->$attr) {
            return $object->$attr;
        }

        $templates = $object->getParents();

        foreach ($templates as $template) {
            if (!$template->$attr) {
                $this->getObjectAttributeRecursive($template, $attr);
            } else {
                return $template->$attr;
            }
        }
    }

    protected function addTemplate(IcingaTemplate $template)
    {
        $this->addDefinition($template);
    }

    public static function parse($file)
    {
        $config = new IcingaConfig($file);
        $config->refresh();
        return $config;
    }

    public function hasHost($search)
    {
        if (! is_array($search)) $search = array($search);
        foreach ($search as $s) {
            if (array_key_exists(strtolower($s), $this->host_index)) {
                return true;
            }
        }
        return false;
    }

    public function getHost($search)
    {
        if (! is_array($search)) $search = array($search);
        foreach ($search as $s) {
            if (! $this->hasHost($s)) continue;
            return $this->definitions['host'][$this->host_index[strtolower($s)]];
        }
        return false;
    }

    public function hasObject($search)
    {
        if (! is_array($search)) $search = array($search);
        foreach ($search as $s) {
            if (array_key_exists(strtolower($s), $this->definitions)) {
                return true;
            }
        }
        return false;
    }

    public function getObject($search, $type)
    {
        if (array_key_exists(strtolower($search), $this->definitions[$type])) {
            return $this->definitions[$type][strtolower($search)];
        }

        return false;
    }

    public function getObjectsByAttributeValue($search_attr_name, $search_attr_value, $type)
    {
        $arr = array();
        foreach ($this->definitions[$type] as $definition) {
            if ($definition->$search_attr_name == $search_attr_value) {
                $array[] = $definition;
            }
        }

        return $arr;
    }

    protected function discoverDefinitionFiles($dir)
    {
        $files = array();
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (is_dir($file)) {
                    $files += $this->discoverDefinitionFiles($file);
                }
                $full_file = $dir . '/' . $file;
                if (is_dir($full_file)) {
                    foreach ($this->discoverDefinitionFiles($full_file) as $f) {
                        $this->addDefinitionFile($f);
                    }
                } elseif (preg_match('~\.cfg$~', $file) && is_file($full_file)) {
                    $this->addDefinitionFile($full_file);
                }
            }
        }
        closedir($handle);
        return $files;
    }

    protected function addDefinitionFile($file)
    {
        if (array_key_exists($file, $this->definition_files)) {
            throw new IcingaConfigException(sprintf(
                'Trying to add definition file twice: %s',
                $file
            ));
        }
        $this->definition_files[$file] = $file;
    }
}
