<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Field\FieldWidget;

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

    $element['value'] = [
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
      '#default_value' => $this->getDefaultVideoUrl($items, $delta),
      '#size' => 50,
      '#placeholder' => 'video.ibm.com/012345678',
      '#maxlength' => 120,
      '#pattern' => IbmVideoUrlHelpers::REGEX_EMBED_URL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) : array {
    foreach ($values as &$itemValues) {
      assert(is_array($itemValues) && isset($itemValues['value']));
      $url = (string) $itemValues['value'];
      if ($url === '') {
        $itemValues['value'] = NULL;
      }
      else {
        $videoId = NULL;
        $baseEmbedUrl = IbmVideoUrlHelpers::extractBaseEmbedUrlAndVideoId($url, $videoId);
        // Set the field value to the JSON appropriate for the base embed URL
        // and video ID.
        $itemValues['value'] = $this->source->prepareVideoData($baseEmbedUrl, $videoId);
      }
    }

    return $values;
  }

  /**
   * Gets the default video URL to associate with the given field item.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field item list.
   * @param int $delta
   *   Position of field item in list.
   *
   * @return string|null
   *   Default video URL, or NULL if the field is not set (or set to NULL) at
   *   $delta. Also can be NULL if the field is corrupt at $delta.
   */
  private function getDefaultVideoUrl(FieldItemListInterface $items, int $delta) : ?string {
    // Build an IBM video URL to use as the default value for the text field. If
    // is nothing is set for the field item, don't display anything for the
    // default value.

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
    $baseEmbedUrl = $videoData[IbmVideo::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME];
    return (is_string($baseEmbedUrl) && IbmVideoUrlHelpers::isBaseEmbedUrlValid($baseEmbedUrl)) ? $baseEmbedUrl : NULL;
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
   *
   * @throws \InvalidArgumentException
   *   Thrown if the field definition does not have a target bundle, or does not
   *   have a target entity type ID of "media", or does not have a media source
   *   of type \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideoWidget {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    /** @var \Drupal\ibm_video_media_type\Plugin\Field\FieldWidget\IbmVideoWidget */
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $bundle = $widget->fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type without a target media bundle.');
    }

    if ($widget->fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type with a target entity type that is not "media".');
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    $entityTypeManager = $container->get('entity_type.manager');
    /** @var \Drupal\media\MediaTypeInterface */
    $mediaType = $entityTypeManager->getStorage('media_type')->load($bundle);
    $source = $mediaType->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('Cannot create an IBM video widget defined for a field type with a media source that is not of type \\Drupal\\ibm_video_media_type\\Plugin\\media\\Source\\IbmVideo.');
    }
    $widget->source = $source;

    return $widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return MediaSourceFieldHelpers::doesFieldDefinitionHaveIbmVideoMediaSource($field_definition);
  }

}
