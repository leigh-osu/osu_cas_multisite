<?php

namespace Drupal\osu_cas_multisite_groups\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * The node's URL alias, as stored.
 *
 * Core ships no views field for the alias: path_alias is a separate entity
 * keyed by system path, not a field on the node, and the node's own "Link"
 * field renders a resolved URL rather than the stored alias. For triaging
 * ungrouped content the stored alias is the point -- its shape is the evidence.
 * A page at /be-deleted/news-media/2011-news-archives was parked for removal
 * during the migration, one at /feature/wheat-research still sits in a section,
 * and a bare /research-day-2017 has no position at all, which is the clearest
 * signal available that nothing references it.
 *
 * Read by correlated subquery rather than per-row lookups, so a 2,400-row
 * listing stays one query and the column can be click-sorted -- sorting by
 * alias is what groups the parked paths together.
 *
 * Only aliases with status 1 count, and the highest id wins: path_alias keeps
 * superseded rows, and the newest is the one that resolves.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cas_node_alias")
 */
class CasNodeAlias extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->field_alias = $this->query->addField(NULL, "(SELECT cas_pa.alias
      FROM {path_alias} cas_pa
      WHERE cas_pa.path = CONCAT('/node/', node_field_data.nid)
        AND cas_pa.status = 1
      ORDER BY cas_pa.id DESC
      LIMIT 1)", 'cas_node_alias');
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $alias = $values->{$this->field_alias} ?? NULL;
    if ($alias === NULL || $alias === '') {
      return $this->sanitizeValue($this->options['empty'] ?: $this->t('— no alias —'));
    }
    return $this->sanitizeValue($alias);
  }

}
