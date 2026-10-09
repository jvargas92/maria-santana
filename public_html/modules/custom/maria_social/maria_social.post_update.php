<?php

/**
 * @file
 * Post-update hooks for the Maria Santana social embeds module.
 */

declare(strict_types=1);

/**
 * Create the Substack feed, which a configuration import cannot carry.
 */
function maria_social_post_update_create_substack_feed(): string {
  if (!\Drupal::moduleHandler()->moduleExists('feeds')) {
    return 'Feeds is not enabled, so the Substack feed was not created. It will be created automatically when Feeds is enabled.';
  }

  $before = \Drupal::entityTypeManager()->getStorage('feeds_feed')
    ->loadByProperties(['type' => 'substack']);
  $feed = maria_social_ensure_substack_feed();

  if ($feed === NULL) {
    return 'The "substack" feed type is missing, so no feed was created. Import configuration first, then run this update again.';
  }
  return $before === []
    ? 'Created the Substack feed: ' . $feed->getSource()
    : 'The Substack feed already existed, so nothing changed.';
}
