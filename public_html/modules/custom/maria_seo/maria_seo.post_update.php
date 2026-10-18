<?php

/**
 * @file
 * Post-update hooks for the Maria Santana SEO fixes module.
 */

declare(strict_types=1);

/**
 * Drop the relative canonical URL token stored on existing Canvas pages.
 */
function maria_seo_post_update_strip_canvas_canonical(): string {
  \Drupal::moduleHandler()->loadInclude('maria_seo', 'inc', 'maria_seo.operations');
  return maria_seo_strip_canvas_canonical_overrides();
}

/**
 * Keep the front page duplicate and the 404 page out of the XML sitemap.
 */
function maria_seo_post_update_exclude_front_and_404_from_sitemap(): string {
  \Drupal::moduleHandler()->loadInclude('maria_seo', 'inc', 'maria_seo.operations');
  return maria_seo_exclude_front_and_404_from_sitemap();
}

/**
 * Re-apply both repairs where installing the module skipped them.
 *
 * The two updates above are recorded as complete the moment this module is
 * installed, so a site that first received the module through a configuration
 * import never ran them. This update re-applies both; each is idempotent, so
 * sites that did run them see no further change.
 */
function maria_seo_post_update_reapply_repairs_after_install(): string {
  \Drupal::moduleHandler()->loadInclude('maria_seo', 'inc', 'maria_seo.operations');
  return implode(' ', [
    maria_seo_strip_canvas_canonical_overrides(),
    maria_seo_exclude_front_and_404_from_sitemap(),
  ]);
}
