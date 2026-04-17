=== Agent Ready ===
Contributors: kkarpieszuk
Tags: ai, markdown, agents, llm, content-negotiation
Requires at least: 6.8
Tested up to: 7.0-beta6
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Agent Ready adds API discovery and Markdown responses so AI agents can use your WordPress content and services.

== Description ==

Agent Ready makes **singular** WordPress content available as **Markdown** so tools and agents do not have to scrape HTML. Behaviour follows common **content negotiation** patterns (similar in spirit to [Markdown for Agents](https://developers.cloudflare.com/fundamentals/reference/markdown-for-agents/) on Cloudflare): clients that prefer Markdown can request it explicitly.

**What is included today**

* **Markdown responses for singular content** — For each **built-in public post type** registered by WordPress core (`post`, `page`, `attachment` on a typical site), a single-item URL can return `Content-Type: text/markdown; charset=UTF-8` instead of the themed HTML page.
* **When Markdown is served**
  * The request includes an `Accept` header that lists **`text/markdown`** (requests with `text/markdown;q=0` are ignored).
  * **Or** the URL includes the query argument **`output_format=md`** (handy for browsers and manual testing).
* **HTML to Markdown** — Rendered post body is passed through WordPress’s `the_content` filters, then converted to Markdown using the [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) library. A short YAML front matter block includes `title` and `permalink`.
* **Password-protected content** — If a password is required, the response is a small Markdown message instead of the full body.
* **Discovery in HTML** — On the normal HTML view, the plugin prints `<link rel="alternate" type="text/markdown" href="…?output_format=md" />` so clients can find the Markdown URL.
* **Admin shortcut** — On the posts and pages list screens, a row action **View as AI Agent** opens the same Markdown URL (preview URLs are used for drafts where WordPress would show Preview).

**Developer filters**

* `agent_ready_markdown_post_types` — Defaults to all **public, built-in** post types from core; override to add custom post types or remove types (e.g. `attachment`).
* `agent_ready_post_markdown` — Filter the final Markdown string.
* `agent_ready_markdown_password_required` — Filter the Markdown shown when a password is required.

**Roadmap**

Further “agent readiness” features (broader API discovery, additional formats) may be added in future releases.

== Installation ==

1. Copy the `agent-ready` folder into `wp-content/plugins/` (or install the distributed package).
2. Activate **Agent Ready** under Plugins in the WordPress admin.
3. That's it! AI Agents will automatically discover your site's content.

== Frequently Asked Questions ==

= Do I need to configure anything? =

No. Just activate the plugin and you're good to go. AI Agents will automatically discover your site's content.

= Does this replace my theme for visitors? =

No. Normal visitors still get HTML. Markdown is returned only when `text/markdown` is negotiated or `?output_format=md` is used (and the same permission rules as viewing the content apply).

= Which URLs are affected? =

Singular URLs for built-in public types from core (typically posts, pages, and media attachment pages). Custom post types are not included unless you add them via the `agent_ready_markdown_post_types` filter.

== Changelog ==

= 0.1.0 =
* Initial release: Markdown negotiation, `output_format=md`, alternate link, admin “View as AI Agent”, HTML-to-Markdown for singular core public post types.
