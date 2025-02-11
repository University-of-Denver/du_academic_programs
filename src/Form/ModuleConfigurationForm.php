<?php

namespace Drupal\du_academic_programs\Form;

use Drupal\node\Entity\Node;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\term;

/**
 * Main du_event_import module configuration form.
 */
class ModuleConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'du_academic_programs_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'du_academic_programs.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('du_academic_programs.settings');

    $form['import_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Data File'),
      '#description' => $this->t('CSV file listing academic programs'),
    ];
    $form['import_rows'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rows to Import'),
    ];
    $form['skip_rows'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Rows to Skip'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Imports a taxonomy term if the term does not already exist.
   *
   * A term is considered to exist if there is already a term with the same name within the same vocabulary.
   * Returns the existing or newly created term.
   */
  private function importTerm($vid, $term_name) {
    $term = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['name' => $term_name]);

    if (empty($term)) {
      $term = term::create([
        'name' => $term_name,
        'vid' => $vid,
      ]);

      if ($vid == 'schools') {
        $term->set('field_schools_banner_code', $term_name);
      }
      if ($vid == 'degree_level') {
        $term->set('field_degree_level_abreviation', $term_name);
      }
      if ($vid == 'degree_types') {
        $term->set('field_degree_types_abreviation', $term_name);
      }

      $term->save();
      return $term;
    }
    else {
      return array_shift($term);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = file_save_upload('import_file', ['file_validate_extensions' => ['csv']]);
    $import_rows = $form_state->getValue('import_rows');
    $skip_rows = $form_state->getValue('skip_rows');

    // Map of column indexes.
    $ind = [
      'college',
      'level',
      'is_major',
      'is_minor',
      'is_dual',
      'is_adult',
      'degrees',
      'fed_aid',
      'program_title',
      'tags',
      'description1',
      'description2',
      'concentration',
      'cta_1_text',
      'cta_1_url',
      'cta_1_meta',
      'cta_1_onclick',
      'cta_1_target',
      'cta_2_text',
      'cta_2_url',
      'cta_2_meta',
      'cta_2_onclick',
      'cta_2_target',
      'cta_3_text',
      'cta_3_url',
      'cta_3_meta',
      'cta_3_onclick',
      'cta_3_target',
      'cta_4_text',
      'cta_4_url',
      'cta_4_meta',
      'cta_4_onclick',
      'cta_4_target',
    ];

    if (!empty($file[0])) {
      $rows = [];
      $handle = fopen($file[0]->getFileUri(), 'r');
      // Skip headings.
      fgetcsv($handle);
      while (FALSE !== $row = fgetcsv($handle)) {
        $rows[] = array_combine($ind, $row);
      }
    }

    $imported = 0;
    foreach ($rows as $ct => $row) {
      if ($ct < $skip_rows) {
        continue;
      }
      if ($ct >= $skip_rows + $import_rows) {
        break;
      }

      $node = Node::create([
        'type' => 'academic_program',
      ]);

      $node->set('title', $row['program_title']);
      $node->set('field_program_description_1', ['value' => "<p>{$row['description1']}</p>", 'format' => 'rich_text']);
      $node->set('field_program_description_2', ['value' => "<p>{$row['description2']}</p>", 'format' => 'rich_text']);

      $node->set('field_program_dual_degree', ($row['is_dual'] == 'Y') ? TRUE : FALSE);
      $node->set('field_program_adult_education', ($row['is_adult'] == 'Y') ? TRUE : FALSE);

      if (!empty($row['college'])) {
        $term = $this->importTerm('schools', $row['college']);
        $node->set('field_program_school_name', ['target_id' => $term->id()]);
      }

      if (!empty($row['level'])) {
        $term = $this->importTerm('degree_level', $row['level']);
        $node->set('field_program_degree_level', ['target_id' => $term->id()]);
      }

      $degrees = [];
      if (!empty($row['degrees'])) {
        $degrees_split = explode(', ', $row['degrees']);
        foreach ($degrees_split as $degree) {
          if ($degree == 'CRTG' || $degree == 'CPUB' || $degree == 'CRTM') {
            $degree = 'CERT';
          }
          $term = $this->importTerm('degree_types', $degree);
          $degrees[] = ['target_id' => $term->id()];
        }
      }
      if ($row['is_minor'] == 'Y') {
        $term = $this->importTerm('degree_types', 'Minor');
        $degrees[] = ['target_id' => $term->id()];
      }
      /*
      if ( $row['is_major'] == 'Y' ) {
      $term = $this->importTerm('degree_types', 'Major');
      $degrees[] = ['target_id' => $term->id()];
      }*/
      $node->set('field_program_degree_type', $degrees);

      $ctas = [];

      if ($row['level'] == 'UG') {
        $ctas[] = [
          'title' => "Start the Application Process",
          'uri' => "https://www.du.edu/admission-aid/undergraduate#admissionCards",
          'options' => [
            'attributes' => [],
          ],
        ];
      }

      if (!empty($row['cta_1_text'])) {
        $ctas[] = [
          'title' => $row['cta_1_text'],
          'uri' => $row['cta_1_url'],
          'options' => [
            'attributes' => [
              'onClick' => $row['cta_1_onclick'],
              'target' => $row['cta_1_target'],
              'title' => $row['cta_1_meta'],
            ],
          ],
        ];
      }
      if (!empty($row['cta_2_text'])) {
        $ctas[] = [
          'title' => $row['cta_2_text'],
          'uri' => $row['cta_2_url'],
          'options' => [
            'attributes' => [
              'onClick' => $row['cta_2_onclick'],
              'target' => $row['cta_2_target'],
              'title' => $row['cta_2_meta'],
            ],
          ],
        ];
      }
      if (!empty($row['cta_3_text'])) {
        $ctas[] = [
          'title' => $row['cta_3_text'],
          'uri' => $row['cta_3_url'],
          'options' => [
            'attributes' => [
              'onClick' => $row['cta_3_onclick'],
              'target' => $row['cta_3_target'],
              'title' => $row['cta_3_meta'],
            ],
          ],
        ];
      }
      if (!empty($row['cta_4_text'])) {
        $ctas[] = [
          'title' => $row['cta_4_text'],
          'uri' => $row['cta_4_url'],
          'options' => [
            'attributes' => [
              'onClick' => $row['cta_4_onclick'],
              'target' => $row['cta_4_target'],
              'title' => $row['cta_4_meta'],
            ],
          ],
        ];
      }

      $node->set('field_program_cta_links', $ctas);

      if ($row['level'] == 'GR') {
        $node->set('field_program_display_req', 1);
      }

      $node->save();
      $imported++;
    }

    $this->messenger()->addStatus($this->t("Imported $imported programs."));
  }

}
