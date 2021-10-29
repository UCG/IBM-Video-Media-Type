<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldItemListInterface;
use Ranine\Helper\StringHelpers;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Handles validation for IBM video configuration data.
 *
 * The associated constraint is
 * @see \Drupal\ibm_video_media_type\Plugin\Validation\Constraint\IbmVideoConfigurationConstraint.
 */
class IbmVideoConfigurationConstraintValidator extends ConstraintValidator {

  /**
   * Validates the given IBM video configuration field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface|null $value
   *   The field list corresponding to the "source" field for the media entity,
   *   or NULL if the field list is not available.
   * @param \Drupal\ibm_video_media_type\Plugin\Validation\Constraint\IbmVideoConfigurationConstraint $constraint
   *   Constraint.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $value or $constraint is of an invalid type.
   * @throws \InvalidArgumentException
   *   T
   */
  public function validate($value, Constraint $constraint) : void {
    if ($value === NULL) {
      return;
    }

    if (!($value instanceof FieldItemListInterface)) {
      throw new \InvalidArgumentException('$value is not of type \\Drupal\\Core\\Field\\FieldItemListInterface.');
    }
    if (!($constraint instanceof IbmVideoConfigurationConstraint)) {
      throw new \InvalidArgumentException('$value is not of type \\Drupal\\ibm_video_media_type\\Plugin\\Validation\\Constraint\\IbmVideoConfigurationConstraint.');
    }

    $item = $value->first();
    if ($item === NULL) {
      return;
    }
    $configurationJson = (string) $item->value;
    if ($configurationJson === '') {
      return;
    }

    $configuration = json_decode($configurationJson, TRUE);
    if (!is_array($configuration)) {
      $this->context->addViolation($constraint->invalidJsonMessage);
      return;
    }
    /** @var array $configuration */

    // Make sure there are no extra elements.
    if (count($configuration) !== 8) {
      $this->context->addViolation($constraint->jsonSchemaInvalidGeneralMessage);
      return;
    }
    // Make sure all of the elements exist.
    if (!array_key_exists('videoId', $configuration)
      || !array_key_exists('useAutoplay', $configuration)
      || !array_key_exists('useHtml5Ui', $configuration)
      || !array_key_exists('displayControls', $configuration)
      || !array_key_exists('initialVolume', $configuration)
      || !array_key_exists('showTitle', $configuration)
      || !array_key_exists('wMode', $configuration)
      || !array_key_exists('defaultQuality', $configuration)) {
      $this->context->addViolation($constraint->jsonSchemaInvalidGeneralMessage);
      return;
    }

    // Perform the data validation for each property.
    if (!StringHelpers::isNonEmptyString($configuration['videoId'])) {
      $this->context->addViolation($constraint->videoIdNotNotEmptyStringMessage);
    }
    if (!is_bool($configuration['useAutoplay'])) {
      $this->context->addViolation($constraint->useAutoplayNotBoolMessage);
    }
    if (!is_bool($configuration['useHtml5Ui'])) {
      $this->context->addViolation($constraint->useHtml5UiNotBoolMessage);
    }
    if (!is_bool($configuration['displayControls'])) {
      $this->context->addViolation($constraint->displayControlsNotBoolMessage);
    }
    $initialVolume = $configuration['initialVolume'];
    if (!is_int($initialVolume) || $initialVolume < 0 || $initialVolume > 100) {
      $this->context->addViolation($constraint->initialVolumeNotIntegerInRightRangeMessage);
    }
    if (!is_bool($configuration['showTitle'])) {
      $this->context->addViolation($constraint->showTitleNotBoolMessage);
    }
    $wMode = $configuration['wMode'];
    switch ($wMode) {
      case 'direct': case 'opaque': case 'transparent': case 'window':
        break;

      default:
        $this->context->addViolation($constraint->wModeInvalidMessage);
    }
    $defaultQuality = $configuration['defaultQuality'];
    switch ($defaultQuality) {
      case 'low': case 'med': case 'high':
        break;

      default:
        $this->context->addViolation($constraint->defaultQualityInvalidMessage);
    }
  }

}
