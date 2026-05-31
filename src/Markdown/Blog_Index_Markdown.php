<?php
/**
 * Serves the blog posts index (including front-page “latest posts”) as Markdown.
 *
 * @package Kolibia_AR
 */

namespace Kolibia_AR\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use League\HTMLToMarkdown\HtmlConverter;
use WP_Post;

/**
 * Markdown for is_home() views (main loop), e.g. when the homepage lists latest posts.
 */
final class Blog_Index_Markdown {

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
	 * @param string[] $vars Registered query variables.
	 * @return string[]
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = 'output_format';
		return $vars;
	}

	/**
	 * Output Markdown instead of HTML when requested on the posts index.
	 */
	public function maybe_serve_markdown(): void {
		if ( is_feed() || is_embed() || is_trackback() ) {
			return;
		}

		if ( ! $this->is_blog_posts_index() ) {
			return;
		}

		if ( ! $this->should_respond_with_markdown() ) {
			return;
		}

		$markdown = $this->build_home_markdown();
		$markdown = apply_filters( 'kolibia_ar_home_markdown', $markdown );

		$this->send_markdown_headers( $markdown );
		if ( 'HEAD' === $this->get_request_method() ) {
			exit;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown document for agents.
		echo $markdown;
		exit;
	}

	/**
	 * Print alternate Markdown link on the HTML posts index.
	 */
	public function print_alternate_link(): void {
		if ( ! $this->is_blog_posts_index() ) {
			return;
		}

		$url   = add_query_arg( 'output_format', 'md', $this->get_index_url() );
		$title = esc_attr__(
			'Markdown alternate of this content for Large Language Models and other AI agents.',
			'kolibia-agent-ready'
		);
		echo '<link rel="alternate" type="text/markdown" title="' . $title . '" href="' . esc_url( $url ) . '" />' . "\n";
	}

	/**
	 * Canonical URL for the current posts index page (including pagination).
	 */
	private function get_index_url(): string {
		$paged = max( 1, (int) get_query_var( 'paged' ) );

		if ( 'posts' === get_option( 'show_on_front' ) ) {
			$base = home_url( '/' );
		} else {
			$posts_page = (int) get_option( 'page_for_posts' );
			if ( $posts_page > 0 ) {
				$permalink = get_permalink( $posts_page );
				$base      = is_string( $permalink ) ? $permalink : home_url( '/' );
			} else {
				$base = home_url( '/' );
			}
		}

		if ( $paged > 1 ) {
			return get_pagenum_link( $paged );
		}

		return $base;
	}

	/**
	 * Build Markdown for the main posts loop (same posts as the themed HTML index).
	 */
	private function build_home_markdown(): string {
		$site_name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$site_name = '' === trim( $site_name ) ? 'Site' : trim( $site_name );

		$description = wp_strip_all_tags( get_bloginfo( 'description' ) );
		$description = trim( $description );

		$index_url = $this->get_index_url();
		$paged     = max( 1, (int) get_query_var( 'paged' ) );

		$lines   = [];
		$lines[] = '---';
		$lines[] = 'title: ' . $this->yaml_double_quoted( $site_name );
		if ( '' !== $description ) {
			$lines[] = 'description: ' . $this->yaml_double_quoted( $description );
		}
		$lines[] = 'permalink: ' . $this->yaml_double_quoted( $index_url );
		$lines[] = '---';
		$lines[] = '';
		$lines[] = '# ' . $this->plain_one_line( $site_name );
		$lines[] = '';

		if ( '' !== $description ) {
			$lines[] = $this->plain_one_line( $description );
			$lines[] = '';
		}

		if ( $paged > 1 ) {
			$lines[] = sprintf( 'Page %d.', $paged );
			$lines[] = '';
		}

		$post_types = apply_filters( 'kolibia_ar_home_markdown_post_types', [ 'post' ] );
		$converter  = $this->create_html_converter();

		if ( have_posts() ) {
			$lines[] = '## Posts';
			$lines[] = '';

			while ( have_posts() ) {
				the_post();
				$post = get_post();
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				if ( ! in_array( $post->post_type, $post_types, true ) ) {
					continue;
				}
				if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
					continue;
				}
				if ( post_password_required( $post ) ) {
					continue;
				}

				$lines[] = $this->build_loop_item_markdown( $post, $converter );
				$lines[] = '';
			}
		} else {
			$lines[] = '_No posts found._';
		}

		wp_reset_postdata();

		return rtrim( implode( "\n", $lines ) ) . "\n";
	}

	/**
	 * @param WP_Post        $post      Post in the main loop.
	 * @param HtmlConverter $converter Shared HTML-to-Markdown converter.
	 */
	private function build_loop_item_markdown( WP_Post $post, HtmlConverter $converter ): string {
		$title     = $this->get_post_title_plain( $post );
		$permalink = get_permalink( $post ) ?: '';
		$excerpt   = $this->get_post_excerpt_markdown( $post, $converter );

		$block  = '### ' . $this->plain_one_line( $title ) . "\n\n";
		$block .= $excerpt;
		if ( '' !== trim( $excerpt ) ) {
			$block .= "\n\n";
		}
		$block .= '[Read more](' . $permalink . ')';

		return $block;
	}

	/**
	 * @param WP_Post        $post_object Post object.
	 * @param HtmlConverter $converter   Shared HTML-to-Markdown converter.
	 */
	private function get_post_excerpt_markdown( WP_Post $post_object, HtmlConverter $converter ): string {
		global $post;
		$backup = $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for the_excerpt / the_content filters.
		$post = $post_object;
		setup_postdata( $post );

		$excerpt_html = get_the_excerpt( $post_object );
		if ( '' === trim( wp_strip_all_tags( $excerpt_html ) ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
			$content_html = apply_filters( 'the_content', $post_object->post_content );
			$excerpt_html = wp_trim_words( $content_html, 55, '…' );
		}

		wp_reset_postdata();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = $backup;

		if ( '' === trim( wp_strip_all_tags( $excerpt_html ) ) ) {
			return '';
		}

		return trim( $converter->convert( $excerpt_html ) );
	}

	/**
	 * HtmlConverter instance reused across loop items (same options as before).
	 */
	private function create_html_converter(): HtmlConverter {
		return new HtmlConverter(
			[
				'header_style' => 'atx',
				'remove_nodes' => 'script style',
			]
		);
	}

	/**
	 * @param WP_Post $post Post object.
	 */
	private function get_post_title_plain( WP_Post $post ): string {
		$title = wp_strip_all_tags( get_the_title( $post ) );
		$title = trim( $title );

		return '' === $title ? '(Untitled)' : $title;
	}
}
