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

namespace Icinga\Web\Form\Decorator;

use Zend_Form_Decorator_Abstract;

/**
 * Decorator that automatically adds a helptext to an input element
 * when the 'helptext' attribute is set
 */
class HelpText extends Zend_Form_Decorator_Abstract
{
    /**
     * Add a helptext to an input field
     *
     * @param   string $content The help text
     *
     * @return  string The generated tag
     */
    public function render($content = '')
    {
        $attributes = $this->getElement()->getAttribs();
        $visible = true;
        if (isset($attributes['condition'])) {
            $visible = $attributes['condition'] == '1';
        }
        if (isset($attributes['helptext']) && $visible) {
            $content =  $content
                . '<p class="help-block">'
                . $attributes['helptext']
                . '</p>';
        }
        return $content;
    }
}
// @codeCoverageIgnoreEnd
