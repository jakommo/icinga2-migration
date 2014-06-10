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

namespace Icinga\Web\Form\Element;

use Zend_Form_Element_Xhtml;

/**
 * Number form element
 */
class Number extends Zend_Form_Element_Xhtml
{
    /**
     * Default form view helper to use for rendering
     *
     * @var string
     */
    public $helper = "formNumber";

    /**
     * Check whether $value is of type integer
     *
     * @param   string      $value      The value to check
     * @param   mixed       $context    Context to use
     *
     * @return  bool
     */
    public function isValid($value, $context = null)
    {
        if (parent::isValid($value, $context)) {
            if (is_numeric($value)) {
                return true;
            }

            $this->addError(t('Please enter a number.'));
        }

        return false;
    }
}
