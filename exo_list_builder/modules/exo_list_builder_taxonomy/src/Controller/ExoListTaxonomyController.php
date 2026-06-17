<?php

namespace Drupal\exo_list_builder_taxonomy\Controller;

use Drupal\exo_icon\ExoIconTranslationTrait;
use Drupal\exo_list_builder\Controller\ExoListController;
use Drupal\exo_list_builder\EntityListInterface;
use Drupal\taxonomy\VocabularyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a generic controller to list entities.
 */
class ExoListTaxonomyController extends ExoListController {
  use ExoIconTranslationTrait;

  /**
   * Provides the listing page for any entity type.
   */
  public function listing(Request $request, EntityListInterface $exo_entity_list) {
    $vocabulary = \Drupal::routeMatch()->getParameter('taxonomy_vocabulary');
    if (is_string($vocabulary)) {
      $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vocabulary);
    }
    // Integrate with taxonomy controls.
    $controls = $vocabulary->getThirdPartySettings('exo_list_builder');
    $show_default = FALSE;
    if (!isset($controls['nest']) || !empty($controls['nest'])) {
      $show_default = TRUE;
    }
    if (!$show_default) {
      /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
      $term_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
      $show_default = $term_storage->getVocabularyHierarchyType($vocabulary->id()) !== 0;
    }
    if ($show_default) {
      // In Drupal 11 OverviewTerms is an EntityForm, so the form object must
      // have its entity set before the form is built (FormBuilder::getForm()
      // does not do this for a class string). The vocabulary is still passed
      // as a build argument because OverviewTerms::buildForm() reads it from
      // the argument rather than $this->getEntity().
      $form_object = $this->entityTypeManager()->getFormObject('taxonomy_vocabulary', 'overview');
      $form_object->setEntity($vocabulary);
      $form = $this->formBuilder()->getForm($form_object, $vocabulary);
      $form['#attached']['library'][] = 'exo_list_builder_taxonomy/list';
      return $form;
    }
    return parent::listing($request, $exo_entity_list);
  }

  /**
   * Provides the listing page for any entity type.
   *
   * @param \Drupal\exo_list_builder\EntityListInterface $exo_entity_list
   *   The exo entity list to render.
   *
   * @return array
   *   A render array as expected by
   *   \Drupal\Core\Render\RendererInterface::render().
   */
  public function listingTitle(EntityListInterface $exo_entity_list) {
    $vocabulary = \Drupal::routeMatch()->getParameter('taxonomy_vocabulary');
    if (is_string($vocabulary)) {
      $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vocabulary);
      return $this->icon('Manage %label Terms', ['%label' => $vocabulary->label()])->setIcon(exo_icon_entity_icon($vocabulary));
    }
    return parent::listingTitle($exo_entity_list);
  }

  /**
   * Simple route redirection.
   */
  public function vocabularyEditRedirect(VocabularyInterface $taxonomy_vocabulary) {
    $url = $taxonomy_vocabulary->toUrl('edit-form');
    return $this->redirect($url->getRouteName(), $url->getRouteParameters());
  }

}
