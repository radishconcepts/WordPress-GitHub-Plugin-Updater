<?php

/*
Plugin Name: WP Github Plugin Updater Test
Plugin URI: https://github.com/jkudish/WordPress-GitHub-Plugin-Updater
Description: Semi-automated test for the Github Plugin Updater
Version: 0.1
Author: Joachim Kudish
Author URI: http://jkudish.com/
License: GPLv2
*/

/**
 * Note: the version # above is purposely low in order to be able to test the updater
 * The real version # is below
 * @package GithubUpdater
 * @author Joachim Kudish @link http://jkudish.com
 * @since 1.3
 * @version 1.3
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


add_action('init', 'github_plugin_updater_test_init');
function github_plugin_updater_test_init() {

	include_once('updater.php');

	define('WP_GITHUB_FORCE_UPDATE', true);

	if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin

		$config = array(
			'slug' => plugin_basename(__FILE__),
			'proper_folder_name' => 'github-updater',
			'api_url' => 'https://api.github.com/repos/jkudish/WordPress-GitHub-Plugin-Updater',
			'raw_url' => 'https://raw.github.com/jkudish/WordPress-GitHub-Plugin-Updater/master',
			'github_url' => 'https://github.com/jkudish/WordPress-GitHub-Plugin-Updater',
			'zip_url' => 'https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/zipball/master',
			'sslverify' => true,
			'requires' => '3.0',
			'tested' => '3.3',
			'readme' => 'README.md'
		);

		new WPGitHubUpdater($config);

	}

}