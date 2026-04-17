<?php
/**
 * Default post type lists aligned with WordPress core.
 *
 * @package Agent_Ready
 */

namespace Agent_Ready;

/**
 * Built-in public post types (post, page, attachment, …) for Markdown features.
 */
final class Builtin_Post_Types {

	/**
	 * All public post types registered by WordPress core (not third-party CPTs).
	 *
	 * @return string[] Post type names.
	 */
	public static function default_markdown_types(): array {
		$types = get_post_types(
			[
				'public'   => true,
				'_builtin' => true,
			],
			'names'
		);

		return array_values( $types );
	}
}
