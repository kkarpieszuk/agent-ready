<?php
/**
 * Serves single posts as Markdown when clients negotiate text/markdown or use ?output_format=md.
 *
 * @package Kolibia_AR
 */

namespace Kolibia_AR\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Kolibia_AR\Builtin_Post_Types;
use League\HTMLToMarkdown\HtmlConverter;
use WP_Post;

/**
 * Handles Markdown content negotiation for singular posts (see Cloudflare “Markdown for Agents” pattern).
 */
final class Single_Post_Markdown {

	use Markdown_Negotiation;

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

		if ( $this->is_blog_posts_index() ) {
			return;
		}

		$post_types = apply_filters( 'kolibia_ar_markdown_post_types', Builtin_Post_Types::default_markdown_types() );
		$post       = $this->resolve_post_for_markdown( $post_types );
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
			$password_md = apply_filters(
				'kolibia_ar_markdown_password_required',
				$this->build_password_required_markdown( $post ),
				$post
			);
			$this->send_markdown_headers( $password_md );
			if ( 'HEAD' === $this->get_request_method() ) {
				exit;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document for agents.
			echo $password_md;
			exit;
		}

		$markdown = $this->build_post_markdown( $post );
		$markdown = apply_filters( 'kolibia_ar_post_markdown', $markdown, $post );

		$this->send_markdown_headers( $markdown );
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
		if ( $this->is_blog_posts_index() ) {
			return;
		}

		$post_types = apply_filters( 'kolibia_ar_markdown_post_types', Builtin_Post_Types::default_markdown_types() );
		$post       = $this->resolve_post_for_markdown( $post_types );
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$url   = add_query_arg( 'output_format', 'md', get_permalink( $post ) );
		$title = esc_attr__(
			'Markdown alternate of this content for Large Language Models and other AI agents.',
			'kolibia-agent-ready'
		);
		echo '<link rel="alternate" type="text/markdown" title="' . $title . '" href="' . esc_url( $url ) . '" />' . "\n";
	}

	/**
	 * Resolve the WP_Post to export (singular URL or static/Woo front page).
	 *
	 * @param string[] $post_types Allowed post type names.
	 */
	private function resolve_post_for_markdown( array $post_types ): ?WP_Post {
		if ( $this->is_blog_posts_index() ) {
			return null;
		}

		if ( is_singular( $post_types ) ) {
			$post = get_queried_object();

			return $post instanceof WP_Post ? $post : null;
		}

		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return null;
		}

		$page_id = (int) get_option( 'page_on_front' );
		if ( $page_id <= 0 ) {
			return null;
		}

		if ( ! is_front_page() && ! $this->is_static_front_page_url() ) {
			return null;
		}

		$post = get_post( $page_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, $post_types, true ) ) {
			return null;
		}

		return $post;
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
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
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
}
