<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Read only explicitly marked business tables, after the view's masking rules. */
class BusinessTableService
{
    public function extract(string $html, string $title, string $currency = 'Rs'): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $tables = [];
        $summary = [];
        foreach ($xpath->query('//*[@data-export-summary]') as $group) {
            foreach ($xpath->query('./div', $group) as $card) {
                $label = $xpath->query('./*[1]', $card)->item(0);
                $value = $xpath->query('./*[2]', $card)->item(0);
                if ($label instanceof DOMElement && $value instanceof DOMElement) {
                    $display = $this->text($value, $xpath);
                    $number = preg_replace('/^'.preg_quote(trim($currency), '/').'\\s*/u', '', $display);
                    $summary[] = [$this->text($label, $xpath), preg_match('/^[-+]?\\d[\\d,]*(?:\\.\\d+)?$/', $number) ? (float) str_replace(',', '', $number) : $display];
                }
            }
        }
        if ($summary) {
            $tables[] = ['title' => 'Summary', 'headers' => ['Metric', 'Value'], 'rows' => $summary];
        }
        foreach ($xpath->query('//table[@data-export-table]') as $table) {
            $headerCells = $xpath->query('./thead/tr[1]/*', $table);
            if (! $headerCells->length) {
                continue;
            }
            $headers = [];
            $included = [];
            foreach ($headerCells as $index => $cell) {
                $heading = $this->text($cell, $xpath);
                if ($heading === '' || preg_match('/^(actions?|image|select)$/i', $heading)) {
                    continue;
                }
                $included[] = $index;
                $headers[] = $heading;
            }
            $rows = [];
            foreach ($xpath->query('./tbody/tr | ./tfoot/tr', $table) as $row) {
                $cells = [];
                foreach ($xpath->query('./td | ./th', $row) as $cell) {
                    $cells[] = $this->text($cell, $xpath);
                    for ($i = 1; $i < max(1, (int) $cell->getAttribute('colspan')); $i++) {
                        $cells[] = '';
                    }
                }
                if (! $cells) {
                    continue;
                }
                $values = [];
                foreach ($included as $i => $column) {
                    $value = $cells[$column] ?? '';
                    // Identifier columns always remain text, including leading zeros.
                    $numericHeading = preg_match('/amount|price|cost|balance|total|quantity|\bqty\b|stock|debit|credit|money|value|^cash$|^cheque$|bank \/ other|^in$|^out$|^paid\b|^due\b|^invoices$|^transactions$|^entries$|profit|tax|vat/i', $headers[$i]);
                    $number = preg_replace('/^'.preg_quote(trim($currency), '/').'\s*/u', '', $value);
                    if ($numericHeading && preg_match('/^[-+]?\d[\d,]*(?:\.\d+)?$/', $number)) {
                        $value = (float) str_replace(',', '', $number);
                    }
                    $values[] = $value;
                }
                $rows[] = $values;
            }
            $tables[] = ['title' => $table->getAttribute('data-export-title') ?: $title, 'headers' => $headers, 'rows' => $rows];
        }

        return $tables;
    }

    public function dates(array $tables): array
    {
        $dates = [];
        foreach ($tables as $table) {
            foreach ($table['headers'] as $column => $heading) {
                if (! preg_match('/^(?:pass |sale |purchase )?date$/i', $heading)) {
                    continue;
                }
                foreach ($table['rows'] as $row) {
                    $value = (string) ($row[$column] ?? '');
                    foreach (['Y-m-d' => '/^(\\d{4}-\\d{2}-\\d{2})/', 'M d, Y' => '/^([A-Za-z]{3} \\d{1,2}, \\d{4})/', 'd M Y' => '/^(\\d{1,2} [A-Za-z]{3} \\d{4})/'] as $format => $pattern) {
                        if (preg_match($pattern, $value, $match)) {
                            try {
                                $dates[] = \Carbon\Carbon::createFromFormat($format, $match[1])->toDateString();
                            } catch (\Exception) {
                                // Ignore non-date summary/empty rows.
                            }
                            break;
                        }
                    }
                }
            }
        }

        return $dates ? [min($dates), max($dates)] : [null, null];
    }

    private function text(DOMElement $element, DOMXPath $xpath): string
    {
        $clone = $element->cloneNode(true);
        foreach ($xpath->query('.//script | .//style | .//button | .//form | .//input | .//select | .//*[@data-export-ignore]', $clone) as $remove) {
            $remove->parentNode?->removeChild($remove);
        }
        // Keep adjacent detail labels and status badges readable in exports.
        foreach ($xpath->query('.//br | .//p | .//div | .//span', $clone) as $node) {
            $node->appendChild($node->ownerDocument->createTextNode(' '));
        }

        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $clone->textContent)));
    }
}
