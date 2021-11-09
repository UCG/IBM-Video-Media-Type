<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\FieldFormatter;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Drupal\media\MediaInterface;
use Ranine\Iteration\ExtendableIterable;

/**
 * Formats the IBM video media type source field.
 *
 * @FieldFormatter(
 *   id = "ibm_video",
 *   label = @Translation("IBM video"),
 *   field_types = {"string_long"}
 * )
 */
class IbmVideoFormatter extends FormatterBase {

  /**
   * Settings for the video player assigned to default values.
   *
   * @var array
   */
  private const PLAYER_SETTINGS_AND_DEFAULTS = [
    'useAutoplay' => FALSE,
    'useHtml5Ui' => TRUE,
    'displayControls' => TRUE,
    'initialVolume' => 50,
    'showTitle' => TRUE,
    'wMode' => NULL,
    'defaultQuality' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() : array {
    return static::PLAYER_SETTINGS_AND_DEFAULTS + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    // Get the media source from the entity associated with $items.
    // @todo Remove dependency on media source and getMetadata().
    $media = $items->getEntity();
    if (!($media instanceof MediaInterface)) {
      throw new \InvalidArgumentException('$items contains a field whose entity is not a media entity.');
    }
    /** @var \Drupal\media\MediaInterface $media */
    $source = $media->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('$items contains a field whose entity has a source of the wrong type.');
    }
    /** @var \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo $source */

    $videoEmbedUrl = $this->generateVideoUrl($source->getMetadata($media, 'baseUrl'));
    $maxWidthPx = $source->getMetadata($media, 'maxWidth');
    $aspectRatio = $source->getMetadata($media, 'aspectRatio');
    /** @var int|null $maxWidthPx */
    /** @var double $aspectRatio */
    // Convert the aspect ratio to a padding % (the height as a percentage of
    // the width):
    $bottomPaddingPercentage = 100 / $aspectRatio;
    /** @var float $bottomPaddingPercentage */

    $renderElement = [];
    foreach ($items as $delta => $item) {
      // Render each item as a nested group of three HTML elements:
      // 1) The outermost element is a <div> sets the width of the video. Thi
      // <div> has a width equal to the maximum width of the video (if it is
      // defined). If this maximum is not defined, the width is set to 100% to
      // fill the parent container. Otherwise, the maximum width of the <div> is
      // set to 100% to ensure the video does not overflow its parent container.
      // 2) The <div> above has a nested <div> with a bottom padding sufficient
      // to make its width/height (aspect) ratio correct for the contained
      // video. A nested <div> is used because a percentage "padding-bottom"
      // value is relative to the width of the *parent* element, not the element
      // itself. This nested <div> is positioned relatively so that the absolute
      // positioning of the <iframe> below is relative to this <div>.
      // 3) The innermost element: a nested <iframe> which points to the video
      // player itself. This is positioned absolutely, which removes it from the normal flow so that it
      // does not affect the height of the parent <div> described above. The
      // parent <div> defines the sizing of the <iframe>, so we just set its
      // width and height to 100%.
      // This should ensure the video is rendered at the maximum possible size,
      // filling the parent container if possible.

      // First, set the attributes for the outer <div> based on whether the max
      // width is defined.
      if ($maxWidthPx === NULL) {
        $outerDivAttributes = [
          'class' => 'ibm-video__outer_container--no-max-width'
        ];
      }
      else {
        $outerDivAttributes = [
          'class' => ['ibm-video__outer_container'],
          'style' => ('width: ' . $maxWidthPx),
        ];
      }

      $renderElement[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => $outerDivAttributes,
        '#attached' => [
          'library' => ['ibm_video_media_type/ibm_video_formatter'],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['ibm-video__inner_container'],
            'style' => ('padding-bottom: ' . number_format($bottomPaddingPercentage, 2, '.')),
          ],
          [
            '#type' => 'html_tag',
            '#tag' => 'iframe',
            '#attributes' => [
              'src' => $videoEmbedUrl,
              'frameborder' => 0,
              'scrolling' => FALSE,
              'allowtransparency' => TRUE,
              'allowfullscreen' => TRUE,
              'mozallowfullscreen' => TRUE,
              'width' => '100%',
              'height' => '100%',
              'class' => ['ibm-video__iframe'],
            ],
          ],
        ],
      ];
      // Add the cache metadata associated with the parent media entity.
      CacheableMetadata::createFromObject($media)->applyTo($renderElement[$delta]);
    }

    return $renderElement;
  }

  /**
   * Generates and returns an embed URL for the IBM video w/ the given video ID.
   *
   * Uses the current settings to set the video player properties.
   *
   * @param string $baseUrl
   *   Base URL (without query string) to use.
   */
  private function generateVideoUrl(string $baseUrl) : string {
    // @todo: Redo.
    $queryString = UrlHelper::buildQuery(ExtendableIterable::from(static::PLAYER_SETTINGS_AND_DEFAULTS)
      ->map(fn($setting) => $this->getSetting($setting))
      ->filter(fn($setting, $value) => $value !== NULL)
      ->map(function ($setting, $value) : string {
        // Cast most types directly to strings for the purposes of generating
        // the query string; however, convert boolean TRUE to 'true' and FALSE
        // to 'false', except for "useHtml5Ui", in which case we convert TRUE to
        // '1' and FALSE to '0'. See https://support.video.ibm.com/hc/en-us/articles/207851927-Using-URL-Parameters-and-Embed-API-for-Custom-Players.
        if ($setting === 'useHtml5Ui') {
          return $value ? '1': '0';
        }
        elseif (is_bool($value)) {
          return $value ? 'true' : 'false';
        }
        else {
          return (string) $value;
        }
      })->toArray());
    return $baseUrl . '?' . $queryString;
  }

}
