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
   * Regex for a base IBM video embed URL.
   *
   * This should be used to match an embed URL with neither query, fragment nor
   * scheme.
   */
  public const REGEX_BASE_EMBED_URL =
    '(?i)video.ibm.com/embed/(?:recorded/)?'
    . '(?:' . self::REGEX_URL_PATH_SEGMENT_CHARACTER_LOWERCASE . ')+';

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
   * Returns the embed URL associated with the arguments provided.
   *
   * @param string $baseEmbedUrl
   *   Base embed URL (no scheme, query, or fragment).
   * @param string $scheme
   *   Scheme. Can be empty.
   * @param IbmVideoUrlParameters $parameters
   *   Video player parameters to include in query string of returned URL.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $baseEmbedUrl is empty.
   */
  public static function assembleEmbedUrl(string $baseEmbedUrl, string $scheme, IbmVideoUrlParameters $parameters) : string {
    ThrowHelpers::throwIfEmptyString($baseEmbedUrl, 'baseEmbedUrl');
    return ($scheme . $baseEmbedUrl . $parameters->toEmbedUrlQueryString());
  }

  /**
   * Extracts the base embed URL (and video/chan. ID) from the given embed URL.
   *
   * @param string $embedUrl
   *   Embed URL. Assumed to be valid (i.e., to match static::REGEX_EMBED_URL).
   * @param string $id
   *   (output parameter) The video ID, if the embed URL corresponds to a
   *   recorded video. Alternatively, if the URL corresponds to a stream, this
   *   should be the channel ID.
   *
   * @return string
   *   Base embed URL: the scheme, query, and fragment is stripped from the
   *   input embed URL. Undefined if $embedUrl is not valid.
   *
   * @throws \InvalidArgumentException
   *   Thrown in some cases in $embedUrl is invalid.
   */
  public static function extractBaseEmbedUrlAndId(string $embedUrl, string &$id) : string {
    ThrowHelpers::throwIfEmptyString($embedUrl, 'embedUrl');
    $invalidUrlConditionalThrow = function(bool $shouldThrow) : void {
      if ($shouldThrow) { throw new \InvalidArgumentException('$url is invalid.'); } };
    // Grab everything after the initial "video.ibm.com/embed/".
    // @todo Make this more efficient (no need to store first part here).
    $parts = explode('video.ibm.com/embed/', $embedUrl, 2);
    $invalidUrlConditionalThrow(count($parts) !== 2);
    $remainder = $parts[1];
    $recordedPathPart = 'recorded/';
    $recordedPathPartLength = 9;
    if (strlen($remainder) > $recordedPathPartLength && substr_compare($remainder, $recordedPathPart, 0, $recordedPathPartLength) === 0) {
      // If the first part of $remainder is "recorded/", the video ID should
      // consist of everything since "recorded/" and up to "?".
      $encodedId = static::getSubstringUpTo($recordedPathPartLength, $remainder, '?');
      $id = urldecode($encodedId);
      return 'video.ibm.com/embed/recorded/' . $encodedId;
    }
    else {
      // Otherwise, the *channel* ID should consist of everything after "embed/"
      // and up to "?".
      $encodedId = static::getSubstringUpTo($recordedPathPartLength, $remainder, '?');
      $id = urldecode($encodedId);
      return 'video.ibm.com/embed/' . $encodedId;
    }
  }

  /**
   * Tells whether the given base embed URL is valid.
   *
   * @param string $baseEmbedUrl
   *   Base embed URL.
   */
  public static function isBaseEmbedUrlValid(string $baseEmbedUrl) : bool {
    // Use our regex to validate the URL. Use bracket delimiters to avoid having
    // to escape extra characters in the regex. Also, the regex stored in the
    // constant is unanchored, so we anchor it here. See also the source for
    // \Drupal\Core\Render\Element\FormElement::validatePattern().
    return preg_match('{^(?:' . static::REGEX_BASE_EMBED_URL . ')$}', $baseEmbedUrl);
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
