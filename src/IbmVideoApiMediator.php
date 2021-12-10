<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type;

use Drupal\ibm_video_media_type\Exception\HttpTransferException;
use Drupal\ibm_video_media_type\Exception\IbmVideoApiBadResponseException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use Ranine\Helper\ThrowHelpers;

/**
 * Handles interactions with the IBM Video REST API.
 */
class IbmVideoApiMediator {

  /**
   * HTTP client.
   */
  private ClientInterface $httpClient;

  /**
   * Creates a new IBM Video API mediator object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   HTTP client.
   */
  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   * Gets a channel thumbnail URI, if possible.
   *
   * If multiple channel thumbnails are available, the URI corresponding to the
   * one with the largest number of pixels is returned.
   *
   * @param string $channelId
   *   Channel ID for which to retrieve the thumbnail URI.
   *
   * @return string|null
   *   Remote URI for a channel thumbnail, or NULL if no URI exists.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $channelId is empty.
   * @throws \Drupal\ibm_video_media_type\Exception\HttpTransferException
   *   Thrown if an error occurs in transit when making the associated API HTTP
   *   request / receiving the response.
   * @throws \Drupal\ibm_video_media_type\Exception\IbmVideoApiBadResponseException
   *   Thrown if the IBM Video REST API returns an invalid response.
   */
  public function getChannelThumbnailUri(string $channelId) : ?string {
    ThrowHelpers::throwIfEmptyString($channelId, 'channelId');

    try {
      $response = $this->httpClient->request('GET', 'https://api.video.ibm.com/channels/'. rawurlencode($channelId) . '.json', [RequestOptions::HTTP_ERRORS => FALSE]);
    }
    catch (TransferException $e) {
      throw new HttpTransferException('An internal HTTP error occurred when attempting to get channel thumbnail URI.', 0, $e);
    }
    $responseCode = $response->getStatusCode();
    if ($responseCode !== 200) {
      throw new IbmVideoApiBadResponseException('The response code returned was ' . $responseCode . ', but response code 200 was expected.');
    }
    $responseData = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($responseData)) {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid body.');
    }

    if (array_key_exists('channel', $responseData)) {
      $videoData = $responseData['channel'];
      if (!is_array($videoData)) {
        throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid "channel" element type.');
      }

      // If there is no "picture" key, just return NULL, as one can envision a
      // situation where no such key exists if there is no thumbnail defined
      // (although the documentation, https://developers.video.ibm.com/channel-api-basic-channel-management),
      // does not mention such a possibility).
      if (!array_key_exists('picture', $responseData)) {
        return NULL;
      }
      $thumbnailUris = $responseData['picture'];
      // Also return NULL if the picture key is NULL or an empty array.
      if ($thumbnailUris === NULL || $thumbnailUris === []) {
        return NULL;
      }
      if (!is_array($thumbnailUris)) {
        throw new IbmVideoApiBadResponseException('The "picture" key of the IBM Video response is invalid.');
      }

      // The URIs should be keyed by size (presumably "[width]x[height]"). Pick
      // the one with the largest size.
      $currentMaxPixels = 0;
      foreach ($thumbnailUris as $size => $uri) {
        if (!isset($currentUri)) {
          $currentUri = (string) $uri;
        }
        if (!is_string($size)) {
          continue;
        }
        $widthAndHeight = explode('x', $size);
        if (!is_array($widthAndHeight) || count($widthAndHeight) !== 2) {
          continue;
        }
        if (!is_numeric($widthAndHeight[0]) || !is_numeric($widthAndHeight[1])) {
          continue;
        }
        $pixels = ((int) $widthAndHeight[0]) * ((int) $widthAndHeight[1]);
        if ($pixels > $currentMaxPixels) {
          $currentMaxPixels = $pixels;
          $currentUri = (string) $uri;
        }
      }

      return (isset($currentUri) && $currentUri !== '') ? $currentUri : NULL;
    }
    else {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response without a root "channel" key.');
    }
  }

  /**
   * Gets the default video thumbnail URI, if possible.
   *
   * @param string $videoId
   *   Video ID for which to retrieve the thumbnail URI.
   *
   * @return string|null
   *   Remote URI for the default video thumbnail, or NULL if no URI exists.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $videoId is empty.
   * @throws \Drupal\ibm_video_media_type\Exception\HttpTransferException
   *   Thrown if an error occurs in transit when making the associated API HTTP
   *   request / receiving the response.
   * @throws \Drupal\ibm_video_media_type\Exception\IbmVideoApiBadResponseException
   *   Thrown if the IBM Video REST API returns an invalid response.
   */
  public function getDefaultVideoThumbnailUri(string $videoId) : ?string {
    ThrowHelpers::throwIfEmptyString($videoId, 'videoId');

    try {
      $response = $this->httpClient->request('GET', 'https://api.video.ibm.com/videos/'. rawurlencode($videoId) . '.json', [RequestOptions::HTTP_ERRORS => FALSE]);
    }
    catch (TransferException $e) {
      throw new HttpTransferException('An internal HTTP error occurred when attempting to get video thumbnail URI.', 0, $e);
    }
    $responseCode = $response->getStatusCode();
    if ($responseCode !== 200) {
      throw new IbmVideoApiBadResponseException('The response code returned was ' . $responseCode . ', but response code 200 was expected.');
    }
    $responseData = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($responseData)) {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid body.');
    }

    if (array_key_exists('video', $responseData)) {
      $videoData = $responseData['video'];
      if (!is_array($videoData)) {
        throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid "channel" element type.');
      }

      // If there is no "thumbnail" key, just return NULL, as one can envision a
      // situation where no such key exists if there is no thumbnail defined
      // (although the documentation, https://developers.video.ibm.com/channel-api-video-management/basic-video-management),
      // does not mention such a possibility).
      if (!array_key_exists('thumbnail', $responseData)) {
        return NULL;
      }
      $thumbnailUris = $responseData['thumbnail'];
      // Also return NULL if the thumbnail key is NULL or an empty array.
      if ($thumbnailUris === NULL || $thumbnailUris === []) {
        return NULL;
      }
      if (!is_array($thumbnailUris)) {
        throw new IbmVideoApiBadResponseException('The "thumbnails" key of the IBM Video response is invalid.');
      }

      // Return the "default" thumbnail URI, if it exists.
      if (!array_key_exists('default', $thumbnailUris)) {
        return NULL;
      }
      $defaultThumbnailUri = (string) $thumbnailUris['defualt'];
      return $defaultThumbnailUri === '' ? NULL : $defaultThumbnailUri;
    }
    else {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response without a root "video" key.');
    }
  }

}
