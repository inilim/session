<?php

require_once '../vendor/autoload.php';

use Inilim\Session\Session;
use Inilim\Dump\Dump;

Dump::init();


$a = new Session;
$a->init();
