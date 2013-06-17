<?php
/*
Plugin Name: WP No Taxonomy Base
Plugin URI: http://markoheijnen.com
Description: Remove base slug from your custom taxonomy terms.
Version: 1.1
Author: Marko Heijnen
Author URI: http://markoheijnen.com
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-no-taxonomy-base
Domain Path: /languages
*/

/*
	Copyright 2013 Marko Heijnen

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//avoid direct calls to this file, because now WP core and framework has been used
//implementation by Frank Bültge (https://github.com/bueltge)
if ( ! function_exists( 'add_filter' ) ) {
		header('Status: 403 Forbidden');
		header('HTTP/1.1 403 Forbidden');
		exit();
}

if ( ! class_exists('WP_No_Taxonomy_Base') ) {
		
		class WP_No_Taxonomy_Base {

			public function __construct() {
				add_filter( 'template_redirect', array( $this, 'redirect' ) );

				add_action( 'admin_init', array( $this, 'settings_init' ) );
				add_action( 'current_screen', array( $this, 'settings_save' ) );

				add_action( 'created_category', array( $this, 'flush_rules' ) );
				add_action( 'delete_category', array( $this, 'flush_rules' ) );
				add_action( 'edited_category', array( $this, 'flush_rules' ) );

				add_filter( 'category_rewrite_rules', array( $this, 'add_rules' ) );

				add_filter( 'term_link', array( $this, 'correct_term_link' ), 10, 3 );
			}

			public function flush_rules() {
				global $wp_rewrite;

				$wp_rewrite->flush_rules();
			}

			public function redirect() {
				global $wp, $wp_query;

				if( is_category() || is_tag() || is_tax() ) {
					$taxonomies = get_option('WP_No_Taxonomy_Base');
					$taxonomy   = get_queried_object()->taxonomy;

					/** Bail */
					if( ! $taxonomies )
						return false;

					if( in_array( $taxonomy, $taxonomies ) ) {
						$url = home_url( $wp->request );

						if( strrpos( $url, '/' . $taxonomy . '/' ) ) {
							$new_url = str_replace( '/' . $taxonomy . '/', '/', $url );

							wp_redirect( $new_url, 301 );
							die();
						}
					}
				}
			}

			public function add_rules( $rules ) {
				/**
				 * @todo 
				 *
				 * Create rewrite rules for terms when 
				 * they are nested under a parent term.
				 *
				 * Example: 
				 * http://#{base_url}/#{parent_term}/#{child_term}
				 *
				 * -------------------------------------------- */

				$taxonomies = get_option('WP_No_Taxonomy_Base');

				/** Time to bail. */
				if( ! $taxonomies )
					return $rules;

				$args  = array( 'hide_empty' => false );

				/**
				 * Loop em.
				 * -------------------------------------------- */
				foreach( $taxonomies as $taxonomy ) {
					$categories = get_terms( $taxonomy, $args );

					foreach( $categories as $category ) {
						$slug = $category->slug;

						$feed_rule  = sprintf( 'index.php?taxonomy=%s&term=%s&feed=$matches[1]'  , $taxonomy , $slug );
						$paged_rule = sprintf( 'index.php?taxonomy=%s&term=%s&paged=$matches[1]' , $taxonomy , $slug );
						$base_rule  = sprintf( 'index.php?taxonomy=%s&term=%s'                   , $taxonomy , $slug );

						$rules[ $slug . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = $feed_rule;
						$rules[ $slug . '/page/?([0-9]{1,})/?$' ]                  = $paged_rule;
						$rules[ $slug . '/?$' ]                                    = $base_rule;
					}
				}

				return $rules;
			}


			public function correct_term_link( $link, $feed, $taxonomy ) {
				$taxonomies = get_option('WP_No_Taxonomy_Base');

				if( $taxonomies ) {
					if( in_array( $taxonomy, $taxonomies ) )
						$link = str_replace( $taxonomy . '/', '', $link );
				}

				return $link;
			}
			
			
			function settings_init(){
				add_settings_section( 
					'wp-no-taxonomy-base-settings', 
					__('Taxonomy Base', 'wp-no-taxonomy-base'), 
					array( $this, 'show_description'), 
					'permalink'
				);

				$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

				foreach( $taxonomies as $taxonomy ) {
					add_settings_field(
						'wp-no-taxonomy-base-settings-' . $taxonomy->name, // id
						$taxonomy->label,
						array( $this, 'show_page'), // display callback
						'permalink', // settings page
						'wp-no-taxonomy-base-settings', // settings section
						array( 'taxonomy' => $taxonomy )
					);
				}
			}

			public function settings_save( $screen ) {
				if( 'options-permalink' == $screen->base && isset( $_POST['wp-no-taxonomy-base-nonce'] ) ) {
					if( wp_verify_nonce( $_POST['wp-no-taxonomy-base-nonce'], 'wp-no-taxonomy-base-update-taxonomies' ) ) {
						update_option( 'WP_No_Taxonomy_Base', ( isset( $_POST['WP_No_Taxonomy_Base'] ) ) ? $_POST['WP_No_Taxonomy_Base'] : false );
					}
				}
                                load_plugin_textdomain( 'wp-no-taxonomy-base', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
			}

			public function show_description() {
				echo '<p>' . __('You can remove the base from all registered taxonomies. Just select the taxonomies to remove their respective bases from your permalinks.', 'wp-no-taxonomy-base');
				wp_nonce_field( 'wp-no-taxonomy-base-update-taxonomies', 'wp-no-taxonomy-base-nonce' );
			}
			
			public function show_page( $args ) {
				$taxonomy  = $args['taxonomy'];
				$selected  = get_option( 'WP_No_Taxonomy_Base', array() );
				$active    = in_array( $taxonomy->name, $selected ) ? 'checked="checked"' : '';
				$id        = esc_attr( 'wp-no-taxonomy-base-' . $taxonomy->name );
				$cpts      = array();

				foreach( $taxonomy->object_type as $object_type ) {
					$cpts[] = get_post_type_object( $object_type );
				}

				$cpt_names = implode( ', ', wp_list_pluck( $cpts, 'label' ) );
				
				if ( '' == $active )
                                        $slug = sprintf( __( 'Activate to remove Slug %s from <abbr title="Post Type">PT(s)</abbr> %s', 'wp-no-taxonomy-base' ), '<code><b>' . $taxonomy->rewrite['slug'] . '</b></code>', '<i>' . $cpt_names . '</i>' );
				else
					$slug = sprintf( __( 'Slug %s is being removed from <abbr title="Post Type">PT(s)</abbr> %s', 'wp-no-taxonomy-base' ), '<code><b>' . $taxonomy->rewrite['slug'] . '</b></code>', '<i>' . $cpt_names . '</i>' );

				printf(
					'
						<input type="checkbox" id="' . $id . '" name="WP_No_Taxonomy_Base[]" value="%s" %s />
						<label for="' . $id . '"> %s </label>
					',
					$taxonomy->name,
					$active,
					$slug
				);

			}

		} // END class WP_No_Taxonomy_Base

		$wp_no_taxonomy_base = new WP_No_Taxonomy_Base();

} // END if class_exists