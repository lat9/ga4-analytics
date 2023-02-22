<?php
// -----
// Part of the "GA4 Analytics" plugin, created by lat9 (https://vinosdefrutastropicales.com)
// Copyright (c) 2022-2023, Vinos de Frutas Tropicales.
//
// Last updated: v1.1.0
//
// Based on:
/**
 * @package Google Enhanced E-Commerce Analytics
 * @copyright (c) 2015 RodG
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart-pro.at/license/2_0.txt GNU Public License V2.0
 * @version $Id: class.ec_analytics.php 2017-12-11  DrByte $
 */
class ga4_analytics extends base
{
    public
        $isConfigured = false,      //- Indicates whether or not the plugin is configured.
        $enabled = false,           //- Indicates whether or not the plugin is enabled; it can be disabled via external call.
        $initialized = false,       //- Indicates that the GA4 initialization script has not yet been loaded.
        $measurement_id,            //- The GA4 measurement ID, copied from the configuration.
        $canShowPrices,             //- Indicates whether prices can be included, based on the store status and customer authorization
        $productAdded = false,      //- Information about a product being added to the cart, an array containing the uprid and quantity on entry; false otherwise.
        $currentCartSaved = false,  //- Identifies that the $currentCart value reflects the customer's cart's current contents.
        $currentCart = false,       //- Contains the current cart's products' info, set on a cart-related action.
        $useModelAsItemId = true;   //- Indicates whether the product's model should be used for any 'item_id' values; if not, then the 'products_id' is used instead.

    function __construct()
    {
        // -----
        // If the configuration's not yet set or the measurement ID doesn't start with 'G-', nothing further to be done.
        //
        if (!defined('GA4_ANALYTICS_TRACKING_ID') || strpos(GA4_ANALYTICS_TRACKING_ID, 'G-') !== 0) {
            return;
        }

        // -----
        // If the variant name separator has not been previously defined, use ' / '.
        //
        if (!defined('GA4_ANALYTICS_VARIANT_SEPARATOR')) {
            define('GA4_ANALYTICS_VARIANT_SEPARATOR', ' / ');
        }

        // -----
        // Determine whether pricing is to be included when products are viewed.
        //
        $is_logged_in = zen_is_logged_in();
        switch (true) {
            //- Customer must be logged in to browse or customer can browse, but no prices
            case ((CUSTOMERS_APPROVAL === '1' || CUSTOMERS_APPROVAL === '2') && $is_logged_in === false):
            //- Customer may browse but no prices
            case (CUSTOMERS_APPROVAL === '3' && TEXT_LOGIN_FOR_PRICE_PRICE_SHOWROOM !== ''):
            //- Customer must be logged in to browse
            case (CUSTOMERS_APPROVAL_AUTHORIZATION !== '0' && CUSTOMERS_APPROVAL_AUTHORIZATION !== '3' && ($is_logged_in === false || (int)$_SESSION['customers_authorization'] > 0)):
            //- Customer is logged in and was changed to must be approved to see prices
            case (isset($_SESSION['customers_authorization']) && (int)$_SESSION['customers_authorization'] == 2):
            //- Showcase only
            case STORE_STATUS === '1':
                $this->canShowPrices = false;
                break;
            default:
                $this->canShowPrices = true;
                break;
        }

        // -----
        // Indicate that the processing is properly configured and start watching for significant events.
        //
        $this->isConfigured = true;
        $this->enabled = true;
        $this->measurement_id = GA4_ANALYTICS_TRACKING_ID;
        $this->useModelAsItemId = (defined('GA4_ANALYTICS_ITEM_ID_VALUE') && GA4_ANALYTICS_ITEM_ID_VALUE === 'products_model');
        $this->attach(
            $this,
            [
                /* script insertion events */
                'NOTIFY_HTML_HEAD_TAG_START',   //- **NEW**, must be just after the <head> tag in the template's html_header.php
                'NOTIFY_FOOTER_END',            //- Just prior to the </body> tag in the template's tpl_main_page.php

                /* 'view_item' events */
                'NOTIFY_HEADER_END_DOCUMENT_GENERAL_INFO',
                'NOTIFY_HEADER_END_DOCUMENT_PRODUCT_INFO',
                'NOTIFY_HEADER_END_PRODUCT_FREE_SHIPPING_INFO',
                'NOTIFY_HEADER_END_PRODUCT_INFO',
                'NOTIFY_HEADER_END_PRODUCT_MUSIC_INFO',

                /* 'view_item_list' events */
                'NOTIFY_HEADER_END_FEATURED_PRODUCTS',
                'NOTIFY_HTML_HEAD_START',       //- for products_all and products_new, since they provide no notifications from their respective headers.

                /* cart-related events */
                'NOTIFIER_CART_RESTORE_CONTENTS_START',
                'NOTIFIER_CART_RESTORE_CONTENTS_END',
                'NOTIFIER_CART_ADD_CART_START',
                'NOTIFIER_CART_ADD_CART_END', 
                'NOTIFIER_CART_REMOVE_START',
                'NOTIFIER_CART_REMOVE_ALL_START',
                'NOTIFY_HEADER_END_SHOPPING_CART',

                /* login/signup events */
                'NOTIFY_LOGIN_SUCCESS',
                'NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT',

                /* purchase-related events */
                'NOTIFY_HEADER_END_CHECKOUT_SUCCESS',

                /* search event */
                'NOTIFY_HEADER_END_ADVANCED_SEARCH_RESULTS',
            ]
        );
    }

    public function isEnabled()
    {
        return $this->isConfigured && $this->enabled;
    }
    public function setDisabled()
    {
        $this->enabled = false;
    }
    public function setEnabled()
    {
        $this->enabled = true;
        return $this->isEnabled();
    }

    public function update(&$class, $eventId, $p1, &$p2, &$p3, &$p4, &$p5, &$p6)
    {
        global $db;

        // -----
        // If not currently enabled, nothing further to be done.  An external process can
        // disable the processing even if it's configured, like a tracking cookie opt-out.
        //
        if ($this->enabled === false) {
            return;
        }

        // -----
        // The GA4 events are recorded in the session, since the add-to/remove-from-cart actions
        // result in a page refresh and the associated events would otherwise be 'lost'.
        //
        if (!isset($_SESSION['ga4_analytics'])) {
            $_SESSION['ga4_analytics'] = [];
        }

        // -----
        // Overall processing switch, building up data for the to-be-rendered script loaded just
        // prior to the page's </body> tag.
        //
        switch ($eventId) {
            // -----
            // Load the GA4 initialization script at the beginning of the page's <head>.
            //
            case 'NOTIFY_HTML_HEAD_TAG_START':
                global $template, $current_page_base;

                $this->initialized = true;
                $ga4_measurement_id = $this->measurement_id;
                require $template->get_template_dir('ga4_analytics_start_script.php', DIR_WS_TEMPLATE, $current_page_base, 'jscript') . '/ga4_analytics_start_script.php';
                break;

            // -----
            // Load the GA4 script that outputs any events associated with the current page.  If
            // the previous notification wasn't received, a PHP error results, since the site hasn't
            // added the required notification to the template's html_header.php.
            //
            case 'NOTIFY_FOOTER_END':
                global $template, $current_page_base;

                if ($this->initialized === false) {
                    trigger_error('Missing NOTIFY_HTML_HEAD_TAG_START notification; GA4 Analytics cannot proceed.', E_USER_WARNING);
                    break;
                }

                // -----
                // The attributes on a product's details page are rendered during the
                // templating stage, so they're not available until this point.
                //
                if (substr($current_page_base, -5) === '_info') {
                    global $product_info, $products_id_current, $cPath_array, $options_name, $options_html_id;

                    // -----
                    // For Zen Cart versions _prior to_ zc157, the $product_info variable was set in the
                    // pages' main_template_vars.php and the $products_id_current was not set.
                    //
                    if (isset($products_id_current)) {
                        $products_id = $products_id_current;
                    } else {
                        global $db;

                        $products_id = isset($_REQUEST['products_id']) ? (int)$_REQUEST['products_id'] : 0;
                        $sql = 
                            "SELECT p.*, pd.*, pt.allow_add_to_cart, pt.type_handler
                               FROM " . TABLE_PRODUCTS . " p
                                    LEFT JOIN " . TABLE_PRODUCT_TYPES . " pt
                                        ON p.products_type = pt.type_id
                                    LEFT JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                                        ON (p.products_id = pd.products_id AND pd.language_id = " . (int)$_SESSION['languages_id'] . ")
                              WHERE p.products_id = " . $products_id;
                        $product_info = $db->Execute($sql, 1, true, 900);
                    }
                    if ($product_info->EOF) {
                        $item = [
                            'item_name' => "Unknown ($products_id)",
                        ];
                    } else {
                        $product = $product_info->fields;
                        $products_id = $product['products_id'];
                        $item = $this->getItemInfo($product);
                    }

                    $item = array_merge($item, $this->getItemCategories($products_id, isset($cPath_array) ? $cPath_array : null));

                    if (isset($options_name) && isset($options_html_id)) {
                        $variants = $this->getItemVariants($options_name, $options_html_id);
                        if ($variants !== '') {
                            $item['item_variant'] = $variants;
                        }
                    }

                    $_SESSION['ga4_analytics'][] = [
                        'event' => 'view_item',
                        'parameters' => [
                            'currency' => $_SESSION['currency'],
                            'value' => $item['price'],
                            'items' => [
                                $item,
                            ]
                        ],
                    ];
                } else {
                    switch ($current_page_base) {
                        case FILENAME_CHECKOUT_SHIPPING:
                            $_SESSION['ga4_analytics'][] = [
                                'event' => 'begin_checkout',
                                'parameters' => $this->getCheckoutParameters(),
                            ];
                            break;

                        case FILENAME_CHECKOUT_PAYMENT:
                            global $order;

                            $checkout_parameters = $this->getCheckoutParameters();
                            $came_from_confirmation_page = (strpos($_SERVER['HTTP_REFERER'], zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, '', 'SSL')) === 0);
                            if ($_SESSION['cart']->get_content_type() === 'virtual' && $came_from_confirmation_page === false) {
                                $_SESSION['ga4_analytics'][] = [
                                    'event' => 'begin_checkout',
                                    'parameters' => $checkout_parameters,
                                ];
                            }
                            if (isset($order->info['shipping_method']) && $came_from_confirmation_page === false) {
                                $checkout_parameters['shipping_tier'] = $order->info['shipping_method'];
                                $_SESSION['ga4_analytics'][] = [
                                    'event' => 'add_shipping_info',
                                    'parameters' => $checkout_parameters,
                                ];
                            }
                            break;

                        case FILENAME_CHECKOUT_CONFIRMATION:
                            if (strpos($_SERVER['HTTP_REFERER'], zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL')) === 0) {
                                $checkout_parameters = $this->getCheckoutParameters();
                                $checkout_parameters['payment_type'] = $_SESSION['payment'];
                                $_SESSION['ga4_analytics'][] = [
                                    'event' => 'add_payment_info',
                                    'parameters' => $checkout_parameters,
                                ];
                            }
                            break;

                        // -----
                        // The 'specials' page has always been (er) special.  It's got no real
                        // notifiers of its own to grab onto, so wait until the footer's being
                        // rendered to gather its listing elements.
                        //
                        case FILENAME_SPECIALS:
                            global $specials, $listing;

                            $products = isset($listing) ? $listing : $specials;
                            if (count($products) === 0) {
                                break;
                            }
                            $_SESSION['ga4_analytics'][] = [
                                'event' => 'view_item_list',
                                'parameters' => [
                                    'item_list_name' => GA4_ANALYTICS_SPECIALS,
                                    'items' => $this->getListingItems($products),
                                ]
                            ];
                            break;

                        // -----
                        // The index products' listing doesn't get generated until the
                        // template renders, pulling in the /modules/products_listing.php
                        //
                        case FILENAME_DEFAULT:
                            global $category_depth, $listing;
                            if ($category_depth !== 'products' || $listing->RecordCount() === 0) {
                                break;
                            }
                            $_SESSION['ga4_analytics'][] = [
                                'event' => 'view_item_list',
                                'parameters' => [
                                    'item_list_name' => GA4_ANALYTICS_PRODUCTS_LISTING,
                                    'items' => $this->getListingItems($listing)
                                ]
                            ];
                            break;

                        default:
                            break;
                    }
                }

                require $template->get_template_dir('ga4_analytics_events_script.php', DIR_WS_TEMPLATE, $current_page_base, 'jscript') . '/ga4_analytics_events_script.php';
                break;

            case 'NOTIFY_HEADER_END_FEATURED_PRODUCTS':
                global $db, $featured_products_split;

                $featured_products = $db->Execute($featured_products_split->sql_query);
                if ($featured_products->EOF) {
                    break;
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'view_item_list',
                    'parameters' => [
                        'item_list_name' => GA4_ANALYTICS_FEATURED_PRODUCTS,
                        'items' => $this->getListingItems($featured_products),
                    ]
                ];
                break;

            // -----
            // The 'products_all' and 'products_new' listings provide no notification of their own.
            // Use the template-based notification issued at the very start of a template's html_header.php
            // to gather the required information.
            //
            case 'NOTIFY_HTML_HEAD_START':
                global $db;
                $item_list_name = false;
                switch ($p1) {
                    case FILENAME_PRODUCTS_ALL:
                        global $products_all_split;
                        $item_list_name = GA4_ANALYTICS_ALL_PRODUCTS;
                        $listing = $db->Execute($products_all_split->sql_query);
                        break;
                    case FILENAME_PRODUCTS_NEW:
                        global $products_new_split;
                        $item_list_name = GA4_ANALYTICS_NEW_PRODUCTS;
                        $listing = $db->Execute($products_new_split->sql_query);
                        break;
                    default:
                        break;
                }
                if ($item_list_name === false || $listing->EOF) {
                    break;
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'view_item_list',
                    'parameters' => [
                        'item_list_name' => $item_list_name,
                        'items' => $this->getListingItems($listing),
                    ]
                ];
                break;

            // -----
            // When the cart's "restore_contents" method starts, grab the cart's current
            // contents.  At the end of that method, see what (if any) products have been
            // restored from the customer's cart.  If any items were added, issue an
            // add_to_cart event.
            //
            case 'NOTIFIER_CART_RESTORE_CONTENTS_START':
                $this->currentCart = $this->cartGetProducts();
                break;
            case 'NOTIFIER_CART_RESTORE_CONTENTS_END':
                $added_items = $this->determineProductsAddedDuringRestore();
                if ($added_items === false) {
                    break;
                }
                $value_added = 0;
                foreach ($added_items as $next_item) {
                    if (isset($next_item['price'])) {
                        $value_added += $next_item['price'] * $next_item['quantity'];
                    }
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'add_to_cart',
                    'parameters' => [
                        'currency' => $_SESSION['currency'],
                        'value' => $this->formatCurrency($value_added),
                        'items' => $added_items,
                    ],
                ];
                break;

            case 'NOTIFIER_CART_ADD_CART_START':
                if ($this->currentCartSaved === false) {
                    $this->currentCartSaved = true;
                    $this->currentCart = $this->cartGetProducts();
                }
                $uprid = $this->getUprid($p2, $p4);
                $this->productAdded = [
                    'uprid' => $uprid,
                    'starting_qty' => $this->getItemCurrentCartQuantity($uprid),
                ];
                break;

            case 'NOTIFIER_CART_ADD_CART_END':
                $uprid = $this->getUprid($p2, $p4);
                if ($this->productAdded === false || $this->productAdded['uprid'] !== $uprid) {
                    trigger_error("Add-to-cart product mismatch for uprid ($uprid): " . json_encode($this->productAdded), E_USER_WARNING);
                    break;
                }
                $qty = $_SESSION['cart']->get_quantity($uprid);

                if ($qty === $this->productAdded['starting_qty']) {
                    break;
                }

                // -----
                // If a product's quantity was reduced, fire a 'remove_from_cart' event to note the quantity removed.
                //
                if ($qty < $this->productAdded['starting_qty']) {
                    $quantity_removed = $this->productAdded['starting_qty'] - $qty;
                    $this->setItemCurrentCartQuantity($uprid, $quantity_removed);
                    $item = $this->getItemsInCart($uprid);
                    if (empty($item)) {
                        break;
                    }
                    $_SESSION['ga4_analytics'][] = [
                        'event' => 'remove_from_cart',
                        'parameters' => [
                            'currency' => $_SESSION['currency'],
                            'value' => $this->formatCurrency($this->getItemCurrentCartFinalPrice($uprid) * $quantity_removed),
                            'items' => $item,
                        ]
                    ];

                // -----
                // If a product's quantity was increased, fire an 'add_to_cart' event to note the additional quantity.
                //
                } elseif ($this->productAdded['starting_qty'] !== 0) {
                    $quantity_added = $qty - $this->productAdded['starting_qty'];
                    $this->setItemCurrentCartQuantity($uprid, $quantity_added);
                    $item = $this->getItemsInCart($uprid);
                    if (empty($item)) {
                        break;
                    }
                    $_SESSION['ga4_analytics'][] = [
                        'event' => 'add_to_cart',
                        'parameters' => [
                            'currency' => $_SESSION['currency'],
                            'value' => $this->formatCurrency($this->getItemCurrentCartFinalPrice($uprid) * $quantity_added),
                            'items' => $item,
                        ]
                    ];

                // -----
                // Otherwise, this product was newly-added to the cart; its information will be
                // pulled from the current cart-item and an 'add_to_cart' event will be fired.
                //
                } else {
                    $item = $this->getItemsInCart($uprid, true);
                    if (empty($item)) {
                        break;
                    }
                    $_SESSION['ga4_analytics'][] = [
                        'event' => 'add_to_cart',
                        'parameters' => [
                            'currency' => $_SESSION['currency'],
                            'value' => $this->formatCurrency($item[0]['price'] * $qty),
                            'items' => $item,
                        ]
                    ];
                }
                break;

            case 'NOTIFIER_CART_REMOVE_START':
               if ($this->currentCartSaved === false) {
                    $this->currentCartSaved = true;
                    $this->currentCart = $this->cartGetProducts();
                }
                $item = $this->getItemsInCart($p2);
                if (empty($item)) {
                    break;
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'remove_from_cart',
                    'parameters' => [
                        'currency' => $_SESSION['currency'],
                        'value' => $this->formatCurrency($this->getItemCurrentCartFinalPrice($p2) * $this->getItemCurrentCartQuantity($p2)),
                        'items' => $item,
                    ]
                ];
                break;

            case 'NOTIFIER_CART_REMOVE_ALL_START':
               if ($this->currentCartSaved === false) {
                    $this->currentCartSaved = true;
                    $this->currentCart = $this->cartGetProducts();
                }
                $cart_items = $this->getItemsInCart();
                if ($cart_items === false) {
                    break;
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'remove_from_cart',
                    'parameters' => [
                        'currency' => $_SESSION['currency'],
                        'value' => $this->formatCurrency($_SESSION['cart']->show_total()),
                        'items' => $cart_items,
                    ]
                ];
                break;

            case 'NOTIFY_HEADER_END_SHOPPING_CART':
                $this->currentCart = $this->cartGetProducts();
                if ($this->currentCart === false) {
                    break;
                }
                $cart_items = $this->getItemsInCart();
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'view_cart',
                    'parameters' => [
                        'currency' => $_SESSION['currency'],
                        'value' => $this->formatCurrency($_SESSION['cart']->show_total()),
                        'items' => $cart_items,
                    ]
                ];
                break;

            case 'NOTIFY_LOGIN_SUCCESS':
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'login',
                    'parameters' => [
                        'method' => 'normal',
                    ]
                ];
                break;

            case 'NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT':
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'sign_up',
                    'parameters' => []
                ];
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'login',
                    'parameters' => [
                        'method' => 'create-account',
                    ]
                ];
                break;

            case 'NOTIFY_HEADER_END_CHECKOUT_SUCCESS':
                global $order_summary;

                if (empty($order_summary)) {
                    break;
                }
                $parameters = [
                    'currency' => $order_summary['currency_code'],
                    'transaction_id' => $order_summary['order_number'],
                    'value' => $this->formatCurrency($order_summary['order_total']),
                    'shipping' => $this->formatCurrency($order_summary['shipping']),
                    'tax' => $this->formatCurrency($order_summary['tax']),
                    'items' => $this->getItemsInOrder(),
                ];
                if (!empty($order_summary['coupon_code'])) {
                    $parameters['coupon'] = $order_summary['coupon_code'];
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'purchase',
                    'parameters' => $parameters,
                ];
                break;

            case 'NOTIFY_HEADER_END_ADVANCED_SEARCH_RESULTS':
                // -----
                // No terms, no event!
                //
                if (empty($p1)) {
                    break;
                }
                $_SESSION['ga4_analytics'][] = [
                    'event' => 'search',
                    'parameters' => [
                        'search_term' => $p1,
                    ]
                ];
                break;

            default:
                break;
        }
    }

    // -----
    // Scaffolding to account for the fact that zen_get_uprid actually returns a
    // mixed (int|string) value; coerce that value to *always* be a string.
    //
    protected function getUprid($prid, $params)
    {
        return (string)zen_get_uprid($prid, $params);
    }

    // -----
    // Ditto to fix-up the uprid values returned by the cart's get_products method, so that
    // exactly-equal-to can be used when trying to find existing products in the cart for the
    // cart-related actions.
    //
    protected function cartGetProducts()
    {
        $cart_products = $_SESSION['cart']->get_products();
        if ($cart_products === false) {
            return false;
        }
        for ($i = 0, $n = count($cart_products); $i < $n; $i++) {
            $cart_products[$i]['id'] = (string)$cart_products[$i]['id'];
        }
        return $cart_products;
    }

    protected function formatCurrency($value)
    {
        return number_format((float)$value, 2, '.', '');
    }

    protected function getItemCategories($products_id, $cPath_array = null)
    {
        if (!is_array($cPath_array)) {
            $cPath_array = explode('_', zen_get_product_path($products_id));
        }
        $categories = [];
        $category_suffix = 1;
        foreach ($cPath_array as $categories_id) {
            $categories['item_category' . (($category_suffix === 1) ? '' : $category_suffix)] = zen_get_category_name($categories_id, $_SESSION['languages_id']);
            $category_suffix++;
            if ($category_suffix > 5) {
                break;
            }
        }
        return $categories;
    }

    protected function getItemVariants($options_names, $options_html_ids)
    {
        $item_variant = '';
        if (is_array($options_names) && is_array($options_html_ids)) {
            if (count($options_names) !== count($options_html_ids)) {
                trigger_error('Count mismatch in $options_names and $options_html_ids: ' . json_encode($options_names) . ' :: ' . json_encode($options_html_ids), E_USER_WARNING);
            } else {
                $variant_options = [];
                for ($i = 0, $n = count($options_names); $i < $n; $i++) {
                    if (strpos($options_html_ids[$i], 'drp') === 0 || strpos($options_html_ids[$i], 'rad') === 0) {
                        $variant_options[] = strip_tags($options_names[$i]);
                    }
                }
                $item_variant = implode(GA4_ANALYTICS_VARIANT_SEPARATOR, $variant_options);
            }
        }
        return $item_variant;
    }

    protected function getItemInfo($product)
    {
        $products_id = $product['products_id'];
        $item = [
            'item_name' => $product['products_name'],
        ];
        $item_price = $this->getItemPrice($products_id);
        if ($item_price !== false) {
            $item['currency'] = $_SESSION['currency'];
            $item['price'] = $this->formatCurrency($item_price);
        }

        $item = array_merge($item, $this->getItemIdAndModel($products_id, $product['products_model'] ?? ''));

        $brand = zen_get_products_manufacturers_name($products_id);
        if ($brand !== '') {
            $item['item_brand'] = $brand;
        }
        return $item;
    }
    
    // -----
    // Essentially, mimicing the processing for zen_get_products_display_price.
    //
    protected function getItemPrice($products_id)
    {
        global $db;

        // -----
        // If prices are not to be included for the current store/customer status, indicate as such.
        //
        if ($this->canShowPrices === false) {
            return false;
        }

        // -----
        // Don't included pricing for products that are of "Document General" type or call-for-price.
        //
        $product_check = $db->Execute(
            "SELECT products_tax_class_id, product_is_free
               FROM " . TABLE_PRODUCTS . "
              WHERE products_id = " . (int)$products_id . "
                AND products_type != 3
                AND product_is_call = 0
              LIMIT 1"
        );
        if ($product_check->EOF) {
            return false;
        }

        // -----
        // If the product is free, its price is 0.
        //
        if ($product_check->fields['product_is_free'] === '1') {
            return 0;
        }

        // -----
        // See if the product has a specials or sale price.
        //
        $products_sale_price = zen_get_products_special_price($products_id, false);

        // -----
        // If the product is on sale/special, get its specials price.
        //
        $products_special_price = ($products_sale_price === false) ? false : zen_get_products_special_price($products_id, true);

        // -----
        // The pricing 'pecking order' is that a sale overrides a special overrides the base.
        //
        if ($products_sale_price !== false) {
            $products_price = $products_sale_price;
        } elseif ($products_special_price !== false) {
            $products_price = $products_special_price;
        } else {
            $products_price = zen_get_products_base_price($products_id);
        }
        return zen_add_tax($products_price, zen_get_tax_rate($product_check->fields['products_tax_class_id']));
    }

    // -----
    // Starting with v1.1.0, a site can specify whether the products_model or $products_id is to
    // be sent as an 'item_id'.  If the products_id is being used and the products_model is not
    // empty, a customized ep.item_model parameter is included.
    //
    protected function getItemIdAndModel($products_id, $products_model)
    {
        $id_and_model = [];
        if ($this->useModelAsItemId === true) {
            if (!empty($products_model)) {
                $id_and_model['item_id'] = $products_model;
            }
        } else {
            // -----
            // See this (https://www.simoahava.com/analytics/implementation-guide-events-google-analytics-4/) article
            // that described GA4 custom-event naming.  An 'ep.' prefix is for a string value, 'epn.' is for a numeric value.
            //
            $id_and_model['item_id'] = $products_id;
            if (!empty($products_model)) {
                $id_and_model['ep.item_model'] = $products_model;
            }
        }
        return $id_and_model;
    }

    // -----
    // The various listing-type pages are very inconsistent in their inclusion of product pricing and, if
    // provided by the template, the pricing tends to be in the form of the product's "display price" which
    // would require error-prone parsing to determine the product's current normal/special/sale pricing.  As
    // such, pricing is not included on the listings.
    //
    protected function getListingItems($db_listing)
    {
        $items = [];
        foreach ($db_listing as $product) {
            $products_id = $product['products_id'];
            $item = [
                'item_name' => isset($product['products_name']) ? $product['products_name'] : zen_get_products_name($products_id),
            ];
            $item_price = $this->getItemPrice($products_id);
            if ($item_price !== false) {
                $item['currency'] = $_SESSION['currency'];
                $item['price'] = $this->formatCurrency($item_price);
            }

            $item = array_merge($item, $this->getItemIdAndModel($products_id, $product['products_model'] ?? ''));

            $brand = zen_get_products_manufacturers_name($products_id);
            if ($brand !== '') {
                $item['item_brand'] = $brand;
            }
            $item = array_merge($item, $this->getItemCategories($products_id));
            $items[] = $item;
        }
        return $items;
    }

    protected function getCheckoutParameters()
    {
        global $db, $order;

        $checkout_parameters = [
            'currency' => $_SESSION['currency'],
            'value' => $this->formatCurrency($order->info['total']),
            'items' => $this->getItemsInOrder(),
        ];
        if (isset($_SESSION['cc_id'])) {
            $coupon = $db->Execute(
                "SELECT coupon_code
                   FROM " . TABLE_COUPONS . "
                  WHERE coupon_id = " . (int)$_SESSION['cc_id'] . "
                  LIMIT 1"
            );
            if (!$coupon->EOF) {
                $checkout_parameters['coupon'] = $coupon->fields['coupon_code'];
            }
        }
        return $checkout_parameters;
    }

    // -----
    // Note: onetime_charges on products are "problematic", since there's no way to
    // provide them as a separate line-item in the GA4 interface.
    //
    protected function getItemsInOrder()
    {
        global $order;

        $items = [];
        foreach ($order->products as $product) {
            $item = [
                'item_name' => $product['name'],
                'currency' => $_SESSION['currency'],
                'quantity' => $product['qty'],
                'price' => $this->formatCurrency($product['final_price']),
            ];

            $item = array_merge($item, $this->getItemIdAndModel($product['id'], $product['model']));

            $brand = zen_get_products_manufacturers_name((int)$product['id']);
            if ($brand !== '') {
                $item['item_brand'] = $brand;
            }
            $item = array_merge($item, $this->getItemCategories((int)$product['id']));
            if (isset($product['attributes'])) {
                $variant_options = [];
                foreach ($product['attributes'] as $next_variant) {
                    if ($next_variant['value_id'] == 0) {
                        continue;
                    }
                    $variant_options[] = $next_variant['option'] . ': ' . $next_variant['value'];
                }
                $item['item_variant'] = implode(GA4_ANALYTICS_VARIANT_SEPARATOR, $variant_options);
            }
            $items[] = $item;
        }
        return $items;
    }

    // -----
    // Retrieves the 'final price' of the specified uprid in the class' currentCart.  If the product
    // is not in the cart, the value (int)0 is returned instead.
    //
    protected function getItemCurrentCartFinalPrice($uprid)
    {
        $final_price = 0;
        foreach ($this->currentCart as $cart_item) {
            if ($cart_item['id'] === $uprid) {
                $final_price = $cart_item['final_price'];
                break;
            }
        }
        return $final_price;
    }

    // -----
    // Retrieves the quantity of the specified uprid in the class' currentCart.  If the product
    // is not in the cart, the value (int)0 is returned instead.
    //
    protected function getItemCurrentCartQuantity($uprid)
    {
        $quantity = 0;
        foreach ($this->currentCart as $cart_item) {
            if ($cart_item['id'] === $uprid) {
                $quantity = $cart_item['quantity'];
                break;
            }
        }
        return $quantity;
    }

    // -----
    // Sets the quantity of the specified uprid in the class' currentCart, so long as the product
    // is present in the currentCart.
    //
    protected function setItemCurrentCartQuantity($uprid, $quantity)
    {
        foreach ($this->currentCart as &$cart_item) {
            if ($cart_item['id'] === $uprid) {
                $cart_item['quantity'] = $quantity;
                break;
            }
        }
        unset($cart_item);
    }

    // -----
    // This 'helper' method retrieves one or all items from a shopping-cart class' formatted array.
    // A single item can be requested by supplying the to-be-gathered uprid and the caller can request
    // that the actual cart-contents be returned rather than searching through the in-class saved variable.
    //
    protected function getItemsInCart($item_uprid = false, $current_cart_override = false)
    {
        $cart_contents = ($current_cart_override === false) ? $this->currentCart : $this->cartGetProducts();
        if ($cart_contents === false) {
            return false;
        }
        $items = [];
        foreach ($cart_contents as $cart_item) {
            $attributes = isset($cart_item['attributes']) ? $cart_item['attributes'] : [];
            if ($item_uprid !== false) {
                if ($item_uprid !== $this->getUprid($cart_item['id'], $attributes)) {
                    continue;
                }
                if ($current_cart_override !== false) {
                    $this->setItemCurrentCartQuantity($item_uprid, $cart_item['quantity']);
                }
            }
            $items[] = $this->createItemFromCartItem($cart_item);
        }
        return $items;
    }

    // -----
    // Create a GA4 item from a shopping_cart class item.
    //
    protected function createItemFromCartItem($cart_item)
    {
        $item = [
            'name' => $cart_item['name'],
            'currency' => $_SESSION['currency'],
            'quantity' => $cart_item['quantity'],
            'price' => $this->formatCurrency($cart_item['final_price']),
        ];

        $item = array_merge($item, $this->getItemIdAndModel($cart_item['id'], $cart_item['model']));

        $brand = zen_get_products_manufacturers_name((int)$cart_item['id']);
        if ($brand !== '') {
            $item['item_brand'] = $brand;
        }
        $item = array_merge($item, $this->getItemCategories((int)$cart_item['id']));

        // -----
        // Gather up any variants for the current product, noting that any text/file attributes are not
        // included in the list!
        //
        if (isset($cart_item['attributes']) && is_array($cart_item['attributes'])) {
            $variant_options = [];
            foreach ($cart_item['attributes'] as $options_id => $values_id) {
                if (0 === (int)$values_id) {
                    continue;
                }
                $variant_options[] = zen_options_name($options_id) . ': ' . zen_values_name($values_id);
            }
            if (count($variant_options) !== 0) {
                $item['item_variant'] = implode(GA4_ANALYTICS_VARIANT_SEPARATOR, $variant_options);
            }
        }
        return $item;
    }

    protected function determineProductsAddedDuringRestore()
    {
        $restored_cart = $this->cartGetProducts();
        if ($restored_cart === false) {
            return false;
        }

        // -----
        // Create an associative array containing the uprid/quantity values of the
        // cart's contents **prior to** the restore.  If there was nothing in the cart
        // previously, it'll be set to an empty array.
        //
        $original_cart_quantities = [];
        if ($this->currentCart !== false) {
            foreach ($this->currentCart as $next_item) {
                $original_cart_quantities[$next_item['id']] = $next_item['quantity'];
            }
        }

        // -----
        // Now, traverse the restored cart looking for product additions and
        // product quantities that have changed.
        //
        $items = [];
        $original_cart_uprids = array_keys($original_cart_quantities);
        foreach ($restored_cart as $restored_item) {
            $uprid = $restored_item['id'];

            // -----
            // If the current uprid wasn't in the original cart, then it's been totally
            // added.
            //
            if (!in_array($uprid, $original_cart_uprids)) {
                $items[] = $this->createItemFromCartItem($restored_item);

            // -----
            // Otherwise, if the product's quantity-in-cart has changed, record an add-to-cart
            // event for the quantity difference ... noting that a restoration **never** reduces
            // a product's quantity!
            //
            } elseif ($original_cart_quantities[$uprid] !== $restored_item['quantity']) {
                $added_quantity = $restored_item['quantity'] - $original_cart_quantities[$uprid];
                $restored_item['quantity'] = $added_quantity;
                $items[] = $this->createItemFromCartItem($restored_item);
            }
        }
        return (count($items) === 0) ? false : $items;
    }
}
