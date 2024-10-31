<?php
/*
* Plugin Name: Plugins switcher for mobile access
* Description: Switch off plugins for mobile devices access
* Author: Wiziapp Solutions Ltd.
* Version: v1.0.0
* Author URI: http://www.wiziapp.com/
*/

class Plugin_Switcher {

	const OPTION_NAME = 'plugin_switcher_setting';

	public static function init() {
		if ( is_admin() ) {
			register_deactivation_hook( __FILE__, array( 'Plugin_Switcher', 'deactivate_plugin_switcher') );

			add_action( 'admin_enqueue_scripts', array( 'Plugin_Switcher', 'admin_styles_scripts' ) );
			add_action( 'admin_menu', array( 'Plugin_Switcher', 'admin_menu' ) );
			add_action( 'wp_ajax_plugin_switcher_change', array( 'Plugin_Switcher', 'plugin_switcher_change') );
		} else {
			add_action( 'plugins_loaded', array( 'Plugin_Switcher', 'disable_plugins' ) );
		}
	}

	public static function admin_styles_scripts($hook) {
		if ( $hook != 'toplevel_page_plugin-switcher-admin-menu' ) {
			return;
		}

		wp_register_style(  'plugin-switcher-admin-style',  plugins_url('style.css', __FILE__) );
		wp_register_script( 'plugin-switcher-admin-script', plugins_url('script.js', __FILE__), array('jquery',) );

		wp_enqueue_style('plugin-switcher-admin-style');
		wp_enqueue_script('plugin-switcher-admin-script');
	}

	public static function deactivate_plugin_switcher() {
		delete_option(self::OPTION_NAME);
	}

	public static function disable_plugins() {
		if ( ! self::_is_mobile_device() && ! self::_is_native_iphone_app() ) {
			return;
		}

		$setting = get_option( self::OPTION_NAME, array() );
		foreach( $setting as $name => $hook_info ) {
			$plugin_directory = realpath( WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.$name );

			foreach ( $GLOBALS['wp_filter'] as $tag => $tag_value ) {
				foreach ( $tag_value as $priority => $priority_value ) {
					foreach ( $priority_value as $function_to_remove => $function_to_remove_value ) {
						try {
							if ( is_array( $function_to_remove_value['function'] ) && count( $function_to_remove_value['function'] ) > 1 ) {
								$reflection = new ReflectionMethod( $function_to_remove_value['function'][0], $function_to_remove_value['function'][1] );
							} else {
								if ( ! function_exists( $function_to_remove_value['function'] ) ) {
									continue;
								}

								$reflection = new ReflectionFunction( $function_to_remove_value['function'] );
							}

							$file_name = realpath( $reflection->getFileName() );
							$position = strpos($file_name, $plugin_directory);

							if ( $position === 0 ) {
								unset($GLOBALS['wp_filter'][$tag][$priority][$function_to_remove]);
							}
						} catch (Exception $e) {
							$error = $e->getMessage();
						}
					}
				}
			}
		}
	}

	public static function plugin_switcher_change() {
		if ( empty($_POST['plugin_directory']) || empty($_POST['plugin_directory']) || ( isset($_POST['plugin_directory']) && $_POST['plugin_directory'] === "plugin_switcher_change" ) ) {
			exit;
		}
		$plugin_directory = $_POST['plugin_directory'];
		$setting = get_option( self::OPTION_NAME, array() );

		if ( isset( $setting[$plugin_directory] ) ) {
			unset($setting[$plugin_directory]);
		}

		if ( $_POST['is_checked'] === '1' ) {
			$setting[$plugin_directory] = 1;
		}

		if ( ! update_option( self::OPTION_NAME, $setting ) ) {
			exit;
		}

		exit('updatedsuccessful');

		// TODO Check for network activated plugins
		/*
		if ( wptouch_is_multisite_enabled() ) {
		$active_site_plugins = get_site_option( 'active_sitewide_plugins' );
		if ( is_array( $active_site_plugins ) && count ( $active_site_plugins ) ) {
		foreach( $active_site_plugins as $key => $value ) {
		if ( !in_array( $key, $active_plugins ) ) {
		$active_plugins[] = $key;
		}
		}
		}
		}
		*/
	}

	public static function admin_menu() {
		add_menu_page( 'Plugin Switcher', 'Plugin Switcher', 'administrator', 'plugin-switcher-admin-menu', array( 'Plugin_Switcher', 'admin_menu_page' ) );
	}

	public static function admin_menu_page() {
		$setting = get_option( self::OPTION_NAME, array() );
		$active_plugins = get_option('active_plugins');
		$active_plugins = array_filter( $active_plugins, array( 'Plugin_Switcher', '_exclude_self' ) );

		?>
		<div id="plugin_switcher_container">
			<?php
			if (count($active_plugins) === 0 ) {
				?>
				<h3>Here are currently no plugins in your Wordpress system</h3>
				<?php
			} else {
				?>
				<h3>Check a plugin in order to block its mobile display</h3>

				<fieldset>
					<legend>Plugin Compatibility</legend>
					<?php
					foreach( $active_plugins as $plugin ) {
						$plugin_data = get_file_data( WP_PLUGIN_DIR.DIRECTORY_SEPARATOR.$plugin, array( 'plugin_name' => 'Plugin Name', ), false );
						$plugin_parts = explode( '/', $plugin );
						$checked = empty( $setting[$plugin_parts[0]] ) ? '' : 'checked="checked"';
						?>
						<div>
							<input type="checkbox" name="<?php echo $plugin_parts[0]; ?>" id="<?php echo $plugin_parts[0]; ?>" <?php echo $checked; ?> />
							<label for="<?php echo $plugin_parts[0]; ?>">
								<?php echo $plugin_data['plugin_name']; ?>
							</label>
						</div>
						<?php
					}
					?>
				</fieldset>
				<?php
			}
			?>
		</div>
		<?php
	}

	private static function _exclude_self($slug) {
		return $slug !== plugin_basename(__FILE__);
	}

	private static function _is_mobile_device() {
		if ( empty($_SERVER['HTTP_USER_AGENT']) ) {
			return false;
		}

		$is_iPhone		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone')  !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')	   !== FALSE;
		$is_iPod		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPod')    !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X')	   !== FALSE;
		$is_android_web	= stripos($_SERVER['HTTP_USER_AGENT'], 'Android') !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'AppleWebKit') !== FALSE;
		$is_android_app = $_SERVER['HTTP_USER_AGENT'] === '72dcc186a8d3d7b3d8554a14256389a4';
		$is_windows		= stripos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'IEMobile')	   !== FALSE && stripos($_SERVER['HTTP_USER_AGENT'], 'Phone') !== FALSE;
		$is_iPad		= stripos($_SERVER['HTTP_USER_AGENT'], 'iPad')    !== FALSE || stripos($_SERVER['HTTP_USER_AGENT'], 'webOS') 	   !== FALSE;

		if ( $is_iPad || $is_iPhone || $is_iPod || $is_android_web || $is_android_app || $is_windows) {
			return true;
		}

		return false;
	}

	private static function _is_native_iphone_app() {
		if ( ! class_exists('WiziappContentHandler') ) {
			return false;
		}

		$wiziapp_content_handler = WiziappContentHandler::getInstance();
		if ( ! $wiziapp_content_handler->isInApp() ) {
			return false;
		}

		return true;
	}
}

Plugin_Switcher::init();
