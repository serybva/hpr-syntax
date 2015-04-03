<?php
/*
Plugin Name: HprSyntax
Plugin URI: http://www.serybva.com
Description: High performance syntax highlighting based on <a href="http://wordpress.org/extend/plugins/wp-syntax/">Wp-syntax</a>/<a href="http://qbnz.com/highlighter/">GeSHi</a>
Version: 1.0
Author: Sébastien Vray
Author URI: http://www.serybva.com
License: GPL2
Text Domain: hpr_syntax
Domain Path: /lang

Original Author: Ryan McGeary

Copyright 2013  Steven A. Zahm  (email : helpdesk@connections-pro.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('HprSyntax')){

	class					HprSyntax{

		/**
		* @var (object) HprSyntax stores the instance of this class.
		*/
		private	static		$instance;

		private				$_IDMatches = array();
		private				$_codes = array();
		private				$_currentPostID = -1;

		/*************************
		**Options
		*************************/

		public	static		$options = array();
		public	static		$lastMessage = '';
		public	static		$cronIntervals = array();
												
		/***********************
		**Class constants
		************************/
		const				PRE_ID_PREFIX = 'hpr-syntax-';
		const				DB_TABLE_NAME = 'hpr_syntax';
		const				DB_CLEAN_HOOK_NAME = 'hpr_syntax_clean_DB';
		const				ACTION_PROCESS_ALL = 'process_all';
		const				ACTION_UNPROCESS_ALL = 'unprocess_all';
		const				ACTION_CLEAN_DB = 'clean_db';
		const				METAPOST_KEY = 'hpr_syntax_meta';
		const				OPTIONS_NAME = 'hpr-syntax-options';
		const				HPR_VERSION = '1.0';

		private	function	__construct() { }

		/**
		 *
		 * Hpr-wp-syntax instance getter (singleton).
		 *
		 * @access public
		 * @since 1.0
		 * @return class instance
		 */
		public	static	function	getInstance(){
			if (!isset(self::$instance)){
				self::$instance = new self;
				self::$instance->init();
			}
			return self::$instance;
		}

		/**
		 * Initiate the plugin.
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private	function			init(){
			load_plugin_textdomain('hpr_syntax', false, dirname(plugin_basename(__FILE__)).'/lang/');
			self::defineConstants();
			self::inludeDependencies();
			self::_getOptions();
			self::$cronIntervals = array('weekly' => array('interval' => 604800, 'display' => __('Weekly', 'hpr_syntax')),
												'monthly' => array('interval' => 2592000, 'display' => __('Monthly', 'hpr_syntax')),
												'minutes' => array('interval' => 60, 'display' => __('Every minute', 'hpr_syntax')));
			add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueueScripts'));

			// Update config for WYSIWYG editor to accept the pre tag and its attributes.
			add_filter('tiny_mce_before_init', array(__CLASS__,'tinyMCEConfig'));

			// We want to run before other filters; hence, a priority of 0 was chosen.
			// The lower the number, the higher the priority.  10 is the default and
			// several formatting filters run at or around 6.
			add_filter('wp_insert_post_data', array(__CLASS__, 'contentPost'), 0, 2);

			// We want to run after other filters; hence, a priority of 99.
			add_filter('the_content', array( __CLASS__, 'contentDisplay'), 99);
			add_filter('the_excerpt', array( __CLASS__, 'contentDisplay'), 99);
		}
		
		public	static	function	uninstall(){
			if (current_user_can('activate_plugins')){
				global $wpdb;
				self::unProcessAll();
				delete_post_meta_by_key(self::METAPOST_KEY);
				$wpdb->query('DROP TABLE `'.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.'`');
				delete_option(self::OPTIONS_NAME);
			}
		}

		public	static	function	HprActivation(){
			if (current_user_can('activate_plugins')){
				global $wpdb;
				$wpdb->query('CREATE TABLE IF NOT EXISTS `'.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.'` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
								`formatted_code` mediumtext NOT NULL, `post_id` bigint(10) unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8');
								
				$wpdb->query('ALTER TABLE `'.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.'` ADD CONSTRAINT `'.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.'_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `'.$wpdb->prefix.'_posts` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;');
				self::_getOptions();
				wp_schedule_event(time(), self::$options['DB_clean_interval'], self::DB_CLEAN_HOOK_NAME);
			}
		}

		public	static	function	HprDeactivation(){
			if (current_user_can('activate_plugins')){
				self::_getOptions();
				wp_clear_scheduled_hook(self::DB_CLEAN_HOOK_NAME);
				wp_schedule_event(time(), self::$options['DB_clean_interval'], self::DB_CLEAN_HOOK_NAME);
			}
		}

		public	static	function	DBCleanIntervalChanged(){
			wp_clear_scheduled_hook(self::DB_CLEAN_HOOK_NAME);
			wp_schedule_event(time(), self::$options['DB_clean_interval'], self::DB_CLEAN_HOOK_NAME);
		}

		private	function			_getOptions(){
			self::$options = get_option(self::OPTIONS_NAME);
			if (!self::$options){
				self::$options = array('use_CSS_classes' => true,
										'hpr_reprocess_on_setting_changes' => false,
										'DB_clean_interval' => 'weekly',
										'post_types' => array('post', 'page'));
				update_option(self::OPTIONS_NAME, self::$options);
			} else
				self::$options = self::$options;
		}

		/**
		 * Define the constants.
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		private	static	function	defineConstants(){
			define('HPR_DIR_NAME', plugin_basename(dirname(__FILE__ )));
			define('HPR_BASE_NAME', plugin_basename(__FILE__ ));
			define('HPR_BASE_PATH', plugin_dir_path(__FILE__ ));
			define('HPR_BASE_URL', plugin_dir_url(__FILE__));
		}

		private	static	function	inludeDependencies(){
			if (!class_exists('GeSHi'))
				include_once('geshi/geshi.php');
		}
		
		/**
		 * Enqueue the CSS and JavaScripts.
		 *
		 * @access private
		 * @since 1.0
		 * @return void
		 */
		public	static	function	enqueueScripts(){
			$url = file_exists(STYLESHEETPATH.'/hpr-syntax.css')?get_bloginfo('stylesheet_directory').'/hpr-syntax.css':HPR_BASE_URL.'css/hpr-syntax.css';
			wp_enqueue_style('hpr-syntax-css', $url, array(), self::HPR_VERSION);
		}

		/**
		 * Update the TinyMCE config to add support for the pre tag and its attributes.
		 *
		 * @access private
		 * @since 0.9.13
		 * @param  (array) $init The TinyMCE config.
		 * @return (array)
		 */
		public	static	function	tinyMCEConfig($init){
			$ext = 'pre[id|name|class|style|lang|line|escaped|highlight|src]';
			if (isset($init['extended_valid_elements']))
				$init['extended_valid_elements'] .= ",".$ext;
			else
				$init['extended_valid_elements'] = $ext;
			return $init;
		}

		// special ltrim b/c leading whitespace matters on 1st line of content
		public static function trimCode($code){
			$code = preg_replace("/^\s*\n/siU", '', $code);
			$code = rtrim($code);
			return $code;
		}

		public	static	function	lineNumbers($code, $start){
			$line_count = count(explode("\n", $code));
			$output = '<pre>';
			for ($i = 0;$i < $line_count;$i++)
				$output .= ( $start + $i )."\n";
			$output .= '</pre>';
			return $output;
		}

		public	static	function	caption($url){
			$parsed = parse_url($url);
			$path = pathinfo($parsed['path']);
			$caption = '';
			if (!isset($path['filename']))
				return;
			if (isset($parsed['scheme']))
				$caption .= '<a href="'.$url.'">';
			if (isset($parsed["host"]) && $parsed["host"] == 'github.com')
				$caption .= substr($parsed['path'], strpos($parsed['path'], '/', 1)); /* strip github.com username */
			else
				$caption .= $parsed['path'];
			/* $caption . $path["filename"];
			if (isset($path["extension"])) {
				$caption .= "." . $path["extension"];
			}*/
			if (isset($parsed['scheme']))
				$caption .= '</a>';
			return $caption;
		}

		public	static	function	highlightSyntax($match){//Highlights code in each matched <pre>
			global $wpdb;
			
			$selectQuery = 'SELECT id FROM '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.' WHERE id=';
			$insertQuery = 'INSERT INTO '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.' VALUES(NULL, \'';
			$updateQuery = 'UPDATE '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.' SET formatted_code=\'';

			$language = strtolower(trim($match['lang']));
			$line = trim($match['line']);
			$caption = self::caption($match['src']);
			$code = self::trimCode($match['code']);
			$ID = $match['id'];

			$code = htmlspecialchars_decode($code);
			$geshi = new GeSHi($code, $language);
			if (self::$options['use_CSS_classes'])
				$geshi->enable_classes();
			$geshi->enable_keyword_links(false);

			if (!empty($match['highlight'])) {
				$linespecs = strpos($match['highlight'], ",") == FALSE ? array($match['highlight']) : explode(',', $match['highlight']);
				$lines = array();
				foreach ( $linespecs as $spec ) {
					$range = explode('-', $spec );
					$lines = array_merge( $lines, ( count( $range ) == 2) ? range( $range[0], $range[1]) : $range );
				}
				$geshi->highlight_lines_extra( $lines );
			}
			$formatted = "\n".'<div class="hpr_syntax"><table>';
			if (!empty($caption))
				$formatted .= '<caption>'.$caption.'</caption>';
			$formatted .= '<tr>';

			if ($line)
				$formatted .= '<td class="line_numbers">'.self::lineNumbers($code, $line).'</td>';
			$formatted .= '<td class="code">';
			$formatted .= $geshi->parse_code();
			$formatted .= '</td></tr></table>';
			$formatted .= '</div>'."\n";
			$content = $match[0];
			$IDtest = $wpdb->get_results($selectQuery.intval($ID).' AND post_id='.intval(self::$instance->_currentPostID), ARRAY_A);
			if (!empty($ID) && count($IDtest) == 0){				
				$wpdb->query($insertQuery.addslashes($formatted).'\', '.intval(self::$instance->_currentPostID).')');
				$content = str_ireplace('id="'.HprSyntax::PRE_ID_PREFIX.$ID.'"', 'id="'.HprSyntax::PRE_ID_PREFIX.$wpdb->insert_id.'"', $content);
			} else if (empty($ID)){
				$wpdb->query($insertQuery.addslashes($formatted).'\', '.intval(self::$instance->_currentPostID).')');
				$content = str_ireplace('<pre ', '<pre id="'.HprSyntax::PRE_ID_PREFIX.$wpdb->insert_id.'" ', preg_replace('/id=["\'][\w\s-\']*["\'] /', '', $content));
			} else {
				$updateQuery .= addslashes($formatted).'\' WHERE id='.intval($ID).' AND post_id='.intval(self::$instance->_currentPostID);
				$wpdb->query($updateQuery);
			}
			return $content;
		}

		public	static	function	contentPost($postData, $_post){//Called on post creation/update
			foreach (self::$options['post_types'] as $postType){
				if ($postData['post_type'] === $postType){
					if (isset($_post['hpr_syntax_enable']) && $_post['hpr_syntax_enable'] === 'on'){
						$regex = '/\s*<pre(?:lang=["\'](?<lang>[\w-]+)["\']|line=["\'](?<line>\d*)["\']';
						$regex .= '|highlight=["\'](?<highlight>(?:\d+[,-])*\d+)["\']';
						$regex .= '|src=["\'](?<src>[^"\']+)["\']|id=["\']'.HprSyntax::PRE_ID_PREFIX.'(?<id>\d+)["\']';
						$regex .= '|[\w-]+=["\'][\w-\s]+["\']|\s)+>(?<code>.*)<\/pre>\s*/siU';
						self::$instance->_currentPostID = $_post['ID'];
						$postData['post_content'] = addslashes(preg_replace_callback($regex, array(__CLASS__, 'highlightSyntax'), stripcslashes($postData['post_content'])));
						update_post_meta($_post['ID'], HprSyntax::METAPOST_KEY, 'true');
						self::$instance->_currentPostID = -1;
					} else if (!isset($_post['hpr_syntax_enable']) ||
								(isset($_post['hpr_syntax_enable']) && $_post['hpr_syntax_enable'] != 'on')){
						$postData['post_content'] = addslashes(self::_removeHprIDs(stripcslashes($postData['post_content'])));
						update_post_meta($_post['ID'], HprSyntax::METAPOST_KEY, 'false');
					}
					break;
				}
			}
			return $postData;
		}

		private	function			_getCode(){//Fetches the highlighted codes corresponding to matched ID from the database
			global $wpdb;
			$selectQuery = 'SELECT id, formatted_code FROM '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.' WHERE id=';
			for ($i=0,$len=count(self::$instance->_IDMatches);$i < $len;$i++)
				$selectQuery .= intval(self::$instance->_IDMatches[$i]).($i+1 < $len?' OR id=':'');
			$queryResult = $wpdb->get_results($selectQuery, ARRAY_A);
			foreach ($queryResult as $row)
				self::$instance->_codes[$row['id']] = $row['formatted_code'];
		}

		public	static	function	dump($data, $append = true){
			$fp = fopen(dirname(__FILE__).'/dump.txt', $append?'a+':'w+');
			fwrite($fp, $data, strlen($data));
			fclose($fp);
		}
		
		public	function			getCodes(){
			return $this->_codes;
		}

		public	static	function	contentDisplay($content){//Replaces highlighted pre tags on content display
			$regex = '/\s*<pre.*(?:id=["\']'.HprSyntax::PRE_ID_PREFIX.'(?<id>\d+)["\'])+.*>.*<\/pre>\s*/siU';
			preg_match_all($regex, $content, self::$instance->_IDMatches);
			self::$instance->_IDMatches = self::$instance->_IDMatches['id'];
			if (count(self::$instance->_IDMatches) > 0){
				self::$instance->_getCode();
				$content = preg_replace_callback($regex, function($match){
																$codes = HprSyntax::getInstance()->getCodes();
																if (count($match) === 3 &&
																	!empty($match['id']) &&
																	!empty($codes[$match['id']]))
																	return $codes[$match['id']];
																else
																	return $match[0];
															}, $content);
				self::$instance->_codes = array();
				self::$instance->_IDMatches = array();
			}
			return $content;
		}

		public	static	function	processAll(){//Highlights syntax in all the published posts
			global $wpdb;
			$selectQuery = 'SELECT post.ID, post.post_content, post.post_type FROM '.$wpdb->posts.' AS post';
			$selectQuery .= ' RIGHT JOIN '.$wpdb->postmeta.' AS meta ON meta.post_id=post.ID WHERE post.post_status=\'publish\' ';
			$selectQuery .= 'AND meta.meta_key=\''.HprSyntax::METAPOST_KEY.'\' AND meta.meta_value=\'true\' AND (post_type=';
			for ($i=0, $len=count(self::$options['post_types']);$i < $len;$i++)
				$selectQuery .= '\''.addslashes(self::$options['post_types'][$i]).'\''.($i+1 < $len?' OR post_type=':'');
			$selectQuery .= ')';
			$posts = $wpdb->get_results($selectQuery, ARRAY_A);
			foreach ($posts as $i => $post) {
				$post['post_content'] = addslashes($post['post_content']);
				$post = self::contentPost($post, array('ID' => $post['ID'],'hpr_syntax_enable' => 'on'));
				$wpdb->query('UPDATE '.$wpdb->posts.' SET post_content=\''.$post['post_content'].'\' WHERE ID='.intval($post['ID']));
			}
		}

		private	static	function	_removeHprIDs($content){//Removes id attr on pre tags
			$regex = '/\s*(<pre(?:.*id=["\']'.HprSyntax::PRE_ID_PREFIX.'(?<id>\d+)["\'].*)+>)\s*/siU';
			$content = preg_replace_callback($regex, function($match){return preg_replace('/id=["\']'.HprSyntax::PRE_ID_PREFIX.'\d+["\'] /', '', $match[0]);},
											$content);
			return $content;
		}

		public	static	function	unProcessAll(){
			global $wpdb;
			$selectQuery = 'SELECT ID, post_content,post_type FROM '.$wpdb->posts.' WHERE post_type=';
			for ($i=0, $len=count(self::$options['post_types']);$i < $len;$i++)
				$selectQuery .= '\''.addslashes(self::$options['post_types'][$i]).'\''.($i+1 < $len?' OR post_type=':'');
			$posts = $wpdb->get_results($selectQuery, ARRAY_A);
			foreach ($posts as $i => $post) {
				$posts[$i]['post_content'] = self::_removeHprIDs($post['post_content']);
				$wpdb->query('UPDATE '.$wpdb->posts.' SET post_content=\''.addslashes($posts[$i]['post_content']).'\' WHERE ID='.intval($posts[$i]['ID']));
			}
			$wpdb->query('TRUNCATE '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME);
		}

		public	static	function	cleanDB(){//Cleans dead references from the database
			global $wpdb;
			$selectQuery = 'SELECT post_content FROM '.$wpdb->posts.' WHERE post_status=\'publish\' AND (post_type=';
			for ($i=0, $len=count(self::$options['post_types']);$i < $len;$i++)
				$selectQuery .= '\''.addslashes(self::$options['post_types'][$i]).'\''.($i+1 < $len?' OR post_type=':'');
			$selectQuery .= ')';
			$posts = $wpdb->get_results($selectQuery, ARRAY_A);
			$selectQuery = 'SELECT id FROM '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME;
			$formattedCodes = $wpdb->get_results($selectQuery, ARRAY_A);
			$strPosts = '';
			$deadReferences = array();
			foreach ($posts as $post)
				$strPosts .= $post['post_content'];
			foreach ($formattedCodes as $code){
				if (stripos($strPosts, HprSyntax::PRE_ID_PREFIX.$code['id']) === false)
					$deadReferences[] = $code['id'];
			}
			$deadReferencesCount = count($deadReferences);
			if ($deadReferencesCount > 0){
				$deleteQuery = 'DELETE FROM '.$wpdb->prefix.HprSyntax::DB_TABLE_NAME.' WHERE id=';
				for ($i=0;$i < $deadReferencesCount;$i++)
					$deleteQuery .= intval($deadReferences[$i]).($i+1 < $deadReferencesCount?' OR id=':'');
				$wpdb->query($deleteQuery);
			}
			self::$lastMessage = $deadReferencesCount.' cleaned';
		}

		public	static	function	showMetabox($post){
			$metaPost = get_post_meta($post->ID, HprSyntax::METAPOST_KEY, true);
			echo '<label for="hpr_syntax_enable"><input type="checkbox" '.($metaPost === 'true' || $metaPost === ''?'checked="checked"':'').' id="hpr_syntax_enable" name="hpr_syntax_enable" />
					'.__('Enable HprSyntax for this post', 'hpr_syntax').'</label>';
		}

		public	static	function	initMetaboxes(){
			foreach (self::$options['post_types'] as $postType)
				add_meta_box('hpr-syntax-post-metabox', 'HprSyntax', array(__CLASS__, 'showMetabox'), $postType, 'side', 'high');
		}
	}

	/**
	 * The main function responsible for returning the HprSyntax instance
	 * to functions everywhere.
	 *
	 * Use this function like you would a global variable, except without needing
	 * to declare the global.
	 *
	 * Example: <?php $hpr_syntax = HprSyntax(); ?>
	 *
	 * @access public
	 * @since 1.0
	 * @return mixed (object)
	 */
	function HprSyntax() {
		return HprSyntax::getInstance();
	}

	/**
	 * Start the plugin.
	 */
	register_activation_hook(__FILE__, array('HprSyntax', 'HprActivation'));
	register_deactivation_hook(__FILE__, array('HprSyntax', 'HprDeactivation'));
	add_action('plugins_loaded', 'HprSyntax');
	add_filter('cron_schedules', function($schedules){return array_merge($schedules, HprSyntax::$cronIntervals);});
	add_action('admin_menu', 'hpr_syntax_admin_panel');
	add_action(HprSyntax::DB_CLEAN_HOOK_NAME, array('HprSyntax', 'cleanDB'));
	add_action('add_meta_boxes', array('HprSyntax', 'initMetaboxes'));

	if (!function_exists('hpr_syntax_admin_panel'))
    {
        function	hpr_syntax_admin_panel(){
            if (function_exists('add_options_page') && is_admin()){
				require_once(dirname(__FILE__).'/hpr-syntax-options.php');
				$hprSyntaxOptions = new HprSyntaxOptions();
                add_options_page('HprSyntax Settings', 'HprSyntax', 'manage_options', 'hpr-syntax-settings', array(&$hprSyntaxOptions, 'displayOptionsPage'));
			}
        }
    }
}