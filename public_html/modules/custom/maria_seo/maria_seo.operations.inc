<?php

/**
 * @file
 * One-time data repairs shared by this module's install and update hooks.
 *
 * These correct entity data rather than configuration, so they cannot travel
 * with a configuration export and have to be applied by code. Both operations
 * are idempotent and safe to run repeatedly.
 */

declare(strict_types=1);

use Drupal\canvas\Entity\Page;

/**
 * Removes the relative canonical URL token stored on Canvas pages.
 *
 * Canvas saves "[canvas_page:url]" into each page's metatags field, and a
 * per-entity Metatag value outranks the metatag_defaults configuration.
 * Removing the key lets the canvas_page defaults supply an absolute canonical.
 *
 * @return string
 *   A summary of what changed.
 */
function maria_seo_strip_canvas_canonical_overrides(): string {
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
    ? 'No Canvas page stored a canonical URL override.'
    : 'Removed the relative canonical override from: ' . implode(', ', $changed);
}

/**
 * Keeps the front page duplicate and the 404 page out of the XML sitemap.
 *
 * Indexing the canvas_page entity type picks up every Canvas page, including
 * two that should not be listed: the front page, whose own path only redirects
 * to /, which is already a custom sitemap link; and the page assigned to 404
 * responses, which should never be a search result.
 *
 * Both are matched by comparing system.site's configured paths against each
 * page's alias and internal path, the way Canvas itself identifies the
 * homepage. Neither Url::fromUserInput() nor the rendered URL can be used on
 * its own here, because inbound path processing rewrites the configured front
 * path to the <front> route (which carries no entity parameter) and outbound
 * processing renders the front page as '/', so in both directions the front
 * page would silently never match.
 *
 * @return string
 *   A summary of what changed.
 *
 * @see \Drupal\canvas\Hook\PageHooks::preventHomepageDeletion()
 */
function maria_seo_exclude_front_and_404_from_sitemap(): string {
  if (!\Drupal::moduleHandler()->moduleExists('simple_sitemap')) {
    return 'Simple XML Sitemap is not enabled, so nothing was excluded.';
  }

  $site = \Drupal::config('system.site');
  $wanted = array_filter([$site->get('page.front'), $site->get('page.404')], 'is_string');
  if ($wanted === []) {
    return 'Neither a front page nor a 404 page is configured.';
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
    ? 'Neither the front page nor the 404 page is a Canvas page.'
    : 'Excluded from the sitemap: ' . implode(', ', $excluded);
}
