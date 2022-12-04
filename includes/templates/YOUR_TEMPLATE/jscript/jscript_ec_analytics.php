<?php 
/**
 * @package Google Enhanced E-Commerce Analytics
 * @copyright (c) 2015 RodG
 * @copyright Copyright 2003-2017 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart-pro.at/license/2_0.txt GNU Public License V2.0
 * @version $Id: jscript_ec_analytics.php 2017-12-22 20:47:36Z DrByte $
 */

if ((!defined('GOOGLE_UA') || GOOGLE_UA === "UA-XXXXXXXX-X")) { 
        echo '<script>alert("The Google Analytics trackingID is not yet defined in\n /includes/extra_datafiles/ec_analytics.php")</script>' ;    
    
  } else { $trackingID = GOOGLE_UA ; }
?>
<script>
    (function (i, s, o, g, r, a, m) {
        i['GoogleAnalyticsObject'] = r;
        i[r] = i[r] || function () {
            (i[r].q = i[r].q || []).push(arguments);
        }, i[r].l = 1 * new Date();
        a = s.createElement(o),
                m = s.getElementsByTagName(o)[0];
        a.async = 1;
        a.src = g;
        m.parentNode.insertBefore(a, m);
    })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

<?php
//global $analytics, $cID;
$cID = (isset($_SESSION['customer_id'])) ? "customerID#".$_SESSION['customer_id']:"guest";
echo ($cID === "guest") ? "ga('create', '".$trackingID."', 'auto') ;\n":"ga('create', '".$trackingID."', {'userId':'{$cID}'});\n";   
echo "ga('require', 'ec');\n"; 
echo "ga('require', 'displayfeatures');\n";
if (!isset($_SESSION['analytics'])) {  echo "ga('send', 'pageview');\n";} 

else {      
function _output($step) { 
    global $analytics, $cID; 
                echo $analytics['addProductItemsStr'] ;   
                echo "ga('ec:setAction','checkout', { 'step': '{$step}' , 'list': 'View Info' , 'option': '{$analytics['action']}' });\n";  
                echo "ga('send', 'pageview');\n";
                             
                echo "ga('send', 'event', '{$analytics['action']}' , '{$analytics['action']}' , '{$cID}');\n";    
}
        
 $analytics = $_SESSION['analytics'];  
    switch ($analytics['action']) {
        case "View Product" :
            if (isset($analytics['item']['productID'])) {
                echo "ga('ec:addImpression', {'id': '{$analytics['item']['productID']}' , 'name': '{$analytics['item']['productName']}' , 'category': '{$analytics['item']['category']}' , 'brand': '{$analytics['item']['brand']}' , 'list': 'View Info' , 'dimension1': '{$cID}'} ); \n";
                echo "ga('ec:setAction', 'detail');\n";
                echo "ga('send', 'pageview');\n";
                echo "ga('send', 'event', '{$analytics['action']}', '{$analytics['action']}' , '{$analytics['item']['productName']}' );\n";
            }
            break;

        case "Add to Cart" :
        case "Delete from Cart":
            if (isset($analytics['item']['productID'])) {
                $action = ($analytics['action'] == "Add to Cart") ? "add" : "remove";
                echo "ga('ec:addProduct', {'id': '{$analytics['item']['productID']}' , 'name': '{$analytics['item']['productName']}', 'quantity': '{$analytics['item']['productQTY']}' , 'category': '{$analytics['item']['category']}' , 'brand': '{$analytics['item']['brand']}', 'variant': '{$analytics['item']['variant']}' , 'dimension1': '{$cID}'} );\n";
                echo "ga('ec:setAction', '{$action}' , { 'list':'View Info' } );\n";
                echo "ga('send', 'pageview');\n";
                echo "ga('send', 'event', '{$analytics['action']}' ,'{$analytics['item']['productName']}' , '{$cID}');\n";
                }
            break;
            
//  Checkout steps
        case "Start Login":                         _output(1) ; break;
        case "Login Success": 
        case "Login Success via Create Account": 
        case "Login Success via No account":        _output(2) ; break; 
            case "Checkout Process begin":              _output(3) ; break; // Only used with the FEC/COWOA checkout mod.                        
            case "Checkout Shipping":                   _output(3) ; break; // Only used with the Standard checkout. 
        case "Checkout Payment":                    _output(4) ; break;  
        case "Checkout Confirmation":               _output(5) ; break;  
            
        case "Checkout Success":  // Record as a Transaction 
            if ((is_array($analytics['transaction'])) && $analytics['transaction']['id'] != "") {
                echo "ga('send', 'event', '{$analytics['action']}' , 'Order#{$analytics['transaction']['id']}','{$cID}' );\n"; //  simple event //
                echo "ga('ec:setAction', 'purchase', { 'id': '{$analytics['transaction']['id']}','affiliation': '{$analytics['transaction']['affiliation']}', 'revenue': '{$analytics['transaction']['revenue']}', 'tax': '{$analytics['transaction']['tax']}','shipping': '{$analytics['transaction']['shipping']}','coupon': '{$analytics['transaction']['coupon']}', 'dimension1': '{$cID}' });\n";
                echo $analytics['addProductItemsStr'] ;                    
                echo "ga('send', 'pageview');\n";  
        }
        break;
        default: echo "ga('send', 'event', '{$analytics['action']}', '{$analytics['action']}' , '{$cID}');\n";//  simple event
    }
unset($_SESSION['analytics']);
}
?>
</script>