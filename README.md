# VAMPIRE for horstoeko/invoicesuite

A document validator for [horstoeko/invoicesuite](https://github.com/horstoeko/invoicesuite) that checks e-invoices against
the official rule sets of EN 16931, XRechnung, Factur-X / ZUGFeRD and Peppol BIS Billing, in pure PHP.

`InvoiceSuiteVampireDocumentValidator` is one of the validators of invoicesuite: it extends
`InvoiceSuiteAbstractDocumentValidator` like `InvoiceSuiteKositDocumentValidator` and is used the same way. It checks with
[VAMPIRE PHP](https://github.com/invoicepress/vampire-php), so it needs no Java, downloads nothing and starts no process.

## Requirements

- PHP 8.2 or newer, as for invoicesuite itself.
- The PHP extensions `dom`, `libxml` and `bcmath`.
- horstoeko/invoicesuite 0.0.37. invoicesuite is at 0.0.x and does not promise a stable interface for its validators; a
  new version is tested against this package before the version constraint is widened.

For horstoeko/zugferd there is [invoicepress/vampire-zugferd](https://github.com/invoicepress/vampire-zugferd). The two
cannot be installed together, because invoicesuite declares a conflict with horstoeko/zugferd.

## Installation

```bash
composer require invoicepress/vampire-invoicesuite
```

## Usage

Create an invoice with the builder, check it, save the positive validation report:

```php
$builder   = InvoiceSuiteDocumentBuilder::createByProviderUniqueId('xrechnungcii'); // ... fill in your invoice
$validator = InvoiceSuiteVampireDocumentValidator::createFromDocumentBuilder($builder)->enablePositiveReport()->validate();
print_r($validator->getErrorOrInternalErrorMessagesInMessageBag());
file_put_contents('report.json', $validator->getJsonReport());
```

`examples/validate.php` does the same for an XML file or a ZUGFeRD / Factur-X PDF:

```bash
php examples/validate.php invoice.pdf report.json
```

### What you can pass in

The four entry points of invoicesuite: `createFromFile()`, `createFromContent()`, `createFromDocumentReader()` and
`createFromDocumentBuilder()`.

Unlike the other validators of invoicesuite, `createFromFile()` and `createFromContent()` also accept a ZUGFeRD /
Factur-X PDF: the embedded XML is taken out with `InvoiceSuitePdfDocumentReader` first. A document invoicesuite cannot
read at all throws from these methods, as with the other validators.

### What you get back

| Severity | Meaning |
|---|---|
| ERROR, WARNING, INFO | the findings of VAMPIRE, as `rule set: [rule] text`, like `InvoiceSuiteKositDocumentValidator` |
| INTERNALERROR | no verdict was reached: empty or unreadable document, no matching rule set ( invoicesuite reads formats VAMPIRE does not check ), missing rule sets |

A document that VAMPIRE could not check is never reported as valid. `hasErrorOrInternalErrorMessagesInMessageBag()` is the
one question that covers both cases.

Every message carries the fields of VAMPIRE as additional data ( `getMessageAdditionalData()` ): `engine`, `rule`,
`rulesets`, `stage`, `location` ( the XPath of the finding ), `line` and `bt` ( the business terms of EN 16931 ).

| Method | Content |
|---|---|
| `getKositReport()` | the report in the XML format of the KoSIT validator, naming VAMPIRE PHP as the engine |
| `getJsonReport()` | the full report of VAMPIRE as JSON |
| `getPositiveReport()` | with `enablePositiveReport()`: every rule of every rule set used, with `passed`, `failed`, `not-applicable` or `not-checked`, the SHA-256 of the checked bytes and the time of the check; off by default |
| `getResult()` | the result object of VAMPIRE PHP |

### Which rules are checked

VAMPIRE reads the specification identifier ( BT-24 ) of the invoice and checks every rule set that belongs to it:
EN 16931 in UBL and CII, XRechnung 3.0.2 in UBL and CII, Factur-X / ZUGFeRD in the profiles MINIMUM, BASIC WL, BASIC,
EN 16931 and EXTENDED, and Peppol BIS Billing 3.0. Which edition of each rule set is used depends on the installed version
of VAMPIRE PHP; `getResult()->rulesets()` names the ones used for a document.

## Migrating from the KoSIT validator

```diff
- $validator = InvoiceSuiteKositDocumentValidator::createFromFile('invoice.xml')->validate();
+ $validator = InvoiceSuiteVampireDocumentValidator::createFromFile('invoice.xml')->validate();
```

The setters of `InvoiceSuiteKositDocumentValidator` for the download, the Java application, the cleanup and the remote
mode exist as well. They are accepted and do nothing, so that existing code runs unchanged.

## Tests

```bash
composer install
vendor/bin/phpunit
```

The tests check XRechnung invoices in UBL and CII and a Factur-X PDF through all four entry points and compare the verdict
with a direct call of VAMPIRE PHP. With the environment variable `VAMPIRE_EXAMPLES` set to a folder of invoices, the test
`CorpusParityTest` does the same for every XML and PDF file below it.

## Licence

This package is published under the MIT licence, like horstoeko/invoicesuite. It contains no code of horstoeko/invoicesuite
and no code of VAMPIRE PHP; Composer installs both as dependencies. VAMPIRE PHP is published under the GNU General Public
License, version 2 or later, and its rule sets carry the licences of their sources ( see `NOTICE` in VAMPIRE PHP ).
horstoeko/invoicesuite itself pulls in mPDF under the GNU General Public License, version 2 only. Which obligations follow
for your application depends on how you distribute it; this note is not legal advice.

## Links

- VAMPIRE in the browser and the documentation: <https://vampire.invoicepress.de>
- VAMPIRE PHP: <https://github.com/invoicepress/vampire-php>
- For horstoeko/zugferd: <https://github.com/invoicepress/vampire-zugferd>
