<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper\IbmVideoUrl;

use Drupal\Component\Utility\UrlHelper;

/**
 * A set of IBM video embed URL parameters.
 */
class IbmVideoUrlParameters {

  /**
   * Code for the low default quality setting.
   *
   * @var int
   */
  public const DEFAULT_QUALITY_LOW = 1;

  /**
   * Code for the medium default quality setting.
   *
   * @var int
   */
  public const DEFAULT_QUALITY_MEDIUM = 2;

  /**
   * Code for the high default quality setting.
   *
   * @var int
   */
  public const DEFAULT_QUALITY_HIGH = 3;

  /**
   * Code for the "direct" video WMode setting.
   *
   * @var int
   */
  public const WMODE_DIRECT = 1;

  /**
   * Code for the "opaque" video WMode setting.
   *
   * @var int
   */
  public const WMODE_OPAQUE = 2;

  /**
   * Code for the "transparent" video WMode setting.
   *
   * @var int
   */
  public const WMODE_TRANSPARENT = 3;

  /**
   * Code for the "window" video WMode setting.
   *
   * @var int
   */
  public const WMODE_WINDOW = 4;

  /**
   * Default playback quality (or NULL to not specify).
   *
   * If not NULL, can be one of static::DEFAULT_QUALITY_LOW,
   * static::DEFAULT_QUALITY_MEDIUM, or static::DEFAULT_QUALITY_HIGH.
   */
  private ?int $defaultQuality = NULL;

  /**
   * Initial volume for video player.
   */
  private int $initialVolume = 50;

  /**
   * Whether to show the video title in the player.
   */
  private bool $showTitle = TRUE;

  /**
   * Whether to autoplay the video.
   */
  private bool $useAutoplay = FALSE;

  /**
   * Whether to use the HTML5 UI.
   */
  private bool $useHtml5Ui = TRUE;

  /**
   * Default WMode setting (or NULL to not specify).
   *
   * If not NULL, can be one of static::WMODE_DIRECT, static::WMODE_OPAQUE,
   * static::_WMODE_TRANSPARENT, or static::_WMODE_WINDOW.
   */
  private ?int $wMode = NULL;

  /**
   * Creates a default set of reasonable video URL parameters.
   */
  public function __construct() {
  }

  /**
   * Gets the default quality property.
   *
   * @return int|null
   *   NULL if no default quality. Otherwise, one of
   *   static::DEFAULT_QUALITY_LOW, static::DEFAULT_QUALITY_MEDIUM, or
   *   static::DEFAULT_QUALITY_HIGH.
   */
  public function getDefaultQuality() : ?int {
    return $this->defaultQuality;
  }

  /**
   * Gets the initial volume.
   *
   * @return int
   *   Initial volume. An integer in the range [0, 100].
   */
  public function getInitialVolume() : int {
    return $this->initialVolume;
  }

  /**
   * Gets the "show title" flag.
   *
   * @return bool
   *   A value indicating whether the title should be shown in the video player.
   */
  public function getShowTitleFlag() : bool {
    return $this->showTitle;
  }

  /**
   * Gets the "use autoplay" flag.
   *
   * @return bool
   *   A value indicating whether autoplay should be initiated by the player.
   */
  public function getUseAutoplayFlag() : bool {
    return $this->useAutoplay;
  }

  /**
   * Gets the "use HTML5 UI" flag.
   *
   * @return bool
   *   A value indicating whether the HTML5 video player UI should be used.
   */
  public function getUseHtml5UiFlag() : bool {
    return $this->useHtml5Ui;
  }

  /**
   * Gets the WMode property.
   *
   * @return int|null
   *   NULL if no WMode. Otherwise, one of static::WMODE_DIRECT,
   *   static::WMODE_OPAQUE, static::_WMODE_TRANSPARENT, or
   *   static::_WMODE_WINDOW.
   */
  public function getWMode() : ?int {
    return $this->wMode;
  }

  /**
   * Sets the default quality to the provided value.
   *
   * @param int|null $defaultQuality
   *   NULL if no default quality. Otherwise, one of
   *   static::DEFAULT_QUALITY_LOW, static::DEFAULT_QUALITY_MEDIUM, or
   *   static::DEFAULT_QUALITY_HIGH.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   Thrown if $defaultQuality is not one of the allowed options.
   */
  public function setDefaultQuality(?int $defaultQuality) : IbmVideoUrlParameters {
    if ($defaultQuality !== NULL) {
      switch ($defaultQuality) {
        case static::DEFAULT_QUALITY_LOW:
        case static::DEFAULT_QUALITY_MEDIUM:
        case static::DEFAULT_QUALITY_HIGH:
          break;

        default:
          throw new \InvalidArgumentException('$defaultQuality is not one of the allowed options.');
      }
    }
    $this->defaultQuality = $defaultQuality;
    return $this;
  }

  /**
   * Sets the initial volume.
   *
   * @param int $initialVolume
   *   Initial volume. An integer in the range [0, 100].
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   Thrown if $initialVolume is not in the correct range.
   */
  public function setInitialVolume(int $initialVolume) : IbmVideoUrlParameters {
    if ($initialVolume < 0 || $initialVolume > 100) {
      throw new \InvalidArgumentException('$initialVolume is not in the range from 0-100, inclusive.');
    }

    $this->initialVolume = $initialVolume;
    return $this;
  }

  /**
   * Sets the "show title" flag.
   *
   * @param bool $showTitle
   *   A value indicating whether the title should be shown in the video player.
   *
   * @return $this
   */
  public function setShowTitleFlag(bool $showTitle) : IbmVideoUrlParameters {
    $this->showTitle = $showTitle;
    return $this;
  }

  /**
   * Sets the "use autoplay" flag.
   *
   * @param bool $useAutoplay
   *   A value indicating whether autoplay should be initiated by the player.
   */
  public function setUseAutoplayFlag(bool $useAutoplay) : IbmVideoUrlParameters {
    $this->useAutoplay = $useAutoplay;
    return $this;
  }

  /**
   * Sets the "use HTML5 UI" flag.
   *
   * @param bool $useHtml5Ui
   *   A value indicating whether the HTML5 video player UI should be used.
   *
   * @return $this
   */
  public function setUseHtml5UiFlag(bool $useHtml5Ui) : IbmVideoUrlParameters {
    $this->useHtml5Ui = $useHtml5Ui;
    return $this;
  }

  /**
   * Sets the WMode property.
   *
   * @param int|null $wMode
   *   NULL if no WMode. Otherwise, one of static::WMODE_DIRECT,
   *   static::WMODE_OPAQUE, static::_WMODE_TRANSPARENT, or
   *   static::_WMODE_WINDOW.
   *
   * @return $this
   *
   * @throws \InvalidArgumentException
   *   Thrown if $wMode is not one of the allowed options.
   */
  public function setWMode(?int $wMode) : IbmVideoUrlParameters {
    if ($wMode !== NULL) {
      switch ($wMode) {
        case static::WMODE_DIRECT:
        case static::WMODE_OPAQUE:
        case static::WMODE_TRANSPARENT:
        case static::WMODE_WINDOW:
          break;

        default:
          throw new \InvalidArgumentException('$wMode is not one of the allowed options.');
      }
    }
    $this->wMode = $wMode;
    return $this;
  }
  
  /**
   * Converts this set of parameters into a query string.
   *
   * @return string
   *   A query string for use in an embed URL.
   */
  public function toEmbedUrlQueryString() : string {
    if ($this->defaultQuality !== NULL) {
      switch ($this->defaultQuality) {
        case static::DEFAULT_QUALITY_LOW:
          $defaultQualityStringRep = 'low';
          break;

        case static::DEFAULT_QUALITY_MEDIUM:
          $defaultQualityStringRep = 'medium';
          break;

        case static::DEFAULT_QUALITY_HIGH:
          $defaultQualityStringRep = 'high';
          break;

        default:
          throw new \RuntimeException('Unexpected default quality.');
      }
    }
    if ($this->wMode !== NULL) {
      switch ($this->wMode) {
        case static::WMODE_DIRECT:
          $wModeStringRep = 'direct';
          break;

        case static::WMODE_OPAQUE:
          $wModeStringRep = 'opaque';
          break;

        case static::WMODE_TRANSPARENT:
          $wModeStringRep = 'transparent';
          break;

        case static::WMODE_WINDOW:
          $wModeStringRep = 'window';
          break;

        default:
          throw new \RuntimeException('Unexpected WMode.');
      }
    }
    $trueFalseTextBoolConversion = fn(bool $s) : string => $s ? 'true' : 'false';
    $query = [
      'initialVolume' => (string) $this->initialVolume,
      'showTitle' => $trueFalseTextBoolConversion($this->showTitle),
      'useAutoplay' => $trueFalseTextBoolConversion($this->useAutoplay),
      'useHtml5Ui' => $this->useHtml5Ui ? '1' : '0',
    ];
    if ($this->defaultQuality !== NULL) {
      $query['defaultQuality'] = $defaultQualityStringRep;
    }
    if ($this->wMode !== NULL) {
      $query['wMode'] = $wModeStringRep;
    }

    return UrlHelper::buildQuery($query);
  }

  /**
   * Tells whether the given default quality is valid.
   *
   * The default quality is valid if it is of type "int" and is one of the
   * static::DEFAULT_QUALITY_* values.
   *
   * @param mixed $defaultQuality
   *   Default quality.
   */
  public static function validateDefaultQuality($defaultQuality) : bool {
    return (is_int($defaultQuality) && static::validateDefaultQualityInternal($defaultQuality)) ? TRUE : FALSE;
  }

  /**
   * Tells whether the given initial volume is valid.
   *
   * The initial volume is valid if it is of type "int" and is in the range
   * 0-100, inclusive.
   *
   * @param mixed $initialVolume
   *   Initial volume.
   */
  public static function validateInitialVolume($initialVolume) : bool {
    return (is_int($initialVolume) && static::validateInitialVolumeInternal($initialVolume)) ? TRUE : FALSE;
  }

  /**
   * Tells whether the given "show title" flag is valid.
   *
   * The flag is valid if it is of type "bool."
   *
   * @param mixed $showTitle
   *   Show title flag.
   */
  public static function validateShowTitleFlag($showTitle) : bool {
    return is_bool($showTitle);
  }

  /**
   * Tells whether the given "use autoplay" flag is valid.
   *
   * The flag is valid if it is of type "bool."
   *
   * @param mixed $useAutoplay
   *   Use autoplay flag.
   */
  public static function validateUseAutoplayFlag($useAutoplay) : bool {
    return is_bool($useAutoplay);
  }

  /**
   * Tells whether the given "use HTML5 UI" flag is valid.
   *
   * The flag is valid if it is of type "bool."
   *
   * @param mixed $useHtml5Ui
   *   Use HTML5 UI flag.
   */
  public static function validateUseHtml5UiFlag($useHtml5Ui) : bool {
    return is_bool($useHtml5Ui);
  }

  /**
   * Tells whether the given WMode is valid.
   *
   * The WMode is valid if it is of type "int" and is one of the static::WMODE_*
   * values.
   *
   * @param mixed $wMode
   *   WMode.
   */
  public static function validateWMode($wMode) : bool {
    return (is_int($wMode) && static::validateWModeInternal($wMode)) ? TRUE : FALSE;
  }

  /**
   * Tells whether the given default quality is valid.
   *
   * The default quality is valid if it is one of the static::DEFAULT_QUALITY_*
   * values.
   *
   * @param int $defaultQuality
   *   Default quality.
   */
  private static function validateDefaultQualityInternal(int $defaultQuality) : bool {
    switch ($defaultQuality) {
      case static::DEFAULT_QUALITY_LOW:
      case static::DEFAULT_QUALITY_MEDIUM:
      case static::DEFAULT_QUALITY_HIGH:
        return TRUE;

      default:
        return FALSE;
    }
  }

  /**
   * Tells whether the given default quality is valid.
   *
   * The default quality is valid if it is one of the static::DEFAULT_QUALITY_*
   * values.
   *
   * @param mixed $defaultQuality
   *   Default quality.
   */
  private static function validateInitialVolumeInternal(int $initialVolume) : bool {
    return ($initialVolume >= 0 && $initialVolume <= 100) ? TRUE : FALSE;
  }

  /**
   * Tells whether the given WMode is valid.
   *
   * The WMode is valid if it is one of the static::WMODE_* values.
   *
   * @param int $wMode
   *   WMode.
   */
  public static function validateWModeInternal(int $wMode) : bool {
    switch ($wMode) {
      case static::WMODE_DIRECT:
      case static::WMODE_OPAQUE:
      case static::WMODE_TRANSPARENT:
      case static::WMODE_WINDOW:
        return TRUE;

      default:
        return FALSE;
    }
  }

}
