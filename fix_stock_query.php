<?php

$file = 'app/Http/Controllers/ReportController.php';
$content = file_get_contents($file);

// Replace stockBaseQuery signature and add logic
$search = 'private function stockBaseQuery(
        ?array $categoryIds,
        ?int $brandId,
        bool $lowStockOnly,
        ?string $search = null,
        array $hiddenProductIds = []
    )
    {
        $query = Product::with([\'category\', \'categories\', \'brand\', \'brands\', \'unit\', \'saleItems\', \'purchaseItems\']);';

$replace = 'private function stockBaseQuery(
        ?array $categoryIds,
        ?int $brandId,
        bool $lowStockOnly,
        ?string $search = null,
        array $hiddenProductIds = [],
        ?int $storeId = null
    )
    {
        $query = Product::with([\'category\', \'categories\', \'brand\', \'brands\', \'unit\', \'saleItems\', \'purchaseItems\']);
        if ($storeId) {
            $query->with([\'storeStocks\' => function($q) use ($storeId) {
                $q->where(\'store_id\', $storeId);
            }]);
            // We join or filter later, but if we need to show only products in this store:
            $query->whereHas(\'storeStocks\', function($q) use ($storeId) {
                $q->where(\'store_id\', $storeId)->where(\'quantity\', \'>\', 0);
            });
        }';

$content = str_replace($search, $replace, $content);

// Also need to update the calls to stockBaseQuery in stock, stockPdf, stockCsv
$searchCall = 'this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds)';
$replaceCall = 'this->stockBaseQuery($categoryIds, $selectedBrandId, $lowStockOnly, $search, $hiddenProductIds, request(\'store_id\'))';

$content = str_replace($searchCall, $replaceCall, $content);

file_put_contents($file, $content);
echo "Done stock base query fix\n";
