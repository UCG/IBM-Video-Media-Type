<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for source field for IBM video media type.
 *
 * @Constraint(
 *   id = "ibm_video_data",
 *   label = @Translation("IBM Video Configuration", context = "Validation"),
 *   type = "string_long"
 * )
 */
class IbmVideoDataConstraint extends Constraint {

  /**
   * Indicates the IBM video data string is not valid JSON.
   */
  public string $invalidJsonMessage = 'The string provided is not valid JSON.';

  /**
   * Indicates the JSON provided has an incorrect set of root-level elements.
   */
  public string $invalidJsonKeySetMessage = 'The JSON provided has an incorrect set of root-level keys.';

  /**
   * Indicates the channel ID is invalid.
   */
  public string $invalidChannelIdMessage = 'The channel ID is invalid.';

  /**
   * Indicates the channel video ID is invalid.
   */
  public string $invalidChannelVideoIdMessage = 'The channel video ID is invalid.';

}
