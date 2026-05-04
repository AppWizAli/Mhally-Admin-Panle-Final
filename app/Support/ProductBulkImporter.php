<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ProductBulkImporter
{
    private const REQUIRED_HEADERS = [
        'product_name',
        'category',
        'original_price',
        'stock_quantity',
    ];

    private const HEADER_ALIASES = [
        'product_name' => ['productname', 'product', 'name', 'itemname', 'اسم المنتج', 'المنتج'],
        'category' => ['category', 'categoryname', 'التصنيف', 'الفئة'],
        'original_price' => ['originalprice', 'price', 'baseprice', 'regularprice', 'السعر الأصلي', 'السعر'],
        'offer_price' => ['offerprice', 'saleprice', 'discountprice', 'سعر العرض'],
        'max_offer_quantity' => ['maxofferquantity', 'maximumofferquantity', 'maximumquantity', 'maxquantity', 'offerlimit', 'quantitylimit', 'حد العرض', 'الحد الأقصى للعرض'],
        'stock_quantity' => ['stockquantity', 'stock', 'quantity', 'availablequantity', 'الكمية', 'كمية المخزون', 'المخزون'],
    ];

    public static function templateHeaders(): array
    {
        return [
            'Product Name',
            'Category',
            'Original Price',
            'Offer Price',
            'Max Offer Quantity',
            'Stock Quantity',
        ];
    }

    public static function importFile(string $path, int $supplierId, ?string $extensionOverride = null): array
    {
        $extension = strtolower($extensionOverride ?: pathinfo($path, PATHINFO_EXTENSION));
        $rows = match ($extension) {
            'csv', 'txt' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            default => throw new RuntimeException('Upload a CSV or XLSX file.'),
        };

        return (new self())->importRows($rows, $supplierId);
    }

    public function importRows(array $rows, int $supplierId): array
    {
        if ($supplierId <= 0 || !DB::table('suppliers')->where('id', $supplierId)->exists()) {
            throw new RuntimeException('Select a valid supplier before importing products.');
        }

        if (count($rows) < 2) {
            throw new RuntimeException('The uploaded file must include a header row and at least one product row.');
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

            DB::transaction(function () use ($payload, $supplierId, &$summary) {
                $categoryId = $this->findOrCreateCategory($payload['category']);
                $catalogId = $this->findOrCreateCatalogProduct($payload['product_name'], $categoryId);
                $existing = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where('catalog_product_id', $catalogId)
                    ->first();

                $listingPayload = [
                    'catalog_product_id' => $catalogId,
                    'supplier_id' => $supplierId,
                    'sku' => $existing->sku ?? 'SKU-' . strtoupper(Str::random(8)),
                    'price' => $payload['original_price'],
                    'stock_quantity' => $payload['stock_quantity'],
                    'min_order_qty' => 1,
                    'min_order_amount' => 0,
                    'delivery_time' => null,
                    'status' => 'active',
                    'is_featured' => 0,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('supplier_products')->where('id', $existing->id)->update($listingPayload);
                    $listingId = (int) $existing->id;
                    $summary['updated_count']++;
                } else {
                    $listingPayload['created_at'] = now();
                    $listingId = (int) DB::table('supplier_products')->insertGetId($listingPayload);
                    $summary['created_count']++;
                }

                $this->upsertOffer($payload, $supplierId, $listingId, $catalogId);
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
                $reference = (string) $cell['r'];
                $columnIndex = self::columnIndex($reference);
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

    private function payloadFromRow(array $row, array $headerMap): array
    {
        $value = fn ($field) => array_key_exists($field, $headerMap) ? trim((string) ($row[$headerMap[$field]] ?? '')) : '';

        return [
            'product_name' => $value('product_name'),
            'category' => $value('category'),
            'original_price' => $this->number($value('original_price')),
            'offer_price' => $this->number($value('offer_price')),
            'max_offer_quantity' => $this->integer($value('max_offer_quantity')),
            'stock_quantity' => $this->integer($value('stock_quantity')),
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
        if ($payload['original_price'] === null || $payload['original_price'] <= 0) {
            $errors['original_price'] = 'Original price must be greater than 0.';
        }
        if ($payload['stock_quantity'] === null || $payload['stock_quantity'] < 0) {
            $errors['stock_quantity'] = 'Stock quantity must be 0 or more.';
        }
        if ($payload['offer_price'] !== null && $payload['offer_price'] < 0) {
            $errors['offer_price'] = 'Offer price must be 0 or more.';
        }
        if (($payload['offer_price'] ?? 0) > 0) {
            if ($payload['original_price'] !== null && $payload['offer_price'] >= $payload['original_price']) {
                $errors['offer_price'] = 'Offer price must be lower than original price.';
            }
            if ($payload['max_offer_quantity'] === null || $payload['max_offer_quantity'] <= 0) {
                $errors['max_offer_quantity'] = 'Max offer quantity is required when offer price is used.';
            }
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

    private function findOrCreateCatalogProduct(string $name, int $categoryId): int
    {
        $existing = DB::table('catalog_products')
            ->where('category_id', $categoryId)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('catalog_products')->insertGetId([
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $this->uniqueSlug('catalog_products', Str::slug($name)),
            'emoji' => null,
            'description' => null,
            'packaging' => null,
            'unit_type' => 'unit',
            'image_url' => null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertOffer(array $payload, int $supplierId, int $listingId, int $catalogId): void
    {
        $offerPrice = (float) ($payload['offer_price'] ?? 0);
        if ($offerPrice <= 0) {
            return;
        }

        $existing = DB::table('offers')
            ->where('supplier_id', $supplierId)
            ->where('supplier_product_id', $listingId)
            ->whereIn('status', ['active', 'draft'])
            ->orderByDesc('id')
            ->first();

        $offerPayload = [
            'title' => $payload['product_name'],
            'description' => 'Bulk upload offer',
            'badge_label' => 'Special Offer',
            'discount_label' => number_format($offerPrice, 2) . ' ' . $this->defaultCurrency(),
            'image_url' => null,
            'supplier_id' => $supplierId,
            'supplier_product_id' => $listingId,
            'catalog_product_id' => $catalogId,
            'offer_price' => $offerPrice,
            'maximum_quantity' => (int) $payload['max_offer_quantity'],
            'city' => optional(DB::table('suppliers')->where('id', $supplierId)->first())->city,
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('offers')->where('id', $existing->id)->update($offerPayload);
            return;
        }

        $offerPayload['created_at'] = now();
        DB::table('offers')->insert($offerPayload);
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
        return preg_replace('/[^\p{Arabic}a-z0-9]+/u', '', Str::lower($header)) ?: '';
    }

    private function label(string $field): string
    {
        return Str::headline(str_replace('_', ' ', $field));
    }

    private function number(string $value): ?float
    {
        $normalized = preg_replace('/[^\d.\-]/', '', $value);
        return $normalized === '' || !is_numeric($normalized) ? null : (float) $normalized;
    }

    private function integer(string $value): ?int
    {
        $normalized = preg_replace('/[^\d\-]/', '', $value);
        return $normalized === '' || !is_numeric($normalized) ? null : (int) $normalized;
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

    private function defaultCurrency(): string
    {
        return (string) (DB::table('settings')->where('setting_key', 'default_currency')->value('setting_value') ?: 'PKR');
    }
}
