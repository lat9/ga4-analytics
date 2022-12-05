<?php
/**
* @package Google Enhanced E-Commerce Analytics
* @copyright (c) 2015 RodG
* @copyright Copyright 2003-2017 Zen Cart Development Team
* @copyright Portions Copyright 2003 osCommerce
* @license http://www.zen-cart-pro.at/license/2_0.txt GNU Public License V2.0
* @version $Id: config.ec_analytics.php 2017-05-18 15:47:36Z webchills $
*/
$autoLoadConfig[90][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/class.ga4_analytics.php'
];
$autoLoadConfig[90][] = [
    'autoType' => 'classInstantiate',
    'className' => 'ga4_analytics',
    'objectName' => 'ga4_analytics'
];
