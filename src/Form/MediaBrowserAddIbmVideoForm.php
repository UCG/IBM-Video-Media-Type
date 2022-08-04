<?php

declare (strict_types = 1);

namespace Drupal\ibm_video_media_type\Form;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ibm_video_media_type\Helper\IbmVideoUrl\IbmVideoUrlHelpers;
use Drupal\ibm_video_media_type\Plugin\media\Source\IbmVideo;
use Drupal\media\MediaTypeInterface;
use Drupal\media_library\Form\AddFormBase;

/**
 * Form for adding IBM video media entities through media browser.
 */
class MediaBrowserAddIbmVideoForm extends AddFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_ibm_video';
  }

  /**
   * Submit handler for this form.
   *
   * @param array $form
   *   Form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @throws \InvalidArgumentException
   *   Thrown in some cases if something in $formState is invalid.
   */
  public function submit(array $form, FormStateInterface $formState) : void {
    $url = (string) $formState->getValue('url');
    $id = '';
    $isRecorded = FALSE;
    IbmVideoUrlHelpers::parseEmbedUrl($url, $id, $isRecorded);
    $this->processInputValues([IbmVideo::prepareVideoData($id, $isRecorded, IbmVideo::generateFreshThumbnailReferenceId())], $form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildInputElement(array $form, FormStateInterface $form_state) : array {
    // For a nice visual effect, group elements together with a container.
    $form['container'] = [
      '#type' => 'container',
    ];

    $form['container']['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add IBM Video with URL'),
      '#description' => $this->t(
<<<'EOS'
The canonical embed URL for the video. Should start with "https://",
"http://", "//" or nothing at all (""), and be followed by
"video.ibm.com/embed/recorded/[video ID]" (for a recorded video) or
"video.ibm.com/embed/[channel ID]" (for a stream).
EOS
      ),
      '#size' => 50,
      '#maxlength' => 120,
      '#required' => TRUE,
      '#element_validate' => [[static::class, 'validateEmbedUrlInput']],
    ];

    $form['container']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#button_type' => 'primary',
      '#submit' => ['::submit'],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        // Code below is taken from @see \Drupal\media_library\Form\OEmbedForm::buildInputElement()
        // This apparently has to be there because of https://www.drupal.org/project/drupal/issues/2504115.
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $this->getMediaLibraryState($form_state)->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   *   Thrown if the media type referenced by the form state does not have an
   *   IBM Video media source.
   */
  protected function getMediaType(FormStateInterface $form_state) : MediaTypeInterface {
    $type = parent::getMediaType($form_state);
    if (!($type->getSource() instanceof IbmVideo)) {
      throw new \InvalidArgumentException('Only media types that have IBM Video media sources are allowed in this context.');
    }
    return $type;
  }

  /**
   * Validates/sets errors for the embed URL input associated with $element.
   *
   * @param array $element
   *   Form element.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form state.
   *
   * @throws \RuntimeException
   *   Sometimes thrown if something is wrong with $element. We don't throw an
   *   \InvalidArgumentException because of how this function should be called.
   */
  public static function validateEmbedUrlInput(array &$element, FormStateInterface &$formState) : void {
    if (!isset($element['#parents']) || !is_array($element['#parents'])) {
      throw new \RuntimeException('The #parents form element item is missing or invalid.');
    }
    $url = (string) $formState->getValue($element['#parents']);
    if (!IbmVideoUrlHelpers::isEmbedUrlValid($url)) {
      $formState->setErrorByName('url', 'Embed URL is not in the required format.');
    }
  }

}
