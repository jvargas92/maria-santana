<?php

/**
 * @file
 * Replaces a year placeholder in Canvas rich text.
 */

declare(strict_types=1);

namespace Drupal\maria_seo\Plugin\Filter;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Substitutes the current year for "[current-year]".
 *
 * The footer copyright line is a Canvas text component, which stores literal
 * HTML and supports no tokens, so the year was hardcoded and silently went
 * stale every January.
 *
 * Token module's "[date:custom:Y]" is deliberately not used here. Core marks
 * every date token as uncacheable, because it reads the current request time:
 *
 * @code
 *   // We depend on the current request time, so the tokens are not
 *   // cacheable at all.
 *   $bubbleable_metadata->setCacheMaxAge(0);
 * @endcode
 *
 * That max-age bubbles up through the render tree, and because this text sits
 * in the site footer it would make every page on the site uncacheable. This
 * filter instead reports the time at which its own output stops being true:
 * midnight on 1 January. Pages stay cacheable all year, and the year rolls
 * over on its own.
 *
 * @see \Drupal\system\Hook\SystemTokensHooks::tokens()
 */
#[Filter(
  id: "maria_seo_current_year",
  title: new TranslatableMarkup("Replace [current-year] with the current year"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Writes the current year wherever the text contains <code>[current-year]</code>, so copyright lines do not have to be edited by hand."),
)]
final class CurrentYearFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The placeholder editors type into the text.
   */
  private const PLACEHOLDER = '[current-year]';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    if (!str_contains((string) $text, self::PLACEHOLDER)) {
      return new FilterProcessResult($text);
    }

    // The site's configured timezone decides when the year turns over, so a
    // site in New York does not roll the copyright forward on UTC's schedule.
    $timezone = new \DateTimeZone(
      $this->configFactory->get('system.date')->get('timezone.default') ?: date_default_timezone_get()
    );
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($timezone);

    $result = new FilterProcessResult(
      str_replace(self::PLACEHOLDER, $now->format('Y'), (string) $text)
    );

    // Hold the rendered text only until the year it names stops being current.
    $new_year = $now->modify('first day of January next year')->setTime(0, 0, 0);
    $result->setCacheMaxAge($new_year->getTimestamp() - $now->getTimestamp());

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE): string {
    return (string) new TranslatableMarkup('Type <code>[current-year]</code> to print the current year.');
  }

}
