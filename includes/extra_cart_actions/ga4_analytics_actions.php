<?php
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022, Vinos de Frutas Tropicales.
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        // -----
        // Each of these base Zen Cart actions have the potential to affect the cart's contents.
        // A notification is issued for the GA4 Analytics' observer class so that it can grab
        // a copy of the products currently in the cart, making it easier to deal with the
        // add_to_cart and remove_from_cart GA4 events.
        //
        case 'update_product':
        case 'add_product':
        case 'buy_now':
        case 'multiple_products_add_product':
        case 'cust_order':
        case 'remove_product':
        case 'empty_cart':
            $zco_notifier->notify('NOTIFY_GA4_CART_ACTION_STARTS', $_GET['action']);
            break;

        // -----
        // Other values, nothing to process here ...
        //
        default:
            break;
    }
}
