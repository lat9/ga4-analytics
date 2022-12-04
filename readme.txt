Google Enhanced E-Commerce Analytics (EC Analytics)
tested with Zen Cart 1.5.5 and 1.5.4


IMPORTANT NOTICE:
----------------------
This plugin is a standalone solution for Google Analytics meant to replace any other Zen Cart plugins you might use right now.
Do NOT use any other Google Analytics plugins (like "Simple Google Analytics or Easy Google Analytics") together with this plugin
Make sure that you uninstall any other Google Analytics plugins before you install this plugin.
Make sure that you did NOT add some Google tracking scripts anywhere manually in your Zen Cart files.
Make sure that you do NOT add additional Adwords conversion tracking codes/scripts anywhere in your store
If you are using Adwords campaigns make sure that your Google Analytics account is linked with your Google Adwords account


Purpose & Aim
----------------------

This module utilises the latest (as of May 2015) Google Enhanced Analytics Ecommerce API.
It records all of your store sales data for viewing from your Google Analytics Account (which must be created before installing this module)
The sales data is separated into product revenue, taxes and postage.
The product category is also recorded (up to 5 levels)
It will record the 1st product variant name (if available)
Records the checkout steps taken by the customers (and dropoffs for each step)
As well as this, the module records the normal 'page views', as well as other selected 'events', such as add/delete from cart. 
In fact anything that can be processed by the zen-cart NOTIFIERS can be recorded and sent to Google simply by adding the notifier to the array in the class file.
Easy to install.
Doesn't overwrite or modify any existing files.
All that is needed for full functionality is your Google Analytics TrackingID "UA-XXXXXX.X" (which is why you need a Google Analytics Account before installing this module). 
If you already have a tracking ID you don't need to create a new one. 


Credits
----------------------
All credits to RodG for creating and publishing this plugin in the Zen Cart forums


Version history
----------------------

1.2.2 
2017-12-12 DrByte 
Updated code for PHP7 compatibility

1.2.1
2017-12-11 webchills
Fix typo preventing the brand name to be recorded see:
https://www.zen-cart.com/showthread.php?217086-Google-Ecommerce-Tracking&p=1339193#post1339193

1.2.0
2017-05-18 webchills
Fixes suggested in the following thread integrated:
https://www.zen-cart.com/showthread.php?217086-Google-Ecommerce-Tracking/
Some unneccessary code removed
readme updated

1.0.1
2015-05-13 RodG
initial release


GitHub
----------------------

Please report any issues you may find at GitHub here:
https://github.com/webchills/zencart-ec-analytics



Installation / Upgrade 
----------------------
1) Unzip this archive 
2) Edit the file /includes/extra_datafiles/ec_analytics.php to include your UA-XXXXXXXX-X userID (from Google Analytics Website) 
3) Rename the folder '/includes/templates/YOUR_TEMPLATE' so that it matches the name of your template/theme 
4) Copy all four files from the /includes/ folder to your store keeping the same folder/directory structure as per the extracted files

Configuration
-------------
If you haven't already done so, you should then log into the Analytics website
Navigate to admin->view->Ecommerce Settings 
Tick the boxes to 'Enable Ecommerce' 
Set the 'Enable Enhanced Ecommerce Reporting' to 'On'

Optionally set up the 'Checkout Labelling' Funnel Steps. 

By default (at time of writing this), the module reports 
1) Start Login 
2) Login Success
3) Start Shipping (Std install) or Checkout Begin (FEC/COWOA)
4) Start Payment
5) Checkout Confirmation

There is also a 'Checkout Success' being reported, but this is NOT logged as being a 'Checkout Step' as it is 
recorded/reported as being a 'completed transaction' in the checkout steps report page instead.

NOTE: Not all checkout steps are recorded at all times.  This is because not everyone follows the same checkout procedure(s),
and the steps are different than the zencart defaults if you happen to be using The FEC or COWOA (or other checkout) mods

Should you wish to add/remove any checkout steps for your particular site this is easily achieved by editing the file
/includes/classes/observers/class.ec_analytics.php.   The checkout steps are clearly identified.
 
Should you wish to track other zen-cart 'NOTIFY' events, this is easily achieved by editing the file
/includes/classes/observers/class.ec_analytics.php.
You may add any valid NOTIFIER to the array at the beginning of the file.  No other changes needed.

Tip#1: As none of my customers use affliation sales (and ZenCart, by default isn't set up for it) I have opted to populate this data 
field (affiliation) with the method of payment instead 

Tip#2. The customerID (from the store) is recorded in Google analytics as 'Dimension1'. You can give this a more meaningful name from the 
GA admin page - > Property - > Custom Definitions -> Custom Dimensions. (I call mine simple 'CustomerID') 

IMPORTANT. Before accumulating too much data you should carefully consider the checkout steps recordings and make changes if needed. 
The data associated with these steps are stored by Google by step number (not name), so if you add/remove/re-order this 'funnel' the 
existing data recordings remain unchanged, so if (for example) you remove the 'Start Login' (step#1), the 'Login Success' will become the new step# 
and all previously stored data for 'Start Login' will now appear in the reports as 'Login Success'. If you add a step before an existing step, 
all of the 'Checkout Confirmation' recordings will 'dissapear' as this will now be the 6th 'step' that has no previously recorded data.
Google analytics doesn't provide any means of modifying existing data (other than deleting the site and re-adding it). 