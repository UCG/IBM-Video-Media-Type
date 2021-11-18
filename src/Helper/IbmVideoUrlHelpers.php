<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper;

use Ranine\Helper\ThrowHelpers;

/**
 * Contains constants and static methods related to IBM video embed URLs.
 *
 * @static
 */
final class IbmVideoUrlHelpers {

  /**
   * Regular expression (without anchors) for a permalink IBM video embed URL.
   *
   * Assumes the query string can have any characters, and that the protocol
   * must either be "http://", "https://", "//", or "".
   */
  public const PERMALINK_REGEX = '(?i)(http://|https://|//)?video.ibm.com/embed/channel/[0-9]+/video/([a-z]|[0-9])+(\?.*)?';

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
   *   "{$protocol}video.ibm.com/embed/channel/$channelId/video/
   *   $channelVideoId".
   *
   * @throws \InvalidArgumentException
   *   Thrown if $channelId or $channelVideoId is empty.
   */
  public static function assemblePermalinkUrl(string $channelId, string $channelVideoId, string $protocol = '') : string {
    ThrowHelpers::throwIfEmptyString($channelId, 'channelId');
    ThrowHelpers::throwIfEmptyString($channelVideoId, 'channelVideoId');

    return $protocol . 'video.ibm.com/embed/channel/' . $channelId . '/video/' . $channelVideoId;
  }

  /**
   * Parses a permalink video embed URL into channel and channel video IDs.
   *
   * No validation of $url is performed. The parser may (but is not required to)
   * through an exception if $url is invalid.
   *
   * @param string $url
   *   Permalink URL.
   * @param string $channelId
   *   (output parameter) Channel ID, if $url is valid.
   * @param string $channelVideoId
   *   (output parameter) Channel video ID, if $url is valid.
   *
   * @throws \InvalidArgumentException
   *   Sometimes thrown if $url is invalid.
   */
  public static function parsePermalinkUrl(string $url, string &$channelId, string &$channelVideoId) : void {
    $invalidUrlConditionalThrow = function(bool $shouldThrow) : void {
      if ($shouldThrow) { throw new \InvalidArgumentException('$url is invalid.'); } };

    // Break the URL into smaller and smaller pieces until we have extracted
    // what we need.
    $parts = explode('video.ibm.com/embed/channel/', $url, 2);
    $invalidUrlConditionalThrow(count($parts) !== 2);
    $channelIdAndRemainder = $parts[1];
    $parts = explode('/video/', $channelIdAndRemainder);
    $invalidUrlConditionalThrow(count($parts) !== 2);
    $channelId = $parts[0];
    $videoIdAndRemainder = $parts[1];
    $parts = explode('?', $videoIdAndRemainder, 2);
    $invalidUrlConditionalThrow(count($parts) !== 2);
    $channelVideoId = $parts[0];
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
   *   Returns TRUE if the ID is valid (a non-empty string made up of
   *   alphanumeric characters), else returns FALSE.
   */
  public static function isChannelVideoIdValid(string $channelVideoId) : bool {
    return $channelVideoId === '' ? FALSE : ctype_alnum($channelVideoId);
  }

}
