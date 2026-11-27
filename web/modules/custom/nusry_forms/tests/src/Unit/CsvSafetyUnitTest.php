<?php

declare(strict_types=1);

namespace Drupal\Tests\nusry_forms\Unit;

use Drupal\nusry_forms\Service\CsvSafety;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CSV spreadsheet formula-injection sanitization.
 */
final class CsvSafetyUnitTest extends TestCase {

  /**
   * Ensures dangerous formula prefixes are neutralized cleanly.
   */
  public function testFormulaInjectionEscaping(): void {
    $this->assertSame("'=cmd|' /C calc'!A0", CsvSafety::escapeFormula("=cmd|' /C calc'!A0"));
    $this->assertSame("'+1+1", CsvSafety::escapeFormula('+1+1'));
    $this->assertSame("'-2+3", CsvSafety::escapeFormula('-2+3'));
    $this->assertSame("'@SUM(1,1)", CsvSafety::escapeFormula('@SUM(1,1)'));
    $this->assertSame("'\tcmd", CsvSafety::escapeFormula("\tcmd"));
    $this->assertSame("'\rcmd", CsvSafety::escapeFormula("\rcmd"));
    $this->assertSame("' =HYPERLINK()", CsvSafety::escapeFormula(' =HYPERLINK()'));
    $this->assertSame('Amira Nasser', CsvSafety::escapeFormula('Amira Nasser'));
    $this->assertSame('', CsvSafety::escapeFormula(''));
  }

}
