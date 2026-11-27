<?php

namespace Drupal\nusry_forms\Service;

/**
 * Neutralizes spreadsheet formula-injection payloads in CSV cells.
 */
final class CsvSafety {

  /**
   * Escapes cell text that could evaluate as a spreadsheet formula.
   */
  public static function escapeFormula(string $value): string {
    if ($value === '') {
      return '';
    }
    if (
      in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], TRUE)
      || preg_match('/^\s+[=+\-@]/', $value) === 1
    ) {
      return "'" . $value;
    }
    return $value;
  }

}
