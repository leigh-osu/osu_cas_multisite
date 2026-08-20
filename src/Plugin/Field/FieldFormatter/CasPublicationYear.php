<?php

namespace Drupal\osu_cas_multisite\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\IntegerFormatter;

/**
 * Renders a publication year, spelling out Biblio's sentinel values.
 *
 * D7's Biblio module had no separate "status" field. It encoded work that had
 * no year yet in the year column itself, and those values came through the
 * migration untouched:
 *
 *   9999  in press   (7 publications)
 *   9998  submitted  (8 publications)
 *
 * Harmless while the listing was ordered by publication date — the field is
 * empty on both groups, so they sat at the bottom where nobody looked. Ordering
 * by year instead put them at the top of /publications, where the citation read
 * "(9999)". The order is right: work in press is the newest thing a department
 * has. It was only ever the display that was wrong.
 *
 * Extending the core integer formatter rather than writing one from scratch
 * keeps the thousand-separator and prefix/suffix settings, so this is a drop-in
 * replacement anywhere number_integer was used, and the prefix and suffix still
 * wrap the substituted text — "(In Press)" is how a citation should read.
 *
 * Year 0 (11 publications) is deliberately left alone: 9998 and 9999 have one
 * meaning each, but a zero year is simply unknown, and whether those records
 * should read "n.d.", stay blank, or be corrected is an editorial decision
 * rather than a display one.
 *
 * @FieldFormatter(
 *   id = "cas_publication_year",
 *   label = @Translation("Publication year (In Press / Submitted)"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class CasPublicationYear extends IntegerFormatter {

  /**
   * Biblio's sentinel year for work accepted but not yet published.
   */
  public const IN_PRESS = 9999;

  /**
   * Biblio's sentinel year for work submitted but not yet accepted.
   */
  public const SUBMITTED = 9998;

  /**
   * {@inheritdoc}
   *
   * The parent applies prefix, suffix and the content attribute around whatever
   * this returns, so substituting here covers every display in one place.
   */
  protected function numberFormat($number) {
    return match ((int) $number) {
      self::IN_PRESS => (string) $this->t('In Press'),
      self::SUBMITTED => (string) $this->t('Submitted'),
      default => parent::numberFormat($number),
    };
  }

}
