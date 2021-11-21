<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper\IbmVideoUrl;

use Ranine\Helper\ThrowHelpers;

/**
 * Contains constants and static methods related to IBM video URLs.
 *
 * @static
 */
final class IbmVideoUrlHelpers {

  // @todo: Fix up regex stuff.

  /**
   * Regex for a base IBM video embed URL.
   *
   * This should be used to match an embed URL with neither query, fragment nor
   * protocol.
   */
  public const REGEX_BASE_EMBED_URL =
    '(?i)video.ibm.com/embed/(?:recorded/)?'
    . '(?:' . self::REGEX_URL_PATH_SEGMENT_CHARACTER_LOWERCASE . ')+';

  /**
   * Regex for an IBM video embed URL.
   *
   * The protocol must be "https://", "http://", "//", or "". Note that some
   * invalid embed URLs (with invalid video IDs, etc.) may still match this
   * regex.
   *
   * @var string
   */
  public const REGEX_EMBED_URL =
    '(?i)(?:https://|http://|//)?video.ibm.com/embed/(?:recorded/)?'
    . '(?:' . self::REGEX_URL_PATH_SEGMENT_CHARACTER_LOWERCASE
    . ')+(?:\\?' . self::REGEX_URL_QUERY_LOWERCASE
    . ')?(?:#' . self::REGEX_URL_FRAGMENT_LOWERCASE . ')?';

  /**
   * The regex for a valid lowercase URL path segment character
   *
   * The path segment does not include any forward slashes. Each path segment is
   * allowed to contain certain characters, as well as "%" followed by two hex
   * digits. See
   * https://stackoverflow.com/questions/4669692/valid-characters-for-directory-part-of-a-url-for-short-links.
   *
   * @var string
   */
  private const REGEX_URL_PATH_SEGMENT_CHARACTER_LOWERCASE =
    '[a-z]|[0-9]|-|\\.|_|~|!|\\$|&|\'|\\(|\\)|\\*|\\+|,|;|=|:|@|(?:%(?:[a-f]|[0-9]){2})';
  
  /**
   * The regex for a valid lowercase URL query string segment.
   *
   * See RFC 3986, 3.4: https://www.rfc-editor.org/rfc/rfc3986#section-3.4.
   *
   * @var string
   */
  private const REGEX_URL_QUERY_LOWERCASE =
    '(?:[a-z]|[0-9]|-|\\.|_|~|!|\\$|&|\'|\\(|\\)|\\*|\\+|,|;|=|:|@|/|\\?|(?:%(?:[a-f]|[0-9]){2}))*';
  
  /**
   * The regex for a valid lowercase URL query string fragment.
   *
   * See RFC 3986, 3.5: https://www.rfc-editor.org/rfc/rfc3986#section-3.5.
   *
   * @var string
   */
  private const REGEX_URL_FRAGMENT_LOWERCASE =
    '(?:[a-z]|[0-9]|-|\\.|_|~|!|\\$|&|\'|\\(|\\)|\\*|\\+|,|;|=|:|@|/|\\?|(?:%(?:[a-f]|[0-9]){2}))*';

  /**
   * Empty private constructor to ensure no one instantiates this class.
   */
  private function __construct() {
  }

  /**
   * Tells whether the given base embed URL is valid.
   *
   * @param string $baseEmbedUrl
   *   Base embed URL.
   */
  public static function isBaseEmbedUrlValid(string $baseEmbedUrl) : bool {
    // @todo: Finish
  }

  /**
   * Parses and cleans the given video embed URL.
   *
   * @param string $embedUrl
   *   "Dirty" embed URL. Assumed to be valid (i.e., to match
   *   static::REGEX_EMBED_URL).
   * @param string $protocol
   *   Protocol to use in cleaned URL (everything before the start of the
   *   domain).
   * @param string|null $videoId
   *   (output parameter) The video ID, if it exists; otherwise NULL. Undefined
   *   if $embedUrl is invalid.
   *
   * @return string
   *   Cleaned (base) embed URL, with the protocol, query, and fragment
   *   stripped. Undefined if $embedUrl is not valid.
   *
   * @throws \InvalidArgumentException
   *   Thrown in some cases in $embedUrl is invalid.
   */
  public static function parseAndCleanEmbedUrl(string $embedUrl, ?string &$videoId) : string {
    ThrowHelpers::throwIfEmptyString($embedUrl, 'embedUrl');
    $invalidUrlConditionalThrow = function(bool $shouldThrow) : void {
      if ($shouldThrow) { throw new \InvalidArgumentException('$url is invalid.'); } };
    // Grab everything after the initial "video.ibm.com/embed/".
    $parts = explode('video.ibm.com/embed/', $embedUrl, 2);
    $invalidUrlConditionalThrow(count($parts) !== 2);
    $remainder = $parts[1];
    $recordedPathPart = 'recorded/';
    $recordedPathPartLength = 9;
    if (strlen($remainder) > $recordedPathPartLength && substr_compare($remainder, $recordedPathPart, 0, $recordedPathPartLength) === 0) {
      // If the first part of $remainder is "recorded/", we should be able to
      // extract a video ID (everything since "recorded/" up to "?").
      $encodedVideoId = static::getSubstringUpTo($recordedPathPartLength, $remainder, '?');
      $videoId = urldecode($encodedVideoId);
      return 'video.ibm.com/embed/recorded/' . $encodedVideoId;
    }
    else {
      $videoId = NULL;
      return 'video.ibm.com/embed/' . static::getSubstringUpTo(0, $remainder, '?');
    }
  }

  /**
   * Returns a substring of $haystack up to the first character in $needle.
   *
   * @param int $startPosition
   *   The position in $haystack to start the substring at.
   * @param string $haystack
   *   Haystack.
   * @param string $needle
   *   Needle.
   *
   * @return string
   *   A substring of $haystack from $startPosition to (but not including) the
   *   first character of the first instance since (or at) $startPosition of
   *   $needle in $haystack. If $needle does not exist in $haystack, returns the
   *   string from $startPosition to the end of $haystack.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $startPosition is less than zero or greater than or equal to
   *   the length of $haystack.
   * @throws \InvalidArgumentException
   *   Thrown if $haystack or $needle is an empty string.
   */
  private static function getSubstringUpTo(int $startPosition, string $haystack, string $needle) : string {
    ThrowHelpers::throwIfEmptyString($haystack, 'haystack');
    ThrowHelpers::throwIfEmptyString($needle, 'needle');
    ThrowHelpers::throwIfLessThanZero($startPosition, 'startPosition');
    if ($startPosition >= strlen($haystack)) {
      throw new \InvalidArgumentException('$startPosition is greater than or equal to the length of $haystack.');
    }

    $needlePosition = strpos($haystack, $needle, $startPosition);
    if ($needlePosition === FALSE) {
      // The needle was not found.
      return substr($haystack, $startPosition);
    }
    else {
      return substr($haystack, $startPosition, $needlePosition - $startPosition);
    }
  }

}
