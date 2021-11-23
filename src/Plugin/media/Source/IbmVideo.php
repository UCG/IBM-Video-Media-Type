<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlHelpers;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use Drupal\media\MediaTypeInterface;
use Ranine\Helper\StringHelpers;

/**
 * Defines the media type plugin for embedded IBM videos or streams.
 *
 * @MediaSource(
 *   id = "ibm_video",
 *   label = @Translation("IBM Video"),
 *   description = @Translation("Handles implementation logic for IBM Video media source."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "no-thumbnail.png",
 * )
 */
class IbmVideo extends MediaSourceBase implements MediaSourceFieldConstraintsInterface {

  /**
   * Video base embed URL video data property name.
   *
   * @var string
   */
  public const VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME = 'base_embed_url';

  /**
   * Flag for a "JSON malformed" video data parse error.
   *
   * @var int
   */
  public const VIDEO_DATA_PARSE_ERROR_BAD_JSON = 1;

  /**
   * Flag for an "invalid key set" video data parse error.
   *
   * @var int
   */
  public const VIDEO_DATA_PARSE_ERROR_INVALID_KEYS = 2;

  /**
   * ID video data property name.
   *
   * @var string
   */
  public const VIDEO_DATA_ID_PROPERTY_NAME = 'id';

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) : FieldConfigInterface {
    return parent::createSourceField($type)->set('label', 'IBM Video Data');
  }

  /**
   * Gets a piece of metadata associated with the given media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param string $name
   *   Name of metadata field to fetch. Can be either
   *   static::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME or
   *   static::VIDEO_DATA_ID_PROPERTY_NAME.
   *
   * @return mixed
   *   If $name is a valid metadata property name, returns the metadata property
   *   value. If $name is invalid, returns NULL. If the source field value for
   *   $media is empty, or the first element of the source field is NULL or an
   *   empty string, returns NULL. If the source field for $media is invalid,
   *   the return value is undefined.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $name is not a string.
   */
  public function getMetadata(MediaInterface $media, $name) {
    if (!is_string($name)) {
      throw new \InvalidArgumentException('$name is not a string.');
    }
    /** @var string $name */

    // The video metadata is stored in the source field as a JSON-encoded
    // string. Grab and decode this JSON, and try to return the value
    // corresponding to $name.

    $videoDataJson = (string) $this->getSourceFieldValue($media);
    if ($videoDataJson === '') {
      return NULL;
    }

    $videoData = json_decode($videoDataJson, TRUE);
    if (!is_array($videoData)) {
      return NULL;
    }

    return array_key_exists($name, $videoData) ? $videoData[$name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() : array {
    return [
      static::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME => $this->t('The base embed URL'),
      static::VIDEO_DATA_ID_PROPERTY_NAME => $this->t('The unique video/channel ID'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() : array {
    return [
      'ibm_video_data' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldValue(MediaInterface $media) : ?string {
    // See code in parent method.

    $sourceField = $this->configuration['source_field'];
    if (empty($sourceField)) {
      throw new \RuntimeException('Source field for IBM video media source is not defined.');
    }

    $items = $media->get($sourceField);
    if ($items->isEmpty()) {
      return NULL;
    }

    $value = $items->first()->value;
    return $value === NULL ? NULL : (string) $value;
  }

  /**
   * Validates the given base embed URL.
   *
   * @param mixed $baseEmbedUrl
   *   Base embed URL (an element from the array returned by
   *   tryParseVideoData()).
   *
   * @return bool
   *   Returns TRUE if the base embed URL is of type "string" and is otherwise
   *   valid; else FALSE.
   */
  public function isBaseEmbedUrlValid($baseEmbedUrl) : bool {
    return (is_string($baseEmbedUrl) && IbmVideoUrlHelpers::isBaseEmbedUrlValid($baseEmbedUrl)) ? TRUE : FALSE;
  }

  /**
   * Validates the given video/channel ID.
   *
   * @param mixed $videoId
   *   An element from the array returned by tryParseVideoData(): either the
   *   video ID (for recorded videos) or the channel ID (for streams).
   *
   * @return bool
   *   Returns TRUE if the ID is of type "string" and is otherwise valid; else
   *   returns FALSE.
   */
  public function isVideoOrChannelIdValid($id) : bool {
    return StringHelpers::isNonEmptyString($id);
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
    // Make sure the source field for the media type has the correct form
    // widget.
    $display->setComponent($sourceFieldName, [
      'type' => 'ibm_video_input',
      'weight' => $display->getComponent($sourceFieldName)['weight'],
    ]);
  }

  /**
   * Assembles the given parameters for use in the source field value.
   *
   * @param string $baseEmbedUrl
   *   Base embed URL (assumed to be valid).
   * @param string $id
   *   Video (for recorded videos) or channel (for streams) ID (assumed to be
   *   valid).
   *
   * @return string
   *   Source field value generated from the function arguments.
   */
  public function prepareVideoData(string $baseEmbedUrl, string $id) : string {
    return json_encode([
      static::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME => $baseEmbedUrl,
      static::VIDEO_DATA_ID_PROPERTY_NAME => $id,
    ]);
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
   * Parses data from the source field into an array.
   *
   * @param string $data
   *   Data from the first item of the source field.
   * @param array<string, mixed> $parsedData
   *   (output parameter) If parsing was successful, this is an array consisting
   *   of two items: one with key
   *   static::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME containing the base
   *   video embed URL (which is not validated), and another with key
   *   static::VIDEO_DATA_ID_PROPERTY_NAME containing the video ID (for a
   *   recorded video) or channel ID (for a stream)
   *
   * @return int
   *   Returns 0 on success. Otherwise, returns either
   *   static::VIDEO_DATA_PARSE_ERROR_BAD_JSON (for malformed JSON), or
   *   static::VIDEO_DATA_PARSE_ERROR_INVALID_KEYS (for an incorrect set of JSON
   *   keys in the root JSON element).
   */
  public function tryParseVideoData(string $data, array &$parsedData) : int {
    $parsedData = json_decode($data, TRUE);
    if (is_array($parsedData)) {
      if (count($parsedData) !== 2) {
        $parsedData = [];
        return static::VIDEO_DATA_PARSE_ERROR_INVALID_KEYS;
      }
      if (!array_key_exists(static::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME, $parsedData) || !array_key_exists(static::VIDEO_DATA_ID_PROPERTY_NAME, $parsedData)) {
        $parsedData = [];
        return static::VIDEO_DATA_PARSE_ERROR_INVALID_KEYS;
      }
    }
    else {
      $parsedData = [];
      return static::VIDEO_DATA_PARSE_ERROR_BAD_JSON;
    }

    return 0;
  }

}
