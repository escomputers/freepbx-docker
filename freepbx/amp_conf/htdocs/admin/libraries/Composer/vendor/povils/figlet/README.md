# Figlet text render
	  ______ _       _      _              _____  _    _ _____  
	 |  ____(_)     | |    | |            |  __ \| |  | |  __ \ 
	 | |__   _  __ _| | ___| |_   ______  | |__) | |__| | |__) |
	 |  __| | |/ _` | |/ _ \ __| |______| |  ___/|  __  |  ___/ 
	 | |    | | (_| | |  __/ |_           | |    | |  | | |     
	 |_|    |_|\__, |_|\___|\__|          |_|    |_|  |_|_|     
	            __/ |                                          
               |___/                                            	         

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/4ff60a14-e810-493e-9997-c77d99ffcd32/mini.png)](https://insight.sensiolabs.com/projects/4ff60a14-e810-493e-9997-c77d99ffcd32)
[![Build Status](https://scrutinizer-ci.com/g/povils/figlet/badges/build.png?b=master)](https://scrutinizer-ci.com/g/povils/figlet/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/povils/figlet/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/povils/figlet/?branch=master)
[![Total Downloads](https://poser.pugx.org/povils/figlet/downloads)](https://packagist.org/packages/povils/figlet)
[![License](https://poser.pugx.org/povils/figlet/license)](https://packagist.org/packages/povils/figlet)

## Installation

 Available as [Composer] package [povils/figlet].

```
composer require povils/figlet "dev-master"
```


[composer]: http://getcomposer.org/
[povils/figlet]: https://packagist.org/packages/povils/figlet

## What is this? And what is Figlet?

This is Php5 library which renders or outputs Figlet text in your console.
Figlet is a computer program that generates text banners, in a variety of typefaces, composed of letters made up of conglomerations of smaller ASCII characters

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Povils\Figlet\Figlet;

// Default font is "big"
$figlet = new Figlet();

//Outputs "Figlet" text using "small" red font in blue background.
$figlet
    ->setFont('small')
    ->setFontColor('red')
    ->setBackgroundColor('blue')
    ->write('Figlet');

//Returns rendered string.
$renderedFiglet = $figlet->render('Another Figlet')

- setFontDir(__DIR_ . '/fonts') // Change default font directory
- setFontStretching(3) // Add spaces between letters
```

#### Also there is figlet command line. Usage is quite straightforward.
```bash
    ./figlet 'some figlet text' --font block --color yellow
```

##### To make figlet executable from everywhere
 - (Linux and OSX) Symlink figlet script file to one of the $PATH (e.g /usr/local/bin/figlet)

##### For more options:
```bash
    figlet -h
```