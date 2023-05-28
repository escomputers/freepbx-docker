<?php

/**
 * This is the part of Povils open-source library.
 *
 * @author Povilas Susinskas
 */

namespace Povils\Figlet;

/**
 * Class Figlet
 *
 * @package Povils\Figlet
 */
class Figlet implements FigletInterface
{
    /**
     * Defines first ASCII character code (blank/space).
     */
    const FIRST_ASCII_CHARACTER = 32;

    /**
     * @var ColorManager
     */
    private $colorManager;

    /**
     * @var FontManager
     */
    private $fontManager;

    /**
     * @var Font
     */
    private $font;

    /**
     * @var string
     */
    private $backgroundColor;

    /**
     * @var string
     */
    private $fontColor;

    /**
     * @var string
     */
    private $fontName;

    /**
     * @var string
     */
    private $fontDir;

    /**
     * @var int
     */
    private $stretching;

    /**
     * This array will hold used Figlet characters.
     *
     * @var array
     */
    private $characters = [];

    public function __construct()
    {
        $this->fontDir = __DIR__ . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR;
        $this->fontName = 'big';
        $this->stretching = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function write($text)
    {
        echo $this->render($text);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function render($text)
    {
        $this->font = $this->getFontManager()->loadFont($this->fontName, $this->fontDir);

        $figletText = $this->generateFigletText($text);

        if ($this->fontColor || $this->backgroundColor) {
            $figletText = $this->colorize($figletText);
        }

        return $figletText;
    }

    /**
     * {@inheritdoc}
     */
    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFontColor($color)
    {
        $this->fontColor = $color;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFont($fontName)
    {
        $this->fontName = $fontName;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFontDir($fontDir)
    {
        $this->fontDir = $fontDir;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFontStretching($stretching)
    {
        $this->stretching = $stretching;

        return $this;
    }

    /**
     * Unset some arrays and objects.
     */
    public function clear()
    {
        unset($this->characters, $this->font);
    }

    /**
     * Generates Figlet text.
     *
     * @param string $text
     *
     * @return string
     */
    private function generateFigletText($text)
    {
        $figletCharacters = $this->getFigletCharacters($text);

        return $this->combineFigletCharacters($figletCharacters);
    }

    /**
     * @param string $text
     *
     * @return array
     */
    private function getFigletCharacters($text)
    {
        $figletCharacters = [];
        foreach (str_split($text) as $character) {
            $figletCharacters[] = $this->getFigletCharacter($character);
        }

        return $figletCharacters;
    }

    /**
     * @param string $character
     *
     * @return array
     */
    private function getFigletCharacter($character)
    {
        if (isset($this->characters[$this->fontName][$character])) {
            return $this->characters[$this->fontName][$character];
        }

        $figletCharacter = [];

        $lines = $this->getFigletCharacterLines($character);

        foreach ($lines as $line) {
            $figletCharacter[] = str_replace(
                ['@', $this->font->getHardBlank()],
                ['', ' '],
                $line
            );
        }

        $this->characters[$this->fontName][$character] = $figletCharacter;

        return $figletCharacter;
    }

    /**
     * @param string $character
     *
     * @return array
     */
    private function getFigletCharacterLines($character)
    {
        $letterStartPosition = $this->getLetterStartPosition($character);

        $lines = array_slice($this->font->getFileCollection(), $letterStartPosition, $this->font->getHeight());

        return $lines;
    }

    /**
     * @param array $figletCharacters
     *
     * @return string
     */
    private function combineFigletCharacters($figletCharacters)
    {
        $figletText = '';

        $height = $this->font->getHeight();
        for ($line = 0; $line < $height; $line++) {
            $singleLine = '';
            foreach ($figletCharacters as $charactersLines) {
                $singleLine .= $charactersLines[$line] . $this->addStretching();
            }
            $singleLine = $this->removeNewlines($singleLine);
            $figletText .= $singleLine . "\n";
        }

        return $figletText;
    }

    /**
     * Colorize text.
     *
     * @param string $figletText
     *
     * @return string
     */
    private function colorize($figletText)
    {
        $figletText = $this
            ->getColorManager()
            ->colorize(
                $figletText,
                $this->fontColor,
                $this->backgroundColor
            );

        return $figletText;
    }

    /**
     * @return ColorManager
     */
    private function getColorManager()
    {
        if (null === $this->colorManager) {
            return $this->colorManager = new ColorManager();
        }

        return $this->colorManager;
    }

    /**
     * @return FontManager
     */
    private function getFontManager()
    {
        if (null === $this->fontManager) {
            return $this->fontManager = new FontManager();
        }

        return $this->fontManager;
    }

    /**
     * Remove newlines characters.
     *
     * @param string $singleLine
     *
     * @return string
     */
    private function removeNewlines($singleLine)
    {
       return preg_replace('/[\\r\\n]*/', '', $singleLine);
    }

    /**
     * Adds space(s) in the end to Figlet character.
     *
     * @return string
     */
    private function addStretching()
    {
        if (is_numeric($this->stretching) && 0 < $this->stretching) {
            $stretchingSpace = ' ';
        } else {
            $stretchingSpace = '';
            $this->stretching = 0;
        }

        return str_repeat($stretchingSpace, $this->stretching);
    }

    /**
     * @param string $character
     *
     * @return int
     */
    private function getLetterStartPosition($character)
    {
        return ((ord($character) - self::FIRST_ASCII_CHARACTER) * $this->font->getHeight()) + 1 + $this->font->getCommentLines();
    }
}
