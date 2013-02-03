<?php
/*
	Plugin Name: Instagrab
	Plugin URI: http://instagrab.org
	Description: Instagrab is a Wordpress plugin that grabs images from one or more Instagram hashtags and create a post for each image.
	Version: 2.0
	Author: Ole-Kenneth Rangnes
	Author URI: http://olekenneth.com
*/
class instagrab {

	private $prefix = "instagrab_",
	$instagrab_db_version = "2",
	$timeout,
	$last_running,
	$client_id,
	$table_name;

	function instagrab() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . $this->prefix . "ids";

		$this->last_running = get_option($this->prefix . "running");
		$this->timeout = get_option($this->prefix . "timeout");
		$this->instagram_logo = plugins_url( 'instagram_logo.png' , __FILE__ );
		$this->running_db_version = (int)get_option($this->prefix . "db_version");
		$this->client_id   = get_option($this->prefix . "client_id");
		
		register_activation_hook(__FILE__, array(&$this, 'instagrabInstall'));
		
		if ($this->running_db_version != $this->instagrab_db_version) {
			$this->instagrabInstall();
		}
		
		add_action('admin_menu', array(&$this, 'instagrab_admin_menu'));
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(&$this, 'instagrab_add_plugin_action_links') );

		
		if (!is_admin() && ($this->last_running + $this->timeout) < time()) {
			add_action("wp_loaded", array(&$this, "instagrabGrabAndSave"));
		}
	}

	function instagrabInstall() {
		global $table_prefix, $table_suffix, $wpdb;

		$deleteSql = "DROP TABLE {$this->table_name};";
		$createSql = "CREATE TABLE {$this->table_name} (
				  `instagram_id` varchar(100) NOT NULL,
				  `time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				  `hashtag` varchar(100) NOT NULL,
				  UNIQUE KEY `instagram_id` (`instagram_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=latin1;";

		mysql_connect( DB_HOST, DB_USER, DB_PASSWORD );
		mysql_select_db( DB_NAME );
		mysql_query( $deleteSql );
		mysql_query( $createSql );

		
		update_option($this->prefix . "db_version", $this->instagrab_db_version);
		$this->running_db_version = $this->instagrab_db_version;
	}

	function instagrab_add_plugin_action_links( $links ) {
		return array_merge($links, array("settings" => "<a href=\"options-general.php?page=instagrab.php\">". __("Settings", "instagrab") . "</a>"));
	}

	function saveCacheToDb($instagram_id, $hashtag) {
		global $wpdb;
		return $wpdb->insert( $this->table_name, array( 'time' => current_time('mysql'), 'instagram_id' => $instagram_id, 'hashtag' => $hashtag ) );
	}

	function instagrabLogin(){
			if ($access_token = get_option($this->prefix . "_access_token")) {
				return $access_token;
			}
			$response = wp_remote_post("https://api.instagram.com/oauth/access_token",
				array(
					'body' => array(
						'username' => get_option($this->prefix . "username"),
						'password' => get_option($this->prefix . "password"),
						'grant_type' => 'password',
					),
					'sslverify' => apply_filters('https_local_ssl_verify', false)
				)
			);
			if(!is_wp_error($response) && $response['response']['code'] < 400 && $response['response']['code'] >= 200) {
				$auth = json_decode($response['body']);
				update_option($this->prefix . "_access_token", $auth->access_token);			
				return $auth->access_token;
			}

			return null;
		}



	function instagrab_add_options_page() {

		if (!current_user_can('manage_options'))
		{
			wp_die( __('You do not have sufficient permissions to access this page.') );
		}


		$hidden_field_name = $this->prefix . 'submit_hidden';

		$tags    = get_option($this->prefix . "tags");
		$username   = get_option($this->prefix . "username");
		$password   = get_option($this->prefix . "password");
		$this->timeout   = get_option($this->prefix . "timeout");

		if( isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
			$tags    = $_POST[$this->prefix . "tags"];
			$username   = $_POST[$this->prefix . "username"];
			$password   = $_POST[$this->prefix . "password"];
			$this->timeout   = $_POST[$this->prefix . "timeout"];
			$this->client_id   = $_POST[$this->prefix . "client_id"];

			update_option( $this->prefix . "tags",   $tags );
			update_option( $this->prefix . "username", $username );
			update_option( $this->prefix . "password", $password );
			update_option( $this->prefix . "timeout",  $this->timeout );
			update_option( $this->prefix . "client_id",  $this->client_id );

			echo "<div class=\"updated\"><p><strong>" . __('Saved', 'instagrab' ) ."</strong></p></div>";
		}
		if (isset($_GET['test'])) {
			$this->instagrabTest();
		}
		if (isset($_GET['run'])) {
			$this->instagrabGrabAndSave();
		}
?>
		<div class="wrap"><h2><?php echo __( 'Instagrab', 'instagrab' ); ?></h2>
		<form name="form1" method="post" action="">
			<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
			<p><?php _e("Hashtags to follow:", 'instagrab' ); ?> <?php _e("(one each line, without #)", 'instagrab' ); ?>
			<br/>
			<textarea cols="50" rows="5" name="<?php echo $this->prefix . "tags"; ?>"><?php echo $tags; ?></textarea>
			</p>
			<p><?php _e("Timeout:", 'instagrab' ); ?>
			<input type="number" name="<?php echo $this->prefix . "timeout"; ?>" value="<?php echo $this->timeout; ?>"/> (sec)
			</p>

			<h3>Autentification</h3>
			<h4>Use an account to access Instagram API</h4>
			<p><?php _e("Instagram username:", 'instagrab' ); ?>
			<input type="text" name="<?php echo $this->prefix . "username"; ?>" value="<?php echo $username; ?>"/>
			</p>
			<p><?php _e("Instagram password:", 'instagrab' ); ?>
			<input type="password" name="<?php echo $this->prefix . "password"; ?>" value="<?php echo $password; ?>"/>
			</p>
			<h4>Or use client id from to access Instagram API (don't use both)</h4>
			<p><?php _e("Client id:", 'instagrab' ); ?>
			<input type="text" name="<?php echo $this->prefix . "client_id"; ?>" value="<?php echo $this->client_id; ?>"/>
			</p>			
			<hr />
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
		</form>
		<?php
			if (!empty($tags) && (((!empty($username)) && (!empty($this->timeout)) && (!empty($password)) ) || (!empty($this->client_id))) ) {
				?>
				<form method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<input type="submit" name="test" class="button-primary" value="<?php esc_attr_e('Test settings') ?>" />
				</form>

				<form method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<input type="submit" name="run" class="button-primary" value="<?php esc_attr_e('Force run') ?>" />
				</form>
				<?php
			}
		?>
		</p>
		</div>
		<?php
	}

	function instagrab_admin_menu() {
		add_options_page(__('Instagrab','instagrab'), __('Instagrab','instagrab'), 'manage_options', 'instagrab', array(&$this, 'instagrab_add_options_page'));
	}

	function addImageToPost($title, $content, $tags, $category_id, $instagram_id, $hashtag) {
		global $wpdb;

		if ($this->saveCacheToDb($instagram_id, $hashtag)) {
			$id = @wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content'  => $content,
					'post_status'   => 'publish',
					'post_author'   => 1,
					'post_category'  => array($category_id),
					'tags_input'   => $tags,
			
				)
			);
			
		}
	}

	function instagrabGrabAndSave() {
		$hashtags = explode("\n", get_option($this->prefix . "tags"));

		require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');

		foreach($hashtags as $hashtag) {
			$category_id = wp_create_category($hashtag, 0);
			$images = $this->instagrabGetImagesByHashtag($hashtag);

			foreach($images as $image) {
				$link = home_url() . "/?tag=" . $image['author']['username'];
				$content = <<<EOF
	<a href="{$link}"><img width="30" src="{$image['author']['picture']}"/> {$image['author']['username']}</a>
	<img src="{$image['images']['large']}" alt=""/>
	<h3><a target="_blank" href="{$image['link']}"><img border="0" src="{$this->instagram_logo}" alt="Posted on Instagram"/></a> {$image['title']}</h3>
EOF;

				$tags = array_unique(array_merge(array($image['author']['username'], $hashtag, "filter: " . $image['filter']), $image['tags']));
				$this->addImageToPost($image['title'], $content, $tags, $category_id, $image['id'], $hashtag);
			}
		}

		update_option($this->prefix . "running", time());
	}


	function instagrabGetImagesByHashtag($hashtag, $count = 50){
		$images = array();
		$max_id = get_option($this->prefix . $hashtag . "_max_id");

		if(isset($hashtag) && trim($hashtag) != "" && preg_match("/[a-zA-Z0-9_\-]+/i", $hashtag)) {
			if (empty($this->client_id)) {
				$access_token = $this->instagrabLogin();
				$apiurl = "https://api.instagram.com/v1/tags/".$hashtag."/media/recent?access_token=" . $access_token;
			} else {
				$apiurl = "https://api.instagram.com/v1/tags/".$hashtag."/media/recent?client_id=" . $this->client_id;
			}
		}

		$data = json_decode(file_get_contents($apiurl));
			if($data->meta->code == 200) {
				foreach($data->data as $item) {
					$images[] = array(
						"id"    => $item->id,
						"author"  => array("username" => $item->user->username, "full_name" => self::utf8Clean($item->user->full_name), "website" => $item->user->website, "bio" => self::utf8Clean($item->user->bio), "picture" => $item->user->profile_picture),
						"title"   => @self::utf8Clean($item->caption->text),
						"link"    => $item->link,
						"filter"   => $item->filter,
						"tags"   => (array)$item->tags,
						"images"  => array(
							"small" => (string)$item->images->thumbnail->url,
							"medium" => (string)$item->images->low_resolution->url,
							"large" => (string)$item->images->standard_resolution->url,
						),
					);
				}
			}
		rsort($images);
		return $images;
	}
	
	public function instagrabTest() {
		
		if (empty($this->client_id)) {
			$access_token = "Using access token: " . $this->instagrabLogin();
		} else {
			$access_token = "Client id: " . $this->client_id;
		}
		
		
		$hashtags = explode("\n", get_option($this->prefix . "tags"));
		echo "<div class=\"error\"><p><strong>";

		if (!is_array($hashtags)) {
			_e('No hashtags stored in settings', 'instagrab' ); echo "<br/>";
		}
		
		echo $access_token . "<br/>";
		
		foreach($hashtags as $hashtag) {
			$images = $this->instagrabGetImagesByHashtag($hashtag);
			if (is_array($images) && count($images)<1) {
				_e("Unable to download any images from #{$hashtag}", 'instagrab' ); echo "<br/>";
			} else {
    			_e("Downloaded ".count($images)." images from #{$hashtag}", 'instagrab' ); echo "<br/>";
			}
		}
		echo "</p></strong></div>";
	}

	static function utf8Clean($str) {
		return str_replace("  ", " ", str_replace("#", " #", iconv("ISO-8859-1", "UTF-8//TRANSLIT//IGNORE", iconv("UTF-8", "ISO-8859-1//TRANSLIT//IGNORE", $str))));
	}

}
	$instagrab = new instagrab();

?>