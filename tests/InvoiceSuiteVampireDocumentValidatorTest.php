<?php

declare(strict_types=1);

namespace InvoicePress\VampireInvoiceSuite\Tests;

use EinvoiceValidator\Pipeline\ScenarioResolver;
use EinvoiceValidator\Result\Flag;
use EinvoiceValidator\Validator;
use horstoeko\invoicesuite\InvoiceSuiteDocumentReader;
use horstoeko\invoicesuite\InvoiceSuitePdfDocumentReader;
use horstoeko\invoicesuite\validators\abstracts\InvoiceSuiteAbstractDocumentValidator;
use horstoeko\invoicesuite\validators\InvoiceSuiteKositDocumentValidator;
use InvoicePress\VampireInvoiceSuite\InvoiceSuiteVampireDocumentValidator;
use InvoicePress\VampireInvoiceSuite\VampireRun;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class InvoiceSuiteVampireDocumentValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/';

    public function testItIsAValidatorOfInvoiceSuite(): void
    {
        self::assertInstanceOf(InvoiceSuiteAbstractDocumentValidator::class, InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . 'xrechnung-ubl-valid.xml'));
    }

    /** @return array<string, array{string, bool}> */
    public static function fixtures(): array
    {
        return [
            'XRechnung UBL, valid' => ['xrechnung-ubl-valid.xml', true],
            'XRechnung CII, valid' => ['xrechnung-cii-valid.xml', true],
            'XRechnung CII, line total wrong' => ['xrechnung-cii-invalid.xml', false],
            'XRechnung UBL, invalid' => ['xrechnung-ubl-invalid.xml', false],
            'Factur-X EN 16931 PDF, valid' => ['facturx-en16931.pdf', true],
        ];
    }

    #[DataProvider('fixtures')]
    public function testTheVerdictOfEachFixture(string $file, bool $valid): void
    {
        $validator = InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . $file)->validate();

        self::assertFalse($validator->hasInternalErrorMessagesInMessageBag(), self::texts($validator->getInternalErrorMessagesInMessageBag()));
        self::assertSame(!$valid, $validator->hasErrorMessagesInMessageBag(), self::texts($validator->getErrorMessagesInMessageBag()));
    }

    /** The main rule: the verdict of the package equals the verdict of VAMPIRE PHP called directly, on all four entry points. */
    #[DataProvider('fixtures')]
    public function testTheVerdictEqualsADirectCallOfVampire(string $file, bool $valid): void
    {
        $direct = self::directValidator();
        $content = (string) file_get_contents(self::FIXTURES . $file);
        $reader = str_ends_with($file, '.pdf')
            ? InvoiceSuitePdfDocumentReader::createFromContent($content)->getDocumentReader()
            : InvoiceSuiteDocumentReader::createFromContent($content);

        $validators = [
            'file' => InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . $file),
            'content' => InvoiceSuiteVampireDocumentValidator::createFromContent($content),
            'reader' => InvoiceSuiteVampireDocumentValidator::createFromDocumentReader($reader),
            'builder' => InvoiceSuiteVampireDocumentValidator::createFromDocumentBuilder($reader->copyToBuilder()),
        ];

        foreach ($validators as $entry => $validator) {
            // ⚠️ The builder writes a new document from the read one, so it is compared with VAMPIRE on THAT content.
            $result = $direct->validateString($validator->getOriginalDocumentContent());
            $validator->validate();

            self::assertSame(!$result->isAccepted(), $validator->hasErrorMessagesInMessageBag(), $entry);
            self::assertSame(count(array_filter($result->messages(), static fn ($m) => $m->flag === Flag::Fatal)), $validator->countErrorMessagesInMessageBag(), $entry);
        }
    }

    public function testEveryMessageCarriesTheFieldsOfVampire(): void
    {
        $messages = InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . 'xrechnung-cii-invalid.xml')->validate()->getErrorMessagesInMessageBag();

        self::assertNotEmpty($messages);

        $data = $messages[0]->getMessageAdditionalData();

        self::assertSame('BR-CO-10', $data['rule']);
        self::assertContains('BT-106', $data['bt']);
        self::assertStringStartsWith('/rsm:CrossIndustryInvoice', (string) $data['location']);
        self::assertStringContainsString('[BR-CO-10]', $messages[0]->getMessageContent());
    }

    public function testAPdfWithoutInvoiceIsRefusedLikeByTheOtherValidators(): void
    {
        $this->expectException(\Throwable::class);

        InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . 'no-xml.pdf');
    }

    /** @return array<string, array{string}> */
    public static function documentsWithoutVerdict(): array
    {
        return [
            'empty' => [''],
            'not XML' => ['this is not xml <'],
            'no matching rule set' => ['<?xml version="1.0"?><FatturaElettronica xmlns="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2"/>'],
        ];
    }

    /**
     * A run without verdict becomes INTERNALERROR. invoicesuite refuses most such documents already in createFrom…(); the
     * run itself is tested here, because it is what doValidate() hands to the message bag.
     */
    #[DataProvider('documentsWithoutVerdict')]
    public function testARunWithoutVerdictIsAProcessError(string $document): void
    {
        $run = VampireRun::run($document, false);

        self::assertFalse($run->executed);
        self::assertNotEmpty($run->processErrors);
        self::assertSame([], $run->errors);
        self::assertSame([], $run->findings);
    }

    public function testThePositiveReportIsOffByDefault(): void
    {
        $file = self::FIXTURES . 'xrechnung-ubl-valid.xml';

        self::assertNull(InvoiceSuiteVampireDocumentValidator::createFromFile($file)->validate()->getPositiveReport());

        $report = InvoiceSuiteVampireDocumentValidator::createFromFile($file)->enablePositiveReport()->validate()->getPositiveReport();

        self::assertIsArray($report);
        self::assertSame(hash_file('sha256', $file), $report['document']['sha256'] ?? null);
    }

    public function testTheKositReportNamesVampire(): void
    {
        $report = InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . 'xrechnung-ubl-valid.xml')->validate()->getKositReport();

        self::assertStringContainsString('<rep:report', $report);
        self::assertStringContainsString('valid="true"', $report);
        self::assertStringContainsString('vampire-php', $report);
    }

    /** Code written for InvoiceSuiteKositDocumentValidator keeps running after the class name is changed. */
    public function testEveryPublicMethodOfTheKositValidatorExists(): void
    {
        $ours = new ReflectionClass(InvoiceSuiteVampireDocumentValidator::class);

        foreach ((new ReflectionClass(InvoiceSuiteKositDocumentValidator::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            self::assertTrue($ours->hasMethod($method->getName()), $method->getName());
        }

        $validator = InvoiceSuiteVampireDocumentValidator::createFromFile(self::FIXTURES . 'xrechnung-ubl-valid.xml')
            ->setBaseDirectory(sys_get_temp_dir())
            ->activateRemoteValidation('localhost', 8080)
            ->validate();

        self::assertFalse($validator->hasErrorOrInternalErrorMessagesInMessageBag());
    }

    /** @param array<int, object> $messages */
    private static function texts(array $messages): string
    {
        return implode("\n", array_map(static fn ($m) => $m->getMessageContent(), $messages));
    }

    private static function directValidator(): Validator
    {
        $base = dirname((string) (new ReflectionClass(Validator::class))->getFileName(), 2);

        return new Validator(scenarioResolver: ScenarioResolver::fromJsonFile($base . '/rulesets/scenarios.json'), baseDir: $base, lang: 'en');
    }
}
