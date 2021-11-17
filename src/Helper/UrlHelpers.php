<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper;

use Ranine\Helper\ThrowHelpers;

/**
 * Contains static URL-related methods.
 *
 * @static
 */
final class UrlHelpers {

  /**
   * Empty private constructor to ensure no one instantiates this class.
   */
  private function __construct() {
  }

  /**
   * Assembles a base IBM video permalink embed URL.
   *
   * @param string $channelId
   *   Non-empty channel ID.
   * @param string $channelVideoId
   *   Non-empty channel video ID.
   * @param string $protocol
   *   (optional) Protocol part of URL (e.g., "https://" or "http://"). Can be
   *   empty.
   *
   * @return string
   *   Assembled base permalink URL:
   *   "{$protocol}video.ibm.com/channel/$channelId/video/$channelVideoId".
   *
   * @throws \InvalidArgumentException
   *   Thrown if $channelId or $channelVideoId is empty.
   */
  public static function assembleIbmVideoPermalinkUrl(string $channelId, string $channelVideoId, string $protocol = '') : string {
    ThrowHelpers::throwIfEmptyString($channelId, 'channelId');
    ThrowHelpers::throwIfEmptyString($channelVideoId, 'channelVideoId');

    return $protocol . 'video.ibm.com/embed/channel/' . $channelId . '/video/' . $channelVideoId;
  }

}
