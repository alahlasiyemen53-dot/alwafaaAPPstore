<?php

declare(strict_types=1);

return [
    'recently_viewed_limit' => (int) env('CUSTOMER_RECENTLY_VIEWED_LIMIT', 20),
    'search_history_limit' => (int) env('CUSTOMER_SEARCH_HISTORY_LIMIT', 15),
];
