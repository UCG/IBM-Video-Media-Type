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
   * Indicates the JSON provided has an incorrect set of root-level keys.
   */
  public string $invalidJsonKeySetMessage = 'The JSON provided has an incorrect set of root-level keys.';

  /**
   * Indicates the video/channel ID is invalid.
   */
  public string $invalidIdMessage = 'The video/channel ID is invalid.';

  /**
   * Indicates the "is recorded" flag is invalid.
   */
  public string $invalidIsRecordedFlagMessage = 'The "is recorded" flag is invalid.';

  /**
   * Indicates the IBM video data string is not valid JSON.
   */
  public string $invalidJsonMessage = 'The string provided is not valid JSON.';

  /**
   * Indicates the thumbnail reference ID is invalid.
   */
  public string $invalidThumbnailReferenceIdMessage = 'The thumbnail reference ID is invalid.';

}
