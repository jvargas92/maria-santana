<?php

declare(strict_types=1);

namespace Drupal\maria_social\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the social platforms as a stack of rows, for a link-in-bio page.
 *
 * It reads the same "social" menu the header icons come from, so a platform is
 * maintained in one place: change a link once and both the header and this
 * page follow. Typing the addresses onto a page instead would guarantee they
 * drift, which has already happened once, when every handle changed.
 *
 * The header renders those links with icon_display set to icon_only, which is
 * why it shows bare marks. A link-in-bio page needs the name as well, so this
 * block renders the icon and the title itself rather than deferring to that
 * per-link setting.
 */
#[Block(
  id: 'maria_social_link_stack',
  admin_label: new TranslatableMarkup('Social links, stacked'),
  category: new TranslatableMarkup('Social'),
)]
final class SocialLinkStackBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The menu holding the platform links.
   */
  private const MENU = 'social';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly MenuLinkTreeInterface $menuTree,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self($configuration, $plugin_id, $plugin_definition, $container->get('menu.link_tree'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $parameters = new MenuTreeParameters();
    $parameters->onlyEnabledLinks()->setMaxDepth(1);

    $tree = $this->menuTree->transform(
      $this->menuTree->load(self::MENU, $parameters),
      [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ]
    );

    $rows = [];
    foreach ($tree as $element) {
      $link = $element->link;
      $address = trim((string) $link->getUrlObject()->toString());

      // A link that has no address yet, such as YouTube while its URL is
      // unknown, would otherwise render a row that goes nowhere. Every
      // placeholder a menu link can hold, route:<nolink>, route:<none> and
      // the internal:# that the menu form stores, generates an empty string,
      // so testing the generated address catches all of them where testing
      // the route name does not.
      if ($address === '' || $address === '#') {
        continue;
      }

      // The menu link's own description field is the blurb, so a platform is
      // still described in one place alongside its address and its icon.
      $description = trim((string) $link->getDescription());

      $rows[] = [
        'title' => $link->getTitle(),
        'url' => $address,
        'icon' => $this->icon($link->getOptions()),
        'description' => $description,
        // The blurb sits outside the link, so it is tied back to it for
        // assistive technology instead of being read as loose text. Generated
        // here rather than from a loop counter so that two of these blocks on
        // one page cannot collide.
        'description_id' => $description === '' ? NULL : Html::getUniqueId('link-stack-description'),
      ];
    }

    if ($rows === []) {
      return [];
    }

    return [
      '#theme' => 'maria_social_link_stack',
      '#rows' => $rows,
      '#attached' => ['library' => ['maria_social/link_stack']],
    ];
  }

  /**
   * Builds the brand mark already configured on a menu link, if there is one.
   */
  private function icon(array $options): ?array {
    $id = $options['icon']['target_id'] ?? NULL;
    if (!is_string($id) || !str_contains($id, ':')) {
      return NULL;
    }
    [$pack, $icon] = explode(':', $id, 2);

    // Size and colour are set here rather than taken from the link, so the
    // rows stay consistent however each link was configured for the header.
    return [
      '#type' => 'icon',
      '#pack_id' => $pack,
      '#icon_id' => $icon,
      '#settings' => ['size' => 28, 'color' => '#111c35'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Editing the menu, or any link in it, has to refresh this block.
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:system.menu.' . self::MENU,
    ]);
  }

}
