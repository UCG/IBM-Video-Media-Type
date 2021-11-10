<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Drupal\media\MediaInterface;
use Ranine\Iteration\ExtendableIterable;

/**
 * Formats the IBM video media type source field.
 *
 * @todo Fix up viewElements().
 * @todo See if isApplicable() should be implemented, and implement if so.
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
    // Get the media source and media entity associated with $items.
    $media = $items->getEntity();
    if (!($media instanceof MediaInterface)) {
      throw new \InvalidArgumentException('$items contains a field whose entity is not a media entity.');
    }
    /** @var \Drupal\media\MediaInterface $media */
    $source = $media->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('$items contains a field whose entity has a source of the wrong type.');
    }
    /** @var \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo $source */

    $videoEmbedUrl = $this->generateVideoUrl($source->getMetadata($media, 'channelId'), $source->getMetadata($media, 'channelVideoId'));
    $renderElement = [];
    foreach ($items as $delta => $item) {
      $renderElement[$delta] = [
        '#theme' => 'ibm_video_media_type_player',
        '#videoUrl' => $videoEmbedUrl,
      ];
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($media)->applyTo($renderElement[$delta]);
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
    return '//video.ibm.com/channel/' . $channelId . '/video/' . $channelVideoId . '?' . $queryString;
  }

}
