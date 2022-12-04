<?php 
/**
 * @package Google Enhanced E-Commerce Analytics
 * @copyright (c) 2015 RodG
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart-pro.at/license/2_0.txt GNU Public License V2.0
 * @version $Id: class.ec_analytics.php 2017-12-11  DrByte $
 */
class ec_analytics extends base {

    function __construct() {
        global $zco_notifier;
        $zco_notifier->attach($this, array('NOTIFY_HEADER_TIMEOUT'));

        $this->attach($this, array(
            // actionable events //
            'NOTIFY_HEADER_START_PRODUCT_INFO',
            'NOTIFIER_CART_ADD_CART_END', 
            'NOTIFIER_CART_REMOVE_END',
            'NOTIFY_HEADER_START_LOGIN',
            'NOTIFY_LOGIN_SUCCESS',
            'NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT',
            'NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT',
            'NOTIFY_CHECKOUT_PROCESS_BEGIN',
            'NOTIFY_HEADER_START_CHECKOUT_SHIPPING',
            'NOTIFY_HEADER_START_CHECKOUT_PAYMENT',
            'NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION',
            'NOTIFY_HEADER_START_CHECKOUT_SUCCESS',
            //  Generic events // 
            'NOTIFY_LOGIN_FAILURE',  
            'NOTIFY_HEADER_START_LOGIN_TIMEOUT' .     
            'NOTIFY_FAILURE_DURING_CREATE_ACCOUNT',
            'NOTIFY_FAILURE_DURING_NO_ACCOUNT',
            'NOTIFY_PAYMENT_PAYPAL_RETURN_TO_STORE',
            'NOTIFY_PAYMENT_PAYPAL_CANCELLED_DURING_CHECKOUT'
        ));
    }

    function getID() {
        $id = '';
        if (isset($_REQUEST['products_id'])) {
            if (is_array($_REQUEST['products_id'])) {  $id = explode(":", $_REQUEST['products_id'][0]);
            } else {  $id = explode(":", $_REQUEST['products_id']);  }
        } else {
            if (isset($_REQUEST['product_id'])) {
                if (is_array($_REQUEST['product_id'])) { $id = explode(":", $_REQUEST['product_id'][0]);
                } else {  $id = explode(":", $_REQUEST['product_id']);  }
            }
        }
        $id = (int)$id[0];
        if ($id === 0) $id = "";
   return $id;
    }

    
 function getCatString($id) {
  global $db, $cPath ;
  $masterCat = zen_get_categories_name_from_product($id) ;
  $catTxt = '';
     $i = 0 ; $flag = 0 ;
        if(isset($cPath)) {
            $p = explode('_',$cPath) ;
            while ($i < count($p)) {
                $the_categories_name= $db->Execute("select categories_name from " . TABLE_CATEGORIES_DESCRIPTION . " where categories_id= '" . $p[$i] . "' and language_id= '" . $_SESSION['languages_id'] . "'");
                if ($masterCat ==  $the_categories_name->fields['categories_name'])  {$flag = 1 ;}
                     
            $i++ ;        
            } 
        $catTxt = substr($catTxt, 1);
        }     
  return ($flag != 1 ) ? $masterCat:$catTxt;      
// return $catTxt ;    
 }   
    
 function addProductItemsStr() { 
    $itemsStr = "" ;  $i=0 ; 
       $products = $_SESSION['cart']->get_products(); 
         if(is_array($products)) { 
                   foreach ($products as $item) { 
                     if(is_array($item['attributes']))  $varTxt = zen_values_name($item['attributes'][1]);                     
                     if(!$varTxt) $varTxt = "n/a"; 
                     
                     $itemID = explode(":", $item['id'] ) ;

                     $brand = zen_get_products_manufacturers_name($itemID[0]) ; 
                         $brandTxt = ($brand != "") ? $brand:"n/a";
                        $itemsStr .= "ga('ec:addProduct',"
                                            . " {'id': '{$itemID[0]}',"
                                            . " 'name': '".addslashes($item['name'])."',"
                                            . " 'brand': '{$brandTxt}',"
                                            . " 'category': '". zen_get_categories_name_from_product($itemID[0])."' ,"
                                            . " 'variant': '{$varTxt}',"
                                            .  " 'price': '".number_format((float)($item['price'] + ($item['price'] *  $item['tax_class_id'] / 100 )) ,2,'.','')."',"
                                            . " 'quantity': '{$item['quantity']}',"
                                            . " 'position': '{$i}' } );\n";
                $i++ ;  
              }
            }    
  return $itemsStr ;           
}
  /////////////////////////////          
    function update(&$callingClass, $notifier, $paramsArray) {
      global $db, $analytics;

        switch ($notifier) {
            case 'NOTIFY_HEADER_START_PRODUCT_INFO' :
                $id = $this->getID();
                if ($id) {
                    $brand = zen_get_products_manufacturers_name($id) ;  
                     $brandTxt = ($brand != "") ? $brand:"n/a";
                    $res = $db->Execute("SELECT products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id = $id");
                    $analytics['item'] = array('productID' => $id, 'productName' => addslashes($res->fields['products_name']), 'category' => $this->getCatString($id), 'brand' => $brandTxt);
                    $analytics['action'] = "View Product";
                }
                break;

                
            case 'NOTIFIER_CART_ADD_CART_END' :
            case 'NOTIFIER_CART_REMOVE_END' :
                 $analytics['action'] = "Delete from Cart";
                 $varTxt = "n/a";  $qty = '';
                if ($notifier == 'NOTIFIER_CART_ADD_CART_END') {
                    $analytics['action'] = "Add to Cart";
                    if (isset($_REQUEST['cart_quantity'])) $qty = (int)$_REQUEST['cart_quantity'];  // @TODO does this need to handle decimals?
                         if (!$qty)  $qty = 1;                     
                        $products = $_SESSION['cart']->get_products(); 
                            if(is_array($products)) { 
                                foreach ($products as $item) { 
                                    if(is_array($item['attributes'])) {
                                        $itemID = explode(":", $item['id'] ) ;
                                        $requestID = explode(":", (string)$_REQUEST['products_id'] ) ;
                                        if ($requestID[0] === $itemID[0]) {
                                            $varTxt = zen_values_name($item['attributes'][1]);
                                        }      
                                    }
                                }
                            }  
                } 
                $id = $this->getID();
                if ($id) {
                $brand = zen_get_products_manufacturers_name($id) ;  
                $brandTxt = ($brand != "") ? $brand:"n/a"; 

                $res = $db->Execute("SELECT products_name FROM " . TABLE_PRODUCTS_DESCRIPTION . " WHERE products_id = $id");
                $analytics['item'] = array('productID' => $id, 'productName' => $res->fields['products_name'], 'category' => $this->getCatString($id), 'brand' => $brandTxt, 'productQTY' => $qty, 'variant' => $varTxt);
                }
                break;

  /////////////   Checkout steps ////////////////////////////
            case 'NOTIFY_HEADER_START_LOGIN':         //  Checkout Step 1
                $analytics['action'] = "Start Login";
                $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                break;
       
            case 'NOTIFY_LOGIN_SUCCESS'://  Checkout Step 2
                $analytics['action'] = "Login Success";
                 $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                 break;
             
            case 'NOTIFY_LOGIN_SUCCESS_VIA_CREATE_ACCOUNT'://  Could also be Checkout step 2
                $analytics['action'] = "Login Success via Create Account";
                 $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                 break;
             
            case 'NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT'://   Could also be Checkout Step 2
                $analytics['action'] = "Login Success via No account";
                 $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                break;
          
            case 'NOTIFY_CHECKOUT_PROCESS_BEGIN': //  Checkout Step 3
                $analytics['action'] = "Checkout Process begin";
                  $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                break;       

            case 'NOTIFY_HEADER_START_CHECKOUT_SHIPPING'://  Checkout Step 4
                $analytics['action'] = "Checkout Shipping";
                 $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                 break;
            
            case 'NOTIFY_HEADER_START_CHECKOUT_PAYMENT'://   Checkout Step 5
                $analytics['action'] = "Checkout Payment";
                  $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                break;
            
            case 'NOTIFY_HEADER_START_CHECKOUT_CONFIRMATION'://   Checkout Step 6
                $analytics['action'] = "Checkout Confirmation";
                 $analytics['addProductItemsStr'] = $this->addProductItemsStr() ;
                 break;
/////////////////////////////////////////////////////////
             
            case 'NOTIFY_HEADER_START_CHECKOUT_SUCCESS'://  All Checkout complete/successful 
                $order_summary = $_SESSION['order_summary'];
                    if ($order_summary['order_number']) {
               
                  // add other cases as you wish for your other payment modules       
                     switch ((string) $order_summary['payment_module_code']) {
                                case "paypalwpp":   $affiliation = "PayPal Express" ; break ;
                               
                                default : $affiliation = (string) $order_summary['payment_module_code'] ;
                     }
    
                
                
                $coupon = isset($order_summary['coupon_code']) ? $order_summary['coupon_code'] : "n/a";
                $analytics['transaction'] = array('id' => (string) $order_summary['order_number'],
                                                  'affiliation' => $affiliation,
                                                  'revenue'  => number_format($order_summary['order_total'],2,'.',''),
                                                  'shipping'  => number_format($order_summary['shipping'],2,'.',''),
                                                  'tax' =>  number_format($order_summary['tax'],2,'.',''),
                                                  'coupon' =>  $coupon,
                                                );
                $items_query = "SELECT DISTINCT orders_products_id, products_id, products_name, products_model, final_price, products_tax, products_quantity
                     FROM " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = :ordersID ORDER BY products_name";

                $items_query = $db->bindVars($items_query, ':ordersID', $order_summary['order_number'], 'integer');
                $items_in_cart = $db->Execute($items_query);
                $i = 0 ; $analytics['addProductItemsStr']= "" ;
                while (!$items_in_cart->EOF) {
                    $variant = $db->Execute("SELECT products_options_values FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " WHERE orders_products_id = " . (string)$items_in_cart->fields['orders_products_id']);
                        $varTxt = ($variant->fields['products_options_values'] != "") ? $variant->fields['products_options_values']:"n/a";
                    $brand = zen_get_products_manufacturers_name($items_in_cart->fields['products_id']); 
                         $brandTxt = ($brand != "") ? $brand:"n/a";
                    
                    $analytics['addProductItemsStr'] .= "ga('ec:addProduct',"
                                            . " {   'id': '{$items_in_cart->fields['products_id']}',"
                                                . " 'name': '".addslashes($items_in_cart->fields['products_name'])."',"
                                                . " 'brand': '{$brandTxt}',"
                                                . " 'category': '".zen_get_categories_name_from_product($items_in_cart->fields['products_id'])."',"
                                                . " 'variant': '{$varTxt}',"
                                                . " 'price':  '".number_format($items_in_cart->fields['final_price'] +  ($items_in_cart->fields['final_price'] *  $items_in_cart->fields['products_tax'] / 100 ),2,'.','')."',"
                                                . " 'quantity': '{$items_in_cart->fields['products_quantity']}',"
                                                . " 'coupon': '{$coupon}',"
                                                . " 'position': '{$i}' } );\n";
                $i++ ;  
                 $items_in_cart->MoveNext();
                }
                $analytics['action'] = "Checkout Success";
            }
                break;


            default:   
                $notifyArr = explode("_", $notifier, 2);
                $analytics['action'] = ucwords(strtolower(str_replace("_", " ", $notifyArr[1])));
}
$_SESSION['analytics'] = $analytics;
    }
}