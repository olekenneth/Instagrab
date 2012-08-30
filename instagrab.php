<?php
/*
	Plugin Name: Instagrab
	Plugin URI: https://github.com/olekenneth/Instagrab
	Description: Instagrab is a Wordpress plugin that grabs images from one or more Instagram hashtags and create a post for each image.
	Version: 0.0.1
	Author: Ole-Kenneth Rangnes
	Author URI: http://olekenneth.com
*/
$prefix = "instagrab_";

$last_running = get_option($prefix . "running");
$timeout = get_option($prefix . "timeout");

add_action('admin_menu', function() {
    add_options_page(__('Instagrab','instagrab'), __('Instagrab','instagrab'), 'manage_options', 'instagrab', function() {

	    if (!current_user_can('manage_options'))
	    {
	      wp_die( __('You do not have sufficient permissions to access this page.') );
	    }
	    
	    global $prefix;
	    
	    $hidden_field_name = 'instagrab_submit_hidden';
	
	    $tags 			= get_option($prefix . "tags");
	    $username 		= get_option($prefix . "username");
	    $password 		= get_option($prefix . "password");
	    $timeout 		= get_option($prefix . "timeout");
	
	    if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
		    $tags 			= $_POST[$prefix . "tags"];
		    $username 		= $_POST[$prefix . "username"];
		    $password 		= $_POST[$prefix . "password"];
		    $timeout 		= $_POST[$prefix . "timeout"];
	
	        update_option( $prefix . "tags", 		$_POST[$prefix . "tags"] );
	        update_option( $prefix . "username",	$_POST[$prefix . "username"] );
	        update_option( $prefix . "password",	$_POST[$prefix . "password"] );
	        update_option( $prefix . "timeout",		$_POST[$prefix . "timeout"] );

			echo "<div class=\"updated\"><p><strong>" . __('Saved', 'instagrab' ) ."</strong></p></div>";
		}
		?>
		<div class="wrap"><h2><?php echo __( 'Instagrab', 'instagrab' ); ?></h2>
		<form name="form1" method="post" action="">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
		<p><?php _e("Hashtags to follow:", 'instagrab' ); ?> <?php _e("(one each line, without #)", 'instagrab' ); ?> 
		<br/>
		<textarea cols="50" rows="5" name="<?php echo $prefix . "tags"; ?>"><?php echo $tags; ?></textarea>
		</p>
		<p><?php _e("Instagram username:", 'instagrab' ); ?> 
		<input type="text" name="<?php echo $prefix . "username"; ?>" value="<?php echo $username; ?>"/>
		</p>
		<p><?php _e("Instagram password:", 'instagrab' ); ?> 
		<input type="password" name="<?php echo $prefix . "password"; ?>" value="<?php echo $password; ?>"/>
		</p>
		<p><?php _e("Timeout:", 'instagrab' ); ?> 
		<input type="number" name="<?php echo $prefix . "timeout"; ?>" value="<?php echo $timeout; ?>"/> (sec)
		</p>
		<hr />
		<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</p>
		</form>
		</div>
		<?php
	});
});

function instagrabLogin(){
	global $prefix;
	
	$response = wp_remote_post("https://api.instagram.com/oauth/access_token",
		array(
			'body' => array(
				'username' => get_option($prefix . "username"),
				'password' => get_option($prefix . "password"),
				'grant_type' => 'password',
				'client_id' => '90c2afb9762041138b620eb56710ca39',
				'client_secret' => 'c605ec6443e348e68643470fdc3ef02a'
			),
			'sslverify' => apply_filters('https_local_ssl_verify', false)
		)
	);
	if(!is_wp_error($response) && $response['response']['code'] < 400 && $response['response']['code'] >= 200) {
		$auth = json_decode($response['body']);
		return $auth->access_token;		
	}
	
	return false;
}

function addImageToPost($title, $content, $tags, $category_id) {
	return @wp_insert_post(
		array(
		     'post_title' 		=> $title,
		     'post_content' 	=> $content,
		     'post_status' 		=> 'publish',
		     'post_author' 		=> 1,
		     'post_category' 	=> array($category_id),
		     'tags_input' 		=> $tags,

		  )
	);
}

function instagrabGrabAndSave() {
	global $prefix;
	
	$hashtags = explode("\n", get_option($prefix . "tags"));

	if (file_exists("wp-admin/includes/taxonomy.php")) {
		require_once("wp-admin/includes/taxonomy.php");
	}

	foreach($hashtags as $hashtag) {
		$category_id = wp_create_category($hashtag, 0);
		$images = instagrabGetImagesByHashtag($hashtag);
		
		foreach($images as $image) {
			$content = <<<EOF
<a href="?tag={$image['author']['username']}"><img width="30" src="{$image['author']['picture']}"/> {$image['author']['username']}</a>
<img src="{$image['images']['large']}" alt=""/>
<h3>{$image['title']}</h3>
EOF;
		
			$tags = array_unique(array_merge(array($image['author']['username'], $hashtag, "filter: " . $image['filter']), $image['tags']));
			addImageToPost($image['title'], $content, $tags, $category_id);
		}
	}
	
	update_option($prefix . "running", time());
}


function instagrabGetImagesByHashtag($hashtag, $count = 1){
	global $prefix;
	
	$images = array();
	$access_token = instagrabLogin();
	$max_id = get_option($prefix . $hashtag."_max_id");
	
	if($access_token != null) {
		if(isset($hashtag) && trim($hashtag) != "" && preg_match("/[a-zA-Z0-9_\-]+/i", $hashtag)) {
			$apiurl = "https://api.instagram.com/v1/tags/".$hashtag."/media/recent?max_id=".$max_id."&count=".$count."&access_token=".$access_token;
		} 
		$response = wp_remote_get($apiurl,
			array(
				'sslverify' => apply_filters('https_local_ssl_verify', false)
			)
		);
		if(!is_wp_error($response) && $response['response']['code'] < 400 && $response['response']['code'] >= 200) {
			$data = json_decode($response['body']);
			if($data->meta->code == 200) {
				foreach($data->data as $item) {
					$images[] = array(
						"id" 			=> $item->id,
						"author"		=> array("username" => $item->user->username, "full_name" => utf8Clean($item->user->full_name), "website" => $item->user->website, "bio" => utf8Clean($item->user->bio), "picture" => $item->user->profile_picture),
						"title"			=> @utf8Clean($item->caption->text),
						"link" 			=> $item->link,
						"filter" 		=> $item->filter,
						"tags"			=> (array)$item->tags,
						"images"		=> array(
											"small"	=> (string)$item->images->thumbnail->url,
											"medium"	=> (string)$item->images->low_resolution->url,
											"large"	=> (string)$item->images->standard_resolution->url,
											),
					);
				}
				update_option($prefix . $hashtag."_max_id", $data->pagination->min_tag_id);
			}
		} 
	}
	
	rsort($images);
	return $images;
}

function utf8Clean($str) {
	return str_replace("  ", " ", str_replace("#", " #", iconv("ISO-8859-1", "UTF-8//TRANSLIT//IGNORE", iconv("UTF-8", "ISO-8859-1//TRANSLIT//IGNORE", $str))));
}

if (!is_admin() && ($last_running + $timeout) < time()) {
	instagrabGrabAndSave();
}
?>