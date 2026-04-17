<?php
/**
 * Post list table row actions in wp-admin.
 *
 * @package Wp_Are
 */

namespace Wp_Are\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Wp_Are\Builtin_Post_Types;
use WP_Post;

/**
 * Adds a “View as AI Agent” action next to View / Preview on the posts list screen.
 */
final class Post_List {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'post_row_actions', [ $this, 'add_view_markdown_link' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_view_markdown_link' ], 10, 2 );
	}

	/**
	 * Insert “View as AI Agent” after the View / Preview action when applicable.
	 *
	 * @param string[] $actions Row action links.
	 * @param WP_Post  $post    Post object.
	 * @return string[]
	 */
	public function add_view_markdown_link( array $actions, $post ): array {
		if ( ! $post instanceof WP_Post ) {
			return $actions;
		}

		$types = apply_filters( 'wp_are_markdown_post_types', Builtin_Post_Types::default_markdown_types() );
		if ( ! in_array( $post->post_type, $types, true ) ) {
			return $actions;
		}

		$url = $this->get_markdown_view_url( $post );
		if ( null === $url ) {
			return $actions;
		}

		$link = sprintf(
			'<a href="%s" rel="bookmark">%s</a>',
			esc_url( $url ),
			esc_html__( 'View as AI Agent', 'agent-ready-essentials' )
		);

		return $this->insert_after_key( $actions, 'view', 'wp_are_markdown', $link );
	}

	/**
	 * Public URL that serves Markdown for this post (same rules as View / Preview).
	 */
	private function get_markdown_view_url( WP_Post $post ): ?string {
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object || ! is_post_type_viewable( $post_type_object ) ) {
			return null;
		}

		$can_edit = current_user_can( 'edit_post', $post->ID );

		$base = null;
		if ( in_array( $post->post_status, [ 'pending', 'draft', 'future' ], true ) ) {
			if ( ! $can_edit ) {
				return null;
			}
			$base = get_preview_post_link( $post );
		} elseif ( 'trash' !== $post->post_status ) {
			$base = get_permalink( $post );
		}

		if ( ! $base ) {
			return null;
		}

		return add_query_arg( 'output_format', 'md', $base );
	}

	/**
	 * @param string[] $actions Associative row actions.
	 * @param string     $after_key Insert new entry after this key if present.
	 * @param string     $new_key New action key.
	 * @param string     $new_html New action markup.
	 * @return string[]
	 */
	private function insert_after_key( array $actions, string $after_key, string $new_key, string $new_html ): array {
		if ( isset( $actions[ $new_key ] ) ) {
			return $actions;
		}

		if ( ! isset( $actions[ $after_key ] ) ) {
			$actions[ $new_key ] = $new_html;
			return $actions;
		}

		$out = [];
		foreach ( $actions as $key => $html ) {
			$out[ $key ] = $html;
			if ( $key === $after_key ) {
				$out[ $new_key ] = $new_html;
			}
		}

		return $out;
	}
}
