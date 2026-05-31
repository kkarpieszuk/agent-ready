<?php
/**
 * Central registration for Markdown content negotiation hooks.
 *
 * @package Kolibia_AR
 */

namespace Kolibia_AR\Markdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shared query vars and dispatches template_redirect to Markdown handlers.
 */
final class Markdown_Router {

	/**
	 * @var Single_Post_Markdown
	 */
	private $single_post_markdown;

	/**
	 * @var Blog_Index_Markdown
	 */
	private $blog_index_markdown;

	public function __construct() {
		$this->single_post_markdown = new Single_Post_Markdown();
		$this->blog_index_markdown  = new Blog_Index_Markdown();
	}

	/**
	 * Register WordPress hooks for Markdown serving and discovery.
	 */
	public function register(): void {
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve_markdown' ], 0 );
		add_action( 'wp_head', [ $this->single_post_markdown, 'print_alternate_link' ], 1 );
		add_action( 'wp_head', [ $this->blog_index_markdown, 'print_alternate_link' ], 1 );
	}

	/**
	 * @param string[] $vars Registered query variables.
	 * @return string[]
	 */
	public function register_query_var( array $vars ): array {
		if ( ! in_array( 'output_format', $vars, true ) ) {
			$vars[] = 'output_format';
		}

		return $vars;
	}

	/**
	 * Try singular/static front Markdown first, then the posts index.
	 */
	public function maybe_serve_markdown(): void {
		$this->single_post_markdown->maybe_serve_markdown();
		$this->blog_index_markdown->maybe_serve_markdown();
	}
}
