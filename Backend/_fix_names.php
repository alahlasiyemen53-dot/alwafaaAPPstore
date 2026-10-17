<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$map = [
    'demo-perfume-01' => 'Lamma Golden Perfume',
    'demo-watch-01' => 'Elegant Wrist Watch',
    'demo-earbud-01' => 'Wireless Earbuds',
    'demo-candle-01' => 'Luxury Scented Candle',
    'demo-scarf-01' => 'Silk Scarf',
];

foreach (App\Models\Product::query()->whereIn('slug', array_keys($map))->get() as $product) {
    $product->update(['name_en' => $map[$product->slug]]);
    echo "{$product->slug} -> {$product->name_en}\n";
}
