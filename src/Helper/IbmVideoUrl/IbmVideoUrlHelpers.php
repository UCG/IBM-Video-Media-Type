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

  /**
   * Regex for an IBM video embed URL.
   *
   * The scheme must be "https://", "http://", "//", or "". Note that some
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
   * Base embed URL (no scheme, query, or ID) for recorded video.
   *
   * @var string
   */
  private const EMBED_URL_BASE_RECORDED = 'video.ibm.com/embed/recorded/';

  /**
   * Base embed URL (no scheme, query, or ID) for streaming video.
   *
   * @var string
   */
  private const EMBED_URL_BASE_STREAM = 'video.ibm.com/embed/';

  /**
   * The regex for a valid lowercase URL path segment character.
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
   * The regex for a valid lowercase URL query string.
   *
   * See RFC 3986, 3.4: https://www.rfc-editor.org/rfc/rfc3986#section-3.4.
   *
   * @var string
   */
  private const REGEX_URL_QUERY_LOWERCASE =
    '(?:[a-z]|[0-9]|-|\\.|_|~|!|\\$|&|\'|\\(|\\)|\\*|\\+|,|;|=|:|@|/|\\?|(?:%(?:[a-f]|[0-9]){2}))*';
  
  /**
   * The regex for a valid lowercase URL fragment.
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
   * Returns the embed URL associated with the arguments provided.
   *
   * @param string $id
   *   Video ID (for recorded videos) or channel ID (for streams).
   * @param bool $isRecorded
   *   TRUE if the video is recorded; FALSE if it is a stream.
   * @param string $scheme
   *   Scheme. Can be empty.
   * @param IbmVideoUrlParameters|null $parameters
   *   (optional) Video player parameters to include in query string of returned
   *   URL, or NULL to assemble a URL without parameters.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $id is empty.
   */
  public static function assembleEmbedUrl(string $id, bool $isRecorded, string $scheme, ?IbmVideoUrlParameters $parameters = NULL) : string {
    ThrowHelpers::throwIfEmptyString($id, 'id');
    $baseUrl = $isRecorded ? static::EMBED_URL_BASE_RECORDED : static::EMBED_URL_BASE_STREAM;
    if ($parameters === NULL) {
      return ($scheme . $baseUrl . rawurlencode($id));
    }
    else {
      $query = $parameters->toEmbedUrlQueryString();
      return ($scheme . $baseUrl . rawurlencode($id) . '?' . $query);
    }
  }

  /**
   * Tells whether the given embed URL is valid.
   *
   * The embed URL is valid if it:
   * 1) starts with "http://", "https://", "//", or an empty string (""), and
   * 2) then continues with "video.ibm.com/embed/", and
   * 3) optionally then continues with "recorded/", and
   * 4) then continues with at least one URL-path-segment-valid characters, and
   * 5) optionally then continues with "?" followed by a valid query string, and
   * 6) optionally finishes with "#" followed by a valid fragment.
   * Another way to see if the URL is valid is by comparing it with the
   * static::REGEX_EMBED_URL regular expression -- the URL is valid if the
   * entire URL matches the expression.
   *
   * @param string $embedUrl
   *   Embed URL.
   */
  public static function isEmbedUrlValid(string $embedUrl) : bool {
    // We use parentheses for delimiters to avoid having to escape stuff in
    // REGEX_EMBED_URL.
    return (preg_match('(^' . static::REGEX_EMBED_URL . '$)', $embedUrl) === 1) ? TRUE : FALSE;
  }

  /**
   * Tells whether the given video or channel ID is valid (non-empty).
   *
   * @param string $id
   *   Video or channel ID.
   */
  public static function isVideoOrChannelIdValid(string $id) : bool {
    return $id === '' ? FALSE : TRUE;
  }

  /**
   * Extracts the video/channel ID and recorded/streamed info from an embed URL.
   *
   * @param string $embedUrl
   *   Embed URL. Assumed to be valid.
   * @param string $id
   *   (output parameter) The video ID (if the embed URL corresponds to a
   *   recorded video) or channel ID (if the URL corresponds to a stream).
   *   Undefined if $embedUrl is invalid.
   * @param bool $isRecorded
   *   (output parameter) TRUE if the embed URL corresponds to a recorded video;
   *   FALSE if the URL corresponds to a stream.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $embedUrl is empty, and in some other cases if $embedUrl is
   *   invalid.
   */
  public static function parseEmbedUrl(string $embedUrl, string &$id, bool &$isRecorded) : void {
    ThrowHelpers::throwIfEmptyString($embedUrl, 'embedUrl');

    // Grab everything after the initial "video.ibm.com/embed/".
    try {
      $remainder = static::getSubstringAfter($embedUrl, 'video.ibm.com/embed/');
    }
    catch (\InvalidArgumentException $e) {
      throw new \InvalidArgumentException('$embedUrl is invalid.');
    }
    $recordedPathPart = 'recorded/';
    $recordedPathPartLength = 9;
    if (strlen($remainder) > $recordedPathPartLength && substr_compare($remainder, $recordedPathPart, 0, $recordedPathPartLength) === 0) {
      $isRecorded = TRUE;
      // Since the first part of $remainder is "recorded/", the video ID should
      // consist of everything since "recorded/" and up to "?".
      $encodedId = static::getSubstringUpTo($recordedPathPartLength, $remainder, '?');
    }
    else {
      $isRecorded = FALSE;
      // Otherwise, the *channel* ID should consist of everything up to "?".
      $encodedId = static::getSubstringUpTo(0, $remainder, '?');
    }
    $id = urldecode($encodedId);
  }

  /**
   * Returns the part of $haystack that occurs after $needle.
   *
   * @param string $haystack
   *   String from which the substring is taken.
   * @param string $needle
   *   String after which to take substring.
   *
   * @return string
   *   The substring of $haystack starting immediately after the first
   *   occurrence of $needle, and continuing to the end of $haystack. If $needle
   *   is an empty string, returns $haystack.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $needle does not exist in $haystack.
   */
  private static function getSubstringAfter(string $haystack, string $needle) : string {
    if ($needle === '') {
      return $haystack;
    }
    $needlePosition = strpos($haystack, $needle);
    if ($needlePosition === FALSE) {
      throw new \InvalidArgumentException('$needle does not exist in $haystack.');
    }
    $positionAfterNeedle = $needlePosition + strlen($needle);
    if ($positionAfterNeedle === strlen($haystack)) {
      return '';
    }
    else {
      return substr($haystack, $positionAfterNeedle);
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
      assert($needlePosition >= $startPosition);
      return substr($haystack, $startPosition, $needlePosition - $startPosition);
    }
  }

}
