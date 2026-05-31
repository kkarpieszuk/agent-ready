<?php
/**
 * Shared Accept / output_format negotiation and response headers for Markdown.
 *
 * @package Kolibia_AR
 */

namespace Kolibia_AR\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for Markdown content negotiation (Cloudflare “Markdown for Agents” pattern).
 */
trait Markdown_Negotiation {

	/**
	 * Whether this request asks for a Markdown representation.
	 */
	private function should_respond_with_markdown(): bool {
		$format = get_query_var( 'output_format' );
		if ( '' !== $format && 'md' === sanitize_key( $format ) ) {
			return true;
		}

		$accept = '';
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$accept = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) );
		}
		if ( '' === $accept ) {
			return false;
		}

		if ( ! str_contains( $accept, 'text/markdown' ) ) {
			return false;
		}

		// Reject explicit q=0 for text/markdown (client does not want this type).
		if ( preg_match( '/text\/markdown\s*;\s*q=\s*0(?:\.0)?\b/', $accept ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $markdown Response body used for optional token estimate.
	 */
	private function send_markdown_headers( string $markdown = '' ): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		header( 'Vary: Accept' );
		if ( '' !== $markdown ) {
			header( 'X-Markdown-Tokens: ' . (string) $this->estimate_markdown_tokens( $markdown ) );
		}
	}

	/**
	 * Rough token estimate (chars / 4), aligned with common LLM heuristics.
	 */
	private function estimate_markdown_tokens( string $markdown ): int {
		$length = strlen( $markdown );

		return max( 1, (int) ceil( $length / 4 ) );
	}

	private function get_request_method(): string {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return 'GET';
		}

		return strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
	}

	/**
	 * YAML double-quoted scalar for front matter.
	 */
	private function yaml_double_quoted( string $value ): string {
		$value = $this->plain_one_line( $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ) ?? $value;
		$value = str_replace(
			[ '\\', '"', "\t" ],
			[ '\\\\', '\\"', '\\t' ],
			$value
		);

		return '"' . $value . '"';
	}

	/**
	 * Single-line plain text for ATX headings and YAML scalars.
	 */
	private function plain_one_line( string $text ): string {
		return preg_replace( '/\R/u', ' ', $text ) ?? $text;
	}

	/**
	 * Whether the current request is the main posts index (not a static front page at /).
	 */
	private function is_blog_posts_index(): bool {
		if ( is_feed() || is_embed() || is_trackback() ) {
			return false;
		}

		if ( $this->is_static_front_page_url() ) {
			return false;
		}

		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 && is_page( $posts_page ) ) {
			return true;
		}

		if ( 'posts' === get_option( 'show_on_front' ) ) {
			return is_home();
		}

		return false;
	}

	/**
	 * True when the request targets the static front page (including subdirectory installs).
	 */
	private function is_static_front_page_url(): bool {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}
		if ( (int) get_option( 'page_on_front' ) <= 0 ) {
			return false;
		}

		// “Your latest posts” on the homepage is is_home(), not a static front page.
		if ( is_home() ) {
			return false;
		}

		if ( is_front_page() ) {
			return true;
		}

		return $this->request_path_matches( $this->get_home_path() );
	}

	/**
	 * Normalized site home path (e.g. "/" or "/subdir").
	 */
	private function get_home_path(): string {
		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? untrailingslashit( $home_path ) : '';

		return '' === $home_path ? '/' : $home_path;
	}

	/**
	 * Normalized path from the current request URI.
	 */
	private function get_request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? untrailingslashit( $path ) : '';

		return '' === $path ? '/' : $path;
	}

	/**
	 * @param string $expected_path Normalized path from get_home_path() or get_request_path().
	 */
	private function request_path_matches( string $expected_path ): bool {
		return $this->get_request_path() === $expected_path;
	}
}
