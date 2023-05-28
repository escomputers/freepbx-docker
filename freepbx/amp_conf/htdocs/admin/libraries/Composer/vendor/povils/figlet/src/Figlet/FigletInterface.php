<?php

/**
 * This is the part of Povils open-source library.
 *
 * @author Povilas Susinskas
 */

namespace Povils\Figlet;

/**
 * Interface FigletInterface
 *
 * @package Povils\Figlet
 */
interface FigletInterface
{
    /**
     * @param string $color
     *
     * @return FigletInterface
     */
    public function setBackgroundColor($color);

    /**
     * @param string $fontName
     *
     * @return FigletInterface
     */
    public function setFont($fontName);

    /**
     * @param string $color
     *
     * @return FigletInterface
     */
    public function setFontColor($color);

    /**
     * @param string $fontDir
     *
     * @return FigletInterface
     */
    public function setFontDir($fontDir);

    /**
     * @param int $stretching
     *
     * @return FigletInterface
     */
    public function setFontStretching($stretching);

    /**
     * @param string $text
     *
     * @return FigletInterface
     */
    public function write($text);

    /**
     * @param string $text
     *
     * @return string
     */
    public function render($text);
}
