<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Helper;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Drupal\media\Entity\MediaType;

/**
 * Contains helper methods related to this module's media source field stuff.
 *
 * @static
 */
final class MediaSourceFieldHelpers {

  /**
   * Empty private constructor to ensure no one instantiates this class.
   */
  private function __construct() {
  }

  /**
   * Tells whether the given field definition meets certain criteria.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   Field definition
   *
   * @return bool
   *   Returns TRUE if $fieldDefinition is for a "media" field bundle, and has
   *   a media source of type
   *   @see \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo. Else,
   *   returns FALSE.
   */
  public static function doesFieldDefinitionHaveIbmVideoMediaSource(FieldDefinitionInterface $fieldDefinition) : bool {
    $bundle = $fieldDefinition->getTargetBundle();
    if (!is_string($bundle) || $fieldDefinition->getTargetEntityTypeId() !== 'media') {
      return FALSE;
    }
    /** @var \Drupal\media\MediaTypeInterface */
    $mediaType = MediaType::load($bundle);
    return ($mediaType->getSource() instanceof IbmVideo) ? TRUE : FALSE;
  }

}
