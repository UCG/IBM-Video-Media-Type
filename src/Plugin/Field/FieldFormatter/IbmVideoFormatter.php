<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Helper\UrlHelpers;
use Drupal\ibm_video_media_type\Helper\ValidationHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Ranine\Iteration\ExtendableIterable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats the IBM video media type source field.
 *
 * @todo Create config schema for settings.
 * @todo Override settingsForm() and settingsSummary(). Add validation for
 * settings, and ensure validation is also performed before settings are used.
 *
 * @FieldFormatter(
 *   id = "ibm_video",
 *   label = @Translation("IBM video"),
 *   field_types = {"string_long"}
 * )
 */
class IbmVideoFormatter extends FormatterBase {

  /**
   * Video source associated with this formatter.
   */
  private IbmVideo $source;

  /**
   * Settings for the video player assigned to default values.
   *
   * @var array
   */
  private const PLAYER_SETTINGS_AND_DEFAULTS = [
    'useAutoplay' => FALSE,
    'useHtml5Ui' => TRUE,
    'displayControls' => TRUE,
    'initialVolume' => 50,
    'showTitle' => TRUE,
    'wMode' => NULL,
    'defaultQuality' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() : array {
    return static::PLAYER_SETTINGS_AND_DEFAULTS + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $entity = $items->getEntity();
    $renderElement = [];
    foreach ($items as $delta => $item) {
      // Skip empty items.
      if ($item === NULL || ($value = (string) $item->value) === '') {
        $renderElement[$delta] = [];
        goto next_item;
      }

      // Try to extract channel ID and channel video ID from field value. Skip
      // invalid field items.
      $videoData = [];
      if ($this->source->tryParseVideoData($value, $videoData) !== 0) {
        $renderElement[$delta] = [];
        goto next_item;
      }
      $channelId = $videoData['channelId'];
      if (!ValidationHelpers::isChannelIdValid($channelId)) {
        $renderElement[$delta] = [];
        goto next_item;
      }
      $channelVideoId = $videoData['channelVideoId'];
      if (!ValidationHelpers::isChannelVideoIdValid($channelVideoId)) {
        $renderElement[$delta] = [];
        goto next_item;
      }

      // Render the item with a template defined by this module.
      $renderElement[$delta] = [
        '#theme' => 'ibm_video_media_type_player',
        '#videoUrl' => $this->generateVideoUrl($channelId, $channelVideoId),
      ];

next_item:
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($entity)->applyTo($renderElement[$delta]);
    }

    return $renderElement;
  }

  /**
   * Generates and returns an embed URL for the given IBM video.
   *
   * Uses the current settings to set the video player properties.
   *
   * @param string $channelId
   *   Video channel ID.
   * @param string $channelVideoId
   *   The ID of the video within the channel (not the unique video ID).
   *
   * @return string
   *   Embed URL. This URL is protocol-independent (starts with "//").
   */
  private function generateVideoUrl(string $channelId, string $channelVideoId) : string {
    $queryString = UrlHelper::buildQuery(ExtendableIterable::from(static::PLAYER_SETTINGS_AND_DEFAULTS)
      ->map(fn($setting) => $this->getSetting($setting))
      ->filter(fn($setting, $value) => $value !== NULL)
      ->map(function ($setting, $value) : string {
        // Cast most types directly to strings for the purposes of generating
        // the query string; however, convert boolean TRUE to 'true' and FALSE
        // to 'false', except for "useHtml5Ui", in which case we convert TRUE to
        // '1' and FALSE to '0'. See https://support.video.ibm.com/hc/en-us/articles/207851927-Using-URL-Parameters-and-Embed-API-for-Custom-Players.
        if ($setting === 'useHtml5Ui') {
          return $value ? '1': '0';
        }
        elseif (is_bool($value)) {
          return $value ? 'true' : 'false';
        }
        else {
          return (string) $value;
        }
      })->toArray());
    // Use a protocol-neutral protocol prefix ("//").
    $baseUrl = UrlHelpers::assembleIbmVideoPermalinkUrl($channelId, $channelVideoId, '//');

    return $queryString === '' ? $baseUrl : ($baseUrl . '?' . $queryString);
  }

  /**
   * Creates and returns a new IBM video widget.
   *
   * @param ContainerInterface $container
   *   Service container
   * @param array $configuration
   *   Configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   Plugin ID for the plugin instance.
   * @param string $plugin_definition
   *   Plugin implementation definition.
   *
   * @return static
   *
   * @throws \InvalidArgumentException
   *   Thrown if the field definition does not have a target bundle, or does not
   *   have a target entity type ID of "media", or does not have a media source
   *   of type \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideoFormatter {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    /** @var \Drupal\ibm_video_media_type\Plugin\FieldFormatter\IbmVideoFormatter */
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // Grab the media source.
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    $entityTypeManager = $container->get('entity_type.manager');
    $bundle = $formatter->fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type without a target media bundle.');
    }
    if ($formatter->fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a target entity type that is not "media".');
    }
    /** @var \Drupal\media\MediaTypeInterface */
    $mediaType = $entityTypeManager->getStorage('media_type')->load($bundle);
    $source = $mediaType->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a media source that is not of type \\Drupal\\ibm_video_media_type\\Plugin\\media\\Source\\IbmVideo.');
    }
    $formatter->source = $source;

    return $formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return MediaSourceFieldHelpers::doesFieldDefinitionHaveIbmVideoMediaSource($field_definition);
  }

}
