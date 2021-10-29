<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for source field for IBM video media type.
 *
 * @Constraint(
 *   id = "ibm_video_configuration",
 *   label = @Translation("IBM Video Configuration", context = "Validation"),
 *   type = "string_long"
 * )
 */
class IbmVideoConfigurationConstraint extends Constraint {

  /**
   * Indicates the default stream playback quality is invalid.
   *
   * Should be used if the default stream playback quality is is neither "low",
   * "med", nor "high".
   */
  public string $defaultQualityInvalidMessage = 'The "defaultQuality" value is neither "low", "med", nor "high"';

  /**
   * Indicates the "display controls" value is not a boolean.
   */
  public string $displayControlsNotBoolMessage = 'The "display controls" value is not a boolean.';

  /**
   * Indicates the initial volume is not an integer from 0-100 (inclusive).
   */
  public string $initialVolumeNotIntegerInRightRangeMessage = 'The initial volume is not an integer between 0-100 (inclusive).';

  /**
   * Indicates the IBM video configuration string is not valid JSON.
   */
  public string $invalidJsonMessage = 'The string provided is not valid JSON.';

  /**
   * Indicates the JSON provided has an invalid schema (incorrect element set).
   */
  public string $jsonSchemaInvalidGeneralMessage = 'The JSON provided has an invalid schema.';

  /**
   * Indicates the "show title" value is not a boolean.
   */
  public string $showTitleNotBoolMessage = 'The "show title" value is not a boolean.';

  /**
   * Indicates the "use autoplay" value is not a boolean.
   */
  public string $useAutoplayNotBoolMessage = 'The "use autoplay" value is not a boolean.';

  /**
   * Indicates the "use HTML5 UI" value is not a boolean.
   */
  public string $useHtml5UiNotBoolMessage = 'The "use HTML5 UI" value is not a boolean.';

  /**
   * Indicates the video ID is not a non-empty string.
   */
  public string $videoIdNotNotEmptyStringMessage = 'The video ID is not a non-empty string.';

  /**
   * Indicates the "wMode" value is invalid.
   *
   * Should be used if "wMode" is neither "direct", "opaque", "transparent", nor
   * "window".
   */
  public string $wModeInvalidMessage = 'The "wMode" value is neither "direct", "opaque", "transparent", nor "window".';

}
