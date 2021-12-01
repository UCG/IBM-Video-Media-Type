<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlHelpers;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlParameters;
use Drupal\ibm_video_media_type\Helper\MediaSourceFieldHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
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
   * Default settings, or NULL if the defaults are not yet defined.
   */
  private static ?array $defaultSettings = NULL;

  /**
   * Video source associated with this formatter.
   */
  private IbmVideo $source;

  /**
   * Creates a new IBM video formatter.
   *
   * @param string $pluginId
   *   Formatter plugin ID.
   * @param mixed $pluginDefinition
   *   Plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Definition of the field with which the formatter is associated.
   * @param array $settings
   *   Formatter settings.
   * @param string $label
   *   Formatter label display setting.
   * @param string $viewMode
   *   View mode.
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
    string $label,
    string $viewMode,
    array $thirdPartySettings,
    EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $label, $viewMode, $thirdPartySettings);

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
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) : array {
    return [
      'use_autoplay' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use Autoplay'),
        '#default_value' => $this->prepareSetting('use_autoplay'),
      ],
      'use_html5_ui' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Use HTML5 UI'),
        '#default_value' => $this->prepareSetting('use_html5_ui'),
      ],
      'display_controls' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Display Playback Controls'),
        '#default_value' => $this->prepareSetting('display_controls'),
      ],
      'show_title' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Show Video Title'),
        '#default_value' => $this->prepareSetting('show_title'),
      ],
      'initial_volume' => [
        '#type' => 'range',
        '#title' => $this->t('Initial Volume'),
        '#default_value' => $this->prepareSetting('initial_volume'),
        '#min' => 1,
        '#max' => 100,
        '#step' => 1,
      ],
      'w_mode' => [
        '#type' => 'list',
        '#title' => $this->t('WMode'),
        '#default_value' => $this->prepareSetting('w_mode'),
        '#options' => [
          IbmVideoUrlParameters::WMODE_DIRECT => 'Direct',
          IbmVideoUrlParameters::WMODE_OPAQUE => 'Opaque',
          IbmVideoUrlParameters::WMODE_TRANSPARENT => 'Transparent',
          IbmVideoUrlParameters::WMODE_WINDOW => 'Window',
        ],
      ],
      'default_quality' => [
        '#type' => 'list',
        '#title' => $this->t('Default Quality'),
        '#default_value' => $this->prepareSetting('default_quality'),
        '#options' => [
          IbmVideoUrlParameters::DEFAULT_QUALITY_LOW => 'Low',
          IbmVideoUrlParameters::DEFAULT_QUALITY_MEDIUM => 'Medium',
          IbmVideoUrlParameters::DEFAULT_QUALITY_HIGH => 'High',
        ],
      ],
    ];
  }

  /**
   * Returns the render array, keyed by delta, for the given field items.
   *
   * Only the first item is rendered, and it is not rendered if it is NULL,
   * empty, or in some cases if it is invalid. The item is parsed, if possible,
   * as a JSON string of video metadata, in accordance with what is defined in
   * @see \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
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
    $firstItemPassed = FALSE;
    foreach ($items as $delta => $item) {
      // Skip all items except the first item, and skip empty items.
      if ($firstItemPassed || $item === NULL || ($value = (string) $item->value) === '') {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }

      // Try to extract base embed URL and video ID from field item.
      $videoData = [];
      if ($this->source->tryParseVideoData($value, $videoData) !== 0) {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }
      $baseEmbedUrl = $videoData[IbmVideo::VIDEO_DATA_BASE_EMBED_BASE_URL_PROPERTY_NAME];
      if (!$this->source->isBaseEmbedUrlValid($baseEmbedUrl)) {
        $renderElement[$delta] = [];
        goto finish_element_item;
      }
      /** @var string $baseEmbedUrl */

      // Render the item with a template defined by this module. We use the "//"
      // "scheme" when generating the embed URL, to force the embed iFrame
      // to be served from the same protocol as is the main website.
      $renderElement[$delta] = [
        '#theme' => 'ibm_video_player',
        '#videoUrl' => IbmVideoUrlHelpers::assembleEmbedUrl($baseEmbedUrl, '//', $this->getEmbedUrlParameters()),
      ];

finish_element_item:
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($entity)->applyTo($renderElement[$delta]);
      $firstItemPassed = TRUE;
    }

    return $renderElement;
  }

  /**
   * Computes the set of embed URL parameters from the settings.
   *
   * Also validates the settings associated with the parameters as the set of
   * parameters is built.
   *
   * @return \Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlParameters
   *   Video URL parameters.
   *
   * @throws \RuntimeException
   *   Thrown if one or more of the relevant settings is invalid.
   */
  public function getEmbedUrlParameters() : IbmVideoUrlParameters {
    $nonNullBoolCasting = fn ($x) => $x === NULL ? NULL : (bool) $x;
    $nonNullIntCasting = fn ($x) => $x === NULL ? NULL : (int) $x;
    try {
      return (new IbmVideoUrlParameters())
        ->setDefaultQuality($nonNullIntCasting($this->getSetting('default_quality')))
        ->setDefaultQuality($nonNullBoolCasting($this->getSetting('display_controls')))
        ->setDefaultQuality($nonNullIntCasting($this->getSetting('initial_volume')))
        ->setDefaultQuality($nonNullBoolCasting($this->getSetting('show_title')))
        ->setDefaultQuality($nonNullBoolCasting($this->getSetting('use_autoplay')))
        ->setDefaultQuality($nonNullBoolCasting($this->getSetting('use_html5_ui')))
        ->setDefaultQuality($nonNullIntCasting($this->getSetting('w_mode')));
    }
    catch (\InvalidArgumentException $e) {
      throw new \RuntimeException('A video formatter setting is invalid.', 0, $e);
    }
  }

  /**
   * Prepares a given setting for use by casting and validating it.
   *
   * @param string $settingName
   *   Setting name -- must be one of the settings defined by this class (not
   *   by a superclass).
   *
   * @return mixed
   *   Prepared setting. If the setting isn't set, the default value for the
   *   setting is returned. If the setting is invalid, an exception is thrown.
   *   Otherwise, the setting is casted to its canonical primitive type and
   *   returned.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $settingName is not a valid setting name (associated with this
   *   class, and not a superclass).
   * @throws \RuntimeException
   *   Thrown if the setting value corresponding to $settingName is invalid.
   */
  private function prepareSetting(string $settingName) {
    $value = $this->getSetting($settingName);
    $invalidSettingConditionalThrowing = function (bool $shouldThrow) use ($settingName) {
      if ($shouldThrow) {
        throw new \RuntimeException('Invalid "' . $settingName . '" value.');
      }
    };
    $nonNullBoolCasting = fn ($x) => $x === NULL ? NULL : (bool) $x;
    $nonNullIntCasting = fn ($x) => $x === NULL ? NULL : (int) $x;
    switch ($value) {
      case 'default_quality':
        $preparedValue = $nonNullIntCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isDefaultQualityValid($preparedValue));
        return $preparedValue;

      case 'display_controls':
        $preparedValue = $nonNullBoolCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isDisplayControlsFlagValid($value));
        return $preparedValue;

      case 'initial_volume':
        $preparedValue = $nonNullIntCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isInitialVolumeValid($value));
        return $preparedValue;

      case 'show_title':
        $preparedValue = $nonNullBoolCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isShowTitleFlagValid($value));
        return $preparedValue;

      case 'use_autoplay':
        $preparedValue = $nonNullBoolCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isUseAutoplayFlagValid($value));
        return $preparedValue;

      case 'use_html5_ui':
        $preparedValue = $nonNullBoolCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isUseHtml5UiFlagValid($value));
        return $preparedValue;
      
      case 'w_mode':
        $preparedValue = $nonNullIntCasting($value);
        $invalidSettingConditionalThrowing(IbmVideoUrlParameters::isWModeValid($value));
        return $preparedValue;

      default:
        throw new \InvalidArgumentException('$settingName is not one of the settings defined by this class.');
    }
  }

  /**
   * Creates and returns a new IBM video formatter.
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : IbmVideoFormatter {
    return new static($plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() : array {
    return static::getUnmergedDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) : bool {
    return MediaSourceFieldHelpers::doesFieldDefinitionHaveIbmVideoMediaSource($field_definition);
  }

  /**
   * Gets the set of default settings corresponding to this class.
   *
   * This function does not merge the default settings with those from the
   * parent class.
   */
  private static function getUnmergedDefaultSettings() : array {
    if (static::$defaultSettings === NULL) {
      $defaultParameterSet = new IbmVideoUrlParameters();
      static::$defaultSettings = [
        'default_quality' => $defaultParameterSet->getDefaultQuality(),
        'display_controls' => $defaultParameterSet->getDisplayControlsFlag(),
        'initial_volume' => $defaultParameterSet->getInitialVolume(),
        'show_title' => $defaultParameterSet->getShowTitleFlag(),
        'use_autoplay' => $defaultParameterSet->getUseAutoplayFlag(),
        'use_html5_ui' => $defaultParameterSet->getUseHtml5UiFlag(),
        'w_mode' => $defaultParameterSet->getWMode(),
      ];
    }
    return static::$defaultSettings;
  }

}
