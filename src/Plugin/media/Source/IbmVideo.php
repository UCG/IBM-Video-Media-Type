<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Flickr.
 *
 * @MediaType(
 *   id = "ibm_video",
 *   label = @Translation("IBM Video"),
 *   description = @Translation("Handles implementation logic for IBM Video media source."),
 *   allowed_field_types = {"string_long"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class IbmVideo extends MediaSourceBase implements MediaSourceFieldConstraintsInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) : FieldConfigInterface {
    // Adapted from source of @see \Drupal\media\Plugin\media\Source\OEmbed.
    $label = (string) $this->t('@type configuration', [
      '@type' => $this->getPluginDefinition()['label'],
    ]);
    return parent::createSourceField($type)->set('label', $label);
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (!is_string($name)) {
      throw new \InvalidArgumentException('$name is not a string.');
    }
    /** @var string $name */

    // The video configuration (URL, autoplay flag, etc.) is stored in the
    // source field as a JSON-encoded string. Grab and decode this JSON, and try
    // to return the value corresponding to $name. Return NULL if 1) $name does
    // not correspond to an existing metadata property, or 2) $name is not one
    // of the possible valid metadata properties.

    $videoConfigurationJson = (string) $this->getSourceFieldValue($media);
    if ($videoConfigurationJson === '') {
      return NULL;
    }

    $videoConfiguration = json_decode($videoConfigurationJson, TRUE);
    if (!is_array($videoConfiguration)) {
      static::throwSourceFieldInvalidException('media');
    }

    if (!array_key_exists($name, $videoConfiguration)) {
      return NULL;
    }

    // Check to ensure $name is valid.
    switch ($name) {
      case 'videoId':
        if (!is_string($videoConfiguration['videoId'])) {
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
        if (!is_bool($videoConfiguration['displayControls'])) {
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
        if (!is_string($videoConfiguration['defaultQuality'])) {
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
      'videoId' => $this->t('The IBM video ID'),
      'useAutoplay' => $this->t('Whether to start playback immediately after iFrame loads (HTML autoplay not supported on iOS)'),
      'useHtml5Ui' => $this->t('Whether to use the new HTML5 player UI'),
      'displayControls' => $this->t('Whether to display the video playback controls'),
      'initialVolume' => $this->t('Initial video volume in %'),
      'showTitle' => $this->t('Whether to show the channel or video title on the bottom left'),
      'wMode' => $this->t('Flash wmode parameter -- not applicable for HTML5 player. Should be either "direct", "opaque", "transparent", or "window".'),
      'defaultQuality' => $this->t('Default stream playback quality -- should be either "low", "med", or "high"'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() {
    return [
      'ibm_video_configuration' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(MediaTypeInterface $type, EntityFormDisplayInterface $display) : void {
    // Let the parent class do whatever it needs to the form display first, so
    // we can later override what we need to override.
    parent::prepareFormDisplay($type, $display);

    // Taken partially from source for
    // @see \Drupal\media\Plugin\media\Source\OEmbed.
    $sourceFieldName = $this->getSourceFieldDefinition($type)->getName();
    $display->setComponent($sourceFieldName, [
    // Make sure the source field for the media type has the correct form
    // widget.
      'type' => 'ibm_video_input',
      'weight' => $display->getComponent($sourceFieldName)['weight'],
    ]);
    $display->removeComponent('name');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareViewDisplay(MediaTypeInterface $type, EntityViewDisplayInterface $display) : void {
    // Let the parent class do whatever it needs to the view display first, so
    // we can later override what we need to override.
    parent::prepareViewDisplay($type, $display);
    // Make sure the source field for the media type is set to the correct field
    // formatter, and ensure the label is hidden.
    $display->setComponent($this->getSourceFieldDefinition($type)->getName(), [
      'type' => 'ibm_video',
      'label' => 'visually_hidden',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideo {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Throws an exception saying the source field of a media entity is invalid.
   *
   * @param string $entityVariableName
   *   Media entity variable name (to use in the exception).
   *
   * @throws \InvalidArgumentException
   */
  private static function throwSourceFieldInvalidException(string $entityVariableName) : void {
    throw new \InvalidArgumentException('The source field for $' . $entityVariableName . ' is corrupt.');
  }

}
