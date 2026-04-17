<?php
/**
 * Serves single posts as Markdown when clients negotiate text/markdown or use ?output_format=md.
 *
 * @package Agent_Ready
 */

namespace Agent_Ready\Markdown;

use Agent_Ready\Builtin_Post_Types;
use League\HTMLToMarkdown\HtmlConverter;
use WP_Post;

/**
 * Handles Markdown content negotiation for singular posts (see Cloudflare “Markdown for Agents” pattern).
 */
final class Single_Post_Markdown {

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_markdown' ], 0 );
		add_action( 'wp_head', [ $this, 'print_alternate_link' ], 1 );
	}

	/**
	 * Allow ?output_format=md to survive canonical redirects and query parsing.
	 *
	 * @param string[] $vars Registered query variables.
	 * @return string[]
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = 'output_format';
		return $vars;
	}

	/**
	 * Output Markdown instead of HTML when requested.
	 */
	public function maybe_serve_markdown(): void {
		if ( is_feed() || is_embed() || is_trackback() ) {
			return;
		}

		$post_types = apply_filters( 'agent_ready_markdown_post_types', Builtin_Post_Types::default_markdown_types() );
		if ( ! is_singular( $post_types ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			return;
		}

		if ( ! $this->should_respond_with_markdown() ) {
			return;
		}

		if ( post_password_required( $post ) ) {
			$this->send_markdown_headers();
			if ( 'HEAD' === $this->get_request_method() ) {
				exit;
			}
			$password_md = apply_filters(
				'agent_ready_markdown_password_required',
				$this->build_password_required_markdown( $post ),
				$post
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document for agents.
			echo $password_md;
			exit;
		}

		$markdown = $this->build_post_markdown( $post );
		$markdown = apply_filters( 'agent_ready_post_markdown', $markdown, $post );

		$this->send_markdown_headers();
		if ( 'HEAD' === $this->get_request_method() ) {
			exit;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document for agents.
		echo $markdown;
		exit;
	}

	/**
	 * Print <link rel="alternate" type="text/markdown"> for discoverability.
	 */
	public function print_alternate_link(): void {
		$post_types = apply_filters( 'agent_ready_markdown_post_types', Builtin_Post_Types::default_markdown_types() );
		if ( ! is_singular( $post_types ) ) {
			return;
		}

		$url = add_query_arg( 'output_format', 'md', get_permalink() );
		echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />' . "\n";
	}

	/**
	 * Whether this request asks for a Markdown representation.
	 */
	private function should_respond_with_markdown(): bool {
		$format = get_query_var( 'output_format' );
		if ( '' !== $format && 'md' === sanitize_key( $format ) ) {
			return true;
		}

		if ( isset( $_GET['output_format'] ) && 'md' === sanitize_key( wp_unslash( $_GET['output_format'] ) ) ) {
			return true;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
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
	 * @param WP_Post $post Post object.
	 */
	private function build_password_required_markdown( WP_Post $post ): string {
		$title = $this->get_post_title_plain( $post );

		return "# {$title}\n\nThis content is password protected.\n";
	}

	/**
	 * Build full Markdown document for a post.
	 *
	 * @param WP_Post $post Post object.
	 */
	private function build_post_markdown( WP_Post $post_object ): string {
		global $post;
		$backup = $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for the_content filters.
		$post = $post_object;
		setup_postdata( $post );
		$content_html = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = $backup;

		$converter = new HtmlConverter(
			[
				'header_style' => 'atx',
				'remove_nodes' => 'script style',
			]
		);
		$body_md = $converter->convert( $content_html );

		$title     = $this->get_post_title_plain( $post_object );
		$permalink = get_permalink( $post_object ) ?: '';

		$lines   = [];
		$lines[] = '---';
		$lines[] = 'title: ' . $this->yaml_double_quoted( $title );
		$lines[] = 'permalink: ' . $this->yaml_double_quoted( $permalink );
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '# ' . $this->plain_one_line( $title );
		$lines[] = '';
		$lines[] = trim( $body_md );

		return implode( "\n", $lines );
	}

	/**
	 * Plain one-line title for headings and front matter.
	 */
	private function get_post_title_plain( WP_Post $post_object ): string {
		$title = wp_strip_all_tags( get_the_title( $post_object ) );
		$title = trim( $title );

		return '' === $title ? '(Untitled)' : $title;
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

	private function send_markdown_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'Content-Type: text/markdown; charset=UTF-8' );
		header( 'Vary: Accept' );
	}

	private function get_request_method(): string {
		return isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
	}
}
