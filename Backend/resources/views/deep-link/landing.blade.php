<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $title }} | لمّة</title>

    <!-- Instant 0ms App Launch Script (Executes before page renders) -->
    <script>
        (function() {
            var ua = navigator.userAgent || navigator.vendor || window.opera || '';
            var isAndroid = /android/i.test(ua);
            var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;

            if (isAndroid || isIOS) {
                document.documentElement.className += ' app-redirecting';

                var intentUrl = @json($intentUrl ?? null);
                var schemeUrl = @json($schemeUrl);

                if (isAndroid && intentUrl) {
                    window.location.replace(intentUrl);
                } else {
                    window.location.replace(schemeUrl);
                }
            }
        })();
    </script>

    <!-- Social Media & Open Graph Meta Tags -->
    <meta property="og:site_name" content="لمّة | LAMMA Luxury Shopping">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:type" content="product">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-dark: #1A1A24;
            --accent-gold: #C5A880;
            --accent-gold-dark: #A7885F;
            --bg-cream: #FAF8F5;
            --card-bg: #FFFFFF;
            --text-main: #2D2D3A;
            --text-muted: #7E7E8F;
            --border-color: #EAE6DF;
            --radius-lg: 20px;
            --radius-md: 12px;
        }

        html.app-redirecting body {
            opacity: 0;
            pointer-events: none;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg, #FDFBF7 0%, #F5EFEB 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: opacity 0.25s ease-in;
        }

        .container {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 40px rgba(26, 26, 36, 0.08), 0 1px 3px rgba(0, 0, 0, 0.02);
            overflow: hidden;
            text-align: center;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-bar {
            padding: 16px 20px;
            background: var(--primary-dark);
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-text {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--accent-gold);
        }

        .badge-tag {
            background: rgba(197, 168, 128, 0.2);
            color: var(--accent-gold);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(197, 168, 128, 0.3);
        }

        .media-container {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #F9F9FA;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .media-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .content {
            padding: 24px 20px 28px;
            text-align: right;
        }

        .category-name {
            font-size: 0.85rem;
            color: var(--accent-gold-dark);
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .item-title {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.4;
            color: var(--primary-dark);
            margin-bottom: 8px;
        }

        .item-price {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 12px;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }

        .item-price span.currency {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent-gold-dark);
        }

        .item-description {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 24px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .redirect-notice {
            background: #F4EFEA;
            border: 1px solid #E5D9CE;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            font-size: 0.82rem;
            color: var(--text-main);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: right;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--accent-gold);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            flex-shrink: 0;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary-dark);
            color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(26, 26, 36, 0.2);
        }

        .btn-primary:hover, .btn-primary:active {
            background: #0D0D14;
            transform: translateY(-1px);
        }

        .btn-store {
            background: #FFFFFF;
            border: 1.5px solid var(--border-color);
            color: var(--primary-dark);
        }

        .btn-store:hover {
            border-color: var(--accent-gold);
            background: #FDFBF7;
        }

        .btn-store svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .footer {
            margin-top: 16px;
            font-size: 0.78rem;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-bar">
            <span class="logo-text">LAMMA | لمّة</span>
            <span class="badge-tag">{{ $badgeLabel ?? 'تطبيق لمّة' }}</span>
        </div>

        <div class="media-container">
            <img src="{{ $image }}" alt="{{ $title }}" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\' fill=\'%23C5A880\' viewBox=\'0 0 24 24\'><path d=\'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z\'/></svg>'">
        </div>

        <div class="content">
            @if(!empty($categoryName))
                <div class="category-name">{{ $categoryName }}</div>
            @endif

            <h1 class="item-title">{{ $title }}</h1>

            @if(!empty($price))
                <div class="item-price">
                    <span>{{ number_format((float) $price, 2) }}</span>
                    <span class="currency">{{ $currency ?? 'ر.س' }}</span>
                </div>
            @endif

            <p class="item-description">{{ $description }}</p>

            <div class="btn-group">
                <a id="openAppBtn" href="{{ $schemeUrl }}" class="btn btn-primary">
                    فتح في التطبيق مباشرة
                </a>

                <a id="androidStoreBtn" href="{{ $storeUrls['android'] }}" class="btn btn-store">
                    <svg viewBox="0 0 24 24">
                        <path d="M3.609 1.814L13.792 12 3.61 22.186a1.942 1.942 0 01-.61-1.42V3.234c0-.555.225-1.055.609-1.42zm11.235 11.238l2.583 2.583-11.41 6.545 8.827-9.128zm0-2.104L6.017 1.821l11.41 6.545-2.583 2.582zm1.488 1.488l3.141 1.8c.84.482.84 1.267 0 1.748l-3.141 1.8-2.22-2.22 2.22-2.128z"/>
                    </svg>
                    <span>تحميل من Google Play</span>
                </a>

                <a id="iosStoreBtn" href="{{ $storeUrls['ios'] }}" class="btn btn-store">
                    <svg viewBox="0 0 24 24">
                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.63-.78 1.06-1.85.94-2.93-.91.04-2.03.61-2.68 1.39-.58.67-1.08 1.76-.94 2.82 1.02.08 2.06-.51 2.68-1.28z"/>
                    </svg>
                    <span>تحميل من App Store</span>
                </a>
            </div>
        </div>
    </div>

    <div class="footer">
        جميع الحقوق محفوظة &copy; {{ date('Y') }} لمّة (LAMMA)
    </div>

    <script>
        (function() {
            var userAgent = navigator.userAgent || navigator.vendor || window.opera || '';
            var isAndroid = /android/i.test(userAgent);
            var isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;

            var androidBtn = document.getElementById('androidStoreBtn');
            var iosBtn = document.getElementById('iosStoreBtn');

            if (isAndroid && iosBtn) iosBtn.style.display = 'none';
            if (isIOS && androidBtn) androidBtn.style.display = 'none';

            // If app is not installed and user stays in browser, smoothly reveal the page
            setTimeout(function() {
                if (document.hidden || document.webkitHidden) {
                    return;
                }
                document.documentElement.classList.remove('app-redirecting');
            }, 300);
        })();
    </script>
</body>
</html>
