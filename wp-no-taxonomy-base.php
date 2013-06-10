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
				add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
				add_filter( 'template_redirect', array( $this, 'redirect' ) );

				add_action( 'created_category', array( $this, 'flush_rules' ) );
				add_action( 'delete_category', array( $this, 'flush_rules' ) );
				add_action( 'edited_category', array( $this, 'flush_rules' ) );

				add_filter( 'category_rewrite_rules', array( $this, 'add_rules' ) );

				add_filter( 'term_link', array( $this, 'correct_term_link' ), 10, 3 );
			}

			public function load_textdomain() {
				load_plugin_textdomain( 'wp-no-taxonomy-base', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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


			public function show_page() {
				 /** @todo make frontend look better */
				 /** @todo UX - notifications after an update */

				if( ! current_user_can('manage_options') )
					wp_die( __('You do not have sufficient permissions to access this page.') );

				if( isset( $_POST['vesave'] ) && $_POST['vesave'] == 'save' ) {
					if( isset( $_POST['wp-no-taxonomy-base-nonce'] ) && wp_verify_nonce( $_POST['wp-no-taxonomy-base-nonce'], 'wp-no-taxonomy-base-update-taxonomies' ) ) {
						update_option( 'WP_No_Taxonomy_Base', ( isset( $_POST['WP_No_Taxonomy_Base'] ) ) ? $_POST['WP_No_Taxonomy_Base'] : false );

						$this->flush_rules();
					}
				}

				$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' ); 
				$selected   = get_option('WP_No_Taxonomy_Base');

				if( ! $selected )
					$selected = array();

		 ?>
			<div class="wrap">

				<h1><?php _e('WP No Taxonomy Base', 'wp-no-taxonomy-base'); ?></h1>

				<p><?php _e('Want to remove the base for a taxonomy? Just select the taxonomy below and click "Save".', 'wp-no-taxonomy-base'); ?></p>

				<form method="post">
					<?php wp_nonce_field( 'wp-no-taxonomy-base-update-taxonomies', 'wp-no-taxonomy-base-nonce' ); ?>
					<input type="hidden" name="vesave" value="save" />

					<table>
					<?php
						foreach( $taxonomies as $taxonomy ) {
							$active = in_array( $taxonomy->name, $selected ) ? 'checked="checked"' : '';

							printf(
							'
								<tr>
									<td> <label>%s</label> </td>
									<td> <input type="checkbox" name="WP_No_Taxonomy_Base[]" value="%s" %s /> </td>
								</tr>
							'
							, $taxonomy->label
							, $taxonomy->name
							, $active
							);
						}
					?>
					</table>

					<button class="button-primary"><?php _e('Save'); ?></button>

				</form>

			</div>

			<?php

			}

		} // END class WP_No_Taxonomy_Base

		$wp_no_taxonomy_base = new WP_No_Taxonomy_Base();

} // END if class_exists