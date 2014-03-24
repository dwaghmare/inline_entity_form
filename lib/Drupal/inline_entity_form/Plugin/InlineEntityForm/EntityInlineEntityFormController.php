<?php

/**
 * @file
 * Defines the base inline entity form controller.
 */

namespace Drupal\inline_entity_form\Plugin\InlineEntityForm;

use \Drupal\Component\Utility\NestedArray;
use Drupal;

class EntityInlineEntityFormController {

  protected $entityType;
  public $settings;

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    $this->entityType = $plugin_id;
    $this->settings = $configuration + $this->defaultSettings();
  }

  /**
   * Returns an array of css filepaths for the current entity type, keyed
   * by theme name.
   *
   * If provided, the "base" CSS file is included for all themes.
   * If a CSS file matching the current theme exists, it will also be included.
   *
   * @code
   * return array(
   *   'base' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.base.css',
   *   'seven' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.seven.css',
   * );
   * @endcode
   */
  public function css() {
    return array();
  }

  /**
   * Returns an array of entity type labels (singular, plural) fit to be
   * included in the UI text.
   */
  public function labels() {
    $labels = array(
      'singular' => t('entity'),
      'plural' => t('entities'),
    );

    return $labels;

    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    // Commerce and its contribs declare permission labels that can be used
    // for more precise and user-friendly strings.
    if (!empty($info['permission labels'])) {
      $labels = $info['permission labels'];
    }

    return $labels;
  }

  /**
   * Returns an array of fields used to represent an entity in the IEF table.
   *
   * The fields can be either Field API fields or properties defined through
   * hook_entity_property_info().
   *
   * Modules can alter the output of this method through
   * hook_inline_entity_form_table_fields_alter().
   *
   * @param $bundles
   *   An array of allowed bundles for this widget.
   *
   * @return
   *   An array of field information, keyed by field name. Allowed keys:
   *   - type: 'field' or 'property',
   *   - label: Human readable name of the field, shown to the user.
   *   - weight: The position of the field relative to other fields.
   *   Special keys for type 'field', all optional:
   *   - formatter: The formatter used to display the field, or "hidden".
   *   - settings: An array passed to the formatter. If empty, defaults are used.
   *   - delta: If provided, limits the field to just the specified delta.
   */
  public function tableFields($bundles) {
    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    // $metadata = \Drupal::entityManager()->getFieldDefinitions($this->entityType);
    $metadata = array();

    $fields = array();
    if ($info->hasKey('label')) {
      $label_key = $info->getKey('label');
      $fields[$label_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$label_key]['label'] : t('Label'),
        'weight' => 1,
      );
    }
    else {
      $id_key = $info->getKey('id');
      $fields[$id_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$id_key]['label'] : t('ID'),
        'weight' => 1,
      );
    }
    if (count($bundles) > 1) {
      $bundle_key = $info->getKey('bundle');
      $fields[$bundle_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$bundle_key]['label'] : t('Type'),
        'weight' => 2,
      );
    }

    return $fields;
  }

  /**
   * Returns a setting value.
   *
   * @param $name
   *   The name of the setting value to return.
   *
   * @return
   *   A setting value.
   */
  public function getSetting($name) {
    return $this->settings[$name];
  }

  /**
   * Returns an array of default settings in the form of key => value.
   */
  public function defaultSettings() {
    $defaults = array();
    $defaults['allow_existing'] = FALSE;
    $defaults['match_operator'] = 'CONTAINS';
    $defaults['delete_references'] = FALSE;

    return $defaults;
  }

  /**
   * Returns the settings form for the current entity type.
   *
   * The settings form is embedded into the IEF widget settings form.
   * Settings are later injected into the controller through $this->settings.
   *
   * @param $field
   *   The definition of the reference field used by IEF.
   * @param $instance
   *   The definition of the reference field instance.
   */
  public function settingsForm($field, $instance) {
    $labels = $this->labels();
    $states_prefix = 'instance[widget][settings][type_settings]';

    $form = array();
    $form['allow_existing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow users to add existing @label.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['allow_existing'],
    );
    $form['match_operator'] = array(
      '#type' => 'select',
      '#title' => t('Autocomplete matching'),
      '#default_value' => $this->settings['match_operator'],
      '#options' => array(
        'STARTS_WITH' => t('Starts with'),
        'CONTAINS' => t('Contains'),
      ),
      '#description' => t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of nodes.'),
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[allow_existing]"]' => array('checked' => TRUE),
        ),
      ),
    );
    // The single widget doesn't offer autocomplete functionality.
    if ($instance['widget']['type'] == 'inline_entity_form_single') {
      $form['allow_existing']['#access'] = FALSE;
      $form['match_operator']['#access'] = FALSE;
    }

    $form['delete_references'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete referenced @label when the parent entity is deleted.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['delete_references'],
    );

    $form['override_labels'] = array(
      '#type' => 'checkbox',
      '#title' => t('Override labels'),
      '#default_value' => $this->settings['override_labels'],
    );
    $form['label_singular'] = array(
      '#type' => 'textfield',
      '#title' => t('Singular label'),
      '#default_value' => $this->settings['label_singular'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
        ),
      ),
    );
    $form['label_plural'] = array(
      '#type' => 'textfield',
      '#title' => t('Plural label'),
      '#default_value' => $this->settings['label_plural'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
        ),
      ),
    );

    return $form;
  }

  /**
   * Returns the entity type managed by this controller.
   *
   * @return
   *   The entity type.
   */
  public function entityType() {
    return $this->entityType;
  }

  /**
   * Returns the entity form to be shown through the IEF widget.
   *
   * When adding data to $form_state it should be noted that there can be
   * several IEF widgets on one master form, each with several form rows,
   * leading to possible key collisions if the keys are not prefixed with
   * $entity_form['#parents'].
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityForm($entity_form, &$form_state) {
    /**
     * @var \Drupal\Core\Entity\ContentEntityInterface $entity
     */
    $entity = $entity_form['#entity'];

    $child_form_state = $form_state;
    $form_display_id = $entity->getEntityType() . '.' . $entity->getBundle() . '.' . 'default';
    $child_form_state['form_display'] = entity_load('entity_form_display', $form_display_id);

    $child_form = \Drupal::entityManager()->getFormController($entity->getEntityType(), 'default');
    $child_form->setEntity($entity);
    $entity_form = $child_form->buildForm($entity_form, $child_form_state);
    return $entity_form;
  }

  /**
   * Validates the entity form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormValidate($entity_form, &$form_state) {
    $info = entity_get_info($this->entityType);
    $entity = $entity_form['#entity'];

    if ($info->isFieldable()) {
      field_attach_form_validate($entity, $entity_form, $form_state);
    }
  }

  /**
   * Handles the submission of an entity form.
   *
   * Prepares the entity stored in $entity_form['#entity'] for saving by copying
   * the values from the form to matching properties and, if the entity is
   * fieldable, invoking Field API submit.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormSubmit(&$entity_form, &$form_state) {
    $entity = $entity_form['#entity'];
//    $controller = \Drupal::entityManager()->getFormController($entity->getEntityType(), 'default');
//    $controller->setEntity($entity);
//
//    $child_form = $entity_form;
//    $child_form_state = $form_state;
//    $form_display_id = $entity->getEntityType() . '.' . $entity->getBundle() . '.' . 'default';
//    $child_form_state['form_display'] = entity_load('entity_form_display', $form_display_id);
//    $entity_form['#entity'] = $controller->submit($entity_form, $child_form_state);
//    return
//
//    $entity = $entity_form['#entity'];
//    if (!empty($entity->inline_entity_form_file_field_widget_submit)) {
//      unset($entity->inline_entity_form_file_field_widget_submit);
//      return;
//    }

    $operation = 'default';

    $child_form['#entity'] = $entity;

    $child_form_state = array();
    $controller = \Drupal::entityManager()->getFormController($entity->getEntityType(), $operation);
    $controller->setEntity($entity);
    $child_form_state['build_info']['callback_object'] = $controller;
    $child_form_state['build_info']['base_form_id'] = $controller->getBaseFormID();
    $child_form_state['build_info']['args'] = array();

    $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);
    $child_form_state['values']['menu'] = array();
    $child_form_state['buttons'] = array();

    $this->formController = \Drupal::entityManager()->getFormController($entity->getEntityType(), 'default');
    $this->formController->setEntity($entity);
    $child_form = $this->formController->buildForm($child_form, $child_form_state);

    $entity_form['#entity'] = $this->formController->submit($child_form, $child_form_state);
    $debug = TRUE;

    /*
    parent::entityFormSubmit($entity_form, $form_state);
    */


    /*
    parent::entityFormSubmit($entity_form, $form_state);

    $child_form_state = form_state_defaults();
    $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);

    $node = $entity_form['#entity'];
    $node->validated = TRUE;
    foreach (\Drupal::moduleHandler()->getImplementations('node_submit') as $module) {
      $function = $module . '_node_submit';
      $function($node, $entity_form, $child_form_state);
    }
    */
  }

  /**
   * Cleans up the form state for each field.
   *
   * After field_attach_submit() has run and the entity has been saved, the form
   * state still contains field data in $form_state['field']. Unless that
   * data is removed, the next form with the same #parents (reopened add form,
   * for example) will contain data (i.e. uploaded files) from the previous form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  protected function cleanupFieldFormState($entity_form, &$form_state) {
    $bundle = $entity_form['#entity']->getBundle();
    /**
     * @var \Drupal\Field\Entity\FieldInstance[] $instances
     */
    $instances = field_info_instances($entity_form['#entity_type'], $bundle);
    foreach ($instances as $instance) {
      $field_name = $instance->getFieldName();
      if (isset($entity_form[$field_name])) {
        $parents = $entity_form[$field_name]['#parents'];

        $field_state = field_form_get_state($parents, $field_name, $form_state);
        unset($field_state['items']);
        unset($field_state['entity']);
        $field_state['items_count'] = 0;
        field_form_set_state($parents, $field_name, $form_state, $field_state);
      }
    }
  }

  /**
   * Returns the remove form to be shown through the IEF widget.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function removeForm($remove_form, &$form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $entity_label = $entity->label();

    $remove_form['message'] = array(
      '#markup' => '<div>' . t('Are you sure you want to remove %label?', array('%label' => $entity_label)) . '</div>',
    );
    if (!empty($entity_id) && $this->getSetting('allow_existing')) {
      $access = $entity->access('delete');
      if ($access) {
        $labels = $this->labels();
        $remove_form['delete'] = array(
          '#type' => 'checkbox',
          '#title' => t('Delete this @type_singular from the system.', array('@type_singular' => $labels['singular'])),
        );
      }
    }

    return $remove_form;
  }

  /**
   * Handles the submission of a remove form.
   * Decides what should happen to the entity after the removal confirmation.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   *
   * @return
   *   IEF_ENTITY_UNLINK or IEF_ENTITY_UNLINK_DELETE.
   */
  public function removeFormSubmit($remove_form, &$form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $form_values = NestedArray::getValue($form_state['values'], $remove_form['#parents']);
    // This entity hasn't been saved yet, we can just unlink it.
    if (empty($entity_id)) {
      return IEF_ENTITY_UNLINK;
    }
    // If existing entities can be referenced, the delete happens only when
    // specifically requested (the "Permanently delete" checkbox).
    if ($this->getSetting('allow_existing') && empty($form_values['delete'])) {
      return IEF_ENTITY_UNLINK;
    }

    return IEF_ENTITY_UNLINK_DELETE;
  }

  /**
   * Permanently saves the given entity.
   *
   * @param $entity
   *   The entity to save.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function save($entity, $context) {
    $entity->save();
  }

  /**
   * Delete permanently saved entities.
   *
   * @param $ids
   *   An array of entity IDs.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function delete($ids, $context) {
    entity_delete_multiple($this->entityType, $ids);
  }
}
