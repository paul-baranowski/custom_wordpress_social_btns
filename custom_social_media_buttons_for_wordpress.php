<?php

//Get current URL of the page
function curPageURL() {
 $pageURL = 'http';
 if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

//custom Facebook share code
function customFShare() {
    $like_results = file_get_contents('http://graph.facebook.com/'. );
    $like_array = json_decode($like_results, true);
    $like_count =  $like_array['shares'];
    return ($like_count ) ? $like_count : "0";
}

function customFShareShortcode( $tr, $content = null ) {
    extract(shortcode_tr(array(
       'type' => ''
    ), $tr));
    return '<a href="#" class="social_button facebook_button" onclick="popUp=window.open(\'http://www.facebook.com/sharer/sharer.php?u='.curPageURL().'\', \'popupwindow\', \'scrollbars=yes,width=800,height=400\');popUp.focus();return false"> 
                <span class="social_count facebook_count">'. customFShare() .'</span>
            </a>';
}
add_shortcode('customshare', 'customFShareShortcode');


/*------Bit.ly shortening for twitter button-----*/

// convert file contents into string
function urlopen($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    } else {
        return file_get_contents($url);
    }
}
// bit.ly url shortening
function shorten_bitly($url, $bitly_key, $bitly_login) {
    if ($bitly_key && $bitly_login && function_exists('json_decode')) {
        $bitly_params = '?login=' . $bitly_login . '&apiKey=' .$bitly_key . '&longUrl=' . urlencode($url);
        $bitly_response = urlopen('http://api.j.mp/v3/shorten' . $bitly_params);
        if ($bitly_response) {
            $bitly_data = json_decode($bitly_response, true);
            if (isset($bitly_data['data']['url'])) {
                $bitly_url = $bitly_data['data']['url'];
            }
        }
    }
    return $bitly_url;
}

/*---------------End of bit.ly shortening-------------*/


//custom Twitter share code
function tweet_button($url) {

    // Your bit.ly API credentials
    $bitly_login = "";
    $bitly_key = "";
    
    // Your twitter account names
    $twitter_via = "";
    
    // Count display
    $count_display = 2;

    global $post;
    $cache_interval = 60;
    $refresh_interval = 3660;
    $retweet_count = null;
    $count = 0;
    
    if (get_post_status($post->ID) == 'publish') {
        $title = $post->post_title;
        
        if ((function_exists('curl_init') || function_exists('file_get_contents')) && function_exists('json_decode')) {
            // shorten url
            if (get_post_meta($post->ID, 'bitly_short_url', true) == '') {
                $short_url = null;
                $short_url = shorten_bitly($url, $bitly_key, $bitly_login);
                if ($short_url) {
                    add_post_meta($post->ID, 'bitly_short_url', $short_url);
                }
            }
            else {
                $short_url = get_post_meta($post->ID, 'bitly_short_url', true);
            }
            
            // retweet data (twitter API)
            $retweet_meta = get_post_meta($post->ID, 'retweet_cache', true);
            if ($retweet_meta != '') {
                $retweet_pieces = explode(':', $retweet_meta);
                $retweet_timestamp = (int)$retweet_pieces[0];
                $retweet_count = (int)$retweet_pieces[1];
            }
            // expire retweet cache
            if ($retweet_count === null || time() > $retweet_timestamp + $cache_interval) {
                $retweet_response = urlopen('http://urls.api.twitter.com/1/urls/count.json?url=' . urlencode($url));
                if ($retweet_response) {
                    $retweet_data = json_decode($retweet_response, true);
                    if (isset($retweet_data['count']) && isset($retweet_data['url']) && $retweet_data['url'] === $url) {
                        if ((int)$retweet_data['count'] >= $retweet_count || time() > $retweet_timestamp + $refresh_interval) {
                            $retweet_count = $retweet_data['count'];
                            if ($retweet_meta == '') {
                                add_post_meta($post->ID, 'retweet_cache', time() . ':' . $retweet_count);
                            } else {
                                update_post_meta($post->ID, 'retweet_cache', time() . ':' . $retweet_count);
                            }
                        }
                    }
                }
            }
            
            // calculate the total count to display
            $count = $retweet_count;
            if ($count > 9999) {
                $count = $count / 1000;
                $count = number_format($count, 1) . 'K';
            } else {
                $count = number_format($count);
            }
        }
        
        // construct the tweet button query string
        $twitter_params = 
        '?text=' . urlencode($title) . '+-' .
        '&amp;url=' . urlencode($short_url) . 
        '&amp;counturl=' . urlencode($url) . 
        '&amp;via=' . $twitter_via;

        if ($count_display == 1 && $count > 0 || $count_display == 2) {
            $counter = '<span class="social_count twitter_count">' . $count . '</a>';
        }
        
        // HTML for the tweet button (add "vcount" to "twitter-share" for vertical count)
        $twitter_share = '
            <a class="social_button twitter_button" 
               rel="external nofollow" 
               title="Share this on Twitter" 
               href="#"
               onclick="popUp=window.open(\'http://twitter.com/share'.$twitter_params.'\', \'popupwindow\', \'scrollbars=yes,width=800,height=400\');popUp.focus();return false" 
               target="_blank">
            ' . $counter . '';

        echo $twitter_share;
    }
}

//Custom Google+ share code
function get_plusones($url) {
    $args = array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'method' => 'pos.plusones.get',
                'id' => 'p',
                'method' => 'pos.plusones.get',
                'jsonrpc' => '2.0',
                'key' => 'p',
                'apiVersion' => 'v1',
                'params' => array(
                    'nolog'=>true,
                    'id'=> $url,
                    'source'=>'widget',
                    'userId'=>'@viewer',
                    'groupId'=>'@self'
                ) 
             )),
             // disable checking SSL sertificates               
            'sslverify'=>false
        );
     
    // retrieves JSON with HTTP POST method for current URL  
    $json_string = wp_remote_post("https://clients6.google.com/rpc", $args);
     
    if (is_wp_error($json_string)){                             
        return "0";             
    } else {        
        $json = json_decode($json_string['body'], true);                    
        // return count of Google +1 for requsted URL
        return intval( $json['result']['metadata']['globalCounts']['count'] ); 
    }
}

//Custom Linkedin share code
function customLinkedInShare() {
    $linked_results = file_get_contents('http://www.linkedin.com/countserv/count/share?url='. curPageURL().'&format=json');
    $linked_array = json_decode($linked_results, true);
    $linked_count =  $linked_array['count'];
    return ($linked_count ) ? $linked_count : "0";
}

function customLinkedInShareShortcode( $tr, $content = null ) {
    extract(shortcode_tr(array(
       'type' => ''
    ), $tr));
    return '<a class="social_button linkedin_button" href="#" onclick="popUp=window.open(\'https://www.linkedin.com/cws/share?url='.curPageURL().'\', \'popupwindow\', \'scrollbars=yes,width=800,height=400\');popUp.focus();return false">
                <span class="social_count linkedin_count">'. customLinkedInShare() .'</span>
            </a>';
}
add_shortcode('customshareLinkedIn', 'customLinkedInShareShortcode');

//Custom Pintrest share code
function customPinterestShare() {
    $pinterest_results = file_get_contents('http://api.pinterest.com/v1/urls/count.json?callback=&url='. curPageURL());
    $pinterest_array = json_decode($pinterest_results, true);
    $pinterest_count =  $pinterest_array['count'];
    return ($pinterest_count ) ? $pinterest_count : "0";
}

function customPinterestShareShortcode( $tr, $content = null ) {
    extract(shortcode_tr(array(
       'type' => ''
    ), $tr));
    return '<a class="social_button pinterest_button" href="#" onclick="popUp=window.open(\'http://pinterest.com/pin/create/button/?url='.curPageURL().'&media='.get_field('image_1').'&description='.get_field('excerpt').'\', \'popupwindow\', \'scrollbars=yes,width=800,height=400\');popUp.focus();return false">
                <span class="social_count pinterest_count">'. customPinterestShare() .'</span>
            </a>';
}
add_shortcode('customsharePintrest', 'customPinterestShareShortcode');

//Custom Stubmle Upon share code
function customStumbleShare() {
    $stumble_results = file_get_contents('http://www.stumbleupon.com/services/1.01/badge.getinfo?url='. curPageURL());
    $stumble_array = json_decode($stumble_results, true);
    $stumble_count =  $stumble_array['result']['views'];
    return ($stumble_count ) ? $stumble_count : "0";
}

function customStumbleShareShortcode( $tr, $content = null ) {
    extract(shortcode_tr(array(
       'type' => ''
    ), $tr));
    return '<a class="social_button stumble_button" href="#" onclick="popUp=window.open(\'http://www.stumbleupon.com/submit?url='.curPageURL().'&title='.get_the_title($ID).'\', \'popupwindow\', \'scrollbars=yes,width=800,height=530\');popUp.focus();return false">
                <span class="social_count stumble_count">'. customStumbleShare() .'</span>
            </a>';
}
add_shortcode('customshareStumble', 'customStumbleShareShortcode');
?>

<!--Example of HTML implementation--> 
<!DOCTYPE html>
<html>
    <head>
        <title>Custom Wordpress Social Media Buttons with Counters</title>
    </head>
    <body>
        <ul class="socialBar">
          <li><?php echo do_shortcode('[customshare type=shareTxt]'); ?></li>
          <li><?php if (function_exists('tweet_button')) {tweet_button(get_permalink());}?></li>
          <li>
            <a href="#" class="social_button google_button" onclick="popUp=window.open('https://plus.google.com/share?url=<?php the_permalink(); ?>', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false">
              <span class="social_count google_count">
                <?php 
                  $permalink = get_permalink($post->ID);
                  _e(get_plusones($permalink)); 
                ?>
              </span>
            </a>
          </li>
          <li><?php echo do_shortcode('[customshareLinkedIn]'); ?></li>
          <li><?php echo do_shortcode('[customsharePintrest]'); ?></li>
          <li><?php echo do_shortcode('[customshareStumble]'); ?></li>
        </ul>
    </body>
</html>