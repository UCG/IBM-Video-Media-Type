<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaSourceFieldConstraintsInterface;
use Drupal\media\MediaTypeInterface;
use Ranine\Helper\StringHelpers;
use Ranine\Helper\ThrowHelpers;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Video (for recorded videos) or channel (for streams) ID property name.
   *
   * @var string
   */
  public const VIDEO_DATA_ID_PROPERTY_NAME = 'id';

  /**
   * Property name for "is recorded" flag.
   *
   * @var string
   */
  public const VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME = 'is_recorded';

  /**
   * Video thumbnail reference ID property name.
   *
   * @var string
   */
  public const VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME = 'thumbnail_reference_id';

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
   * Stream wrapper manager.
   */
  private StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) : array {
    // See the source for
    // \Drupal\media\Plugin\media\Source\OEmbed::buildConfigurationForm(),
    // whence some of this was taken.
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['thumbnails_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Thumbnails location'),
      '#default_value' => $this->configuration['thumbnails_directory'],
      '#description' => $this->t('Directory of local video thumbnail cache.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) : FieldConfigInterface {
    return parent::createSourceField($type)->set('label', 'IBM Video Data');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'thumbnails_directory' => 'public://ibm_video_thumbnails',
    ] + parent::defaultConfiguration();
  }

  /**
   * Gets a piece of metadata associated with the given media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media entity.
   * @param string $name
   *   Name of metadata field to fetch.
   *
   * @return mixed
   *   If $name is a valid metadata property name, returns the metadata property
   *   value. If $name is invalid, returns NULL.
   */
  public function getMetadata(MediaInterface $media, $name) {
    if ($name === 'thumbnail_uri') {
      // To retrieve the thumbnail URI, we have to grab some video metadata from
      // the source field. If we can't extract the necessary data, use the
      // parent class's implementation of this method.
      $videoDataJson = (string) $this->getSourceFieldValue($media);
      if ($videoDataJson === '') {
        return parent::getMetadata($media, $name);
      }
      $videoData = [];
      if (!$this->tryParseVideoData($videoDataJson, $videoData)) {
        return parent::getMetadata($media, $name);
      }
      $videoData = json_decode($videoDataJson, TRUE);
      if (!is_array($videoData)) {
        return parent::getMetadata($media, $name);
      }
      $id = $videoData[static::VIDEO_DATA_ID_PROPERTY_NAME];
      $isRecorded = $videoData[static::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME];
      $thumbnailReferenceId = $videoData[static::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME];
      if (!$this->isVideoOrChannelIdValid($id) || !$this->isIsRecordedFlagValid($isRecorded) || !$this->isThumbnailReferenceIdValid($thumbnailReferenceId)) {
        return parent::getMetadata($media, $name);
      }
      return ($this->prepareLocalThumbnailUri($thumbnailReferenceId, $id, $isRecorded) ?? parent::getMetadata($media, $name));
    }
    else {
      return parent::getMetadata($media, $name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() : array {
    return ['thumbnail_uri' => $this->t('The local thumbnail URI')] + parent::getMetadataAttributes();
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints() : array {
    return ['ibm_video_data' => []];
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
   * Tells whether the given "is recorded" flag is valid.
   *
   * The flag is valid if it is a boolean.
   *
   * @param mixed $flag
   *   The "is recorded" flag.
   */
  public function isIsRecordedFlagValid($flag) : bool {
    return is_bool($flag);
  }

  /**
   * Tells whether the given thumbnail reference ID is valid.
   *
   * The ID is valid if it is a non-empty string.
   *
   * @param mixed $thumbnailReferenceId
   *   Thumbnail reference ID (an element from the array returned by
   *   tryParseVideoData()).
   */
  public function isThumbnailReferenceIdValid($thumbnailReferenceId) : bool {
    return StringHelpers::isNonEmptyString($thumbnailReferenceId);
  }

  /**
   * Tells whether the given video or channel ID is valid.
   *
   * The video/channel ID is valid if it is a non-empty string.
   *
   * @param mixed $id
   *   Video or channel ID.
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
   * @param string $videoOrChannelId
   *   The video (for recorded videos) or channel (for streams) ID.
   * @param bool $isRecordedFlag
   *   Flag indicating whether the video is recorded (TRUE) or a stream (FALSE).
   * @param string|null $currentThumbnailReferenceId
   *   Current thumbnail reference ID, or NULL if none exists.
   *
   * @return string
   *   Source field value generated from the function arguments.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $videoOrChannelId or $currentThumbnailReferenceId is empty.
   */
  public function prepareVideoData(string $videoOrChannelId, bool $isRecordedFlag, ?string $currentThumbnailReferenceId) : string {
    ThrowHelpers::throwIfEmptyString($videoOrChannelId, 'videoOrChannelId');
    // Generate a random thumbnail reference ID if none exists.
    if ($currentThumbnailReferenceId === NULL) {
      $currentThumbnailReferenceId = static::generateThumbnailReferenceId();
    }
    else {
      ThrowHelpers::throwIfEmptyString($currentThumbnailReferenceId, 'currentThubmanilReferenceId');
    }

    return json_encode([
      static::VIDEO_DATA_ID_PROPERTY_NAME => $videoOrChannelId,
      static::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME => $isRecordedFlag,
      static::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME => $currentThumbnailReferenceId,
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
   *   of items with the static::VIDEO_DATA_* constants as keys (see the
   *   documentation for these constants for details on what the corresponding
   *   values should be). No validation of the corresponding values is
   *   performed.
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
      if (count($parsedData) !== 3) {
        $parsedData = [];
        return static::VIDEO_DATA_PARSE_ERROR_INVALID_KEYS;
      }
      if (!array_key_exists(static::VIDEO_DATA_ID_PROPERTY_NAME, $parsedData)
        || !array_key_exists(static::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME, $parsedData)
        || !array_key_exists(static::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME, $parsedData)) {
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

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // See the source for
    // \Drupal\media\Plugin\media\Source\OEmbed::validateConfigurationForm(),
    // from where some of this was taken.
    $thumbnailsDirectory = (string) $form_state->getValue('thumbnails_directory');
    if ($thumbnailsDirectory === '') {
      $form_state->setErrorByName('thumbnails_directory', $this->t('The local thumbnails directory cannot be empty.'));
    }
    else {
      if (!$this->streamWrapperManager->isValidUri($thumbnailsDirectory)) {
        $form_state->setErrorByName('thumbnails_directory', $this->t('"@directory" is not a valid thumbnails directory.', [
          '@directory' => $thumbnailsDirectory,
        ]));
      }
    }
  }

  /**
   * Prepares, if possible, the local thumbnail URI for the given parameters.
   *
   * If the thumbnail corresponding to the function arguments has not yet been
   * locally cached, an attempt is made to download the thumbnail, and the URI
   * of the downloaded thumbnail is returned if possible (if the remote
   * thumbnail could not be fetched, NULL is returned). If the thumbnail has
   * been cached, the local URI is returned.
   *
   * @param string $videoOrChannelId
   *   Video or channel ID.
   * @param bool $isRecorded
   *   Whether the video is a recorded video (TRUE) or a stream (FALSE).
   * @param string $thumbnailReferenceId
   *   Thumbnail reference ID.
   *
   * @return string|null
   *   The local thumbnail URL, or NULL if it could not be obtained or if there
   *   is no thumbnail.
   */
  private function prepareLocalThumbnailUri(string $videoOrChannelId, bool $isRecorded, string $thumbnailReferenceId) : ?string {
    // The thumbnail file name (sans extension) consists of the lowercase
    // hexadecimal representation of the hash of "$videoOrChannelId.[0/1 for
    // recorded video / stream].$thumbnailReferenceId". This way, the file name
    // should be unique for a given video/media entity combination (because the
    // thumbnail reference IDs of different media entities should be different).
    
  }

  /**
   * Creates and returns a new IBM video source.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   * @param array $configuration
   *   Configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   Plugin ID for the plugin instance.
   * @param string $plugin_definition
   *   Plugin implementation definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideo {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    /** @var \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo */
    $source = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $source->streamWrapperManager = $container->get('stream_wrapper_manager');
    return $source;
  }

  /**
   * Generates a random thumbnail reference ID.
   *
   * @return string
   *   New thumbnail reference ID (base-64 encoding of eight cryptographically
   *   random bytes).
   */
  public static function generateThumbnailReferenceId() : string {
    return base64_encode(random_bytes(8));
  }

}
