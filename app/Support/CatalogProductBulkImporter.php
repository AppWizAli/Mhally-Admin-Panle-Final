<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CatalogProductBulkImporter
{
    private const REQUIRED_HEADERS = ['product_name', 'category'];

    private const HEADER_ALIASES = [
        'product_name' => ['productname', 'product', 'name', 'itemname'],
        'category' => ['category', 'categoryname'],
        'emoji' => ['emoji', 'emojiimage', 'icon'],
        'description' => ['description', 'details'],
        'packaging' => ['packaging', 'pack', 'size'],
        'unit_type' => ['unittype', 'unit', 'unitlabel'],
        'image_url' => ['imageurl', 'image', 'productimage', 'photourl'],
        'status' => ['status'],
    ];

    public static function templateHeaders(): array
    {
        return [
            'Product Name',
            'Category',
            'Emoji',
            'Description',
            'Packaging',
            'Unit Type',
            'Image URL',
            'Status',
        ];
    }

    public static function importFile(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new RuntimeException('Upload a CSV or XLSX file.'),
        };

        return (new self())->importRows($rows);
    }

    public function importRows(array $rows): array
    {
        if (count($rows) < 2) {
            throw new RuntimeException('The uploaded file must include a header row and at least one catalog product row.');
        }

        $headerMap = $this->headerMap($rows[0]);
        $missing = array_values(array_filter(self::REQUIRED_HEADERS, fn ($field) => !array_key_exists($field, $headerMap)));
        if (!empty($missing)) {
            throw new RuntimeException('Missing required columns: ' . implode(', ', array_map([$this, 'label'], $missing)) . '.');
        }

        $summary = [
            'total_rows' => max(0, count($rows) - 1),
            'imported_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'error_count' => 0,
            'errors' => [],
        ];

        foreach (array_slice($rows, 1) as $offset => $row) {
            $rowNumber = $offset + 2;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $payload = $this->payloadFromRow($row, $headerMap);
            $errors = $this->validatePayload($payload);
            if (!empty($errors)) {
                foreach ($errors as $field => $issue) {
                    $summary['errors'][] = [
                        'row' => $rowNumber,
                        'field' => $this->label($field),
                        'issue' => $issue,
                    ];
                }
                $summary['error_count'] += count($errors);
                continue;
            }

            DB::transaction(function () use ($payload, &$summary) {
                $categoryId = $this->findOrCreateCategory($payload['category']);
                $existing = DB::table('catalog_products')
                    ->where('category_id', $categoryId)
                    ->whereRaw('LOWER(name) = ?', [Str::lower($payload['product_name'])])
                    ->first();

                $catalogPayload = [
                    'category_id' => $categoryId,
                    'name' => $payload['product_name'],
                    'slug' => $existing->slug ?? $this->uniqueSlug('catalog_products', Str::slug($payload['product_name'])),
                    'emoji' => $payload['emoji'] ?: null,
                    'description' => $payload['description'] ?: null,
                    'packaging' => $payload['packaging'] ?: null,
                    'unit_type' => $payload['unit_type'] ?: 'unit',
                    'image_url' => $payload['image_url'] ?: null,
                    'status' => $payload['status'] ?: 'active',
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('catalog_products')->where('id', $existing->id)->update($catalogPayload);
                    $summary['updated_count']++;
                } else {
                    $catalogPayload['created_at'] = now();
                    DB::table('catalog_products')->insert($catalogPayload);
                    $summary['created_count']++;
                }

                $summary['imported_count']++;
            });
        }

        return $summary;
    }

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }
            $rows[] = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        }
        fclose($handle);

        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP Zip extension is required for XLSX imports.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the XLSX file.');
        }

        $sharedStrings = self::xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('The XLSX file must contain a first worksheet.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            throw new RuntimeException('The XLSX worksheet is empty.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $row = [];
            foreach ($rowNode->c as $cell) {
                $columnIndex = self::columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 's') {
                    $value = $sharedStrings[(int) $cell->v] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }

                $row[$columnIndex] = trim($value);
            }
            if (!empty($row)) {
                ksort($row);
                $rows[] = array_values($row);
            }
        }

        return $rows;
    }

    private static function xlsxSharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sharedXml);
        if (!$xml || !isset($xml->si)) {
            return [];
        }

        $strings = [];
        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }
            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    private static function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function payloadFromRow(array $row, array $headerMap): array
    {
        $value = fn ($field) => array_key_exists($field, $headerMap) ? trim((string) ($row[$headerMap[$field]] ?? '')) : '';

        return [
            'product_name' => $value('product_name'),
            'category' => $value('category'),
            'emoji' => $value('emoji'),
            'description' => $value('description'),
            'packaging' => $value('packaging'),
            'unit_type' => $value('unit_type'),
            'image_url' => $value('image_url'),
            'status' => strtolower($value('status') ?: 'active'),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];
        if ($payload['product_name'] === '') {
            $errors['product_name'] = 'Product name is required.';
        }
        if ($payload['category'] === '') {
            $errors['category'] = 'Category is required.';
        }
        if (!in_array($payload['status'], ['active', 'draft', 'archived'], true)) {
            $errors['status'] = 'Status must be active, draft, or archived.';
        }

        return $errors;
    }

    private function findOrCreateCategory(string $name): int
    {
        $existing = DB::table('categories')->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('categories')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueSlug('categories', Str::slug($name)),
            'icon' => null,
            'description' => null,
            'accent_color' => '#2f6bff',
            'sort_order' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function uniqueSlug(string $table, string $slug): string
    {
        $base = $slug ?: Str::lower(Str::random(8));
        $candidate = $base;
        $counter = 2;
        while (DB::table($table)->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $counter++;
        }

        return $candidate;
    }

    private function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower($header)) ?: '';
    }

    private function label(string $field): string
    {
        return Str::headline(str_replace('_', ' ', $field));
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
