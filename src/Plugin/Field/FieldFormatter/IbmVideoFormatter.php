<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Helper\UrlHelpers;
use Drupal\ibm_video_media_type\Helper\ValidationHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Ranine\Iteration\ExtendableIterable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formats the IBM video media type source field.
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
   * Code for the low default quality setting.
   *
   * @var int
   */
  private const SETTING_DEFAULT_QUALITY_LOW = 1;

  /**
   * Code for the medium default quality setting.
   *
   * @var int
   */
  private const SETTING_DEFAULT_QUALITY_MEDIUM = 2;

  /**
   * Code for the high default quality setting.
   *
   * @var int
   */
  private const SETTING_DEFAULT_QUALITY_HIGH = 3;

  /**
   * Code for the "direct" video WMode setting.
   *
   * @var int
   */
  private const SETTING_WMODE_DIRECT = 1;

  /**
   * Code for the "opaque" video WMode setting.
   *
   * @var int
   */
  private const SETTING_WMODE_OPAQUE = 2;

  /**
   * Code for the "transparent" video WMode setting.
   *
   * @var int
   */
  private const SETTING_WMODE_TRANSPARENT = 3;

  /**
   * Code for the "window" video WMode setting.
   *
   * @var int
   */
  private const SETTING_WMODE_WINDOW = 4;

  /**
   * Video source associated with this formatter.
   */
  private IbmVideo $source;

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    // Cast the settings for which we are allowing the user to specify NULL in
    // the UI to appropriate values, after checking for NULLs.
    $defaultInitialVolume = $this->getSetting('initialVolume');
    if ($defaultInitialVolume !== NULL) {
      $defaultInitialVolume = (int) $defaultInitialVolume;
    }
    $defaultWMode = $this->getSetting('wMode');
    if ($defaultWMode !== NULL) {
      $defaultWMode = (int) $defaultWMode;
    }
    $defaultDefaultQuality = $this->getSetting('defaultQuality');
    if ($defaultDefaultQuality !== NULL) {
      $defaultDefaultQuality = (int) $defaultDefaultQuality;
    }
    return [
      'useAutoplay' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Autoplay'),
        '#default_value' => (bool) $this->getSetting('useAutoplay'),
      ],
      'useHtml5Ui' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use HTML5 UI'),
        '#default_value' => (bool) $this->getSetting('useHtml5Ui'),
      ],
      'displayControls' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Display Playback Controls'),
        '#default_value' => (bool) $this->getSetting('displayControls'),
      ],
      'showTitle' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Video Title'),
        '#default_value' => (bool) $this->getSetting('showTitle'),
      ],
      'initialVolume' => [
        '#type' => 'range',
        '#title' => $this->t('Initial Volume'),
        '#default_value' => $defaultInitialVolume,
        '#min' => 1,
        '#max' => 100,
        '#step' => 1,
      ],
      'wMode' => [
        '#type' => 'list',
        '#title' => $this->t('WMode'),
        '#default_value' => (int) $defaultWMode,
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
        '#default_value' => (int) $defaultDefaultQuality,
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
    foreach ($items as $delta => $item) {
      // Skip empty items.
      if ($item === NULL || ($value = (string) $item->value) === '') {
        $renderElement[$delta] = [];
        goto next_item;
      }

      // Try to extract channel ID and channel video ID from field value. Skip
      // invalid field items.
      $videoData = [];
      if ($this->source->tryParseVideoData($value, $videoData) !== 0) {
        $renderElement[$delta] = [];
        goto next_item;
      }
      $channelId = $videoData['channelId'];
      if (!ValidationHelpers::isChannelIdValid($channelId)) {
        $renderElement[$delta] = [];
        goto next_item;
      }
      $channelVideoId = $videoData['channelVideoId'];
      if (!ValidationHelpers::isChannelVideoIdValid($channelVideoId)) {
        $renderElement[$delta] = [];
        goto next_item;
      }

      // Render the item with a template defined by this module.
      $renderElement[$delta] = [
        '#theme' => 'ibm_video_media_type_player',
        '#videoUrl' => $this->generateVideoUrl($channelId, $channelVideoId),
      ];

next_item:
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($entity)->applyTo($renderElement[$delta]);
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
      ->map(fn($setting) => $this->getSetting($setting))
      ->filter(fn($setting, $value) => $value !== NULL)
      ->map(function ($setting, $value) : string {
        // Validate and cast the setting value to an appropriate form. See
        // https://support.video.ibm.com/hc/en-us/articles/207851927-Using-URL-Parameters-and-Embed-API-for-Custom-Players.
        switch ($setting) {
          case 'useHtml5Ui':
            return $value ? '1' : '0';

          case 'wMode':
            switch ((int) $value) {
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
            switch ((int) $value) {
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
            $initialVolume = (int) $value;
            if (!static::isInitialVolumeInRange($initialVolume)) {
              throw new \RuntimeException('Unexpected initialVolume setting encountered.');
            }
            return (string) $initialVolume;

          default:
            return $value ? 'true' : 'false';
        }
      })->toArray());
    // Use a protocol-neutral protocol prefix ("//").
    $baseUrl = UrlHelpers::assembleIbmVideoPermalinkUrl($channelId, $channelVideoId, '//');

    return $queryString === '' ? $baseUrl : ($baseUrl . '?' . $queryString);
  }

  /**
   * Creates and returns a new IBM video widget.
   *
   * @param ContainerInterface $container
   *   Service container
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
    /** @var \Drupal\ibm_video_media_type\Plugin\FieldFormatter\IbmVideoFormatter */
    $formatter = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // Grab the media source.
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
    $entityTypeManager = $container->get('entity_type.manager');
    $bundle = $formatter->fieldDefinition->getTargetBundle();
    if (!is_string($bundle)) {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type without a target media bundle.');
    }
    if ($formatter->fieldDefinition->getTargetEntityTypeId() !== 'media') {
      throw new \InvalidArgumentException('Cannot create an IBM video formatter defined for a field type with a target entity type that is not "media".');
    }
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
