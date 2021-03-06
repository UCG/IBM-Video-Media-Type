<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Drupal\media\MediaInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Handles validation for IBM video source field data.
 *
 * The associated constraint is
 * @see \Drupal\ibm_video_media_type\Plugin\Validation\Constraint\IbmVideoDataConstraint.
 */
class IbmVideoDataConstraintValidator extends ConstraintValidator {

  /**
   * Validates the given IBM video source field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface|null $items
   *   The field list corresponding to the "source" field for the media entity,
   *   or NULL if the field list is not available.
   * @param \Drupal\ibm_video_media_type\Plugin\Validation\Constraint\IbmVideoDataConstraint $constraint
   *   Constraint.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $items or $constraint is of an invalid type.
   * @throws \InvalidArgumentException
   *   Thrown if $items has an entity type which is not of type
   *   \Drupal\media\MediaInterface, or if the corresponding media has a source
   *   which is not of type
   *   \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo.
   */
  public function validate($items, Constraint $constraint) : void {
    if ($items === NULL) {
      return;
    }

    if (!($items instanceof FieldItemListInterface)) {
      throw new \InvalidArgumentException('$items is not of type \\Drupal\\Core\\Field\\FieldItemListInterface.');
    }
    if (!($constraint instanceof IbmVideoDataConstraint)) {
      throw new \InvalidArgumentException('$constraint is not of type \\Drupal\\ibm_video_media_type\\Plugin\\Validation\\Constraint\\IbmVideoDataConstraint.');
    }

    $media = $items->getEntity();
    if (!($media instanceof MediaInterface)) {
      throw new \InvalidArgumentException('$items does not have an entity of type \\Drupal\\media\\MediaInterface.');
    }
    /** @var \Drupal\media\MediaInterface $media */
    $source = $media->getSource();
    if (!($source instanceof IbmVideo)) {
      throw new \InvalidArgumentException('$items does not have a media source of type \\Drupal\\ibm_video_media_type\\Plugin\\media\\Source\\IbmVideo.');
    }
    /** @var \Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo $source */

    // We only care about the first item. We don't perform validation if the
    // item doesn't exist or evaluates to an empty string.
    $item = $items->first();
    if ($item === NULL) {
      return;
    }
    $rawData = (string) $item->value;
    if ($rawData === '') {
      return;
    }

    $data = [];
    $decodeError = $source->tryParseVideoData($rawData, $data);
    if ($decodeError === IbmVideo::VIDEO_DATA_PARSE_ERROR_BAD_JSON) {
      $this->context->addViolation($constraint->invalidJsonMessage);
      return;
    }
    elseif ($decodeError === IbmVideo::VIDEO_DATA_PARSE_ERROR_INVALID_KEYS) {
      $this->context->addViolation($constraint->invalidJsonKeySetMessage);
      return;
    }

    if (!$source->isIsRecordedFlagValid($data[IbmVideo::VIDEO_DATA_RECORDED_FLAG_PROPERTY_NAME])) {
      $this->context->addViolation($constraint->invalidIsRecordedFlagMessage);
    }
    if (!$source->isThumbnailReferenceIdValid($data[IbmVideo::VIDEO_DATA_THUMBNAIL_REFERENCE_ID_PROPERTY_NAME])) {
      $this->context->addViolation($constraint->invalidThumbnailReferenceIdMessage);
    }
    if (!$source->isVideoOrChannelIdValid($data[IbmVideo::VIDEO_DATA_ID_PROPERTY_NAME])) {
      $this->context->addViolation($constraint->invalidIdMessage);
    }
  }

}
