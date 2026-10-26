<?php

declare(strict_types=1);

/**
 * This file is part of invoicepress/vampire-invoicesuite.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace InvoicePress\VampireInvoiceSuite;

use EinvoiceValidator\Result\Result;
use horstoeko\invoicesuite\InvoiceSuitePdfDocumentReader;
use horstoeko\invoicesuite\utils\InvoiceSuiteMessageSeverity;
use horstoeko\invoicesuite\validators\abstracts\InvoiceSuiteAbstractDocumentValidator;

/**
 * A document validator for horstoeko/invoicesuite that checks with VAMPIRE PHP, in pure PHP and without Java.
 *
 * It is one of the validators of invoicesuite: it extends `InvoiceSuiteAbstractDocumentValidator` like
 * `InvoiceSuiteKositDocumentValidator`, so it has the same four entry points `createFromFile()`, `createFromContent()`,
 * `createFromDocumentReader()` and `createFromDocumentBuilder()`, the same `validate()` and the same message bag with the
 * severities INFO, WARNING, ERROR and INTERNALERROR.
 *
 * ```php
 * $validator = InvoiceSuiteVampireDocumentValidator::createFromFile('invoice.xml')->validate();
 * $validator->getErrorMessagesInMessageBag();
 * ```
 *
 * ⚠️ THE BASE CLASS IS THE DOCKING POINT, AND IT IS NOT DOCUMENTED AS ONE. invoicesuite is at 0.0.x, its abstract validator is
 * not marked `@internal` and not described for third parties either. Every new version of invoicesuite is therefore tested
 * against this class before the version constraint in composer.json is widened.
 *
 * ⚠️ A RUN WITHOUT VERDICT IS AN INTERNALERROR. An empty or unreadable document, a document no rule set of VAMPIRE matches
 * ( invoicesuite reads formats VAMPIRE does not check, FatturaPA for example ) and missing rule sets end as INTERNALERROR,
 * never as ERROR and never as a valid document. `hasErrorOrInternalErrorMessagesInMessageBag()` is the one question that
 * covers both.
 *
 * ⚠️ EVERY MESSAGE CARRIES THE FIELDS OF VAMPIRE AS ADDITIONAL DATA: `engine`, `rule`, `rulesets`, `stage`, `location`
 * ( the XPath of the finding ), `line` and `bt` ( the business terms of EN 16931 ). The message text keeps the shape
 * "<rule set>: <message>" of InvoiceSuiteKositDocumentValidator.
 */
class InvoiceSuiteVampireDocumentValidator extends InvoiceSuiteAbstractDocumentValidator
{
    /** Whether the positive validation report is produced; off by default. */
    private bool $positiveReport = false;

    private ?VampireRun $run = null;

    /**
     * Create a validator from the content of an XML file or of a ZUGFeRD / Factur-X PDF.
     *
     * ⚠️ PDF CONTENT GOES THROUGH InvoiceSuitePdfDocumentReader. The base class looks for a format provider that reads the
     * bytes as they are and finds none for a PDF ( InvoiceSuiteFormatProviderNotFoundException, measured with invoicesuite
     * 0.0.37 ). The other validators of invoicesuite behave like that; this one takes the embedded XML out first, so
     * `createFromFile()` accepts a PDF as well.
     */
    public static function createFromContent(
        string $fromDocumentContent
    ): static {
        if (str_starts_with(ltrim(substr($fromDocumentContent, 0, 1024)), '%PDF-')) {
            return static::createFromDocumentReader(
                InvoiceSuitePdfDocumentReader::createFromContent($fromDocumentContent)->getDocumentReader()
            );
        }

        return parent::createFromContent($fromDocumentContent);
    }

    /**
     * Switch the positive validation report on: VAMPIRE then lists every rule of every rule set it used, with its result.
     * Read it with `getPositiveReport()` or as part of `getJsonReport()`.
     */
    public function enablePositiveReport(): static
    {
        $this->positiveReport = true;

        return $this;
    }

    public function disablePositiveReport(): static
    {
        $this->positiveReport = false;

        return $this;
    }

    /** The report in the format of the KoSIT validator, naming VAMPIRE PHP as the engine. Empty before `validate()`. */
    public function getKositReport(): string
    {
        return $this->run?->kositReport() ?? '';
    }

    /** The full report of VAMPIRE as JSON, with the positive validation report if switched on. Empty before `validate()`. */
    public function getJsonReport(): string
    {
        return $this->run?->jsonReport() ?? '';
    }

    /**
     * The positive validation report, or null if it was not switched on or no verdict was reached.
     *
     * @return null|array<string, mixed>
     */
    public function getPositiveReport(): ?array
    {
        return $this->run?->positiveReport();
    }

    /** The result object of VAMPIRE PHP, for everything the message bag leaves out. */
    public function getResult(): ?Result
    {
        return $this->run?->result;
    }

    protected function doValidate(): static
    {
        $this->clearMessageBag();

        $this->run = VampireRun::run($this->getOriginalDocumentContent(), $this->positiveReport);

        foreach ($this->run->processErrors as $processError) {
            $this->addMessageToMessageBag($processError, InvoiceSuiteMessageSeverity::INTERNALERROR, null, ['engine' => 'VAMPIRE PHP']);
        }

        foreach ($this->run->findings as $finding) {
            $severity = match ($finding['kind']) {
                'error' => InvoiceSuiteMessageSeverity::ERROR,
                'warning' => InvoiceSuiteMessageSeverity::WARNING,
                default => InvoiceSuiteMessageSeverity::INFO,
            };

            $this->addMessageToMessageBag($finding['text'], $severity, null, $finding['data']);
        }

        return $this;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Accepted for compatibility with InvoiceSuiteKositDocumentValidator. VAMPIRE needs none of this; every method returns
    // the instance, so that code written for the KoSIT validator runs after changing the class name.
    // ---------------------------------------------------------------------------------------------------------------

    public function setBaseDirectory(string $newBaseDirectory): static
    {
        return $this;
    }

    public function setValidatorDownloadUrl(string $newValidatorDownloadUrl): static
    {
        return $this;
    }

    public function setValidatorScenarioDownloadUrl(string $newValidatorScenarioDownloadUrl): static
    {
        return $this;
    }

    public function setValidatorAppZipFilename(string $newValidatorAppZipFilename): static
    {
        return $this;
    }

    public function setValidatorScenarioZipFilename(string $newValidatorScenarioZipFilename): static
    {
        return $this;
    }

    public function setValidatorAppJarFilename(string $newValidatorAppJarFilename): static
    {
        return $this;
    }

    public function setValidatorAppScenarioFilename(string $newValidatorAppScenarioFilename): static
    {
        return $this;
    }

    public function disableCleanup(): static
    {
        return $this;
    }

    public function enableCleanup(): static
    {
        return $this;
    }

    public function disableRemoteMode(): static
    {
        return $this;
    }

    public function enableRemoteMode(): static
    {
        return $this;
    }

    public function setRemoteModeHost(string $remoteModeHost): static
    {
        return $this;
    }

    public function setRemoteModePort(int $remoteModePort): static
    {
        return $this;
    }

    /** Always empty: VAMPIRE talks to no host. */
    public function getRemoteModeUrl(): string
    {
        return '';
    }

    public function activateRemoteValidation(string $remoteModeHost, int $remoteModePort): static
    {
        return $this;
    }

    public function deactivateRemoteValidation(): static
    {
        return $this;
    }
}
