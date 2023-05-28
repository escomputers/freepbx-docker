<?php

/**
 * This is the part of Povils open-source library.
 *
 * @author Povilas Susinskas
 */

namespace Povils\Figlet\Tests;

use Povils\Figlet\Figlet;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Class FigletTest
 */
class FigletTest extends TestCase
{
    public function testRender_Default()
    {
        $figlet = new Figlet();
        $output = $figlet->render('Test');

        $this->assertEquals($this->getDefaultBigFontText(), $output);
    }

    public function testRender_SlantFont()
    {
        $figlet = new Figlet();
        $figlet->setFont('slant');
        $output = $figlet->render('Test');

        $this->assertEquals($this->getSlantFontText(), $output);
    }

    public function testRender_StretchedAndColorized()
    {
        $figlet = new Figlet();
        $figlet
            ->setFontStretching(1)
            ->setFontColor('red')
            ->setBackgroundColor('light_gray');

        $output = $figlet->render('Test');

        $this->assertEquals($this->getModifiedDefaultBigFontText("0;31", "47"), $output);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRender_UndefinedFontColor()
    {
        $figlet = new Figlet();
        $figlet
            ->setFontColor('bright_red');
        $figlet->render('Test');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testRender_UndefinedBackgroundColor()
    {
        $figlet = new Figlet();
        $figlet
            ->setBackgroundColor('bright_light_gray');
        $figlet->render('Test');
    }

    public function testRender_ChangeFontAndCachedLetters()
    {
        $figlet = new Figlet();
        $figlet->setFont('slant');
        $figlet->render('Test');
        $figlet->setFont('big');
        $figlet->render('Test');
        $output = $figlet->render('Test');

        $this->assertEquals($this->getDefaultBigFontText(), $output);
    }

    public function testRender_NewFontDir()
    {
        $figlet = new Figlet();

        $figlet
            ->setFontDir(__DIR__  .'/font/')
            ->setFont('slant');

        $output = $figlet->render('Test');

        $this->assertEquals($this->getSlantFontText(), $output);
    }

    /**
     * @expectedException Exception
     */
    public function testRender_BandFont()
    {
        $figlet = new Figlet();

        $figlet
            ->setFontDir(__DIR__  .'/font/')
            ->setFont('badfile');

        $figlet->render('Test');
    }

    /**
     * @return string
     */
    private function getDefaultBigFontText()
    {
      return
     '  _______                _   ' . "\n" .
     ' |__   __|              | |  ' . "\n" .
     '    | |      ___   ___  | |_ ' . "\n" .
     '    | |     / _ \ / __| | __|' . "\n" .
     '    | |    |  __/ \__ \ | |_ ' . "\n" .
     '    |_|     \___| |___/  \__|' . "\n" .
     '                             ' . "\n" .
     '                             ' . "\n";
    }

    /**
     * @return string
     */
    private function getModifiedDefaultBigFontText($fontColor, $backgroundColor)
    {
        return
            "\033[" . $fontColor . 'm' . "\033[" . $backgroundColor . 'm' .
            '  _______                   _    ' . "\n" .
            ' |__   __|                 | |   ' . "\n" .
            '    | |       ___    ___   | |_  ' . "\n" .
            '    | |      / _ \  / __|  | __| ' . "\n" .
            '    | |     |  __/  \__ \  | |_  ' . "\n" .
            '    |_|      \___|  |___/   \__| ' . "\n" .
            '                                 ' . "\n" .
            '                                 ' . "\n" .
            "\033[0m";
    }

    private function getSlantFontText()
    {
        return
        '  ______                 __ ' .  "\n" .
        ' /_  __/  ___    _____  / /_' .  "\n" .
        '  / /    / _ \  / ___/ / __/' .  "\n" .
        ' / /    /  __/ (__  ) / /_  ' .  "\n" .
        '/_/     \___/ /____/  \__/  ' .  "\n" .
        '                            ' .  "\n";
    }

}
