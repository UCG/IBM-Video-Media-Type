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
 *   type = "string"
 * )
 */
class IbmVideoDataConstraint extends Constraint {

  /**
   * Indicates the channel ID is invalid.
   */
  public string $invalidBaseEmbedUrlMessage = 'The base embed URL is invalid.';

  /**
   * Indicates the JSON provided has an incorrect set of root-level keys.
   */
  public string $invalidJsonKeySetMessage = 'The JSON provided has an incorrect set of root-level keys.';

  /**
   * Indicates the IBM video data string is not valid JSON.
   */
  public string $invalidJsonMessage = 'The string provided is not valid JSON.';

  /**
   * Indicates the video ID is invalid.
   */
  public string $invalidVideoIdMessage = 'The video ID is invalid.';

}
