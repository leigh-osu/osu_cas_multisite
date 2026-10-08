<?php

namespace Drupal\osu_cas_multisite\Entity;

use Drupal\group\Entity\Controller\GroupListBuilder;

/**
 * Group admin list (/admin/group): every group on one page, sorted by name.
 *
 * The parent marks no column as the default sort, so table sort falls back
 * to the first sortable header, Group ID. Clicking any header still re-sorts.
 * There are only a couple of hundred groups, so the 50-per-page pager just
 * gets in the way of scanning the list.
 */
class CasGroupListBuilder extends GroupListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $limit = FALSE;

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = parent::buildHeader();
    $header['label']['sort'] = 'asc';
    return $header;
  }

}
