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
   *   Formatter plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Definition of the field with which the formatter is associated.
   * @param array $settings
   *   Formatter settings.
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

    $bundle = $fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type without a target media bundle.');
    }

    if ($fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a target entity type that is not "media".');
    }

    /** @var \Drupal\media\MediaTypeInterface */
    $mediaType = $entityTypeManager->getStorage('media_type')->load($bundle);
    $source = $mediaType->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a media source that is not of type \\Drupal\\ibm_video_media_type\\Plugin\\media\\Source\\IbmVideo.');
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

    $videoData = $this->getVideoData($items, $delta);
    // We will need the thumbnail reference ID later, when we generate the
    // source field value after submission in massageFormValues(). Since that
    // method does not receive something that retrieves the media entity as an
    // argument, we'll try to retrieve the thumbnail reference ID here and store
    // it in the '#custom' property. We'll also go ahead and obtain the default
    // video URL.
    if ($videoData === NULL) {
      $defaultVideoUrl = NULL;
      $thumbnailReferenceId = NULL;
    }
    else {
      $defaultVideoUrl = $this->getDefaultVideoUrl($videoData);
      if (array_key_exists(IbmVideo::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME, $videoData)) {
        $thumbnailReferenceId = $videoData[IbmVideo::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME];
        if (!$this->source->isThumbnailReferenceIdValid($thumbnailReferenceId)) {
          // Don't use the thumbnail reference ID if it's invalid.
          $thumbnailReferenceId = NULL;
        }
      }
    }
    $element['#custom'] = ['thumbnail_reference_id' => $thumbnailReferenceId];
    $element['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Embed URL'),
      '#description' => $this->t(
<<<'EOS'
The canonical embed URL for the video. Should be a URL starting with "https://",
"http://", "//" or nothing at all (""), and followed by
"video.ibm.com/recorded/[video ID]" (for a recorded video) or
"video.ibm.com/[channel ID]" (for a stream), with an optional query string
and/or fragment. This URL may be "cleaned up" before it is used to embed the
video.
EOS
      ),
      '#default_value' => $defaultVideoUrl,
      '#size' => 50,
      '#maxlength' => 120,
      '#pattern' => IbmVideoUrlHelpers::REGEX_EMBED_URL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) : array {
    foreach ($values as $elementKey => &$itemValues) {
      assert(is_array($itemValues) && isset($itemValues['value']));
      $url = (string) $itemValues['value'];
      if ($url === '') {
        $itemValues['value'] = NULL;
      }
      else {
        $id = '';
        $isRecorded = FALSE;
        IbmVideoUrlHelpers::parseEmbedUrl($url, $id, $isRecorded);
        // Unset the "url" property, and set the field value in correspondence
        // with the ID, "is recorded" flag, and with the thumbnail reference ID
        // we set earlier.
        $thumbnailReferenceId = $form[$elementKey]['#custom'];
        assert(is_null($thumbnailReferenceId) || $this->source->isThumbnailReferenceIdValid($thumbnailReferenceId));
        unset($itemValues['url']);
        $itemValues['value'] = $this->source->prepareVideoData($id, $isRecorded, $thumbnailReferenceId);
      }
    }

    return $values;
  }

  /**
   * Gets the default video URL to associate with the given field item.
   *
   * @param array $videoData
   *   Video data, as returned by $this-getVideoData().
   *
   * @return string|null
   *   Default video URL. Can be NULL if the field is corrupt at $delta.
   */
  private function getDefaultVideoUrl(array $videoData) : ?string {
    // Build an IBM video URL to use as the default value for the text field. If
    // is nothing is set for the field item, don't display anything for the
    // default value.
    $id = $videoData[IbmVideo::VIDEO_DATA_ID_PROPERTY_NAME];
    $isRecorded = $videoData[IbmVideo::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME];
    if (!$this->source->isIsRecordedFlagValid($isRecorded) || !$this->source->isVideoOrChannelIdValid($id)) {
      return NULL;
    }
    return IbmVideoUrlHelpers::assembleEmbedUrl($id, $isRecorded, '');
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

}
