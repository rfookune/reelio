<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CspReportController extends Controller
{
    /**
     * Report fields kept in the log; the rest of a report is dropped.
     *
     * @var list<string>
     */
    private const KEPT_FIELDS = [
        'effective-directive',
        'violated-directive',
        'document-uri',
        'referrer',
        'blocked-uri',
        'source-file',
        'line-number',
        'column-number',
    ];

    /**
     * Fields that may carry a query string, which is stripped.
     *
     * @var list<string>
     */
    private const URL_FIELDS = ['document-uri', 'referrer', 'blocked-uri', 'source-file'];

    /**
     * Log a browser CSP violation report. Always answers 204, so a bad or
     * unwanted report tells the sender nothing.
     */
    public function __invoke(Request $request): Response
    {
        $report = json_decode($request->getContent(), true)['csp-report'] ?? null;

        if (is_array($report)) {
            $kept = [];

            foreach (self::KEPT_FIELDS as $field) {
                if (! isset($report[$field]) || ! is_scalar($report[$field])) {
                    continue;
                }

                $kept[$field] = in_array($field, self::URL_FIELDS, true)
                    ? $this->withoutQuery((string) $report[$field])
                    : $report[$field];
            }

            if ($kept !== []) {
                Log::warning('CSP violation reported', $kept);
            }
        }

        return response()->noContent();
    }

    /**
     * A URL without its query string or fragment.
     */
    private function withoutQuery(string $url): string
    {
        return Str::before(Str::before($url, '?'), '#');
    }
}
