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

if ( !class_exists('WP_No_Taxonomy_Base') ) {
    
    class WP_No_Taxonomy_Base {

      public function __construct() {

        add_action('admin_menu'             , array($this , 'add_page'    )  ) ;
        add_action('created_category'       , array($this , 'flush_rules' )  ) ;
        add_action('delete_category'        , array($this , 'flush_rules' )  ) ;
        add_action('edited_category'        , array($this , 'flush_rules' )  ) ;
        add_action('init'                   , array($this , 'redirect'    )  ) ;

        add_filter('category_rewrite_rules' , array($this , 'add_rules'   )  ) ;

        add_filter('term_link'              , array($this , 'correct_term_link' ), 10, 3 ) ;

        // load textdomain - to move?
        $this->textdomain;
      }

      public function textdomain() {
        load_plugin_textdomain( 'wp-no-taxonomy-base', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
      }

      public function flush_rules() {

        global $wp_rewrite;
        $wp_rewrite->flush_rules();

      }

      public function redirect() {
        $request    = $_SERVER['REQUEST_URI'];
        $host       = $_SERVER['HTTP_HOST'];
        $redirect   = false;
        $blogurl    = get_bloginfo('url');
        $taxonomies = get_option('WP_No_Taxonomy_Base');
        $http       = ( strrpos($blogurl, 'https://') === false ) ? 'http://' : 'https://';

        /** Bail */
        if(!$taxonomies)
          return false;

        /** build the URL */
        $url = $http . $host . $request;

        foreach( $taxonomies as $term ) {

          /**
           * If the url contains a taxonomy term base
           * then redirect it to the new page.
           * Only redirect one time.
           * -------------------------------------------- */
          if( strrpos($url, '/' . $term . '/') && !$redirect) {

            $new_url = str_replace('/' . $term . '/', '/', $url);

            wp_redirect($new_url, 301);

            $redirect = true;
            die();

          }

        }

      }

      public function add_rules($rules) {

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
        if(!$taxonomies)
          return $rules;


        $args  = array('hide_empty' => false);

        /**
         * Loop em.
         * -------------------------------------------- */
        foreach( $taxonomies as $taxonomy ) {

          $categories = get_terms($taxonomy, $args);

          foreach($categories as $category) {

            $slug = $category->slug;

            $feed_rule  = sprintf('index.php?taxonomy=%s&term=%s&feed=$matches[1]'  , $taxonomy , $slug);
            $paged_rule = sprintf('index.php?taxonomy=%s&term=%s&paged=$matches[1]' , $taxonomy , $slug);
            $base_rule  = sprintf('index.php?taxonomy=%s&term=%s'                   , $taxonomy , $slug);

            $rules[$slug . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = $feed_rule;
            $rules[$slug . '/page/?([0-9]{1,})/?$']                  = $paged_rule;
            $rules[$slug . '/?$']                                    = $base_rule;

          }

        }

        return $rules;

      }


      public function correct_term_link( $link, $feed, $taxonomy ) {
        $taxonomies = get_option('WP_No_Taxonomy_Base');

        /** Bail */
        if( ! $taxonomies )
          return false;

        if( in_array( $taxonomy, $taxonomies ) )
          $link = str_replace( $taxonomy . '/', '', $link );

        return $link;
      }


      public function add_page() {

        add_submenu_page('options-general.php', __('WP No Taxonomy Base', 'wp-no-taxonomy-base'), __('WP No Taxonomy Base', 'wp-no-taxonomy-base'), 'manage_options', 'wp-no-taxonomy-base', array($this, 'show_page'));

      }

      public function show_page() {

         /** @todo internationalization */
         /** @todo make frontend look better */
         /** @todo UX - notifications after an update */

        if(!current_user_can('manage_options'))
          wp_die( __('You do not have sufficient permissions to access this page.') );

        if(isset($_POST['vesave']) && $_POST['vesave'] == 'save') {

          update_option( 'WP_No_Taxonomy_Base', ( isset($_POST['WP_No_Taxonomy_Base']) ) ? $_POST['WP_No_Taxonomy_Base'] : false );

          $this->flush_rules();

        }

        $taxonomies = get_taxonomies( array('public' => true) ); 
        $selected = get_option('WP_No_Taxonomy_Base');

        if(!$selected)
          $selected = array();

     ?>
      <div class="wrap">

        <h1><?php _e('WP No Taxonomy Base', 'wp-no-taxonomy-base'); ?></h1>

        <p><?php sprintf( __('Want to remove the base for a taxonomy? Just select the taxonomy below and click "%s".', 'wp-no-taxonomy-base'), __('Save') ); ?></p>

        <form method="post">

          <input type="hidden" name="vesave" value="save" />

          <table>

          <?php 

            foreach( $taxonomies as $taxonomy ) {

              $active = in_array($taxonomy, $selected) ? 'checked="checked"' : '';

              printf(
              '
                <tr>
                  <td> <label>%s</label> </td>
                  <td> <input type="checkbox" name="WP_No_Taxonomy_Base[]" value="%s" %s /> </td>
                </tr>
              '
              , $taxonomy
              , $taxonomy
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

    $no_base = new WP_No_Taxonomy_Base();

} // END if class_exists
