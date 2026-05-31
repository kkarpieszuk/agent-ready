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
		return '"' . str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $value ) . '"';
	}

	/**
	 * Single-line plain text for ATX heading.
	 */
	private function plain_one_line( string $text ): string {
		return str_replace( "\n", ' ', $text );
	}

	/**
	 * True when the request targets the site root used as a static front page.
	 */
	private function is_static_front_page_url(): bool {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}
		if ( (int) get_option( 'page_on_front' ) <= 0 ) {
			return false;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			$path = '/';
		}

		$path = untrailingslashit( $path );

		return '' === $path;
	}
}
