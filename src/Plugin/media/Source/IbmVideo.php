<?php

declare (strict_types = 1);

use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use Ranine\Helper\ThrowHelpers;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Flickr.
 *
 * @MediaType(
 *   id = "ibm_video",
 *   label = @Translation("IBM Video"),
 *   description = @Translation("Handles implementation logic for IBM Video media source."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class IbmVideo extends MediaSourceBase implements MediaSourceFieldConstraintsInterface {

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (!is_string($name)) {
      throw new \InvalidArgumentException('$name is not a string.');
    }

    // The video configuration (URL, autoplay flag, etc.) is stored in the
    // source field as a JSON-encoded string. Grab and decode this JSON,
    // and try to return the value corresponding to $name.

    $videoConfigurationJson = (string) $this->getSourceFieldValue($media);
    if ($videoConfigurationJson === '') {
      return NULL;
    }

    $videoConfiguration = json_decode($videoConfigurationJson);
    if (!is_array($videoConfiguration)) {
      static::throwSourceFieldInvalidException('media');
    }

    if (!array_key_exists($name, $videoConfiguration)) {
      return NULL;
    }

    // Check to ensure $name is valid.
    switch ($name) {
      case 'url':
        if (!is_string($videoConfiguration['url'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'useAutoplay':
        if (!is_bool($videoConfiguration['useAutoplay'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'useHtml5Ui':
        if (!is_bool($videoConfiguration['useHtml5Ui'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'displayControls':
        if (!is_bool($videoConfiguration['useHtml5Ui'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'initialVolume':
        if (!is_int($videoConfiguration['initialVolume'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'showTitle':
        if (!is_bool($videoConfiguration['showTitle'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'wMode':
        if (!is_string($videoConfiguration['wMode'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      case 'defaultQuality':
        if (!is_bool($videoConfiguration['useHtml5Ui'])) {
          static::throwSourceFieldInvalidException('media');
        }
        break;

      default:
        return NULL;
    }

    return $videoConfiguration[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [
      'url' => $this->t('The base embed URL of the video'),
      'useAutoplay' => $this->t('Whether to start playback immediately after iFrame loads (HTML autoplay not supported on iOS)'),
      'useHtml5Ui' => $this->t('Whether to use the new HTML5 player UI'),
      'displayControls' => $this->t('Whether to display the video playback controls'),
      'initialVolume' => $this->t('Initial video volume in %'),
      'showTitle' => $this->t('Whether to show the channel or video title on the bottom left'),
      'wMode' =>  $this->t('Flash wmode parameter -- not applicable for HTML5 player. Should be either "direct," "opaque," "transparent," or "window."'),
      'defaultQuality' => $this->t('Default stream playback quality -- should be either "low," "med," or "high"'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideo {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

}
