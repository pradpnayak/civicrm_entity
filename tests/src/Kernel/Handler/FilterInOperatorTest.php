<?php

namespace Drupal\Tests\civicrm_entity\Kernel\Handler;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\civicrm_entity\Kernel\CivicrmEntityTestBase;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;

/**
 * Test InOperator.
 *
 * @group civicrim_entity
 */
final class FilterInOperatorTest extends CivicrmEntityTestBase {

  use ViewResultAssertionTrait;

  public function alter(ContainerBuilder $container) {
    // Disable mocks.
  }

  protected static $modules = [
    'views',
    'civicrm_entity_views_test',
  ];

  public static $testViews = ['test_view'];

  protected $columnMap = [
    'contact_type' => 'contact_type',
    'display_name' => 'display_name',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createTestViews(static::$testViews);
    /** @var \Drupal\civicrm_entity\CiviCrmApi $civicrm_api */
    $civicrm_api = $this->container->get('civicrm_entity.api');

    $option_group_result = $civicrm_api->save('OptionGroup', ['name' => 'Test options']);
    $option_group_result = reset($option_group_result['values']);

    $options = [
      ['label' => 'Test', 'value' => 1],
      ['label' => 'Test 1', 'value' => 2],
      ['label' => 'Test 2', 'value' => 3],
    ];

    foreach ($options as $option) {
      $civicrm_api->save('OptionValue', $option + ['option_group_id' => $option_group_result['id']]);
    }

    $result = $civicrm_api->save('CustomGroup', [
      'title' => 'Test',
      'extends' => 'Individual',
    ]);

    $result = reset($result['values']);

    $civicrm_api->save('CustomField', [
      'custom_group_id' => $result['id'],
      'label' => 'Test select',
      'serialize' => 1,
      'data_type' => 'String',
      'html_type' => 'Multi-Select',
      'option_group_id' => $option_group_result['id'],
    ]);

    $contacts = $this->createSampleData();

    foreach ($contacts as $contact) {
      $civicrm_api->save('Contact', $contact);
    }

    drupal_flush_all_caches();
  }

  /**
   * Test civicrm_entity_in_operator pllgin.
   */
  public function testFilterInOperatorSimple() {
    $view = Views::getView('test_view');
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('filters', [
      'contact_type' => [
        'id' => 'contact_type',
        'table' => 'civicrm_contact',
        'field' => 'contact_type',
        'value' => ['Individual' => 'Individual'],
        'operator' => 'or',
        'entity_type' => 'civicrm_contact',
        'entity_field' => 'contact_type',
        'plugin_id' => 'list_field',
      ],
    ]);

    $view->preExecute();
    $view->execute();

    $expected_result = [
      [
        'display_name' => 'John Smith',
        'contact_type' => 'Individual',
      ],
      [
        'display_name' => 'Jane Smith',
        'contact_type' => 'Individual',
      ],
      [
        'display_name' => 'John Doe',
        'contact_type' => 'Individual',
      ],
      [
        'display_name' => 'Jane Doe',
        'contact_type' => 'Individual',
      ],
    ];

    $this->assertCount(4, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    // $view->destroy();
    // $view->setDisplay();

    // $view->displayHandlers->get('default')->overrideOption('filters', [
    //   'contact_type' => [
    //     'id' => 'contact_type',
    //     'table' => 'civicrm_contact',
    //     'field' => 'contact_type',
    //     'value' => ['Individual' => 'Individual'],
    //     'operator' => 'not',
    //     'entity_type' => 'civicrm_contact',
    //     'entity_field' => 'contact_type',
    //     'plugin_id' => 'list_field',
    //   ],
    // ]);

    // $view->preExecute();
    // $view->execute();

    // $expected_result = [
    //   [
    //     'organization_name' => 'Default organization',
    //     'contact_type' => 'Organization',
    //   ],
    //   [
    //     'organization_name' => 'The Trevor Project',
    //     'contact_type' => 'Organization',
    //   ],
    // ];

    // $this->assertCount(2, $view->result);
    // $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('filters', [
      'test_select_1' => [
        'id' => 'test_select_1',
        'field' => 'test_select_1',
        'table' => 'civicrm_value_test_1',
        'value' => [1 => '1', 2 => '2'],
        'operator' => 'in',
        'entity_type' => 'civicrm_contact',
        'entity_field' => 'custom_1',
        'plugin_id' => 'civicrm_entity_in_operator',
      ],
    ]);

    $view->preExecute();
    $view->execute();

    $expected_result = [
      [
        'display_name' => 'John Smith',
        'contact_type' => 'Individual',
      ],
      [
        'display_name' => 'Jane Smith',
        'contact_type' => 'Individual',
      ],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);

    $view->destroy();
    $view->setDisplay();

    $view->displayHandlers->get('default')->overrideOption('filters', [
      'test_select_1' => [
        'id' => 'test_select_1',
        'field' => 'test_select_1',
        'table' => 'civicrm_value_test_1',
        'value' => [1 => '1', 2 => '2'],
        'operator' => 'not in',
        'entity_type' => 'civicrm_contact',
        'entity_field' => 'custom_1',
        'plugin_id' => 'civicrm_entity_in_operator',
      ],
    ]);

    $view->preExecute();
    $view->execute();

    $expected_result = [
      [
        'display_name' => 'John Doe',
        'contact_type' => 'Individual',
      ],
      [
        'display_name' => 'Jane Doe',
        'contact_type' => 'Individual',
      ],
    ];

    $this->assertCount(2, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $this->columnMap);
  }

  /**
   * Create the views configurations.
   */
  protected function createTestViews(array $views) {
    $storage = \Drupal::entityTypeManager()->getStorage('view');
    $module_handler = \Drupal::moduleHandler();

    $config_dir = \Drupal::service('extension.list.module')->getPath('civicrm_entity_views_test') . '/config/install';
    if (is_dir($config_dir) && $module_handler->moduleExists('civicrm_entity_views_test')) {
      $file_storage = new FileStorage($config_dir);
      $available_views = $file_storage->listAll('views.view.');
      foreach ($views as $id) {
        $config_name = 'views.view.' . $id;
        if (in_array($config_name, $available_views)) {
          $storage
            ->create($file_storage->read($config_name))
            ->save();
        }
      }
    }
  }

  /**
   * Create sample data.
   */
  protected function createSampleData() {
    return [
      [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'contact_type' => 'Individual',
        'custom_1' => [1],
      ],
      [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'contact_type' => 'Individual',
        'custom_1' => [2],
      ],
      [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'contact_type' => 'Individual',
        'custom_1' => [],
      ],
      [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'contact_type' => 'Individual',
      ],
      [
        'organization_name' => 'The Trevor Project',
        'contact_type' => 'organization',
      ],
    ];
  }

}
