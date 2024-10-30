<?php

/**
 * Plugin Name: WordPress Copy Page | Copy Post | Copy Custom Post Type
 * Plugin URI: https://wordpress.org/plugins/copy-page-post/
 * Description: The plugin allows users to copy or duplicate a post or page with a single click.
 * Version: 1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: GPressTheme
 * Author URI: https://profiles.wordpress.org/gpresstheme/
 * License: GPL-2.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Add the 'Copy' link to the post/page row actions
function cpp_duplicate_post_link( $actions, $post ) {
    if ( current_user_can( 'edit_posts' ) && in_array( $post->post_type, array( 'post', 'page' ) ) ) {
        $actions['duplicate'] = '<a href="' . wp_nonce_url( 'admin.php?action=cpp_duplicate_post&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
    }
    return $actions;
}
add_filter( 'post_row_actions', 'cpp_duplicate_post_link', 10, 2 );
add_filter( 'page_row_actions', 'cpp_duplicate_post_link', 10, 2 );

// Handle the duplication process
function cpp_duplicate_post() {
    if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'cpp_duplicate_post' == $_REQUEST['action'] ) ) ) {
        wp_die( esc_html( 'Post creation failed, could not find original post.' ) );
    }

    // Nonce verification
    if ( ! isset( $_GET['duplicate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['duplicate_nonce'] ) ), basename( __FILE__ ) ) ) {
        return;
    }

    // Get the original post id
    $post_id = ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
    // Get the original post object
    $post = get_post( $post_id );

    if ( ! $post ) {
        wp_die( esc_html( 'Post creation failed, could not find original post.' ) );
    }

    // Copy the post data
    $new_post = array(
        'post_title'    => $post->post_title . ' (Copy)',
        'post_content'  => $post->post_content,
        'post_status'   => 'draft',
        'post_type'     => $post->post_type,
        'post_author'   => $post->post_author,
        'post_category' => wp_get_post_categories( $post_id ),
    );

    // Insert the post into the database
    $new_post_id = wp_insert_post( $new_post );

    // Copy post meta
    $post_meta = get_post_meta( $post_id );
    foreach ( $post_meta as $key => $values ) {
        foreach ( $values as $value ) {
            update_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
        }
    }

    // Copy post terms
    $taxonomies = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
        wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
    }

    // Redirect to the edit post screen for the new draft
    wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
    exit;
}
add_action( 'admin_action_cpp_duplicate_post', 'cpp_duplicate_post' );
