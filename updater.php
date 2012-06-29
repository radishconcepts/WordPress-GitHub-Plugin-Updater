<?php
/**
 * @package GithubUpdater
 * @author Joachim Kudish @link http://jkudish.com
 * @since 1.3
 * @version 1.4
 * @author Joachim Kudish <info@jkudish.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2011, Joachim Kudish
 */

if ( !class_exists('WPGitHubUpdater') ) :

add_action('admin_init', create_function('', 'global $WPGitHubUpdater; $WPGitHubUpdater = new WPGitHubUpdater();') );

class WPGitHubUpdater {

	/**
	 *	Whether to verify SSL for Git-related connections
	 * Override with <code> add_filter('git_sslverify', create_function('', 'return false;') ); </code>
	 */
	var $ssl_verify = true;

	/**
	 *	List of URLs related to Git repositories.
	 * Used by disable_git_ssl() method
	 */
	var $git_urls = array();

	/**
	 * Installed plugins that list Github as the Plugin URI. Includes metadata.
	 */
	var $plugins = array();

	/**
	 * Class Constructor
	 *
	 * @since 1.0
	 * @param array $config configuration
	 * @return void
	 */
	public function __construct( $config = array() ) {

		$this->ssl_verify = apply_filters('git_sslverify', $this->ssl_verify);

		if ( ( defined('WP_DEBUG') && WP_DEBUG ) || ( defined('WP_GITHUB_FORCE_UPDATE') || WP_GITHUB_FORCE_UPDATE ) )
			add_action( 'init', array( $this, 'delete_transients' ), 11 );

		// Build Git plugin list
		add_action( 'admin_init', array($this, 'load_plugins'), 0 );
		add_filter( 'extra_plugin_headers', array($this, 'extra_plugin_headers') );

		// Check for update from Git API
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );

		// Plugin details screen
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );

		// Cleanup and activate plugins after update
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );

		// HTTP Timeout
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );

		// Maybe disable HTTP SSL Certificate Check for Git URLs
		// If statement can likely be removed.
		// @see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2#issuecomment-6654644
		if ( false === $this->ssl_verify ) {
			add_filter( 'http_request_args', array($this, 'disable_git_ssl_verify'), 10, 2 );
		}
	}


	/**
	 *	Build $this->plugins, a list of Github-hosted plugins based on installed plugin headers
	 *
	 * @return void
	 */
	public function load_plugins( $plugins ) {
		$this->plugins = get_site_transient( 'git_plugins' );

		if ( false !== $this->plugins ) {
			return;
		}

		global $wp_version;

		foreach ( get_plugins() as $slug => $meta ) {
			$repo = $this->get_repo_parts( $meta['PluginURI'] );

			if (false === $repo ) {
				continue;
			}

			$key = dirname($slug);

			$settings = array(
				'name' => $meta['Name'],
				'slug' => $slug,
				'folder_name' => $key,
				'host'  => $repo['host'],
				'username' => $repo['username'],
				'repository' => $repo['repository'],
				'version' => $meta['Version'],
				'author' => $meta['Author'],
				'homepage' => $meta['PluginURI'],
				'api_url' =>  "https://api.github.com/repos/{$repo['username']}/{$repo['repository']}",
				'tags_url' => "https://api.github.com/repos/{$repo['username']}/{$repo['repository']}/tags",
			);

			$settings['requires'] = (empty($meta['requires'])) ? $wp_version : $meta['requires'];
			$settings['tested']   = (empty($meta['tested'])) ? $wp_version : $meta['tested'];

			// Using folder name as key for array_key_exists() check in $this->get_plugin_info()
			$this->plugins[$key] = wp_parse_args( $settings, $this->defaults );

			$this->set_new_version_and_zip_url( $key );
			$this->set_last_updated( $key );
			$this->set_description( $key );

		}

		// Refresh plugin list and Git metadata every 6 hours
		set_site_transient( 'git_plugins', $this->plugins, 60*60*6 );

	}


	/**
	 * Callback fn for the http_request_timeout filter
	 *
	 * @since 1.0
	 * @return int timeout value
	 */
	public function http_request_timeout() {
		return 2;
	}


	/**
	 * Additional headers
	 *
	 * @return array plugin header search terms
	 */
	public function extra_plugin_headers() {
		return array( 'requires', 'tested' );
	}


	/**
	 * Disable SSL only for git repo URLs, but no other HTTP requests
	 *	Allows SSL to be disabled for zip are downloadeds outside plugin scope
	 *
	 * @return array $args http_request_args
	 */
	public function disable_git_ssl_verify($args, $url) {
		if ( empty($this->plugins)) {
			return;
		}
		if ( empty($this->git_urls) ) {
			foreach( $this->plugins as $plugin ) {
				$this->git_urls[] = $plugin['homepage'];
				$this->git_urls[] = $plugin['api_url'];
				$this->git_urls[] = $plugin['tags_url'];
				$this->git_urls[] = $plugin['zip_url'];
			}
		} 
		if ( in_array($url, $this->git_urls) ) {
			$args['sslverify'] = false; 
		}

		return $args;
	}


	/**
	 * Delete transients (runs when WP_DEBUG is on)
	 * For testing purposes the site transient will be reset on each page load
	 *
	 * @since 1.0
	 * @return void
	 */
	public function delete_transients() {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( 'git_plugins' );
	}


	/**
	 * Get New Version from github
	 *
	 * @since 1.0
	 * @return void
	 */
	public function set_new_version_and_zip_url( $key ) {
		$plugin = $this->plugins[$key];

		$raw_response = wp_remote_get( $plugin['tags_url'] );

		if ( is_wp_error( $raw_response ) )
			return false;

		$tags = json_decode( $raw_response['body'] );
			
		$version = false;
		$zip_url = false;
		foreach ( $tags as $tag ) {
			if ( version_compare($tag->name, $version, '>=') ) {
				$version = $tag->name;
				$zip_url = $tag->zipball_url;
			}
		}

		$this->plugins[ $key ]['new_version'] = $version;
		$this->plugins[ $key ]['zip_url'] = $zip_url;

	}

	/**
	 * Check if a URI is a github repo.
	 * Return host, username, and repository name if so.
	 *
	 * @return array host, username, repository
	 */
	public function get_repo_parts( $uri ) {
		$parsed = parse_url( $uri );
		
		if ( false !== strpos($parsed['host'], 'github.com') ) {
			list( /*nothing*/, $username, $repository ) = explode('/', $parsed['path'] );
			return array(
				'host' => $parsed['host'],
				'username' => $username,
				'repository' => $repository,
			);
		}else {
			return false;
		}
	}


	/**
	 * Get GitHub Data from the specified repository
	 *
	 * @since 1.0
	 * @return array $github_data the data
	 */
	public function get_github_data( $key ) {

		$plugin = $this->plugins[$key];
		$github_data = $plugin['github_data'];

		if ( ! isset( $github_data ) || ! $github_data || '' == $github_data ) {
			$github_data = wp_remote_get( $plugin['api_url'] );

			if ( is_wp_error( $github_data ) )
				return false;

			$github_data = json_decode( $github_data['body'] );

			$this->plugins[$key]['github_data'] = $github_data;
		}

		return $github_data;
	}


	/**
	 * Get update date
	 *
	 * @since 1.0
	 * @return string $date the date
	 */
	public function set_last_updated( $key ) {
		$_date = $this->get_github_data( $key );
		return ( !empty($_date->updated_at) ) ? date( 'Y-m-d', strtotime( $_date->updated_at ) ) : false;
	}


	/**
	 * Get plugin description
	 *
	 * @since 1.0
	 * @return string $description the description
	 */
	public function set_description( $key ) {
		$_description = $this->get_github_data( $key );
		return ( !empty($_description->description) ) ? $_description->description : false;
	}


	/**
	 * Hook into the plugin update check and connect to github
	 *
	 * @since 1.0
	 * @param object $transient the plugin data transient
	 * @return object $transient updated plugin data transient
	 */
	public function api_check( $transient ) {

		// Check if the transient contains the 'checked' information
		// If not, just return its value without hacking it
		if ( empty( $transient->checked ) )
			return $transient;

		foreach( $this->plugins as $plugin ) {
			// check the version and decide if it's new
			$update = version_compare( $plugin['new_version'], $plugin['version'] );

			if ( 1 === $update ) {
				$response = new stdClass;
				$response->new_version = $plugin['new_version'];
				$response->slug = $plugin['folder_name'];
				$response->url = $plugin['homepage'];
				$response->package = $plugin['zip_url'];

				// If response is false, don't alter the transient
				if ( false !== $response )
					$transient->response[ $plugin['slug'] ] = $response;
			}
		}

		return $transient;
	}


	/**
	 * Get Plugin info
	 *
	 * @since 1.0
	 * @param bool $false always false
	 * @param string $action the API function being performed
	 * @param object $args plugin arguments
	 * @return object $response the plugin info
	 */
	public function get_plugin_info( $false, $action, $response ) {
		// Check if this call API is for the right plugin
		if ( !array_key_exists($response->slug, $this->plugins) )
			return false;

		$plugin = $this->plugins[ $response->slug ];

		$response->slug = $plugin['slug'];
		$response->plugin_name  = $plugin['name'];
		$response->version = $plugin['new_version'];
		$response->author = $plugin['author'];
		$response->homepage = $plugin['homepage'];
		$response->requires = $plugin['requires'];
		$response->tested = $plugin['tested'];
		$response->downloaded   = 0;
		$response->last_updated = $plugin['last_updated'];
		$response->sections = array( 'description' => $plugin['description'] );
		$response->download_link = $plugin['zip_url'];

		return $response;
	}


	/**
	 * Upgrader/Updater
	 * Move & activate the plugin, echo the update message
	 *
	 * @since 1.0
	 * @param boolean $true always true
	 * @param mixed $hook_extra not used
	 * @param array $result the result of the move
	 * @return array $result the result of the move
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) {

		global $wp_filesystem;

		$plugin = $this->plugins[ $hook_extra['plugin'] ];

		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR.'/'.$plugin['folder_name'];
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR.'/'.$plugin['slug'] );

		// Output the update message
		$fail		= __('The plugin has been updated, but could not be reactivated. Please reactivate it manually.', 'github_plugin_updater');
		$success	= __('Plugin reactivated successfully.', 'github_plugin_updater');
		echo is_wp_error( $activate ) ? $fail : $success;
		return $result;

	}

}

endif; // endif class exists