<?php

/**
 * Validate an XML invoice or a ZUGFeRD / Factur-X PDF and save the positive validation report.
 *
 *   php examples/validate.php invoice.pdf [report.json]
 */

declare(strict_types=1);

use InvoicePress\VampireInvoiceSuite\InvoiceSuiteVampireDocumentValidator;

require __DIR__ . '/../vendor/autoload.php';

$file = $argv[1] ?? exit("Usage: php examples/validate.php <invoice.xml|invoice.pdf> [report.json]\n");

// Check the invoice with VAMPIRE. These three lines are all it takes.
$validator = InvoiceSuiteVampireDocumentValidator::createFromFile($file)->enablePositiveReport()->validate();
foreach ($validator->getErrorOrInternalErrorMessagesInMessageBag() as $message) {
    echo $message->getMessageContent(), "\n";
}
file_put_contents($argv[2] ?? 'report.json', $validator->getJsonReport());

echo $validator->hasErrorOrInternalErrorMessagesInMessageBag() ? "Invalid or not checked.\n" : "Valid.\n";
exit($validator->hasErrorOrInternalErrorMessagesInMessageBag() ? 1 : 0);
