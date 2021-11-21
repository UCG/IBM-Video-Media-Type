<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrlHelpers;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Ranine\Iteration\ExtendableIterable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats the IBM video media type source field.
 *
 * @FieldFormatter(
 *   id = "ibm_video",
 *   label = @Translation("IBM Video"),
 *   field_types = {"string"},
 * )
 */
class IbmVideoFormatter extends FormatterBase {

  /**
   * Video source associated with this formatter.
   */
  private IbmVideo $source;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    return [
      'useAutoplay' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Autoplay'),
        '#default_value' => $this->prepareSetting('useAutoplay'),
      ],
      'useHtml5Ui' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use HTML5 UI'),
        '#default_value' => $this->prepareSetting('useHtml5Ui'),
      ],
      'displayControls' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Display Playback Controls'),
        '#default_value' => $this->prepareSetting('displayControls'),
      ],
      'showTitle' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Video Title'),
        '#default_value' => $this->prepareSetting('showTitle'),
      ],
      'initialVolume' => [
        '#type' => 'range',
        '#title' => $this->t('Initial Volume'),
        '#default_value' => $this->prepareSetting('initialVolume'),
        '#min' => 1,
        '#max' => 100,
        '#step' => 1,
      ],
      'wMode' => [
        '#type' => 'list',
        '#title' => $this->t('WMode'),
        '#default_value' => $this->prepareSetting('wMode'),
        '#options' => [
          static::SETTING_WMODE_DIRECT => 'Direct',
          static::SETTING_WMODE_OPAQUE => 'Opaque',
          static::SETTING_WMODE_TRANSPARENT => 'Transparent',
          static::SETTING_WMODE_WINDOW => 'Window',
        ],
      ],
      'defaultQuality' => [
        '#type' => 'list',
        '#title' => $this->t('Default Quality'),
        '#default_value' => $this->prepareSetting('defaultQuality'),
        '#options' => [
          static::SETTING_DEFAULT_QUALITY_LOW => 'Low',
          static::SETTING_DEFAULT_QUALITY_MEDIUM => 'Medium',
          static::SETTING_DEFAULT_QUALITY_HIGH => 'High',
        ],
      ],
    ];
  }

  /**
   * Returns the render array, keyed by delta, for the given field items.
   *
   * NULL, empty, and invalid field items are skipped. Each field item is
   * parsed as a JSON string of video metadata, in accordance with what is
   * defined in @see \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   Field items.
   * @param string $langcode
   *   Language that should be used to render the field.
   *
   * @throws \RuntimeException
   *   Thrown if an invalid formatter setting is encountered when trying to
   *   build a video embed URL.
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $entity = $items->getEntity();
    $renderElement = [];
    $isFirstItem = TRUE;
    foreach ($items as $delta => $item) {
      // Skip all items except the first item, and skip empty items.
      if (!$isFirstItem && $item === NULL || ($value = (string) $item->value) === '') {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }

      // Try to extract channel ID and channel video ID from field value. Skip
      // invalid field items.
      $videoData = [];
      if ($this->source->tryParseVideoData($value, $videoData) !== 0) {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }
      $channelId = $videoData['channelId'];
      if (!IbmVideoUrlHelpers::isChannelIdValid($channelId)) {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }
      $channelVideoId = $videoData['channelVideoId'];
      if (!IbmVideoUrlHelpers::isChannelVideoIdValid($channelVideoId)) {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }

      // Render the item with a template defined by this module.
      $renderElement[$delta] = [
        '#theme' => 'ibm_video_player',
        '#videoUrl' => $this->generateVideoUrl($channelId, $channelVideoId),
      ];

finish_element_item:
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($entity)->applyTo($renderElement[$delta]);
      $isFirstItem = FALSE;
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
   *
   * @throws \RuntimeException
   *   Thrown if an invalid formatter setting is encountered when trying to
   *   build the URL.
   */
  private function generateVideoUrl(string $channelId, string $channelVideoId) : string {
    $queryString = UrlHelper::buildQuery(ExtendableIterable::from(static::PLAYER_SETTINGS_AND_DEFAULTS)
      ->map(fn(string $setting) => $this->prepareSetting($setting))
      ->filter(fn($setting, $value) => $value !== NULL)
      ->map(function (string $setting, $value) : string {
        // Validate and cast the setting value to an appropriate form. See
        // https://support.video.ibm.com/hc/en-us/articles/207851927-Using-URL-Parameters-and-Embed-API-for-Custom-Players.
        switch ($setting) {
          case 'useHtml5Ui':
            return $value ? '1' : '0';

          case 'wMode':
            switch ($value) {
              case static::SETTING_WMODE_DIRECT:
                return 'direct';

              case static::SETTING_WMODE_OPAQUE:
                return 'opaque';

              case static::SETTING_WMODE_TRANSPARENT:
                return 'transparent';

              case static::SETTING_WMODE_WINDOW:
                return 'window';

              default:
                throw new \RuntimeException('Unexpected wMode setting encountered.');
            }

          case 'defaultQuality':
            switch ($value) {
              case static::SETTING_DEFAULT_QUALITY_LOW:
                return 'low';

              case static::SETTING_DEFAULT_QUALITY_MEDIUM:
                return 'medium';

              case static::SETTING_DEFAULT_QUALITY_HIGH:
                return 'high';

              default:
                throw new \RuntimeException('Unexpected defaultQuality setting encountered.');
            }

          case 'initialVolume':
            if (!static::isInitialVolumeInRange($value)) {
              throw new \RuntimeException('Unexpected initialVolume setting encountered.');
            }
            return (string) $value;

          case 'useAutoplay':
          case 'useHtml5Ui':
          case 'displayControls':
          case 'showTitle':
            return $value ? 'true' : 'false';

          default:
            throw new \RuntimeException('Unexpected setting encountered.');
        }
      })->toArray());
    // Use a protocol-neutral protocol prefix ("//").
    $baseUrl = IbmVideoUrlHelpers::assemblePermalinkUrl($channelId, $channelVideoId, '//');

    return $queryString === '' ? $baseUrl : ($baseUrl . '?' . $queryString);
  }

  /**
   * Prepares a given setting for use.
   *
   * @param string $settingName
   *   Setting name.
   *
   * @return mixed
   *   Prepared setting. If the setting isn't set, or is considered
   *   "non-nullable" but is set to NULL, the default value for the setting is
   *   is returned. The returned setting is also casted to the primitive type
   *   corresponding to the setting.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $settingName is not a valid setting name.
   */
  private function prepareSetting(string $settingName) {
    $value = $this->getSetting($settingName);
    if ($value === NULL) {
      // If the setting isn't nullable, look up the default setting (if we can).
      switch ($settingName) {
        case 'wMode':
        case 'defaultQuality':
          // These are the nullable settings.
          return NULL;

        default:
          if (!array_key_exists($settingName, static::PLAYER_SETTINGS_AND_DEFAULTS)) {
            throw new \InvalidArgumentException('$settingName is not valid.');
          }
          return static::PLAYER_SETTINGS_AND_DEFAULTS[$settingName];
      }
    }
    else {
      // Cast the setting to an appropriate type.
      switch ($settingName) {
        case 'wMode':
        case 'defaultQuality':
        case 'initialVolume':
          return (int) $value;

        case 'useAutoplay':
        case 'useHtml5Ui':
        case 'displayControls':
        case 'showTitle':
          return (bool) $value;

        default:
          throw new \RuntimeException('Unexpected setting name.');
      }
    }
  }

  /**
   * Creates and returns a new IBM video formatter.
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideoFormatter {
    // We call parent::create() instead of invoking our own constructor, because
    // Drupal plugin constructors are technically not part of the public API.
    /** @var \Drupal\ibm_video_media_type\Plugin\Field\FieldFormatter\IbmVideoFormatter */
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $bundle = $formatter->fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type without a target media bundle.');
    }

    if ($formatter->fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a target entity type that is not "media".');
    }

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    $entityTypeManager = $container->get('entity_type.manager');
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
  public static function defaultSettings() : array {
    return static::PLAYER_SETTINGS_AND_DEFAULTS + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return MediaSourceFieldHelpers::doesFieldDefinitionHaveIbmVideoMediaSource($field_definition);
  }

  /**
   * Tells whether given initial volume value is valid (btwn 0-100, inclusive).
   *
   * @param int $initialVolume
   *   Initial volume value to check.
   */
  private static function isInitialVolumeInRange(int $initialVolume) : bool {
    return ($initialVolume >= 0 && $initialVolume <= 100) ? TRUE : FALSE;
  }

}
