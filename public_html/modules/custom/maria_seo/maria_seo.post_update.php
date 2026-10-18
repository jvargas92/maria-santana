<?php

/**
 * @file
 * Post-update hooks for the Maria Santana SEO fixes module.
 */

declare(strict_types=1);

use Drupal\canvas\Entity\Page;

/**
 * Drop the relative canonical URL token stored on existing Canvas pages.
 *
 * Canvas saved "[canvas_page:url]" into each page's metatags field, and a
 * per-entity Metatag value outranks the metatag_defaults configuration.
 * Removing the key lets the canvas_page defaults supply an absolute canonical
 * instead.
 *
 * @see maria_seo_entity_base_field_info_alter()
 */
function maria_seo_post_update_strip_canvas_canonical(): string {
  $storage = \Drupal::entityTypeManager()->getStorage(Page::ENTITY_TYPE_ID);
  $changed = [];

  foreach ($storage->loadMultiple() as $page) {
    if (!$page->hasField('metatags')) {
      continue;
    }

    $raw = $page->get('metatags')->value;
    if ($raw === NULL || $raw === '') {
      continue;
    }

    $tags = json_decode((string) $raw, TRUE);
    if (!is_array($tags) || !array_key_exists('canonical_url', $tags)) {
      continue;
    }

    unset($tags['canonical_url']);
    $page->set('metatags', json_encode($tags));
    // Keep the editorial history intact: this is a metadata correction, not
    // an editorial change worth a new revision.
    if ($page->getEntityType()->isRevisionable()) {
      $page->setNewRevision(FALSE);
    }
    $page->save();
    $changed[] = $page->label();
  }

  return $changed === []
    ? 'No Canvas page stored a canonical URL override, so nothing changed.'
    : 'Removed the relative canonical override from: ' . implode(', ', $changed);
}

/**
 * Keep the front page duplicate and the 404 page out of the XML sitemap.
 *
 * Indexing the canvas_page entity type picks up every Canvas page, including
 * two that should not be listed: the front page, whose own path (/home) only
 * 301s to /, which is already a custom sitemap link; and the page assigned to
 * 404 responses, which should never be a search result.
 *
 * Both are matched by comparing system.site's configured paths against each
 * page's alias and internal path, the way Canvas itself identifies the
 * homepage. Neither Url::fromUserInput() nor the
 * rendered URL can be used on its own here, because inbound path processing
 * rewrites the configured front path to the <front> route (which carries no
 * entity parameter) and outbound processing renders the front page as '/', so
 * in both directions the front page would silently never match.
 *
 * @see \Drupal\canvas\Hook\PageHooks::preventHomepageDeletion()
 */
function maria_seo_post_update_exclude_front_and_404_from_sitemap(): string {
  if (!\Drupal::moduleHandler()->moduleExists('simple_sitemap')) {
    return 'Simple XML Sitemap is not enabled, so nothing was excluded.';
  }

  $site = \Drupal::config('system.site');
  $wanted = array_filter([$site->get('page.front'), $site->get('page.404')], 'is_string');
  if ($wanted === []) {
    return 'Neither a front page nor a 404 page is configured, so nothing was excluded.';
  }

  $manager = \Drupal::service('simple_sitemap.entity_manager');
  $excluded = [];

  foreach (\Drupal::entityTypeManager()->getStorage(Page::ENTITY_TYPE_ID)->loadMultiple() as $id => $page) {
    try {
      $url = $page->toUrl('canonical');
      $internal = '/' . $url->getInternalPath();
      // The alias is looked up directly rather than taken from toString(),
      // because outbound path processing rewrites the front page's URL to '/'
      // and would hide the very path configured in system.site.
      $paths = [
        $internal,
        \Drupal::service('path_alias.manager')->getAliasByPath($internal),
        $url->toString(),
      ];
    }
    catch (\Exception) {
      continue;
    }

    if (array_intersect($paths, $wanted) === []) {
      continue;
    }

    $manager->setEntityInstanceSettings(Page::ENTITY_TYPE_ID, (string) $id, ['index' => FALSE]);
    $excluded[] = $page->label() . ' (' . $paths[0] . ')';
  }

  return $excluded === []
    ? 'Neither the front page nor the 404 page is a Canvas page, so nothing was excluded.'
    : 'Excluded from the sitemap: ' . implode(', ', $excluded);
}
