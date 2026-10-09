<?php

declare(strict_types=1);

namespace Drupal\maria_social;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Keeps this module usable on a site where Feeds is not enabled.
 *
 * The Substack import subscriber names Feeds' own classes, and a module's
 * classes cannot be autoloaded unless that module is enabled, so compiling a
 * container that still holds the subscriber would fatal on every request. That
 * is exactly what a deploy does between `composer install`, which only puts
 * Feeds' code on disk, and the configuration import that actually enables it.
 * Dropping the service in that window leaves the embeds and the social menu
 * working, and the import starts by itself once Feeds is enabled.
 */
final class MariaSocialServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['feeds']) && $container->hasDefinition('maria_social.feeds_import_subscriber')) {
      $container->removeDefinition('maria_social.feeds_import_subscriber');
    }
  }

}
