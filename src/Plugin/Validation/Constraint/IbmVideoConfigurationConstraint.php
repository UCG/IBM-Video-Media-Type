<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a value represents a valid oEmbed resource URL.
 *
 * @Constraint(
 *   id = "ibm_video_configuration",
 *   label = @Translation("IBM Video Configuration", context = "Validation"),
 *   type = "string"
 * )
 */
class IbmVideoConfigurationConstraint extends Constraint {

  // @todo: Add documentation.

  public string $defaultQualityInvalidMessage = 'The "defaultQuality" value is neither "low," "med," nor "high"';

  public string $displayControlsNotBoolMessage = 'The "display controls" value is not a boolean.';

  public string $initialVolumeNotIntegerInRightRangeMessage = 'The initial volume is not an integer between 0-100 (inclusive).';

  public string $invalidJsonMessage = 'The string provided is not valid JSON.';

  public string $jsonSchemaInvalidSchemaGeneralMessage = 'The JSON provided has an invalid schema.';

  public string $showTitleNotBoolMessage = 'The "show title" value is not a boolean.';

  public string $useAutoplayNotBoolMessage = 'The "use autoplay" value is not a boolean.';

  public string $useHtml5UiNotBoolMessage = 'The "use HTML5 API" value is not a boolean.';

  public string $videoIdNotNotEmptyStringMessage = 'The video ID is not a non-empty string.';

  public string $wModeInvalidMessage = 'The "wMode" value is neither "direct," "opaque," "transparent," nor "window."';

}
