<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Helper\UrlHelpers;
use Drupal\ibm_video_media_type\Helper\ValidationHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for IBM video source field.
 *
 * @FieldWidget(
 *   id = "ibm_video_input",
 *   label = @Translation("IBM Video Input"),
 *   field_types = {"string_long"},
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
      '#title' => $this->t('Permalink Embed URL'),
      '#description' => $this->t(
<<<'EOS'
The permalink embed URL for the video. Should be a URL starting with "https://",
"http://", "//" or nothing at all (""), and followed by
"video.ibm.com/embed/channel/[channel ID made of digits 0-9]/video/[channel
video ID made of alphanumeric characters][optional query string preceeded by
'?']". This URL may be "cleaned up" by us before it is used to embed the video.
EOS
      ),
      '#default_value' => $this->getDefaultVideoUrl($items, $delta),
      '#size' => 50,
      '#placeholder' => 'https://video.ibm.com/embed/channel/01234567/video/abcdef',
      '#maxlength' => 120,
      '#pattern' => '(?i)(http://|https://|//)?video.ibm.com/embed/channel/[0-9]+/video/([a-z]|[0-9])+(\?.*)?',
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
        // Extract the channel and channel video IDs.
        $parts = explode('video.ibm.com/embed/channel/', $url, 2);
        assert(count($parts) === 2);
        $channelIdAndRemainder = $parts[1];
        $parts = explode('/video/', $channelIdAndRemainder);
        assert(count($parts) === 2);
        $channelId = $parts[0];
        assert(is_string($channelId));
        assert(ValidationHelpers::isChannelIdValid($channelId));
        $videoIdAndRemainder = $parts[1];
        $parts = explode('?', $videoIdAndRemainder, 2);
        assert(count($parts) === 2);
        $channelVideoId = $parts[0];
        assert(is_string($channelVideoId));
        assert(ValidationHelpers::isChannelVideoIdValid($channelVideoId));

        // Set the field value to the JSON appropriate for the channel and video
        // IDs.
        $itemValues['value'] = $this->source->prepareVideoData($channelId, $channelVideoId);
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
   *   Default video URL, or NULL if the field is not set to a valid value at
   *   $delta.
   */
  private function getDefaultVideoUrl(FieldItemListInterface $items, int $delta) : ?string {
    // Build an IBM video URL to use as the default value for the text field. If
    // is nothing is set for the field item, or the item is incorrectly
    // formatted, don't display anything for the default value.

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
    $channelId = $videoData[IbmVideo::VIDEO_DATA_CHANNEL_ID_PROPERTY_NAME];
    if (!is_string($channelId) || !ValidationHelpers::isChannelIdValid($channelId)) {
      return NULL;
    }
    $channelVideoId = $videoData[IbmVideo::VIDEO_DATA_CHANNEL_VIDEO_ID_PROPERTY_NAME];
    if (!is_string($channelVideoId) || !ValidationHelpers::isChannelVideoIdValid($channelVideoId)) {
      return NULL;
    }
    /** @var string $channelId */
    /** @var string $channelVideoId */
    return UrlHelpers::assembleIbmVideoPermalinkUrl($channelId, $channelVideoId, 'https://');
  }

  /**
   * Creates and returns a new IBM video widget.
   *
   * @param ContainerInterface $container
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
