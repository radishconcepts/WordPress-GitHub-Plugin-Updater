<?php
# -*- coding: utf-8 -*-
declare ( encoding = 'UTF-8' );

// Prevent loading this file directly - Busted!
if ( ! class_exists('WP') ) 
{
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}



if ( ! class_exists( 'wp_github_updater' ) ) :

/**
 * @version 1.1
 * @author Joachim Kudish <info@jkudish.com>
 * @link http://jkudish.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright Copyright (c) 2011, Joachim Kudish
 *
 * GNU General Public License, Free Software Foundation 
 * <http://creativecommons.org/licenses/GPL/2.0/>
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
class wp_github_updater {
	/**
	 * i18n Text Domain
	 * @var string 
	 */
	const LANG = 'kudish_updater';


	/**
	 * Construct
	 * @since 
	 * 
	 * @param array $config
	 * 
	 * @return void
	 */
	public function __construct( $config = array() ) {
		global $wp_version;

		$g = 'github.com';
		$defaults = array(
			'slug'					=> plugin_basename( __FILE__ ),
			'proper_folder_name'	=> plugin_basename( __FILE__ ),
			'api_url'				=> "https://api.{$g}/repos/jkudish/WordPress-GitHub-Plugin-Updater",
			'raw_url'				=> "https://raw.{$g}/jkudish/WordPress-GitHub-Plugin-Updater/master",
			'github_url'			=> "https://{$g}/jkudish/WordPress-GitHub-Plugin-Updater",
			'zip_url'				=> "https://{$g}/jkudish/WordPress-GitHub-Plugin-Updater/zipball/master",
			'sslverify'				=> true,
	    	'requires'				=> $wp_version,
        	'tested'				=> $wp_version,
		);	

		$this->config = wp_parse_args( $config, $defaults );

		$this->set_defaults();

		if ( WP_DEBUG )
			add_action( 'init', array( $this, 'delete_transients' ) );

		if ( ! defined( 'WP_MEMORY_LIMIT' ) )
			define( 'WP_MEMORY_LIMIT', '96M' );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'api_check' ) );

		// Hook into the plugin details screen
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );

		// set timeout
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );
	}


	/**
	 * Set defaults
	 * Fill in all optional values, if not set
	 * @since 
	 * 
	 * @return void
	 */
	public function set_defaults() {
		if ( ! isset( $this->config['new_version'] ) )
			$this->config['new_version'] = $this->get_new_version();

		if ( ! isset( $this->config['last_updated'] ) )
			$this->config['last_updated'] = $this->get_date();

		if ( ! isset( $this->config['description'] ) )
			$this->config['description'] = $this->get_description();

		$plugin_data = $this->get_plugin_data();
		if ( ! isset( $this->config['plugin_name'] ) )
			$this->config['plugin_name'] = $plugin_data['Name'];

		if ( ! isset( $this->config['version'] ) )
			$this->config['version'] = $plugin_data['Version'];

		if ( ! isset( $this->config['author'] ) )
			$this->config['author'] = $plugin_data['Author'];

		if ( ! isset( $this->config['homepage'] ) )
			$this->config['homepage'] = $plugin_data['PluginURI'];
	}


	/**
	 * Callback fn for the http_request_timeout filter
	 * @since 
	 * 
	 * @return int $timeout
	 */
	public function http_request_timeout() {
		return 2;
	}


	/**
	 * Delete transients
	 * For testing purpose, the site transient will be reset on each page load
	 * @since 
	 * 
	 * @return void
	 */
	public function delete_transients() {
		delete_site_transient( 'update_plugins' );
		delete_site_transient( "{$this->config['slug']}_new_version" );
		delete_site_transient( "{$this->config['slug']}_github_data" );
		delete_site_transient( "{$this->config['slug']}_changelog" );
	}


	/**
	 * Get New Version
	 * @since
	 * 
	 * @return int $version
	 */
	public function get_new_version() {
		$version = get_site_transient( "{$this->config['slug']}_new_version" );

		if ( ! isset( $version ) || ! $version || '' == $version ) {
			$raw_response = wp_remote_get( "{$this->config['raw_url']}/README.md", $this->config['sslverify'] );

			if ( is_wp_error( $raw_response ) )
				return false;

			$__version	= explode( '~Current Version:', $raw_response['body'] );
			$_version	= explode( '~', $__version[1] );
			$version	= $_version[0];
			// refresh every 6 hours
			set_site_transient( "{$this->config['slug']}_new_version", $version, 60*60*6 );
		}

		return $version;
	}


	/**
	 * Get GitHub Data
	 * @uses wp_remote_get();
	 * @since 
	 * 
	 * @return 
	 */
	public function get_github_data() {
		$github_data = get_site_transient( "{$this->config['slug']}_github_data" );

		if ( ! isset( $github_data ) || ! $github_data || '' == $github_data ) {		
			$github_data = wp_remote_get( $this->config['api_url'], $this->config['sslverify'] );

			if ( is_wp_error( $github_data ) )
				return false;

			$github_data = json_decode( $github_data['body'] );
			// refresh every 6 hours
			set_site_transient( "{$this->config['slug']}_github_data", $github_data, 60*60*6);
		}

		return $github_data;			
	}


	/**
	 * Get Date
	 * @since
	 * 
	 * @return string $date
	 */
	public function get_date() {
		$_date	= $this->get_github_data();
		$date	= $_date->updated_at;
		$date	= date( 'Y-m-d', strtotime( $_date->updated_at ) );
		return $date;
	}


	/**
	 * Get description
	 * @since 
	 * 
	 * @return string $description
	 */
	public function get_description() {
		$_description = $this->get_github_data();
		return $_description->description;
	}


	/**
	 * Get Plugin Data
	 * @since 
	 * 
	 * @return object $data
	 */
	public function get_plugin_data() {
		include_once( ABSPATH.'/wp-admin/includes/plugin.php' );
		$data = get_plugin_data( WP_PLUGIN_DIR."/{$this->config['slug']}" );
		return $data;
	}


	/**
	 * API Check
	 * Hook into the plugin update check
	 * @since 
	 * 
	 * @param object $transient
	 * 
	 * @return object $transient
	 */
	public function api_check( $transient ) {
		// Check if the transient contains the 'checked' information
		// If not, just return its value without hacking it
		if ( empty( $transient->checked ) )
			return $transient;
		
		// check the version and make sure it's new
		$update = version_compare( $this->config['new_version'], $this->config['version'] );
		if ( 1 === $update ) {		
			$response = new stdClass;
			$response->new_version	= $this->config['new_version'];
			$response->slug			= $this->config['slug'];		
			$response->url			= $this->config['github_url'];
			$response->package		= $this->config['zip_url'];

			// If response is false, don't alter the transient
			if ( false !== $response )
				$transient->response[ $this->config['slug'] ] = $response;
		}			

		return $transient;
	}


	/**
	 * Get Plugin info
	 * @since 
	 * 
	 * @param boolean $false
	 * @param unknown_type $action
	 * @param unknown_type $result
	 * 
	 * @return unknown_type $args
	 */
	public function get_plugin_info( $false, $action, $args ) {
		$plugin_slug = plugin_basename( __FILE__ );

		// Check if this plugins API is about this plugin
		if ( $args->slug != $this->config['slug'] )
			return false;

	    $response->slug			= $this->config['slug'];
	    $response->plugin_name	= $this->config['plugin_name'];
	    $response->version		= $this->config['new_version'];
	    $response->author		= $this->config['author'];
	    $response->homepage		= $this->config['homepage'];
	    $response->requires		= $this->config['requires'];
	    $response->tested		= $this->config['tested'];
	    $response->downloaded	= 0;
	    $response->last_updated	= $this->config['last_updated'];
	    $response->sections		= array(
	        'description' => $this->config['description']
	    );        
	    $response->download_link = $this->config['zip_url'];

		return $response;
	}


	/**
	 * Upgrader/Updater
	 * Move & activate the plugin, echo the update message
	 * @since 
	 * 
	 * @param boolean $true
	 * @param unknown_type $hook_extra
	 * @param unknown_type $result
	 * 
	 * @return unknown_type $result
	 */
	public function upgrader_post_install( $true, $hook_extra, $result ) {
		global $wp_filesystem;

		// Move & Activate
		$proper_destination = WP_PLUGIN_DIR."/{$this->config['proper_folder_name']}";
		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination'] = $proper_destination;
		$activate = activate_plugin( WP_PLUGIN_DIR."/{$this->config['slug']}" );

		// Output the update message
		$fail		= __( 
			 'The plugin has been updated, but could not be reactivated. Please reactivate it manually.'
			,self :: LANG 
		);
		$success	= __( 
			 'Plugin reactivated successfully.'
			,self :: LANG 
		);
		echo is_wp_error( $activate ) ? $fail : $success;

		return $result;
	}
			
}

endif; // endif class exists