<?php

/**
 * This is the part of Povils open-source library.
 *
 * @author Povilas Susinskas
 */

namespace Povils\Figlet;

/**
 * Class Font
 *
 * @package Povils\Figlet
 */
class Font
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $fileCollection;

    /**
     * @var string
     */
    private $signature;

    /**
     * @var string
     */
    private $hardBlank;

    /**
     * @var int
     */
    private $height;

    /**
     * @var int
     */
    private $maxLength;

    /**
     * @var int
     */
    private $oldLayout;

    /**
     * @var int
     */
    private $commentLines;

    /**
     * @var int
     */
    private $printDirection;

    /**
     * @var int
     */
    private $fullLayout;

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return Font
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     */
    public function getFileCollection()
    {
        return $this->fileCollection;
    }

    /**
     * @param array $fileCollection
     *
     * @return Font
     */
    public function setFileCollection($fileCollection)
    {
        $this->fileCollection = $fileCollection;

        return $this;
    }


    /**
     * @return string
     */
    public function getHardBlank()
    {
        return $this->hardBlank;
    }

    /**
     * @param string $hardBlank
     *
     * @return Font
     */
    public function setHardBlank($hardBlank)
    {
        $this->hardBlank = $hardBlank;

        return $this;
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return $this->signature;
    }

    /**
     * @param string $signature
     *
     * @return Font
     */
    public function setSignature($signature)
    {
        $this->signature = $signature;

        return $this;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param int $height
     *
     * @return Font
     */
    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @return int
     */
    public function getOldLayout()
    {
        return $this->oldLayout;
    }

    /**
     * @param int $oldLayout
     *
     * @return Font
     */
    public function setOldLayout($oldLayout)
    {
        $this->oldLayout = $oldLayout;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxLength()
    {
        return $this->maxLength;
    }

    /**
     * @param int $maxLength
     *
     * @return Font
     */
    public function setMaxLength($maxLength)
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    /**
     * @return int
     */
    public function getPrintDirection()
    {
        return $this->printDirection;
    }

    /**
     * @param int $printDirection
     *
     * @return Font
     */
    public function setPrintDirection($printDirection)
    {
        $this->printDirection = $printDirection;

        return $this;
    }

    /**
     * @return int
     */
    public function getCommentLines()
    {
        return $this->commentLines;
    }

    /**
     * @param int $commentLines
     *
     * @return Font
     */
    public function setCommentLines($commentLines)
    {
        $this->commentLines = $commentLines;

        return $this;
    }

    /**
     * @return int
     */
    public function getFullLayout()
    {
        return $this->fullLayout;
    }

    /**
     * @param int $fullLayout
     *
     * @return Font
     */
    public function setFullLayout($fullLayout)
    {
        $this->fullLayout = $fullLayout;

        return $this;
    }
}
