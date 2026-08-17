<?php

namespace Drupal\osu_cas_weather\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;

/**
 * Weather station table: a weather_daily / weather_monthly view display.
 *
 * The D10 stand-in for D7's daily_weather_data / monthly_weather_data
 * viewfield embeds: pick a station, a table and a period ("current" or a
 * fixed year / year-month) and the block runs the matching view display
 * with the station and period as contextual filters. "Current" periods
 * resolve at render time, so the block is cached for an hour.
 *
 * @Block(
 *   id = "cas_weather",
 *   admin_label = @Translation("Weather station table"),
 *   category = @Translation("OSU CAS")
 * )
 */
class CasWeatherBlock extends BlockBase {

  /**
   * table => [view, display, period kind].
   */
  const TABLES = [
    'daily_month' => ['weather_daily', 'month_block', 'month'],
    'daily_year' => ['weather_daily', 'year_block', 'year'],
    'gdd_year' => ['weather_daily', 'gdd_year_block', 'year'],
    'gdd_current' => ['weather_daily', 'gdd_current', NULL],
    'monthly_month' => ['weather_monthly', 'month_block', 'month'],
    'monthly_year' => ['weather_monthly', 'year_block', 'year'],
  ];

  /**
   * {@inheritDoc}
   */
  public function defaultConfiguration() {
    return [
      'station' => 'malheur',
      'table' => 'daily_month',
      'period' => 'current',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['station'] = [
      '#type' => 'select',
      '#title' => $this->t('Station'),
      '#options' => ['malheur' => $this->t('Malheur Experiment Station'), 'hyslop' => $this->t('Hyslop Farm')],
      '#default_value' => $this->configuration['station'],
    ];
    $form['table'] = [
      '#type' => 'select',
      '#title' => $this->t('Table'),
      '#options' => [
        'daily_month' => $this->t('Daily readings for a month'),
        'daily_year' => $this->t('Daily readings for a year'),
        'gdd_year' => $this->t('Growing degree days for a year'),
        'gdd_current' => $this->t('Current growing degree days (headline)'),
        'monthly_month' => $this->t('Monthly summary for a month'),
        'monthly_year' => $this->t('Monthly summaries for a year'),
      ],
      '#default_value' => $this->configuration['table'],
    ];
    $form['period'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Period'),
      '#description' => $this->t('"current" for the current month/year, or a fixed year (2024) or year-month (202407).'),
      '#default_value' => $this->configuration['period'],
      '#size' => 12,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['station'] = $form_state->getValue('station');
    $this->configuration['table'] = $form_state->getValue('table');
    $period = trim((string) $form_state->getValue('period'));
    $this->configuration['period'] = $period === '' ? 'current' : $period;
  }

  /**
   * {@inheritDoc}
   */
  public function build() {
    [$view_id, $display, $kind] = self::TABLES[$this->configuration['table']] ?? self::TABLES['daily_month'];
    $view = Views::getView($view_id);
    if (!$view || !$view->access($display)) {
      return [];
    }
    $args = [$this->configuration['station']];
    $current = FALSE;
    if ($kind) {
      $period = $this->configuration['period'] ?: 'current';
      if ($period === 'current') {
        $current = TRUE;
        $period = $kind === 'month' ? date('Ym') : date('Y');
      }
      // Accept 2024-07 for a month too.
      $args[] = preg_replace('/\D/', '', $period);
    }
    $view->setDisplay($display);
    $view->setArguments($args);
    $build = $view->buildRenderable($display, $args);
    if ($current) {
      $build['#cache']['max-age'] = 3600;
    }
    return $build;
  }

}
