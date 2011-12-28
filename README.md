WordPress Github Plugin Updater

This class is meant to be used with your Github hosted WordPress plugins. The purpose of the class is to allow your WordPress plugin to be updated whenever you push out a new version of your plugin; similarly to the experience users know and love with the WordPress.org plugin repository. 

Not all plugins can or should be hosted on the WordPress.org plugin repository, or you may chose to host it on github only. 

The code is still in it's infancy, but [I am currently using it](https://github.com/jkudish/JigoShop-Software-Add-on) on a production plugin and production website, without any glitches. That being said, please consider this as a beta release. The project started off as a private client request, but is now public for anyone to collaborate on. I am open to any suggestions :)

Usage instructions
===========

* The class should be included somewhere in your plugin. You will need to require the file (example: `include_once('updater.php');`). 
* You will need to initialize the class using something similar to this:
	
	<pre>
	if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
		$config = array(
			'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
			'proper_folder_name' => 'plugin-name', // this is the name of the folder your plugin lives in
			'api_url' => 'https://api.github.com/repos/jkudish/WordPress-GitHub-Plugin-Updater', // the github API url of your github repo
			'raw_url' => 'https://raw.github.com/jkudish/WordPress-GitHub-Plugin-Updater', // the github raw url of your github repo
			'github_url' => 'https://github.com/jkudish/WordPress-GitHub-Plugin-Updater', // the github url of your github repo
			'zip_url' => 'https://github.com/jkudish/JigoShop-Software-Add-on/zipball/master', // the zip url of the github repo
			'sslverify' => true // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
			'requires' => '3.0', // which version of WordPress does your plugin require?
			'tested' => '3.3', // which version of WordPress is your plugin tested up to?
		);
		new wp_github_updater($config);
	}
	</pre>	
	
* In your Github repository, you will need to include the following line (formatted exactly like this) anywhere in your Readme file: 

	`~Current Version:1.0.3~`

* You will need to update the version number anytime you update the plugin, this will ultimately let the plugin know that a new version is available.

* **Note**: this class will unfortunately not work with a private repository, your repository needs to be publicly accessible. If anyone knows how to make this work for private repositories, please get in touch!

FAQ
===========

Q: I am getting the following error:
	<pre>
	Download failed. SSL certificate problem, verify that the CA cert is OK. Details: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed
	</pre>

A: See the discussion and answer [here](https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2)

**UPDATE**: this is now fixed in the class thanks to [@pmichael](https://github.com/pmichael), [details here](https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4)


Changelog
===========

### 1.0.3
* Fixed sslverify issue, props [@pmichael](https://github.com/pmichael)


### 1.0.2
* Fixed potential timeout

### 1.0.1
* Fixed potential fatal error with wp_error

### 1.0
* Initial Public Release

Credits
===========

This class is built and maintained by [Joachim Kudish](http://jkudish.com "Joachim Kudish")

License
===========

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to:

Free Software Foundation, Inc.
51 Franklin Street, Fifth Floor,
Boston, MA
02110-1301, USA.