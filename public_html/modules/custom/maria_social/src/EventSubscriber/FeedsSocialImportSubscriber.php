<?php

declare(strict_types=1);

namespace Drupal\maria_social\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\feeds\Event\EntityEvent;
use Drupal\feeds\Event\FeedsEvents;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Fills in what the Feeds mappings cannot express for imported social posts.
 *
 * Three things have to happen before the node validates:
 * - field_source is set, so the card can be told apart from work written here.
 * - The node is published. Articles run through the basic_editorial workflow,
 *   which defaults to draft, and a draft would never reach the front page
 *   views. These are posts she has already published herself, so they go live.
 * - field_featured_image is populated from the feed item's enclosure. The field
 *   is required and references a media entity, which no Feeds target can create
 *   from a remote URL.
 */
final class FeedsSocialImportSubscriber implements EventSubscriberInterface {

  /**
   * Feed type ID => the field_source value for nodes it creates.
   */
  private const FEED_SOURCES = [
    'substack' => 'substack',
  ];

  /**
   * Refuse anything larger than this, so a bad URL cannot fill the disk.
   */
  private const MAX_BYTES = 8388608;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly FileSystemInterface $fileSystem,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [FeedsEvents::PROCESS_ENTITY_PREVALIDATE => 'onPrevalidate'];
  }

  /**
   * Completes an imported node before Feeds validates it.
   */
  public function onPrevalidate(EntityEvent $event): void {
    $source = self::FEED_SOURCES[$event->getFeed()->getType()->id()] ?? NULL;
    $node = $event->getEntity();
    if ($source === NULL || !$node instanceof NodeInterface) {
      return;
    }

    // Set unconditionally: the field defaults to "original", so an emptiness
    // check would never fire, and the feed knows the real source anyway.
    if ($node->hasField('field_source')) {
      $node->set('field_source', $source);
    }

    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'published');
    }

    if (!$node->hasField('field_featured_image') || !$node->get('field_featured_image')->isEmpty()) {
      return;
    }
    $enclosures = $event->getItem()->get('enclosures');
    if (!is_array($enclosures) || $enclosures === []) {
      return;
    }
    $media = $this->mediaFor((string) reset($enclosures), (string) $node->label());
    if ($media !== NULL) {
      $node->set('field_featured_image', $media->id());
    }
  }

  /**
   * Returns a media item for a remote image, reusing one already imported.
   *
   * Substack falls back to the publication's avatar for posts without a cover
   * image, so the same URL recurs across items. Deriving the filename from the
   * URL lets those posts share a single media item instead of stacking up
   * copies of the same picture.
   */
  private function mediaFor(string $url, string $alt): ?MediaInterface {
    if (!str_starts_with($url, 'https://')) {
      $this->logger->warning('Refused a non-HTTPS image URL from a feed: @url', ['@url' => $url]);
      return NULL;
    }

    $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'gif', 'jpg', 'jpeg', 'webp'], TRUE)) {
      $extension = 'jpg';
    }
    $directory = 'public://' . date('Y-m');
    $uri = $directory . '/feed-' . substr(hash('sha256', $url), 0, 16) . '.' . $extension;

    $file = $this->existingFile($uri) ?? $this->download($url, $uri, $directory);
    if ($file === NULL) {
      return NULL;
    }

    $media_storage = $this->entityTypeManager->getStorage('media');
    $existing = $media_storage->loadByProperties([
      'bundle' => 'image',
      'field_media_image.target_id' => $file->id(),
    ]);
    if ($existing !== []) {
      return reset($existing);
    }

    $media = $media_storage->create([
      'bundle' => 'image',
      'name' => $alt !== '' ? $alt : $file->getFilename(),
      'uid' => $file->getOwnerId(),
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $alt,
      ],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Loads a managed file already saved at this URI.
   */
  private function existingFile(string $uri): ?FileInterface {
    $files = $this->entityTypeManager->getStorage('file')->loadByProperties(['uri' => $uri]);
    return $files === [] ? NULL : reset($files);
  }

  /**
   * Fetches a remote image and saves it as a managed file.
   */
  private function download(string $url, string $uri, string $directory): ?FileInterface {
    $prepare = FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS;
    if (!$this->fileSystem->prepareDirectory($directory, $prepare)) {
      $this->logger->error('Could not prepare @dir for feed images.', ['@dir' => $directory]);
      return NULL;
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 30,
        'headers' => ['Accept' => 'image/*'],
      ]);
      $length = $response->getHeaderLine('Content-Length');
      if ($length !== '' && (int) $length > self::MAX_BYTES) {
        $this->logger->warning('Skipped an oversized feed image (@bytes bytes): @url', [
          '@bytes' => $length,
          '@url' => $url,
        ]);
        return NULL;
      }
      $data = (string) $response->getBody();
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not fetch the feed image @url: @message', [
        '@url' => $url,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }

    if ($data === '' || strlen($data) > self::MAX_BYTES) {
      return NULL;
    }
    // Trust the bytes, not the URL or the Content-Type the server claimed.
    if (@getimagesizefromstring($data) === FALSE) {
      $this->logger->warning('A feed enclosure was not a usable image: @url', ['@url' => $url]);
      return NULL;
    }

    try {
      return $this->fileRepository->writeData($data, $uri, FileExists::Replace);
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not save the feed image to @uri: @message', [
        '@uri' => $uri,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
