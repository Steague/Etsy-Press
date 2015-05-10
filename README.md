#Etsy Press
Tags: etsy, etsy listing, bracket, shortcode, shopping, shop, store, sell
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

##Description

Plugin that allow you to insert Etsy Shop sections in pages or posts using the bracket/shortcode method. This enable Etsy users to share their products through their blog!

##Installation

1. Upload the plugin to the '/wp-content/plugins/' directory;
2. Give read & write access to tmp folder;
3. Activate the plugin through the 'Plugins' menu in WordPress;
4. Get your own Etsy Developer API key (http://www.etsy.com/developers/register);
5. Enter your API key in the Etsy Press Options page;
6. Place '[etsy-press shop_name="*your-etsy-shop-name*" section_id="*your-etsy-shop-setion-id*"]' in your page or post;
7. Viewers will be able to click on your your items.

##Frequently Asked Questions

###How may I find the shop section id?

Here is an example:

URL: http://www.etsy.com/shop/sushipot?section_id=11502395

So, in this example:
sushipot is **etsy-shop-name**
11502395 is **etsy-shop-section-id**

###I got Etsy Shop: empty arguments

See below 'Etsy Shop: missing arguments'.

###I got Etsy Shop: missing arguments

2 arguments are mandatory:

* etsy-shop-name
* etsy-shop-section-id

So, you should have someting like this: **[etsy-press shop_name="Laplume" section_id="10088437"]**

More argument:
* show_available_tag [0 or 1]

###I got Etsy Shop: Your section ID is invalid

Please use a valid section ID, to find your section ID.

###I got Etsy Shop: API reponse should be HTTP 200

Please open a new topic in Forum, with all details.

###I got Etsy Shop: Error on API Request

Please make sure that your API Key is valid.

###How to integrate directly in template?
```
<?php echo do_shortcode( '[etsy-press shop_name="*your-etsy-shop-name*" section_id="*your-etsy-shop-setion-id*"]' ); ?>
```

##Changelog

###0.1
* First release.