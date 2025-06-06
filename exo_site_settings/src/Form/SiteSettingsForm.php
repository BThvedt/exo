<?php

namespace Drupal\exo_site_settings\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\exo\ExoNestedEntityFormBaseTrait;
use Drupal\exo\ExoNestedEntityFormInterface;
use Drupal\exo_site_settings\Event\SiteSettingsConfigCloneEvent;
use Drupal\field\FieldConfigInterface;

/**
 * Form controller for config page edit forms.
 *
 * @ingroup exo_site_settings
 */
class SiteSettingsForm extends ContentEntityForm implements ExoNestedEntityFormInterface {

  use ExoNestedEntityFormBaseTrait;

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    // Clone to config as specified.
    foreach ($this->entity->getFieldDefinitions() as $field) {
      if ($field instanceof FieldConfigInterface) {
        if ($clone_info = $this->getCloneDefinition($field)) {
          $values = [];
          $name = $clone_info['name'];
          $key = $clone_info['key'];
          $delimiter = $clone_info['delimiter'];
          foreach ($this->entity->get($field->getName())->getValue() as $value) {
            $value = $value[$field->getFieldStorageDefinition()->getMainPropertyName()];
            if ($field->getType() == 'link') {
              $value = str_replace('internal:', '', $value);
            }
            $values[] = $value;
          }
          $value = implode($delimiter ? $delimiter : '', $values);
          \Drupal::configFactory()->getEditable($name)
            ->set($key, $value)
            ->save();
        }
      }
    }

    $message_args = ['%label' => $this->entity->label()];
    $logger_args = [
      '%label' => $this->entity->label(),
      'link' => Url::fromRoute('<current>')->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New site settings %label has been created.', $message_args));
        $this->logger('exo_site_settings')->notice('New site settings %label has been created.', $logger_args);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The site settings %label has been updated.', $message_args));
        $this->logger('exo_site_settings')->notice('The site settings %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save the entity.');
    }

    return $result;
  }

  /**
   * Get clone information.
   *
   * @param \Drupal\field\FieldConfigInterface $field
   *   The field config.
   *
   * @return array|null
   *   An array of name/key values.
   */
  protected function getCloneDefinition(FieldConfigInterface $field): ?array {
    $name = $field->getThirdPartySetting('exo_site_settings', 'config_name');
    $key = $field->getThirdPartySetting('exo_site_settings', 'config_key');
    $delimiter = $field->getThirdPartySetting('exo_site_settings', 'config_delimiter');
    $event = new SiteSettingsConfigCloneEvent($field, $name, $key, $delimiter);
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $event_dispatcher->dispatch($event, SiteSettingsConfigCloneEvent::EVENT_NAME);
    $name = $event->getName();
    $key = $event->getKey();
    if (!$name || !$key) {
      return NULL;
    }
    return [
      'name' => $event->getName(),
      'key' => $event->getKey(),
      'delimiter' => $event->getDelimiter(),
    ];
  }

}
