<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper;

/**
 * Contains static validation methods.
 *
 * @static
 */
final class ValidationHelpers {

  /**
   * Empty private constructor to ensure no one instantiates this class.
   */
  private function __construct() {
  }

  /**
   * Tells whether the given channel ID is valid.
   *
   * @param string $channelId
   *   Channel ID.
   *
   * @return bool
   *   Returns TRUE if the ID is valid (a non-empty string made up of digits
   *   from 0-9, inclusive), else returns FALSE.
   */
  public static function isChannelIdValid(string $channelId) : bool {
    return $channelId === '' ? FALSE : ctype_digit($channelId);
  }

  /**
   * Tells whether the given channel video ID is valid.
   *
   * @param string $channelVideoId
   *   Channel ID.
   *
   * @return bool
   *   Returns TRUE if the ID is valid (a non-empty string made up of characters
   *   from a-z and A-Z, inclusive), else returns FALSE.
   */
  public static function isChannelVideoIdValid(string $channelVideoId) : bool {
    return $channelVideoId === '' ? FALSE : ctype_alpha($channelVideoId);
  }

}
