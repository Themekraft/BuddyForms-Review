<?php
/*
 * Plugin Name: BuddyForms Moderation ( Former: Review Logic )
 * Plugin URI: https://themekraft.com/products/review/
 * Description: Create new drafts or pending moderations from new or published posts without changing the live version.
 * Version: 1.4.1
 * Author: ThemeKraft
 * Author URI: https://themekraft.com/buddyforms/
 * License: GPLv2 or later
 * Network: false
 * Svn: buddyforms-review
 *
 * @fs_premium_only /includes/moderators.php, /includes/moderators-taxonomy.php, /includes/moderators-reject.php
 *
 *****************************************************************************
 *
 * This script is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 ****************************************************************************
 */

add_action( 'init', 'bf_moderation_includes', 10 );
function bf_moderation_includes() {
	global $buddyforms_new;
	if ( ! empty( $buddyforms_new ) ) {
		include_once( dirname( __FILE__ ) . '/includes/buddyforms-moderation.php' );
		include_once( dirname( __FILE__ ) . '/includes/form-elements.php' );
		include_once( dirname( __FILE__ ) . '/includes/duplicate-post.php' );
		include_once( dirname( __FILE__ ) . '/includes/functions.php' );
		if ( bfmod_fs()->is__premium_only() ) {
			if ( bfmod_fs()->is_plan( 'professional', true ) ) {
				include_once( dirname( __FILE__ ) . '/includes/moderators-taxonomy.php' );
				include_once( dirname( __FILE__ ) . '/includes/moderators-form-element.php' );
				include_once( dirname( __FILE__ ) . '/includes/moderators-reject.php' );
			}
		}
		include_once( dirname( __FILE__ ) . '/includes/shortcodes.php' );
		define( 'BUDDYFORMS_MODERATION_ASSETS', plugins_url( 'assets/', __FILE__ ) );
	}

	// Only Check for requirements in the admin
	if ( ! is_admin() ) {
		return;
	}

	// Require TGM
	require( dirname( __FILE__ ) . '/includes/resources/tgm/class-tgm-plugin-activation.php' );

	// Hook required plugins function to the tgmpa_register action
	add_action( 'tgmpa_register', 'buddyform_moderation_dependency' );
}

function buddyform_moderation_dependency() {
	// Create the required plugins array
	if ( ! defined( 'BUDDYFORMS_PRO_VERSION' ) ) {
		$plugins['buddyforms'] = array(
			'name'     => 'BuddyForms',
			'slug'     => 'buddyforms',
			'required' => true,
		);

		$config = array(
			'id'           => 'buddyforms-tgmpa',
			// Unique ID for hashing notices for multiple instances of TGMPA.
			'parent_slug'  => 'plugins.php',
			// Parent menu slug.
			'capability'   => 'manage_options',
			// Capability needed to view plugin install page, should be a capability associated with the parent menu used.
			'has_notices'  => true,
			// Show admin notices or not.
			'dismissable'  => false,
			// If false, a user cannot dismiss the nag message.
			'is_automatic' => true,
			// Automatically activate plugins after installation or not.
		);

		// Call the tgmpa function to register the required plugins
		tgmpa( $plugins, $config );
	}
}


// Create a helper function for easy SDK access.
function bfmod_fs() {
	global $bfmod_fs;

	if ( ! isset( $bfmod_fs ) ) {
		// Include Freemius SDK.
		if ( file_exists( dirname( dirname( __FILE__ ) ) . '/buddyforms/includes/resources/freemius/start.php' ) ) {
			// Try to load SDK from parent plugin folder.
			require_once dirname( dirname( __FILE__ ) ) . '/buddyforms/includes/resources/freemius/start.php';
		} else if ( file_exists( dirname( dirname( __FILE__ ) ) . '/buddyforms-premium/includes/resources/freemius/start.php' ) ) {
			// Try to load SDK from premium parent plugin folder.
			require_once dirname( dirname( __FILE__ ) ) . '/buddyforms-premium/includes/resources/freemius/start.php';
		}

		$bfmod_fs = fs_dynamic_init( array(
			'id'                  => '409',
			'slug'                => 'buddyforms-review',
			'type'                => 'plugin',
			'public_key'          => 'pk_b92e3b1876e342874bdc7f6e80d05',
			'is_premium'          => true,
			'premium_suffix'      => 'Professional',
			// If your addon is a serviceware, set this option to false.
			'has_premium_version' => true,
			'has_paid_plans'      => true,
			'trial'               => array(
				'days'               => 14,
				'is_require_payment' => true,
			),
			'parent'              => array(
				'id'         => '391',
				'slug'       => 'buddyforms',
				'public_key' => 'pk_dea3d8c1c831caf06cfea10c7114c',
				'name'       => 'BuddyForms',
			),
			'menu'                => array(
				'slug'    => 'buddyforms',
				'support' => false,
			)
		) );
	}

	return $bfmod_fs;
}

function bfmod_fs_is_parent_active_and_loaded() {
	// Check if the parent's init SDK method exists.
	return function_exists( 'buddyforms_core_fs' );
}

function bfmod_fs_is_parent_active() {
	$active_plugins_basenames = get_option( 'active_plugins' );

	foreach ( $active_plugins_basenames as $plugin_basename ) {
		if ( 0 === strpos( $plugin_basename, 'buddyforms/' ) ||
		     0 === strpos( $plugin_basename, 'buddyforms-premium/' )
		) {
			return true;
		}
	}

	return false;
}

if ( bfmod_fs_is_parent_active_and_loaded() ) {
	// If parent already included, init add-on.
	bfmod_fs();
} else if ( bfmod_fs_is_parent_active() ) {
	// Init add-on only after the parent is loaded.
	add_action( 'buddyforms_core_fs_loaded', 'bfmod_fs' );
} else {
	// Even though the parent is not activated, execute add-on for activation / uninstall hooks.
	bfmod_fs();
}


function buddyforms_modertaion_scripts() {
	//
	// todo: This is a hack to avoid issues with the jQuery in the form. Its conflicting if loaded in the loop. buddyforms/includes/resources/pfbc/Form.php Line 463
	//
	// @gfirem Why not always load jquery in the core?

	wp_enqueue_script("jquery");
}

add_action( 'wp_enqueue_scripts', 'buddyforms_modertaion_scripts' );