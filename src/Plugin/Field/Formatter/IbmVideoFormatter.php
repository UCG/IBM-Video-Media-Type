<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\FieldFormatter;

use Drupal\Core\Field\FormatterBase;

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
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'useAutoplay' => FALSE,
      'useHtml5Ui' => TRUE,
      'displayControls' => TRUE,
      'initialVolume' => 50,
      'showTitle' => TRUE,
      'wMode' => NULL,
      'defaultQuality' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    throw new \RuntimeException('Method not implemented.');
  }

}
