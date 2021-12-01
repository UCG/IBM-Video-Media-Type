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
   * Gets the default channel thumbnail URI, if possible.
   *
   * @param string $channelId
   *   Channel ID for which to retrieve the thumbnail URI.
   *
   * @return string|null
   *   Remote URI for the default channel thumbnail, or NULL if no URI exists.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $channelId is empty.
   * @throws \Drupal\ibm_video_media_type\Exception\HttpTransferException
   *   Thrown if an error occurs in transit when making the associated API HTTP
   *   request / receiving the response.
   * @throws \Drupal\ibm_video_media_type\Exception\IbmVideoApiBadResponseException
   *   Thrown if the IBM Video REST API returns an invalid response.
   */
  public function getDefaultChannelThumbnailUri(string $channelId) : ?string {
    ThrowHelpers::throwIfEmptyString($channelId, 'channelId');

    try {
      $response = $this->httpClient->request('GET', 'https://api.video.ibm.com/channels/'. rawurlencode($channelId) . '.json', [RequestOptions::HTTP_ERRORS => FALSE]);
    }
    catch (TransferException $e) {
      throw new HttpTransferException('An internal HTTP error occurred when attempting to get thumbnail URI.', 0, $e);
    }
    $responseCode = $response->getStatusCode();
    if ($responseCode !== 200) {
      throw new IbmVideoApiBadResponseException('The response code returned was ' . $responseCode . ', but response code 200 was expected.');
    }
    $responseData = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($responseData)) {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid body.');
    }

    // If there is no "thumbnail" key, just return NULL, as one can envision a
    // situation where no such key exists if there is no thumbnail defined
    // (although the documentation, https://developers.video.ibm.com/channel-api-video-management/basic-video-management#success-response),
    // does not mention such a possibility.
    if (!array_key_exists('thumbnail', $responseData)) {
      return NULL;
    }
    $thumbnailSizes = $responseData['thumbnail'];
    // Also return NULL if the thumbnail key is NULL or an empty array.
    if ($thumbnailSizes === NULL || $thumbnailSizes === []) {
      return NULL;
    }
    if (!is_array($thumbnailSizes)) {
      throw new IbmVideoApiBadResponseException('The "thumbnails" key of the IBM Video response is invalid.');
    }

    // Return the "default" thumbnail URI, if it exists. Note that the
    // documentation does not explicitly say to expect the "default" key to
    // exist (though it does say so for the corresponding case of a video
    // thumbnail), but experimentation seems to suggest it does...
    if (!array_key_exists('default', $thumbnailSizes)) {
      return NULL;
    }
    $defaultThumbnailUri = (string) $thumbnailSizes['defualt'];
    return $defaultThumbnailUri === '' ? NULL : $defaultThumbnailUri;
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
      throw new HttpTransferException('An internal HTTP error occurred when attempting to get thumbnail URI.', 0, $e);
    }
    $responseCode = $response->getStatusCode();
    if ($responseCode !== 200) {
      throw new IbmVideoApiBadResponseException('The response code returned was ' . $responseCode . ', but response code 200 was expected.');
    }
    $responseData = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($responseData)) {
      throw new IbmVideoApiBadResponseException('The IBM Video API returned a response with an invalid body.');
    }

    // If there is no "thumbnail" key, just return NULL, as one can envision a
    // situation where no such key exists if there is no thumbnail defined
    // (although the documentation, https://developers.video.ibm.com/channel-api-video-management/basic-video-management#success-response),
    // does not mention such a possibility.
    if (!array_key_exists('thumbnail', $responseData)) {
      return NULL;
    }
    $thumbnailSizes = $responseData['thumbnail'];
    // Also return NULL if the thumbnail key is NULL or an empty array.
    if ($thumbnailSizes === NULL || $thumbnailSizes === []) {
      return NULL;
    }
    if (!is_array($thumbnailSizes)) {
      throw new IbmVideoApiBadResponseException('The "thumbnails" key of the IBM Video response is invalid.');
    }

    // Return the "default" thumbnail URI, if it exists.
    if (!array_key_exists('default', $thumbnailSizes)) {
      return NULL;
    }
    $defaultThumbnailUri = (string) $thumbnailSizes['defualt'];
    return $defaultThumbnailUri === '' ? NULL : $defaultThumbnailUri;
  }

}
