<?php

declare(strict_types=1);

namespace InvoicePress\VampireInvoiceSuite\Tests;

use EinvoiceValidator\Pipeline\ScenarioResolver;
use EinvoiceValidator\Result\Flag;
use EinvoiceValidator\Result\Stage;
use EinvoiceValidator\Result\StageStatus;
use EinvoiceValidator\Validator;
use InvoicePress\VampireInvoiceSuite\InvoiceSuiteVampireDocumentValidator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

/**
 * The verdict of the package against a direct call of VAMPIRE PHP, over a folder of invoices.
 *
 * Runs only when the environment variable VAMPIRE_EXAMPLES names a folder; the invoices are not part of this repository.
 * A file that invoicesuite itself cannot read ( createFromFile() throws ) is counted apart and not compared: the other
 * validators of invoicesuite refuse it in the same way.
 */
final class CorpusParityTest extends TestCase
{
    public function testEveryReadableFileGetsTheVerdictOfADirectCall(): void
    {
        $dir = (string) getenv('VAMPIRE_EXAMPLES');

        if ($dir === '' || !is_dir($dir)) {
            self::markTestSkipped('Set VAMPIRE_EXAMPLES to a folder of invoices to run this test.');
        }

        $base = dirname((string) (new ReflectionClass(Validator::class))->getFileName(), 2);
        $direct = new Validator(scenarioResolver: ScenarioResolver::fromJsonFile($base . '/rulesets/scenarios.json'), baseDir: $base, lang: 'en');
        $checked = $refused = 0;
        $differences = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!preg_match('/\.(xml|pdf)$/i', $file->getFilename())) {
                continue;
            }

            try {
                $validator = InvoiceSuiteVampireDocumentValidator::createFromFile($file->getPathname());
            } catch (Throwable) {
                $refused++;
                continue;
            }

            $checked++;
            $result = $direct->validateString($validator->getOriginalDocumentContent());
            $expected = 'none';

            if ($result->hasVerdict() && $result->scenarioName() !== null && $result->stageStatus(Stage::Wellformed) !== StageStatus::Failed) {
                $expected = count(array_filter($result->messages(), static fn ($m) => $m->flag === Flag::Fatal));
            }

            $validator->validate();
            $actual = $validator->hasInternalErrorMessagesInMessageBag() ? 'none' : $validator->countErrorMessagesInMessageBag();

            if ($expected !== $actual) {
                $differences[] = sprintf('%s: direct %s, package %s', $file->getPathname(), $expected, $actual);
            }
        }

        fwrite(STDERR, sprintf("\nCorpus: %d files checked, %d refused by invoicesuite, %d differences\n", $checked, $refused, count($differences)));

        self::assertSame([], $differences);
    }
}
