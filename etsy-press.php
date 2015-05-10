<?
/**
 * @package Etsy-Press
 */
/*
Plugin Name: Etsy Press
Plugin URI: http://wordpress.org/extend/plugins/etsy-press/
Description: Inserts Etsy products in page or post using bracket/shortcode method.
Author: Sean Teague
Version: 0.1
*/

/*
 * Copyright 2015  Sean Teague  (email : sean.teague@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/* Roadmap to version 1.x
 * TODO: touch() file in tmp folder
 * TODO: reset cache function
 * TODO: edit cache life
 * TODO: allow more than 100 items
 * TODO: customize currency
 * TODO: get Etsy translations
 * TODO: Use Transients API
 * TODO: Add MCE Button
 */

class EtsyPress
{
    const ETSY_PRESS_VERSION    = 0.1;
    const ETSY_PRESS_CACHE_LIFE = 21600; // 6 hours
    const ETSY_PRESS_SHORT_CODE = 'etsy-press';

    const ETSY_PRESS_SHOW_AVAILABLE_TAG = true;
    const ETSY_PRESS_DEFAULT_WIDTH      = "170px";
    const ETSY_PRESS_DEFAULT_HEIGHT     = "135px";
    const ETSY_PRESS_DEFAULT_COLUMNS    = 4;

    //Options
    const ETSY_PRESS_OPTION_API_KEY      = 'etsy_press_api_key';
    const ETSY_PRESS_OPTION_DEBUG_MODE   = "etsy_press_debug_mode";
    const ETSY_PRESS_OPTION_VERSION      = 'etsy_press_version';
    const ETSY_PRESS_OPTION_TIMEOUT      = 'etsy_press_timeout';
    const ETSY_PRESS_OPTION_TARGET_BLANK = 'etsy_press_target_blank';

    private static $init = false;

    function __construct()
    {
        if (get_option(self::ETSY_PRESS_OPTION_API_KEY))
        {
            self::$init = true;
        }

        // plugin activation
        register_activation_hook(__FILE__, array($this,'etsy_press_activate'));

        // add Settings link
        add_filter('plugin_action_links', array($this,'etsy_press_plugin_action_links'), 10, 2);

        add_action('wp_print_styles', array($this,'etsy_press_css'));
        add_action('admin_menu', array($this,'etsy_press_menu'));

        add_shortcode(self::ETSY_PRESS_SHORT_CODE, array($this,'etsy_press_shortcode'));

        if (is_admin())
        {
            $this->etsy_press_warning();
        }
    }

    public function etsy_press_activate()
    {
        // version upgrade
        add_option(self::ETSY_PRESS_OPTION_VERSION, self::ETSY_PRESS_VERSION);

        if (get_option(self::ETSY_PRESS_OPTION_VERSION) != self::ETSY_PRESS_VERSION)
        {
            // initialize timeout option if not already there
            if (!get_option(self::ETSY_PRESS_OPTION_TIMEOUT))
            {
                add_option(self::ETSY_PRESS_OPTION_TIMEOUT, '10');
            }

            // update the version value
            update_option(self::ETSY_PRESS_OPTION_VERSION, self::ETSY_PRESS_VERSION);
        }
    }

    public function etsy_press_plugin_action_links($links, $file)
    {
        if ($file == plugin_basename(dirname(__FILE__) . '/etsy-press.php'))
        {
            $links[] = '<a href="' . admin_url('options-general.php?page=etsy-press.php') . '">' . __('Settings') . '</a>';
        }

        return $links;
    }

    public function etsy_press_shortcode($attrs)
    {
        $attributes = shortcode_atts(array(
            'shop_name'          => null,
            'section_id'         => null,
            'listing_id'         => null,
            'thumb_size'         => null,
            'language'           => null,
            'show_available_tag' => self::ETSY_PRESS_SHOW_AVAILABLE_TAG,
            'width'              => self::ETSY_PRESS_DEFAULT_WIDTH,
            'height'             => self::ETSY_PRESS_DEFAULT_HEIGHT,
            'count'              => self::ETSY_PRESS_DEFAULT_COLUMNS
        ), $attrs);

        return $this->etsy_press_process($attributes);
    }

    protected function parseThumbSize($thumb_size)
    {
        switch ($thumb_size) {
            case ("small"):
                return "url_75x75";
            case ("large"):
                return "url_570xN";
            case ("original"):
                return "url_fullxfull";
            case ("medium"):
            default:
                return "url_170x135";
        }
    }

    protected function etsy_press_process($attributes)
    {
        // Make sure the API key is set in the settings
        if (!self::$init)
        {
            return 'Etsy Press: Shortcode detected but API KEY is not set.';
        }

        extract($attributes);

        // Filter the values
        // $shop_name  = preg_replace('/[^a-zA-Z0-9,]/', '', $shop_name);
        // $section_id = preg_replace('/[^a-zA-Z0-9,]/', '', $section_id);
        // $listing_id = preg_replace('/[^a-zA-Z0-9,]/', '', $listing_id);

        // Make sure at least the shop name and section id are set before continuing
        if ($shop_name == '' ||
            $section_id == '' )
        {
            return "Etsy Press: empty arguments";
        }

        $thumb_size = $this->parseThumbSize($thumb_size);

        // generate listing for shop section
        $listings = $this->get_shop_section_listings($attributes);

        if (get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE))
        {
            print_r('<h2>' . __('Etsy Press Debug Mode', 'etsypress') . '</h2>');
            print_r($listings);

            return;
        }

        if (is_wp_error($listings))
        {
            return $listings->get_error_message();
        }

        $target = (get_option(self::ETSY_PRESS_OPTION_TARGET_BLANK) ? '_blank' : '_self');

        $data = '<table class="etsy-press-listing-table"><tr>';

        $n = 0;
        foreach ($listings->results as $result)
        {
            if (!empty($listing_id) &&
                $result->listing_id != $listing_id)
            {
                continue;
            }

            $listing_html = $this->etsy_press_generate_listing(array(
                "listing_id"         => $result->listing_id,
                "title"              => $result->title,
                "state"              => $result->state,
                "price"              => $result->price,
                "currency_code"      => $result->currency_code,
                "quantity"           => $result->quantity,
                "url"                => $result->url,
                "imgurl"             => $result->Images[0]->$thumb_size,
                "target"             => $target,
                "show_available_tag" => $show_available_tag,
                "count"              => $count,
                "width"              => $width,
                "height"             => $height
            ));

            if ($listing_html !== false)
            {
                $data .= '<td class="etsy-press-listing">' . $listing_html . '</td>';
                
                $n++;
                if ($n % $count == 0 )
                {
                    $data .= '</tr><tr>';
                }
            }
        }
        $data = $data.'</tr></table>';

        return $data;
    }

    protected function etsy_press_generate_listing($data)
    {
        extract($data);

        if (strlen($title) > 18)
        {
            $title = substr($title, 0, 25);
            $title .= "...";
        }

        // if the Shop Item is active
        if ($state != 'active')
        {
            return false;
        }
        
        $state           = ($show_available_tag ? __('Available', 'etsypress') : '&nbsp;');
        $currency_symbol = ($currency_code == 'EUR' ? '&#8364;' : '&#36;');

        ob_start();
        ?>
        <div class="etsy-press-listing-card" id="<?=$listing_id;?>" style="width:<?=$width;?>">
            <a title="<?=$title;?>" href="<?=$url;?>" target="<?=$target;?>" class="etsy-press-listing-thumb">
                <div class="etsy-press-image-cropped" style="background-image:url(<?=$imgurl;?>);height:<?=$height;?>"></div>
            </a>
            <div class="etsy-press-listing-detail">
                <p class="etsy-press-listing-title">
                    <a title="<?=$title;?>" href="<?=$url;?>" target="<?=$target;?>"><?=$title;?></a>
                </p>
                <p class="etsy-press-listing-maker">
                    <a title="<?=$title;?>" href="<?=$url;?>" target="<?=$target;?>"><?=$state;?></a>
                </p>
            </div>
            <p class="etsy-press-listing-price"><?=$currency_symbol . $price;?> <span class="etsy-press-currency-code"><?=$currency_code;?></span></p>
        </div>
        <?
        return ob_get_clean();
    }

    protected function get_shop_section_listings($attributes)
    {
        $tmp_file        = $attributes['shop_name'] . '-' . $attributes['section_id'] . '_cache';
        $etsy_cache_file = dirname(__FILE__) . '/tmp/' . $tmp_file . '.json';

        // if no cache file exist
        if (file_exists($etsy_cache_file) &&
            (time() - filemtime($etsy_cache_file) < self::ETSY_PRESS_CACHE_LIFE) &&
            !get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE))
        {
            return json_decode(file_get_contents($etsy_cache_file));
        }

        $response = $this->etsy_press_api_request("shops/" . $attributes['shop_name'] . "/sections/" . $attributes['section_id'] . "/listings/active", '&limit=100&includes=Images');

        if (is_wp_error($reponse))
        {
            return $response;
        }

        $this->cache_etsy_response($etsy_cache_file, $response);

        if (get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE))
        {
            print_r('<h3>--- Etsy Raw Response ---</h3>');
            print_r($reponse);

            $file_content = file_get_contents($etsy_cache_file);
            print_r('<h3>--- Etsy Cache File:' . $etsy_cache_file . ' ---</h3>');
            print_r($file_content);
        }

        return json_decode($response);
    }

    protected function cache_etsy_response($etsy_cache_file, $response)
    {
        $tmp_file = $etsy_cache_file . rand() . '.tmp';
        file_put_contents($tmp_file, $response);
        rename($tmp_file, $etsy_cache_file);

        return;
    }

    protected function etsy_press_api_request($etsy_request, $args = NULL, $noDebug = NULL)
    {
        $url = "https://openapi.etsy.com/v2/$etsy_request?api_key=" . get_option(self::ETSY_PRESS_OPTION_API_KEY) . $args;
        
        $request = wp_remote_request($url, array(
            'timeout' => get_option(self::ETSY_PRESS_OPTION_TIMEOUT)
        ));

        if (get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE) &&
            !$noDebug)
        {
            ?>
            <h3>--- Etsy Debug Mode - version <?=self::ETSY_PRESS_VERSION;?> ---</h3>
            <p>Go to Etsy Press Options page if you wan't to disable debug output.</p>
            <h3>--- Etsy Request URL ---</h3>
            <?=$url;?>
            <h3>--- Etsy Response ---</h3>
            <? print_r($request); ?>
            <?
        }

        if (is_wp_error($request))
        {
            return  new WP_Error('etsy-press', __('Etsy Press: Error on API Request', 'etsypress'));
        }

        if ($request['response']['code'] != 200)
        {
            switch (true)
            {
                case ($request['headers']['x-error-detail'] ==  'Not all requested shop sections exist.'):
                    return  new WP_Error('etsy-press', __('Etsy Press: Your section ID is invalid.', 'etsypress'));
                    break;
                case ($request['response']['code'] == 0):
                    return  new WP_Error('etsy-press', __('Etsy Press: The plugin timed out waiting for etsy.com reponse. Please change Time out value in the Etsy Press Options page.', 'etsypress'));
                    break;
                default:
                    return  new WP_Error('etsy-press', __('Etsy Press: API reponse should be HTTP 200 <br />API Error Description:', 'etsypress' ) . ' ' . $request['headers']['x-error-detail']);
                    break;
            }
        }

        return $request['body'];
    }

    public function etsy_press_menu()
    {
        add_options_page(__('Etsy Press Options', 'etsypress'), __('Etsy Press', 'etsypress'), 'manage_options', basename(__FILE__), array($this,'etsy_press_options_page'));
    }

    public function etsy_press_options_page()
    {
        // did the user is allowed?
        if (!current_user_can('manage_options'))
        {
            wp_die(__('You do not have sufficient permissions to access this page.', 'etsypress'));
        }

        if (isset($_POST['submit']))
        {
            $updated = true;

            // did the user enter an API Key?
            if (isset($_POST['etsy_press_api_key']))
            {
                $etsy_press_api_key = wp_filter_nohtml_kses(preg_replace('/[^a-z0-9]/', '', $_POST['etsy_press_api_key']));
                update_option(self::ETSY_PRESS_OPTION_API_KEY, $etsy_press_api_key);
            }

            $etsy_press_debug_mode = 0;
            // did the user enter Debug mode?
            if (isset($_POST[self::ETSY_PRESS_OPTION_DEBUG_MODE]))
            {
                $etsy_press_debug_mode = wp_filter_nohtml_kses($_POST[self::ETSY_PRESS_OPTION_DEBUG_MODE]);
            }
            update_option(self::ETSY_PRESS_OPTION_DEBUG_MODE, $etsy_press_debug_mode);

            // did the user enter target new window for links?
            $etsy_press_target_blank = 0;
            if (isset($_POST['etsy_press_target_blank']))
            {
                $etsy_press_target_blank = wp_filter_nohtml_kses($_POST['etsy_press_target_blank']);
            }
            update_option(self::ETSY_PRESS_OPTION_TARGET_BLANK, $etsy_press_target_blank);

            // did the user enter an Timeout?
            if (isset($_POST['etsy_press_timeout']))
            {
                $etsy_press_timeout = wp_filter_nohtml_kses(preg_replace('/[^0-9]/', '', $_POST['etsy_press_timeout']));
                update_option(self::ETSY_PRESS_OPTION_TIMEOUT, $etsy_press_timeout );
            }
        }
        
        // delete cache file
        if (isset($_GET['delete']))
        {
            // did a file was choosed?
            if (isset($_GET['file']))
            {
                $tmp_directory = plugin_dir_path(__FILE__) . 'tmp/';
                
                // REGEX for security!
                $filename = str_replace('.json', '', $_GET['file']);
                $filename = preg_replace('/[^a-zA-Z0-9-_]/', '', $filename);

                $fullpath_filename = $tmp_directory . $filename . '.json';
                $deletion = false;
                if (file_exists($fullpath_filename))
                {
                    $deletion = unlink($fullpath_filename);
                }
                
                if ($deletion)
                {
                    // and remember to note deletion to user
                    $deleted = true;
                    $deleted_file = $fullpath_filename;
                }
            }
        }

        // grab the Etsy API key
        if (!get_option(self::ETSY_PRESS_OPTION_API_KEY))
        {
            add_option(self::ETSY_PRESS_OPTION_API_KEY, '');
        }

        // grab the Etsy Debug Mode
        if (!get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE))
        {
            add_option(self::ETSY_PRESS_OPTION_DEBUG_MODE, '0');
        }

        // grab the Etsy Target for links
        if (!get_option(self::ETSY_PRESS_OPTION_TARGET_BLANK))
        {
            add_option(self::ETSY_PRESS_OPTION_TARGET_BLANK, '0' );
        }

        // grab the Etsy Tiomeout
        if (!get_option(self::ETSY_PRESS_OPTION_TIMEOUT))
        {
            add_option(self::ETSY_PRESS_OPTION_TIMEOUT, '10');
        }

        if ($updated)
        {
            echo '<div class="updated fade"><p><strong>'. __('Options saved.', 'etsypress') . '</strong></p></div>';
        }
        
        if ($deleted)
        {
            echo '<div class="updated fade"><p><strong>'. __('Cache file deleted:', 'etsypress') . ' ' . $deleted_file . '</strong></p></div>';
        }

        // print the Options Page
        ?>
        <div class="wrap">
            <div id="icon-options-general" class="icon32"><br /></div><h2><? _e('Etsy Press Options', 'etsypress'); ?></h2>
            <form name="etsy_press_options_form" method="post" action="<?=$_SERVER['REQUEST_URI'];?>">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="etsy_press_api_key"></label><? _e('Etsy API Key', 'etsypress'); ?>
                        </th>
                        <td>
                            <input id="etsy_press_api_key" name="etsy_press_api_key" type="text" size="25" value="<?=get_option(self::ETSY_PRESS_OPTION_API_KEY);?>" class="regular-text code" />
                            <? if (!is_wp_error($this->etsy_press_test_API_key())): ?>
                                <span id="etsy_press_api_key_status" style="color:green;font-weight:bold;"><? _e('Your API Key is valid', 'etsypress'); ?></span>
                            <? elseif (get_option(self::ETSY_PRESS_OPTION_API_KEY)): ?>
                                <span id="etsy_press_api_key_status" style="color:red;font-weight:bold;"><? _e('You API Key is invalid', 'etsypress'); ?></span>
                            <? endif; ?>
                            <p class="description">
                                <?=sprintf(__('You may get an Etsy API Key by <a href="%1$s">Creating a new Etsy App</a>', 'etsypress'), 'http://www.etsy.com/developers/register');?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label for="etsy_press_debug_mode"></label><? _e('Debug Mode', 'etsypress'); ?>
                        </th>
                        <td>
                            <input id="etsy_press_debug_mode" name="etsy_press_debug_mode" type="checkbox" value="1" <? checked('1', get_option(self::ETSY_PRESS_OPTION_DEBUG_MODE)); ?> />
                            <p class="description">
                                <?=__('Useful if you want to post a bug on the forum', 'etsypress'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                         <th scope="row">
                             <label for="etsy_press_target_blank"></label><? _e('Link to new window', 'etsypress'); ?>
                         </th>
                         <td>
                            <input id="etsy_press_target_blank" name="etsy_press_target_blank" type="checkbox" value="1" <? checked('1', get_option(self::ETSY_PRESS_OPTION_TARGET_BLANK)); ?> />
                            <p class="description">
                                <?=__('If you want your links to open a page in a new window', 'etsypress');?>
                            </p>
                         </td>
                    </tr>
                    <tr valign="top">
                         <th scope="row">
                             <label for="etsy_press_timeout"></label><? _e('Timeout', 'etsypress'); ?>
                         </th>
                         <td>
                             <input id="etsy_press_timeout" name="etsy_press_timeout" type="text" size="2" class="small-text" value="<?=get_option(self::ETSY_PRESS_OPTION_TIMEOUT); ?>" class="regular-text code" />
                            <p class="description">
                                <?=__('Time in seconds until a request times out. Default 10.', 'etsypress'); ?>
                            </p>
                         </td>
                    </tr>
                    <tr valign="top">
                         <th scope="row">
                             <label for="etsy_press_cache_life"></label><? _e('Cache life', 'etsypress'); ?>
                         </th>
                         <td>
                            <? _e('6 hours.', 'etsypress'); ?>
                            <p class="description">
                                <?=__('Time before the cache update the listing', 'etsypress');?>
                            </p>
                         </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><? _e('Cache Status', 'etsypress'); ?></th>
                        <td>
                            <? if (get_option(self::ETSY_PRESS_OPTION_API_KEY)): ?>
                                <table class="wp-list-table widefat">
                                    <thead id="EtsyPressCacheTableHead">
                                        <tr>
                                            <th><? _e('Shop Section', 'etsypress'); ?></th>
                                            <th><? _e('Filename', 'etsypress'); ?></th>
                                            <th><? _e('Last update', 'etsypress'); ?></th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <?
                                    $files = glob(dirname(__FILE__).'/tmp/*.json');
                                    $time_zone = get_option('timezone_string');
                                    date_default_timezone_set($time_zone);
                                    foreach ($files as $file)
                                    {
                                        $etsy_press_section = explode("-", substr(basename($file), 0, strpos(basename($file), '_cache.json')));
                                        $etsy_press_section_info = $this->etsy_press_get_shop_section($etsy_press_section[0], $etsy_press_section[1]);
                                        if (!is_wp_error($etsy_press_section_info))
                                        {
                                            ?>
                                            <tr>
                                                <td><?=$etsy_press_section[0];?> / <?=$etsy_press_section_info->results[0]->title;?></td>
                                                <td><?=basename($file);?></td>
                                                <td><?=date("Y-m-d H:i:s", filemtime($file));?></td>
                                                <td>
                                                    <a href="options-general.php?page=etsy-press.php&delete&file=<?=basename($file);?>" title="<?=__('Delete cache file', 'etsypress');?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?
                                        }
                                        else
                                        {
                                           ?>
                                           <tr>
                                                <td><?=$etsy_press_section[0];?> / <span style="color:red;">Error on API Request</span></td>
                                                <td><?=basename($file);?></td>
                                                <td><?=date("Y-m-d H:i:s", filemtime($file));?></td>
                                                <td></td>
                                            </tr>
                                            <?
                                        }
                                    }
                                    ?>
                                </table>
                            <? else: ?>
                                <? _e('You must enter your Etsy API Key to view cache status!', 'etsypress'); ?>
                            <? endif; ?>
                            <p class="description">
                                <? _e('You may reset cache a any time by deleting files in tmp folder of the plugin.', 'etsypress'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3 class="title"><? _e('Need help?', 'etsypress'); ?></h3>
                <p>
                    <?=sprintf(__('Please open a <a href="%1$s">new topic</a> on Wordpress.org Forum. This is your only way to let me know!', 'etsypress'), 'http://wordpress.org/support/plugin/etsy-press' );?>
                </p>

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button-primary" value="<? _e('Save Changes', 'etsypress'); ?>" />
                </p>

            </form>
        </div>
        <?
    }

    protected function etsy_press_test_API_key()
    {
        $reponse = $this->etsy_press_api_request('listings/active', '&limit=1&offset=0', 1);
        if (is_wp_error($reponse))
        {
            return $reponse;
        }

        return json_decode($reponse);
    }

    protected function etsy_press_get_shop_section($etsy_press_id, $etsy_section_id)
    {
        $reponse = $this->etsy_press_api_request("shops/" . $etsy_press_id . "/sections/" . $etsy_section_id, NULL);

        if (is_wp_error($reponse))
        {
            return $reponse;
        }

        return json_decode($reponse);
    }

    protected function etsy_press_warning()
    {
        if (!get_option(self::ETSY_PRESS_OPTION_API_KEY))
        {
            function etsy_press_api_key_warning()
            {
                ?>
                <div id='etsy-press-warning' class='updated fade'>
                    <p>
                        <strong><?=__('Etsy Press is almost ready.', 'etsypress');?></strong> <?=sprintf(__('You must <a href="%1$s">enter your Etsy API key</a> for it to work.', 'etsypress'), 'options-general.php?page=etsy-press.php');?>
                    </p>
                </div>
                <?
            }

            add_action('admin_notices', 'etsy_press_api_key_warning');
        }
    }

    public function etsy_press_css()
    {
        $link = plugins_url('etsy-press.css', __FILE__);
        wp_register_style('etsy_press_style', $link);
        wp_enqueue_style('etsy_press_style');
    }
}

new EtsyPress;
