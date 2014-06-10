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


namespace Icinga\Chart\Graph;

use \DOMElement;
use \Icinga\Chart\Primitive\Drawable;
use \Icinga\Chart\Primitive\Path;
use \Icinga\Chart\Primitive\Circle;
use \Icinga\Chart\Primitive\Styleable;
use \Icinga\Chart\Render\RenderContext;

/**
 * LineGraph implementation for drawing a set of datapoints as
 * a connected path
 */
class LineGraph extends Styleable implements Drawable
{
    /**
     * The dataset to use
     *
     * @var array
     */
    private $dataset;

    /**
     * True to show dots for each datapoint
     *
     * @var bool
     */
    private $showDataPoints = false;

    /**
     * When true, the path will be discrete, i.e. showing hard steps instead of a direct line
     *
     * @var bool
     */
    private $isDiscrete = false;

    /**
     * The default stroke width
     * @var int
     */
    public $strokeWidth = 5;

    /**
     * Create a new LineGraph displaying the given dataset
     *
     * @param array $dataset An array of [x, y] arrays to display
     */
    public function __construct(array $dataset)
    {
        usort($dataset, array($this, 'sortByX'));
        $this->dataset = $dataset;
    }

    /**
     * Set datapoints to be emphased via dots
     *
     * @param bool $bool True to enable datapoints, otherwise false
     */
    public function setShowDataPoints($bool)
    {
        $this->showDataPoints = $bool;
    }

    /**
     * Sort the daset by the xaxis
     *
     * @param   array $v1
     * @param   array $v2
     * @return  int
     */
    private function sortByX(array $v1, array $v2)
    {
        if ($v1[0] === $v2[0]) {
            return 0;
        }
        return ($v1[0] < $v2[0]) ? -1 : 1;
    }

    /**
     * Configure this style
     *
     * @param array $cfg The configuration as given in the drawLine call
     */
    public function setStyleFromConfig(array $cfg)
    {
        $fill = false;
        foreach ($cfg as $elem => $value) {
            if ($elem === 'color') {
                $this->setStrokeColor($value);
            } elseif ($elem === 'width') {
                $this->setStrokeWidth($value);
            } elseif ($elem === 'showPoints') {
                $this->setShowDataPoints($value);
            } elseif ($elem === 'fill') {
                $fill = $value;
            } elseif ($elem === 'discrete') {
                $this->isDiscrete = true;
            }
        }
        if ($fill) {
            $this->setFill($this->strokeColor);
            $this->setStrokeColor('black');
        }
    }

    /**
     * Render this BarChart
     *
     * @param   RenderContext   $ctx    The rendering context to use for drawing
     *
     * @return  DOMElement      $dom    Element
     */
    public function toSvg(RenderContext $ctx)
    {
        $path = new Path($this->dataset);
        if ($this->isDiscrete) {
            $path->setDiscrete(true);
        }
        $path->setStrokeColor($this->strokeColor);
        $path->setStrokeWidth($this->strokeWidth);

        $path->setAttribute('data-icinga-graph-type', 'line');
        if ($this->fill !== 'none') {
            $firstX = $this->dataset[0][0];
            $lastX = $this->dataset[count($this->dataset)-1][0];
            $path->prepend(array($firstX, 100))
                ->append(array($lastX, 100));
            $path->setFill($this->fill);
        }

        $path->setAdditionalStyle('clip-path: url(#clip);');
        $path->setId($this->id);
        $group = $path->toSvg($ctx);
        if ($this->showDataPoints === true) {
            foreach ($this->dataset as $point) {
                $dot = new Circle($point[0], $point[1], $this->strokeWidth*5);
                $dot->setFill('black');

                $group->appendChild($dot->toSvg($ctx));
            }
        }
        return $group;
    }
}
