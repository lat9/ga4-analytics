<?php
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022-2023, Vinos de Frutas Tropicales.
//
// Last updated: v1.1.1
//
// Based on:
/**
* @package Google Enhanced E-Commerce Analytics
* @copyright (c) 2015 RodG
* @copyright Copyright 2003-2017 Zen Cart Development Team
* @copyright Portions Copyright 2003 osCommerce
* @license http://www.zen-cart-pro.at/license/2_0.txt GNU Public License V2.0
* @version $Id: config.ec_analytics.php 2017-05-18 15:47:36Z webchills $
*/
$autoLoadConfig[120][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/class.ga4_analytics.php'
];
$autoLoadConfig[120][] = [
    'autoType' => 'classInstantiate',
    'className' => 'ga4_analytics',
    'objectName' => 'ga4_analytics'
];
