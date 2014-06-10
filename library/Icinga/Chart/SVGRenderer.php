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

namespace Icinga\Chart;

use \DOMNode;
use \DOMElement;
use \DOMDocument;
use \DOMImplementation;
use \Icinga\Util\Dimension;
use \Icinga\Chart\Render\LayoutBox;
use \Icinga\Chart\Render\RenderContext;
use \Icinga\Chart\Primitive\Canvas;

/**
 * SVG Renderer component.
 *
 * Creates the basic DOM tree of the SVG to use
 */
class SVGRenderer
{
    /**
     * The XML-document
     *
     * @var DOMDocument
     */
    private $document;

    /**
     * The SVG-element
     *
     * @var DOMNode
     */
    private $svg;

    /**
     * The root layer for all elements
     *
     * @var Canvas
     */
    private $rootCanvas;

    /**
     * The width of this renderer
     *
     * @var int
     */
    private $width = 100;

    /**
     * The height of this renderer
     *
     * @var int
     */
    private $height = 100;

    /**
     * Create the root document and the SVG root node
     */
    private function createRootDocument()
    {
        $implementation = new DOMImplementation();
        $docType = $implementation->createDocumentType(
            'svg',
            '-//W3C//DTD SVG 1.1//EN',
            'http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd'
        );

        $this->document = $implementation->createDocument(null, null, $docType);
        $this->svg = $this->createOuterBox();
        $this->document->appendChild($this->svg);
    }

    /**
     * Create the outer SVG box  containing the root svg element and namespace and return it
     *
     * @return DOMElement The SVG root node
     */
    private function createOuterBox()
    {
        $ctx = $this->createRenderContext();
        $svg = $this->document->createElement('svg');
        $svg->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        $svg->setATtribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
        $svg->setAttribute('width', $this->width . '%');
        $svg->setAttribute('height', $this->width . '%');
        $svg->setAttribute(
            'viewBox',
            sprintf(
                '0 0 %s %s',
                $ctx->getNrOfUnitsX(),
                $ctx->getNrOfUnitsY()
            )
        );
        return $svg;
    }

    /**
     * Initialises the XML-document, SVG-element and this figure's root canvas
     *
     * @param int $width    The width ratio
     * @param int $height   The height ratio
     */
    public function __construct($width, $height)
    {
        $this->width = $width;
        $this->height = $height;
        $this->rootCanvas = new Canvas('root', new LayoutBox(0, 0));
    }

    /**
     * Render the SVG-document
     *
     * @return string The resulting XML structure
     */
    public function render()
    {
        $this->createRootDocument();
        $ctx = $this->createRenderContext();
        $this->svg->appendChild($this->rootCanvas->toSvg($ctx));
        $this->document->formatOutput = true;
        return $this->document->saveXML();
    }

    /**
     * Create a render context that will be used for rendering elements
     *
     * @return RenderContext The created RenderContext instance
     */
    public function createRenderContext()
    {
        return new RenderContext($this->document, $this->width, $this->height);
    }

    /**
     * Return the root canvas of this rendered
     *
     * @return Canvas The canvas that will be the uppermost element in this figure
     */
    public function getCanvas()
    {
        return $this->rootCanvas;
    }
}
