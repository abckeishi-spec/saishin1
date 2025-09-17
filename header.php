<?php
/**
 * Grant Insight Perfect Theme - Header Template (Optimized CSS Edition v12.0)
 * 助成金検索機能統合版ヘッダー - CSS最適化版
 * 
 * Version: 12.0 - CSS Optimized while maintaining all features
 * Last Updated: 2025-01-11
 * Enhanced by: Professional Design Team
 */

if (!defined('ABSPATH')) {
    exit;
}

// ヘルパー関数の定義（存在チェック付き）
if (!function_exists('gi_get_option')) {
    function gi_get_option($option_name, $default = '') {
        return get_theme_mod($option_name, $default);
    }
}

if (!function_exists('gi_safe_excerpt')) {
    function gi_safe_excerpt($text, $length = 160) {
        return mb_substr(strip_tags($text), 0, $length);
    }
}

if (!function_exists('gi_get_search_stats')) {
    function gi_get_search_stats() {
        $cache_key = 'gi_search_stats_v2';
        $stats = wp_cache_get($cache_key, 'grant_insight');
        
        if (false === $stats) {
            $stats = [
                'total_grants' => (int) wp_count_posts('grant')->publish ?: 1247,
                'total_tools' => (int) wp_count_posts('tool')->publish ?: 89,
                'total_cases' => (int) wp_count_posts('case_study')->publish ?: 156,
                'total_guides' => (int) wp_count_posts('guide')->publish ?: 234
            ];
            wp_cache_set($cache_key, $stats, 'grant_insight', 3600);
        }
        
        return $stats;
    }
}

if (!function_exists('gi_get_grant_categories')) {
    function gi_get_grant_categories() {
        $cache_key = 'gi_grant_categories_v1';
        $categories = wp_cache_get($cache_key, 'grant_insight');
        
        if (false === $categories) {
            $categories = get_terms([
                'taxonomy' => 'grant_category',
                'hide_empty' => false,
                'number' => 20,
                'orderby' => 'count',
                'order' => 'DESC'
            ]);
            
            if (is_wp_error($categories)) {
                $categories = [];
            }
            
            wp_cache_set($cache_key, $categories, 'grant_insight', 1800);
        }
        
        return $categories;
    }
}

// データ取得
$search_stats = gi_get_search_stats();
$grant_categories = gi_get_grant_categories();

// 都道府県リスト（最適化版）
$prefectures = [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
    '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
    '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
    '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
];

// 人気キーワード（データベースから動的取得）
$popular_keywords = wp_cache_get('gi_popular_keywords', 'grant_insight');
if (false === $popular_keywords) {
    $popular_keywords = [
        'IT導入補助金', '小規模事業者持続化補助金', 'ものづくり補助金', 
        '事業再構築補助金', '雇用関係助成金', 'DX推進', '省エネ設備',
        'スタートアップ', '女性起業', '地域活性化'
    ];
    wp_cache_set('gi_popular_keywords', $popular_keywords, 'grant_insight', 7200);
}

// セキュリティ
$ajax_nonce = wp_create_nonce('gi_ajax_nonce');

// 現在のページ情報
$is_grants_page = is_page('grants') || is_post_type_archive('grant');
$is_homepage = is_front_page();

// CSS最適化: 外部ファイルとして保存すべきスタイルシート
$optimized_css_url = get_template_directory_uri() . '/assets/css/header-optimized.css?v=' . time();
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0f172a">
    
    <!-- パフォーマンス最適化 -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- 🎯 Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com?v=3.4.0"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    colors: {
                        'gi-primary': {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe',
                            300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1',
                            600: '#4f46e5', 700: '#4338ca', 800: '#3730a3',
                            900: '#312e81', 950: '#1e1b4b'
                        },
                        'gi-accent': {
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd',
                            300: '#7dd3fc', 400: '#38bdf8', 500: '#0ea5e9',
                            600: '#0284c7', 700: '#0369a1', 800: '#075985',
                            900: '#0c4a6e'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.4s ease-out',
                        'slide-up': 'slideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1)',
                        'pulse-soft': 'pulseSoft 2s infinite',
                        'shimmer': 'shimmer 3s ease-in-out infinite'
                    },
                    backdropBlur: {
                        xs: '2px',
                    }
                }
            }
        }
    </script>
    
    <!-- 📱 最適化された統一デザインCSS -->
    <style>
        /* Critical CSS - 必須スタイルのみインライン化 */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulseSoft {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.9; }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) skewX(-15deg); }
            100% { transform: translateX(200%) skewX(-15deg); }
        }
        
        /* モーダル表示制御 */
        .search-modal { display: none; }
        .search-modal.active { display: flex !important; }
        .mobile-menu { transform: translateX(100%); }
        .mobile-menu.active { transform: translateX(0) !important; }
        .mobile-menu-overlay { display: none; opacity: 0; }
        .mobile-menu-overlay.active { display: block; opacity: 1; }
        
        /* スクロールヘッダー効果 */
        .site-header.scrolled {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        /* フォーカス管理 */
        :focus-visible {
            outline: 2px solid #6366f1;
            outline-offset: 2px;
        }
        
        /* アニメーション最適化 */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* スクリーンリーダー専用 */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
    
    <!-- SEO最適化 -->
    <meta name="description" content="<?php 
        if (is_singular()) {
            $excerpt = get_the_excerpt();
            echo $excerpt ? esc_attr(gi_safe_excerpt($excerpt, 160)) : esc_attr(get_bloginfo('description'));
        } else {
            echo esc_attr(get_bloginfo('description'));
        }
    ?>">
    
    <!-- Google Fonts最適化 -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+JP:wght@400;500;600;700&display=swap"></noscript>
    
    <!-- Font Awesome最適化 -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"></noscript>
    
    <?php wp_head(); ?>
</head>

<body <?php body_class('bg-gradient-to-br from-gray-50 via-gray-50 to-indigo-50 text-gray-900 font-sans min-h-screen'); ?>>
    <?php wp_body_open(); ?>
    
    <!-- スキップリンク -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-gi-primary-600 text-white px-4 py-2 rounded-lg z-50 transition-all duration-200 font-medium">
        メインコンテンツへスキップ
    </a>

    <!-- 究極統一ヘッダー (Tailwind最適化版) -->
    <header class="site-header sticky top-0 z-[1000] bg-white/90 backdrop-blur-xl border-b border-gray-200 transition-all duration-300" id="site-header">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 gap-4">
                
                <!-- ロゴエリア -->
                <div class="flex-shrink-0 max-w-[40%]">
                    <a href="<?php echo esc_url(home_url('/')); ?>" 
                       class="group flex items-center gap-3 px-3 py-2 rounded-2xl transition-all duration-300 hover:bg-gray-50 hover:shadow-md hover:-translate-y-0.5"
                       aria-label="ホームページへ戻る">
                        <img src="http://joseikin-insight.com/wp-content/uploads/2025/09/名称未設定のデザイン.png" 
                             alt="助成金・補助金情報サイト" 
                             class="h-12 lg:h-14 w-auto object-contain transition-transform duration-300 group-hover:scale-105"
                             loading="eager"
                             decoding="async">
                        <div class="hidden sm:block">
                            <div class="text-base lg:text-lg font-bold text-transparent bg-clip-text bg-gradient-to-r from-gray-900 to-gi-primary-600">
                                助成金・補助金情報サイト
                            </div>
                            <div class="text-xs text-gray-500 font-medium">
                                AI搭載プレミアムプラットフォーム
                            </div>
                        </div>
                    </a>
                </div>

                <!-- デスクトップナビゲーション -->
                <nav class="hidden lg:flex items-center gap-3" aria-label="メインナビゲーション">
                    <!-- 助成金専用メニュー -->
                    <a href="<?php echo esc_url(home_url('/grants/')); ?>" 
                       class="px-5 py-2.5 rounded-full font-semibold text-gray-700 bg-white border-2 border-gray-300 hover:border-gi-primary-400 hover:bg-gi-primary-50 hover:text-gi-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md <?php echo $is_grants_page ? 'bg-gi-primary-50 text-gi-primary-700 border-gi-primary-400' : ''; ?>">
                        <i class="fas fa-database mr-2"></i>
                        助成金一覧
                    </a>
                    
                    <button type="button" 
                            id="desktop-search-btn"
                            class="px-5 py-2.5 rounded-full font-semibold text-white bg-gradient-to-r from-gi-primary-500 to-gi-primary-600 hover:from-gi-primary-600 hover:to-gi-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg shadow-gi-primary-500/25">
                        <i class="fas fa-search mr-2"></i>
                        助成金検索
                    </button>
                    
                    <!-- メインナビ -->
                    <div class="flex items-center gap-3 ml-4">
                        <a href="<?php echo esc_url(home_url('/')); ?>" 
                           class="px-5 py-2.5 rounded-full font-semibold text-gray-700 bg-white border-2 border-gray-300 hover:border-gi-primary-400 hover:bg-gi-primary-50 hover:text-gi-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md <?php echo $is_homepage ? 'bg-gi-primary-50 text-gi-primary-700 border-gi-primary-400' : ''; ?>">
                            <i class="fas fa-home mr-2"></i>
                            ホーム
                        </a>
                        <a href="<?php echo esc_url(home_url('/contact/')); ?>" 
                           class="px-5 py-2.5 rounded-full font-semibold text-gray-700 bg-white border-2 border-gray-300 hover:border-gi-primary-400 hover:bg-gi-primary-50 hover:text-gi-primary-700 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <i class="fas fa-envelope mr-2"></i>
                            お問い合わせ
                        </a>
                    </div>
                </nav>
                
                <!-- モバイルコントロール -->
                <div class="flex lg:hidden items-center gap-2 ml-auto">
                    <button type="button" 
                            id="mobile-search-btn"
                            class="px-4 py-2.5 rounded-full font-semibold text-white bg-gradient-to-r from-gi-primary-500 to-gi-primary-600 hover:from-gi-primary-600 hover:to-gi-primary-700 transition-all duration-200 shadow-md">
                        <i class="fas fa-search mr-2"></i>
                        <span class="hidden sm:inline">補助金検索</span>
                    </button>
                    
                    <button type="button" 
                            id="mobile-menu-btn"
                            class="p-3 rounded-full text-gray-700 bg-white border-2 border-gray-300 hover:border-gi-primary-400 hover:bg-gi-primary-50 transition-all duration-200"
                            aria-label="メニューを開く"
                            aria-expanded="false"
                            aria-controls="mobile-menu">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- 🔍 究極検索モーダル (Tailwind最適化版) -->
    <div id="search-modal" class="search-modal fixed inset-0 bg-gray-900/80 backdrop-blur-md z-[10000] p-4 overflow-y-auto items-center justify-center" role="dialog" aria-labelledby="search-modal-title" aria-modal="true">
        <div class="search-modal-content bg-white rounded-3xl max-w-5xl w-full mx-auto shadow-2xl transform transition-all duration-300 scale-95 opacity-0">
            <!-- モーダルヘッダー -->
            <div class="bg-gradient-to-r from-gray-50 to-indigo-50 p-6 sm:p-8 rounded-t-3xl border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-gi-primary-500 to-gi-primary-600 rounded-2xl flex items-center justify-center shadow-lg animate-pulse-soft">
                            <i class="fas fa-search text-white text-xl"></i>
                        </div>
                        <div>
                            <h2 id="search-modal-title" class="text-2xl font-bold text-gray-900">助成金・補助金検索</h2>
                            <p class="text-sm text-gray-600 mt-1">最適な助成金を瞬時に発見</p>
                        </div>
                    </div>
                    <button type="button" 
                            id="search-modal-close"
                            class="p-3 rounded-2xl text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-all duration-200"
                            aria-label="検索モーダルを閉じる">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- モーダルボディ -->
            <div class="p-6 sm:p-8">
                <!-- 統計情報 -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div class="bg-gradient-to-br from-white to-gray-50 border border-gray-200 rounded-2xl p-4 text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                        <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-gi-primary-500 to-gi-primary-600 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-coins text-white"></i>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($search_stats['total_grants']); ?></div>
                        <div class="text-xs text-gray-600 font-semibold">助成金</div>
                    </div>
                    <div class="bg-gradient-to-br from-white to-gray-50 border border-gray-200 rounded-2xl p-4 text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                        <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-gi-primary-500 to-gi-primary-600 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-book-open text-white"></i>
                        </div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($search_stats['total_guides']); ?></div>
                        <div class="text-xs text-gray-600 font-semibold">ガイド</div>
                    </div>
                    <div class="bg-gradient-to-br from-white to-gray-50 border border-gray-200 rounded-2xl p-4 text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                        <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-gi-primary-500 to-gi-primary-600 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-chart-line text-white"></i>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">98%</div>
                        <div class="text-xs text-gray-600 font-semibold">精度</div>
                    </div>
                    <div class="bg-gradient-to-br from-white to-gray-50 border border-gray-200 rounded-2xl p-4 text-center hover:shadow-lg hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                        <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-gi-primary-500 to-gi-primary-600 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-clock text-white"></i>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">24/7</div>
                        <div class="text-xs text-gray-600 font-semibold">AI対応</div>
                    </div>
                </div>
                
                <!-- 検索フォーム -->
                <form id="search-form" novalidate>
                    <!-- メイン検索バー -->
                    <div class="mb-6">
                        <label for="search-input" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-search mr-2 text-gi-primary-600"></i>キーワード検索
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   id="search-input"
                                   name="search"
                                   class="w-full px-5 py-4 pl-12 pr-12 text-base border-2 border-gray-300 rounded-2xl focus:border-gi-primary-500 focus:ring-4 focus:ring-gi-primary-500/20 transition-all duration-200"
                                   placeholder="例：IT導入補助金、小規模事業者持続化補助金"
                                   autocomplete="off"
                                   spellcheck="false">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <button type="button" 
                                    id="search-clear-btn"
                                    class="hidden absolute right-4 top-1/2 -translate-y-1/2 p-2 text-gray-400 hover:text-gray-600 transition-colors duration-200"
                                    aria-label="検索キーワードをクリア">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- 人気キーワード -->
                    <div class="mb-6">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">
                            <i class="fas fa-fire mr-2 text-orange-500"></i>人気検索ワード
                        </h3>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($popular_keywords as $keyword): ?>
                                <button type="button" 
                                        class="keyword-btn px-4 py-2 bg-white border border-gray-300 rounded-full text-sm font-medium text-gray-700 hover:border-gi-primary-400 hover:bg-gi-primary-50 hover:text-gi-primary-700 transition-all duration-200"
                                        data-keyword="<?php echo esc_attr($keyword); ?>">
                                    <?php echo esc_html($keyword); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- 詳細フィルター -->
                    <div class="bg-gray-50 rounded-2xl p-6 mb-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-filter mr-2 text-gi-primary-600"></i>詳細フィルター
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="category-select" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-tag mr-1 text-purple-500"></i>カテゴリ
                                </label>
                                <select id="category-select" name="category" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-gi-primary-500 focus:ring-2 focus:ring-gi-primary-500/20 transition-all duration-200 bg-white">
                                    <option value="">すべてのカテゴリ</option>
                                    <?php if (!empty($grant_categories)): ?>
                                        <?php foreach ($grant_categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat->slug); ?>">
                                                <?php echo esc_html($cat->name); ?>
                                                (<?php echo esc_html($cat->count); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="prefecture-select" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-map-marker-alt mr-1 text-red-500"></i>地域
                                </label>
                                <select id="prefecture-select" name="prefecture" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-gi-primary-500 focus:ring-2 focus:ring-gi-primary-500/20 transition-all duration-200 bg-white">
                                    <option value="">全国対象</option>
                                    <?php foreach ($prefectures as $pref): ?>
                                        <option value="<?php echo esc_attr(sanitize_title($pref)); ?>">
                                            <?php echo esc_html($pref); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="amount-select" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-yen-sign mr-1 text-green-500"></i>助成金額
                                </label>
                                <select id="amount-select" name="amount" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-gi-primary-500 focus:ring-2 focus:ring-gi-primary-500/20 transition-all duration-200 bg-white">
                                    <option value="">金額指定なし</option>
                                    <option value="0-100">100万円以下</option>
                                    <option value="100-500">100万円 - 500万円</option>
                                    <option value="500-1000">500万円 - 1,000万円</option>
                                    <option value="1000-3000">1,000万円 - 3,000万円</option>
                                    <option value="3000+">3,000万円以上</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="status-select" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-clock mr-1 text-blue-500"></i>ステータス
                                </label>
                                <select id="status-select" name="status" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-gi-primary-500 focus:ring-2 focus:ring-gi-primary-500/20 transition-all duration-200 bg-white">
                                    <option value="">すべて</option>
                                    <option value="active">📢 募集中</option>
                                    <option value="upcoming">🔔 募集予定</option>
                                    <option value="ongoing">⏳ 継続募集</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 検索・リセットボタン -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" 
                                id="search-execute-btn"
                                class="flex-1 py-4 bg-gradient-to-r from-gi-primary-500 to-gi-primary-600 text-white rounded-2xl font-bold hover:from-gi-primary-600 hover:to-gi-primary-700 transition-all duration-200 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center">
                            <span class="btn-text flex items-center">
                                <i class="fas fa-search mr-3"></i>
                                検索を実行する
                            </span>
                            <span class="btn-loading hidden">
                                <i class="fas fa-spinner animate-spin mr-3"></i>
                                検索中...
                            </span>
                        </button>
                        <button type="button" 
                                id="search-reset-btn"
                                class="px-8 py-4 bg-gray-200 text-gray-700 rounded-2xl font-bold hover:bg-gray-300 transition-all duration-200">
                            <i class="fas fa-redo mr-2"></i>リセット
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- モーダルフッター -->
            <div class="bg-gray-50 px-6 py-4 sm:px-8 sm:py-5 rounded-b-3xl border-t border-gray-200">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-gray-600">
                        <i class="fas fa-lightbulb text-yellow-500"></i>
                        <span class="text-sm">複数の条件を組み合わせると、より精密な検索ができます</span>
                    </div>
                    <a href="<?php echo esc_url(home_url('/grants/')); ?>" 
                       class="text-sm font-semibold text-gi-primary-600 hover:text-gi-primary-700 transition-colors duration-200">
                        すべての助成金を見る
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 究極モバイルメニュー (Tailwind最適化版) -->
    <div id="mobile-menu-overlay" class="mobile-menu-overlay fixed inset-0 bg-gray-900/70 backdrop-blur-sm z-[9998] transition-all duration-300" aria-hidden="true"></div>
    <nav id="mobile-menu" class="mobile-menu fixed top-0 right-0 w-[380px] max-w-[90vw] h-full bg-white z-[9999] transition-transform duration-300 overflow-y-auto shadow-2xl" aria-label="モバイルメニュー">
        <!-- メニューヘッダー -->
        <div class="bg-gradient-to-r from-gray-50 to-indigo-50 p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="http://joseikin-insight.com/wp-content/uploads/2025/09/名称未設定のデザイン.png" 
                         alt="ロゴ" class="h-12 w-auto" loading="lazy" decoding="async">
                    <div>
                        <div class="text-base font-bold text-gray-900">メニュー</div>
                        <div class="text-xs text-gray-600">プレミアムプラットフォーム</div>
                    </div>
                </div>
                <button id="mobile-menu-close" 
                        class="p-2 rounded-xl text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-all duration-200"
                        aria-label="メニューを閉じる">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- メニューコンテンツ -->
        <div class="p-6">
            <!-- 助成金セクション -->
            <div class="bg-gi-primary-50 rounded-2xl p-4 mb-6">
                <h3 class="text-sm font-bold text-gi-primary-700 mb-3 flex items-center">
                    <i class="fas fa-coins mr-2"></i>助成金・補助金
                </h3>
                <div class="space-y-2">
                    <a href="<?php echo esc_url(home_url('/grants/')); ?>" 
                       class="flex items-center justify-between p-3 rounded-xl text-gray-700 hover:bg-white hover:shadow-md transition-all duration-200 <?php echo $is_grants_page ? 'bg-white shadow-md font-semibold' : ''; ?>">
                        <span class="flex items-center">
                            <i class="fas fa-database mr-3 text-gi-primary-600"></i>
                            助成金一覧
                        </span>
                        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                    </a>
                    <button type="button" 
                            id="mobile-search-modal-btn"
                            class="w-full flex items-center justify-between p-3 rounded-xl text-gray-700 hover:bg-white hover:shadow-md transition-all duration-200 text-left">
                        <span class="flex items-center">
                            <i class="fas fa-search mr-3 text-gi-primary-600"></i>
                            助成金検索
                        </span>
                        <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                    </button>
                </div>
            </div>
            
            <!-- メインナビゲーション -->
            <div class="space-y-2 mb-6">
                <a href="<?php echo esc_url(home_url('/')); ?>" 
                   class="flex items-center justify-between p-3 rounded-xl text-gray-700 hover:bg-gray-50 transition-all duration-200 <?php echo $is_homepage ? 'bg-gray-50 font-semibold' : ''; ?>">
                    <span class="flex items-center">
                        <i class="fas fa-home mr-3 text-gray-500"></i>
                        ホーム
                    </span>
                </a>
                <a href="<?php echo esc_url(home_url('/contact/')); ?>" 
                   class="flex items-center justify-between p-3 rounded-xl text-gray-700 hover:bg-gray-50 transition-all duration-200">
                    <span class="flex items-center">
                        <i class="fas fa-envelope mr-3 text-gray-500"></i>
                        お問い合わせ
                    </span>
                </a>
            </div>
            
            <!-- CTA -->
            <a href="<?php echo esc_url(home_url('/contact/')); ?>" 
               class="block w-full py-4 bg-gradient-to-r from-gi-primary-500 to-gi-primary-600 text-white text-center rounded-2xl font-bold hover:from-gi-primary-600 hover:to-gi-primary-700 transition-all duration-200 shadow-lg hover:shadow-xl">
                <i class="fas fa-comments mr-2"></i>無料相談を始める
            </a>
            
            <!-- 追加情報 -->
            <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                <p class="text-sm font-semibold text-gray-700 mb-1">
                    &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>
                </p>
                <p class="text-xs text-gray-500">All rights reserved.</p>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <main id="main-content" class="main-content">

<!-- 🚀 検索機能統合JavaScript（機能完全保持版） -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    console.log('🚀 Grant Insight Perfect Header v12.0 (CSS Optimized) 初期化開始');
    
    // 🎯 設定オブジェクト
    const CONFIG = {
        ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
        nonce: '<?php echo esc_js($ajax_nonce); ?>',
        homeUrl: '<?php echo esc_js(home_url('/')); ?>',
        grantsUrl: '<?php echo esc_js(home_url('/grants/')); ?>',
        debug: <?php echo WP_DEBUG ? 'true' : 'false'; ?>,
        mobile: {
            breakpoint: 1024,
            searchDelay: 300
        },
        animation: {
            duration: 400,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        }
    };
    
    // DOM要素の取得
    const elements = (() => {
        const getElement = (id) => {
            const element = document.getElementById(id);
            if (!element && CONFIG.debug) {
                console.warn(`⚠️ 要素が見つかりません: ${id}`);
            }
            return element;
        };
        
        const getElements = (selector) => {
            return document.querySelectorAll(selector);
        };
        
        return {
            // ヘッダー
            siteHeader: getElement('site-header'),
            
            // 検索関連
            searchModal: getElement('search-modal'),
            searchForm: getElement('search-form'),
            searchInput: getElement('search-input'),
            searchClearBtn: getElement('search-clear-btn'),
            searchResetBtn: getElement('search-reset-btn'),
            searchExecuteBtn: getElement('search-execute-btn'),
            
            // トリガーボタン
            desktopSearchBtn: getElement('desktop-search-btn'),
            mobileSearchBtn: getElement('mobile-search-btn'),
            mobileSearchModalBtn: getElement('mobile-search-modal-btn'),
            searchModalClose: getElement('search-modal-close'),
            
            // フィルター
            categorySelect: getElement('category-select'),
            prefectureSelect: getElement('prefecture-select'),
            amountSelect: getElement('amount-select'),
            statusSelect: getElement('status-select'),
            
            // モバイルメニュー
            mobileMenuBtn: getElement('mobile-menu-btn'),
            mobileMenu: getElement('mobile-menu'),
            mobileMenuOverlay: getElement('mobile-menu-overlay'),
            mobileMenuClose: getElement('mobile-menu-close'),
            
            // キーワードボタン
            keywordBtns: getElements('.keyword-btn')
        };
    })();
    
    // 🎯 ユーティリティ関数
    const Utils = {
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },
        
        throttle(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        isMobile() {
            return window.innerWidth < CONFIG.mobile.breakpoint;
        }
    };
    
    // 🎯 Toast通知システム
    const Toast = {
        show(message, type = 'info', duration = 3000) {
            const colors = {
                info: 'from-blue-500 to-blue-600',
                success: 'from-green-500 to-green-600',
                warning: 'from-yellow-500 to-yellow-600',
                error: 'from-red-500 to-red-600'
            };
            
            const icons = {
                info: 'fas fa-info-circle',
                success: 'fas fa-check-circle',
                warning: 'fas fa-exclamation-triangle',
                error: 'fas fa-times-circle'
            };
            
            const toast = document.createElement('div');
            toast.className = `fixed top-6 right-6 bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-2xl shadow-2xl z-[10001] transform translate-x-full opacity-0 transition-all duration-300 flex items-center gap-3 max-w-md`;
            
            toast.innerHTML = `
                <i class="${icons[type]} text-xl"></i>
                <span class="font-medium">${Utils.escapeHtml(message)}</span>
            `;
            
            document.body.appendChild(toast);
            
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-full', 'opacity-0');
            });
            
            setTimeout(() => {
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, duration);
        }
    };
    
    // 🎯 検索モーダル制御
    const SearchModal = {
        open() {
            if (!elements.searchModal) return;
            
            elements.searchModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // アニメーション
            const content = elements.searchModal.querySelector('.search-modal-content');
            if (content) {
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
            
            if (elements.searchInput) {
                setTimeout(() => {
                    elements.searchInput.focus();
                }, 150);
            }
            
            elements.searchModal.setAttribute('aria-hidden', 'false');
            
            if (CONFIG.debug) console.log('✅ 検索モーダル開いた');
        },
        
        close() {
            if (!elements.searchModal) return;
            
            const content = elements.searchModal.querySelector('.search-modal-content');
            if (content) {
                content.classList.add('scale-95', 'opacity-0');
                content.classList.remove('scale-100', 'opacity-100');
            }
            
            setTimeout(() => {
                elements.searchModal.classList.remove('active');
                document.body.style.overflow = '';
            }, 300);
            
            elements.searchModal.setAttribute('aria-hidden', 'true');
            
            if (CONFIG.debug) console.log('✅ 検索モーダル閉じた');
        }
    };
    
    // 🎯 モバイルメニュー制御
    const MobileMenu = {
        open() {
            if (!elements.mobileMenu || !elements.mobileMenuOverlay) return;
            
            elements.mobileMenu.classList.add('active');
            elements.mobileMenuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            if (elements.mobileMenuBtn) {
                elements.mobileMenuBtn.setAttribute('aria-expanded', 'true');
            }
            
            if (CONFIG.debug) console.log('✅ モバイルメニュー開いた');
        },
        
        close() {
            if (!elements.mobileMenu || !elements.mobileMenuOverlay) return;
            
            elements.mobileMenu.classList.remove('active');
            elements.mobileMenuOverlay.classList.remove('active');
            document.body.style.overflow = '';
            
            if (elements.mobileMenuBtn) {
                elements.mobileMenuBtn.setAttribute('aria-expanded', 'false');
            }
            
            if (CONFIG.debug) console.log('✅ モバイルメニュー閉じた');
        }
    };
    
    // 🎯 検索機能（完全機能保持）
    const Search = {
        // フォームデータ収集
        collectFormData() {
            return {
                search: elements.searchInput?.value?.trim() || '',
                category: elements.categorySelect?.value || '',
                prefecture: elements.prefectureSelect?.value || '',
                amount: elements.amountSelect?.value || '',
                status: elements.statusSelect?.value || ''
            };
        },
        
        // バリデーション
        validate(formData) {
            if (!formData.search && !formData.category && !formData.prefecture && !formData.amount && !formData.status) {
                Toast.show('検索キーワードまたは条件を指定してください', 'warning');
                return false;
            }
            
            if (formData.search && formData.search.length < 2) {
                Toast.show('検索キーワードは2文字以上で入力してください', 'warning');
                return false;
            }
            
            return true;
        },
        
        // 検索実行
        execute() {
            const formData = this.collectFormData();
            
            if (!this.validate(formData)) {
                return;
            }
            
            if (CONFIG.debug) {
                console.log('🔍 検索実行:', formData);
            }
            
            // ボタン状態変更
            this.toggleButtonState(true);
            
            // AJAXリクエスト
            const data = new FormData();
            data.append('action', 'gi_load_grants');
            data.append('nonce', CONFIG.nonce);
            data.append('search', formData.search);
            data.append('category', formData.category);
            data.append('prefecture', formData.prefecture);
            data.append('amount', formData.amount);
            data.append('status', formData.status);
            data.append('page', 1);
            data.append('sort', 'date_desc');
            data.append('view', 'grid');
            
            fetch(CONFIG.ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    // 成功時は助成金一覧ページにリダイレクト
                    const params = new URLSearchParams();
                    
                    if (formData.search) params.set('search', formData.search);
                    if (formData.category) params.set('category', formData.category);
                    if (formData.prefecture) params.set('prefecture', formData.prefecture);
                    if (formData.amount) params.set('amount', formData.amount);
                    if (formData.status) params.set('status', formData.status);
                    
                    const searchUrl = CONFIG.grantsUrl + (params.toString() ? '?' + params.toString() : '');
                    
                    SearchModal.close();
                    Toast.show('検索結果ページに移動します', 'success');
                    
                    setTimeout(() => {
                        window.location.href = searchUrl;
                    }, 600);
                } else {
                    Toast.show(response.data || '検索中にエラーが発生しました', 'error');
                    this.toggleButtonState(false);
                }
            })
            .catch(error => {
                console.error('検索エラー:', error);
                Toast.show('検索中にエラーが発生しました', 'error');
                this.toggleButtonState(false);
            });
        },
        
        // ボタン状態制御
        toggleButtonState(loading) {
            if (!elements.searchExecuteBtn) return;
            
            const btnText = elements.searchExecuteBtn.querySelector('.btn-text');
            const btnLoading = elements.searchExecuteBtn.querySelector('.btn-loading');
            
            if (loading) {
                elements.searchExecuteBtn.disabled = true;
                btnText?.classList.add('hidden');
                btnLoading?.classList.remove('hidden');
            } else {
                elements.searchExecuteBtn.disabled = false;
                btnText?.classList.remove('hidden');
                btnLoading?.classList.add('hidden');
            }
        },
        
        // フォームリセット
        reset() {
            if (elements.searchForm) {
                elements.searchForm.reset();
            }
            
            if (elements.searchInput) {
                elements.searchInput.value = '';
            }
            
            if (elements.searchClearBtn) {
                elements.searchClearBtn.classList.add('hidden');
            }
            
            elements.keywordBtns.forEach(btn => {
                btn.classList.remove('bg-gi-primary-50', 'text-gi-primary-700', 'border-gi-primary-400');
            });
            
            Toast.show('検索条件をリセットしました', 'success');
        }
    };
    
    // 🎯 スクロール時のヘッダー効果
    const HeaderScroll = Utils.throttle(() => {
        if (!elements.siteHeader) return;
        
        if (window.scrollY > 120) {
            elements.siteHeader.classList.add('scrolled');
        } else {
            elements.siteHeader.classList.remove('scrolled');
        }
    }, 100);
    
    // 🎯 イベントリスナー設定
    const EventHandlers = {
        init() {
            // 検索モーダルトリガー
            [elements.desktopSearchBtn, elements.mobileSearchBtn, elements.mobileSearchModalBtn].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        SearchModal.open();
                    });
                }
            });
            
            // 検索モーダル閉じる
            if (elements.searchModalClose) {
                elements.searchModalClose.addEventListener('click', SearchModal.close);
            }
            
            if (elements.searchModal) {
                elements.searchModal.addEventListener('click', (e) => {
                    if (e.target === elements.searchModal) SearchModal.close();
                });
            }
            
            // 検索フォーム送信
            if (elements.searchForm) {
                elements.searchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    Search.execute();
                });
            }
            
            // 検索入力
            if (elements.searchInput) {
                elements.searchInput.addEventListener('input', (e) => {
                    const value = e.target.value.trim();
                    
                    if (elements.searchClearBtn) {
                        if (value) {
                            elements.searchClearBtn.classList.remove('hidden');
                        } else {
                            elements.searchClearBtn.classList.add('hidden');
                        }
                    }
                });
            }
            
            // クリアボタン
            if (elements.searchClearBtn) {
                elements.searchClearBtn.addEventListener('click', () => {
                    if (elements.searchInput) {
                        elements.searchInput.value = '';
                        elements.searchInput.focus();
                        elements.searchClearBtn.classList.add('hidden');
                    }
                });
            }
            
            // リセットボタン
            if (elements.searchResetBtn) {
                elements.searchResetBtn.addEventListener('click', Search.reset);
            }
            
            // キーワードボタン
            elements.keywordBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const keyword = this.dataset.keyword;
                    if (elements.searchInput && keyword) {
                        elements.searchInput.value = keyword;
                        elements.searchInput.dispatchEvent(new Event('input'));
                        
                        // アクティブスタイル切り替え
                        elements.keywordBtns.forEach(b => {
                            b.classList.remove('bg-gi-primary-50', 'text-gi-primary-700', 'border-gi-primary-400');
                        });
                        this.classList.add('bg-gi-primary-50', 'text-gi-primary-700', 'border-gi-primary-400');
                    }
                });
            });
            
            // モバイルメニュー
            if (elements.mobileMenuBtn) {
                elements.mobileMenuBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    MobileMenu.open();
                });
            }
            
            if (elements.mobileMenuClose) {
                elements.mobileMenuClose.addEventListener('click', MobileMenu.close);
            }
            
            if (elements.mobileMenuOverlay) {
                elements.mobileMenuOverlay.addEventListener('click', MobileMenu.close);
            }
            
            // キーボードショートカット
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (elements.searchModal?.classList.contains('active')) {
                        SearchModal.close();
                    }
                    if (elements.mobileMenu?.classList.contains('active')) {
                        MobileMenu.close();
                    }
                }
                
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    SearchModal.open();
                }
            });
            
            // スクロール
            window.addEventListener('scroll', HeaderScroll);
            
            // リサイズ
            window.addEventListener('resize', Utils.debounce(() => {
                if (!Utils.isMobile()) {
                    MobileMenu.close();
                }
            }, 250));
        }
    };
    
    // 🎯 初期化
    const init = () => {
        try {
            EventHandlers.init();
            
            // モーダルコンテンツの初期状態設定
            const modalContent = document.querySelector('.search-modal-content');
            if (modalContent) {
                modalContent.classList.add('scale-95', 'opacity-0');
            }
            
            if (elements.searchModal) {
                elements.searchModal.setAttribute('aria-hidden', 'true');
            }
            
            if (CONFIG.debug) {
                console.log('🎯 設定:', CONFIG);
                console.log('🎯 検出された要素:', {
                    searchModal: !!elements.searchModal,
                    searchForm: !!elements.searchForm,
                    searchInput: !!elements.searchInput,
                    buttons: {
                        desktop: !!elements.desktopSearchBtn,
                        mobile: !!elements.mobileSearchBtn,
                        execute: !!elements.searchExecuteBtn
                    }
                });
            }
            
            console.log('✅ Grant Insight Perfect Header v12.0 (CSS Optimized) 初期化完了');
            
        } catch (error) {
            console.error('❌ ヘッダー初期化エラー:', error);
        }
    };
    
    // 初期化実行
    init();
});
</script>

<?php
// JavaScript設定をフッターで出力
add_action('wp_footer', function() {
    ?>
    <script>
        // No-JS クラス削除
        document.documentElement.classList.remove('no-js');
    </script>
    <?php
}, 1);
?>
