<?php

namespace Drupal\osu_cas_multisite\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filters publications by any part of an author's name.
 *
 * Authors are taxonomy terms, so the obvious way to search them is a
 * relationship to the term and a filter on its name. That is a trap here: the
 * join emits one row per author, and publications average four or five, so
 * adding it took the unfiltered listing from 8,140 rows to 36,206 and repeated
 * every multi-author paper. Views' distinct setting does not save it, because
 * the joined columns differ per row.
 *
 * EXISTS asks the same question without widening the result set: does this
 * publication have any author whose name contains the text? One row per
 * publication, whether it has one author or twenty, and no interaction with the
 * grouping or the pager.
 *
 * It also replaces a filter that had been quietly dead. The view carried an
 * exposed "Author(s)" box on field_pub_authors_value, a column that stopped
 * existing when authors became a taxonomy; Views resolved it to a Broken
 * handler, so the box rendered on /publications and did nothing.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cas_publication_author")
 */
class CasPublicationAuthor extends StringFilter {

  /**
   * {@inheritdoc}
   *
   * Only the substring match is offered. Anything anchored ("starts with",
   * "is") is a poor fit for a name field holding "S. I. Rondon", "Rondon, S."
   * and "Silvia I. Rondon" for the same person.
   */
  public function operators() {
    return [
      'contains' => [
        'title' => $this->t('Contains'),
        'short' => $this->t('contains'),
        'method' => 'opContains',
        'values' => 1,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $value = trim((string) $this->value);
    if ($value === '') {
      return;
    }
    $connection = \Drupal::database();
    $this->query->addWhereExpression($this->options['group'], "EXISTS (
      SELECT 1
      FROM {node__field_pub_authors} cas_pa
      INNER JOIN {taxonomy_term_field_data} cas_pt
        ON cas_pt.tid = cas_pa.field_pub_authors_target_id
      WHERE cas_pa.entity_id = node_field_data.nid
        AND cas_pa.deleted = 0
        AND cas_pt.name LIKE :cas_author)", [
          ':cas_author' => '%' . $connection->escapeLike($value) . '%',
        ]);
  }

}
