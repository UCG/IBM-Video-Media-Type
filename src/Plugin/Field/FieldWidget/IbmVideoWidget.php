<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlHelpers;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for IBM video source field.
 *
 * @FieldWidget(
 *   id = "ibm_video_input",
 *   label = @Translation("IBM Video Input"),
 *   field_types = {"string"},
 * )
 */
class IbmVideoWidget extends WidgetBase {

  /**
   * Video source associated with this widget.
   */
  private IbmVideo $source;

  /**
   * Creates a new IBM video widget.
   *
   * @param string $pluginId
   *   Widget plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Definition of the field with which the widget is associated.
   * @param array $settings
   *   Widget settings.
   * @param array $thirdPartySettings
   *   Third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the field definition does not have a target bundle, or does not
   *   have a target entity type ID of "media", or does not have a media source
   *   of type \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
   */
  public function __construct(string $pluginId,
    $pluginDefinition,
    FieldDefinitionInterface $fieldDefinition,
    array $settings,
    array $thirdPartySettings,
    EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings);

    if ($fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type with a target entity type that is not "media".');
    }

    $bundle = $fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type without a valid target media bundle.');
    }

    /** @var \Drupal\media\MediaTypeInterface */
    $mediaType = $entityTypeManager->getStorage('media_type')->load($bundle);
    $source = $mediaType->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type with a media source that is not of type \\Drupal\\ibm_video_media_type\\Plugin\\media\\Source\\IbmVideo.');
    }
    $this->source = $source;
  }

  /**
   * Gets the form element used for setting item $delta of $items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param int $delta
   *   Field item ID.
   * @param array $element
   *   Form element the outputted element is based upon.
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $delta is not an integer.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) : array {
    if (!is_int($delta)) {
      throw new \InvalidArgumentException('$delta is not an integer.');
    }

    // Generate a default video URL from any existing video data. Also store any
    // current thumbnail reference ID (for use below).
    $videoData = $this->getVideoData($items, $delta);
    if ($videoData === NULL) {
      $defaultVideoUrl = NULL;
      $currentThumbnailReferenceId = NULL;
    }
    else {
      $defaultVideoUrl = $this->getDefaultVideoUrl($videoData);
      if (array_key_exists(IbmVideo::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME, $videoData)) {
        $currentThumbnailReferenceId = $videoData[IbmVideo::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME];
        if (!$this->source->isThumbnailReferenceIdValid($currentThumbnailReferenceId)) {
          // Don't use the thumbnail reference ID if it's invalid.
          $currentThumbnailReferenceId = NULL;
        }
      }
      else {
        $currentThumbnailReferenceId = NULL;
      }
    }
    // Create a "fake" element for the thumbnail reference ID, that is processed
    // in order to set the ID as a submitted form "value."
    $element['thumbnail_reference_id'] = [
      '#custom' => [
        // This data is used by the #process callback.
        'default_video_url' => $defaultVideoUrl,
        'current_thumbnail_reference_id' => $currentThumbnailReferenceId,
      ],
      '#process' => [[static::class, 'processThumbnailReferenceId']],
    ];
    $element['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Embed URL'),
      '#description' => $this->t(
<<<'EOS'
The canonical embed URL for the video. Should be a URL starting with "https://",
"http://", "//" or nothing at all (""), and followed by
"video.ibm.com/embed/recorded/[video ID]" (for a recorded video) or
"video.ibm.com/embed/[channel ID]" (for a stream), with an optional query string
and/or fragment. This URL may be "cleaned up" before it is used to embed the
video.
EOS
      ),
      '#default_value' => $defaultVideoUrl,
      '#size' => 50,
      '#maxlength' => 120,
      '#required' => TRUE,
      '#element_validate' => [[static::class, 'validateEmbedUrlInput']],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) : array {
    foreach ($values as $delta => &$itemValues) {
      assert(is_array($itemValues));
      $url = array_key_exists('url', $itemValues) ? ((string) $itemValues['url']) : '';
      $thumbnailReferenceId = $itemValues['thumbnail_reference_id'];
      // Unset the "url" and "thumbnail_reference_id" elements, as they don't
      // actually correspond to field components (it they are just used to
      // temporarily store values so we can generate the actual field value).
      unset($itemValues['url']);
      unset($itemValues['thumbnail_reference_id']);
      if ($url === '') {
        // We will end up with an entity validation error if we give an empty
        // value, so we just use some valid sample data.
        $itemValues['value'] = $this->getSampleVideoData();
      }
      else {
        // $url might not be valid, but we don't want to re-validate if it isn't
        // necessary. Hence, we store the validation result for a given URL
        // (along w/ the URL) in the $form_state. We then check this cached
        // validation result (if it exists) here.
        if (static::isEmbedUrlValid($url, $form_state)) {
          $id = '';
          $isRecorded = FALSE;
          IbmVideoUrlHelpers::parseEmbedUrl($url, $id, $isRecorded);
          // Set the field value in correspondence with the ID, "is recorded"
          // flag, and the thumbnail reference ID we set earlier.
          assert($this->source->isThumbnailReferenceIdValid($thumbnailReferenceId));
          $itemValues['value'] = $this->source->prepareVideoData($id, $isRecorded, $thumbnailReferenceId);
        }
        else {
          // See earlier comment...
          $itemValues['value'] = $this->getSampleVideoData();
        }
      }
    }

    return $values;
  }

  /**
   * Gets the default video URL to associate with the given field item.
   *
   * @param array $videoData
   *   Video data, as returned by $this->getVideoData().
   *
   * @return string|null
   *   Default video URL. Will be NULL if the URL in $videoData is invalid.
   */
  private function getDefaultVideoUrl(array $videoData) : ?string {
    $id = $videoData[IbmVideo::VIDEO_DATA_ID_PROPERTY_NAME];
    $isRecorded = $videoData[IbmVideo::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME];
    if (!$this->source->isIsRecordedFlagValid($isRecorded) || !$this->source->isVideoOrChannelIdValid($id)) {
      return NULL;
    }
    return IbmVideoUrlHelpers::assembleEmbedUrl($id, $isRecorded, '');
  }

  /**
   * Gets a generic sample video data string.
   */
  private function getSampleVideoData() : string {
    return $this->source->prepareVideoData('123456', FALSE, 'ABCDE');
  }

  /**
   * Gets the video data associated with the given field item, if possible.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field item list.
   * @param int $delta
   *   Position of item in list.
   *
   * @return array|null
   *   Video data, if it exists, or NULL if the source field is not set (or set
   *   to NULL) at $delta. Also can be NULL if the field is corrupt at $delta.
   */
  private function getVideoData(FieldItemListInterface $items, int $delta) : ?array {
    if (!isset($items[$delta])) {
      return NULL;
    }
    $item = $items[$delta];
    if (!isset($item->value)) {
      return NULL;
    }
    $value = (string) $item->value;
    if ($value === '') {
      return NULL;
    }
    $videoData = [];
    if ($this->source->tryParseVideoData($value, $videoData) !== 0) {
      return NULL;
    }

    return $videoData;
  }

  /**
   * Creates and returns a new IBM video widget.
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideoWidget {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return MediaSourceFieldHelpers::doesFieldDefinitionHaveIbmVideoMediaSource($field_definition);
  }

  /**
   * Processes the thumbnail reference ID form element.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state.
   *
   * @throws \RuntimeException
   *   Sometimes thrown if something is wrong with $element. We don't throw an
   *   \InvalidArgumentException because of how this function should be called.
   */
  public static function processThumbnailReferenceId(array &$element, FormStateInterface $formState) : void {
    if (!isset($element['#parents'])) {
      throw new \RuntimeException('The #parents form element item is missing.');
    }
    if (!array_key_exists('#custom', $element)) {
      throw new \RuntimeException('The #custom form element item is missing.');
    }

    $customData = $element['#custom'];
    if (!is_array($customData)) {
      throw new \RuntimeException('The #custom form element item is invalid.');
    }
    if (!array_key_exists('current_thumbnail_reference_id', $customData)) {
      throw new \RuntimeException('The #custom["current_thumbnail_reference_id"] form element item is missing.');
    }
    if (!array_key_exists('default_video_url', $customData)) {
      throw new \RuntimeException('The #custom["default_video_url"] form element item is missing.');
    }

    $currentThumbnailReferenceId = $customData['current_thumbnail_reference_id'];
    if ($currentThumbnailReferenceId !== NULL) $currentThumbnailReferenceId = (string) $currentThumbnailReferenceId;
    $defaultVideoUrl = $customData['default_video_url'];
    if ($defaultVideoUrl !== NULL) $defaultVideoUrl = (string) $defaultVideoUrl;

    $urlElementPath = $element['#parents'];
    if (array_pop($urlElementPath) === NULL) {
      throw new \RuntimeException('The #parents form element item is invalid.');
    }
    array_push($urlElementPath, 'url');
    // Generate a new thumbnail reference ID for a new URL, in order to
    // invalidate any cached thumbnail. Also generate a new ID if no old ID
    // exists. Otherwise, use the old ID.
    if (isset($currentThumbnailReferenceId) && $formState->getValue($urlElementPath) === $defaultVideoUrl) {
      $formState->setValueForElement($element, $currentThumbnailReferenceId);
    }
    else {
      $formState->setValueForElement($element, IbmVideo::generateFreshThumbnailReferenceId());
    }
  }

  /**
   * Validates/sets errors for the embed URL input associated with $element.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state.
   *
   * @throws \RuntimeException
   *   Sometimes thrown if something is wrong with $element. We don't throw an
   *   \InvalidArgumentException because of how this function should be called.
   */
  public static function validateEmbedUrlInput(array &$element, FormStateInterface &$formState) : void {
    if (!isset($element['#parents']) || !is_array($element['#parents'])) {
      throw new \RuntimeException('The #parents form element item is missing or invalid.');
    }
    $url = (string) $formState->getValue($element['#parents']);
    if ($url !== '' && !static::isEmbedUrlValid($url, $formState)) {
      $formState->setErrorByName('url', 'Embed URL is not in the required format.');
    }
  }

  /**
   * Tells whether the given embed URL is valid.
   *
   * If the validation result for the given embed URL is cached in the form
   * state, returns the cached result. Otherwise, validates the URL and stores
   * (in $formState) and returns the result.
   *
   * @param string $embedUrl
   *   Embed URL.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state where the URL validation state cache is located.
   */
  private static function isEmbedUrlValid(string $embedUrl, FormStateInterface $formState) : bool {
    // Use the cached result if possible.
    if ($formState->has('ibm_video_media_type_url_validity')) {
      $validationResult = $formState->get('ibm_video_media_type_url_validity');
      assert(is_array($validationResult));
      if ($validationResult['url'] === $embedUrl) {
        return $validationResult['validity'];
      }
    }
    // Otherwise, perform the validation and dump the result into the cache.
    $isValid = IbmVideoUrlHelpers::isEmbedUrlValid($embedUrl);
    $formState->set('ibm_video_media_type_url_validity', ['url' => $embedUrl, 'validity' => $isValid]);
    return $isValid;
  }

}
