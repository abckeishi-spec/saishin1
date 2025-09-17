<?php
/**
 * Grant Insight Perfect - Mobile Optimization Functions
 * モバイル最適化機能
 * 
 * @package Grant_Insight_Perfect
 * @version 6.2.2
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * より精密なモバイル判定関数
 */
if (!function_exists('gi_is_mobile_device')) {
    function gi_is_mobile_device() {
        // キャッシュされた判定結果を確認
        static $is_mobile = null;
        
        if ($is_mobile !== null) {
            return $is_mobile;
        }
        
        // ユーザーエージェントベースの判定
        $mobile_agents = array(
            'Mobile', 'Android', 'Silk/', 'Kindle', 
            'BlackBerry', 'Opera Mini', 'Opera Mobi', 
            'iPhone', 'iPad', 'iPod', 'Windows Phone',
            'webOS', 'IEMobile', 'Blackberry', 'BB10'
        );
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        foreach ($mobile_agents as $agent) {
            if (stripos($user_agent, $agent) !== false) {
                $is_mobile = true;
                return $is_mobile;
            }
        }
        
        // 画面サイズベースの判定（JavaScript経由）
        if (isset($_COOKIE['gi_is_mobile'])) {
            $is_mobile = $_COOKIE['gi_is_mobile'] === '1';
            return $is_mobile;
        }
        
        // WordPressのモバイル判定を最後のフォールバック
        $is_mobile = wp_is_mobile();
        return $is_mobile;
    }
}

/**
 * タブレット判定関数
 */
if (!function_exists('gi_is_tablet')) {
    function gi_is_tablet() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $tablet_agents = array(
            'iPad', 'Android.*Tablet', 'Tablet.*Android',
            'Kindle', 'Silk', 'Galaxy Tab', 'Nexus 7',
            'Nexus 10', 'KFAPWI', 'Playbook'
        );
        
        foreach ($tablet_agents as $agent) {
            if (preg_match('/' . $agent . '/i', $user_agent)) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * レスポンシブグリッドクラス生成（モバイルファースト）
 * 既存関数との重複を防ぐため条件分岐
 */
if (!function_exists('gi_get_responsive_grid_classes')) {
    function gi_get_responsive_grid_classes($desktop_cols = 3, $gap = 6) {
        $mobile_class = 'grid grid-cols-1';
        $tablet_class = 'md:grid-cols-2';
        $desktop_class = "lg:grid-cols-{$desktop_cols}";
        $gap_class = "gap-{$gap}";
        
        return "{$mobile_class} {$tablet_class} {$desktop_class} {$gap_class}";
    }
}

/**
 * 既存のグリッドクラス関数の拡張版（名前を変更して重複回避）
 */
if (!function_exists('gi_get_responsive_grid_classes_enhanced')) {
    function gi_get_responsive_grid_classes_enhanced($desktop_cols = 3, $gap = 6, $mobile_cols = 1, $tablet_cols = 2) {
        // 有効な値の範囲を制限
        $mobile_cols = max(1, min(2, $mobile_cols));
        $tablet_cols = max(1, min(4, $tablet_cols));
        $desktop_cols = max(1, min(6, $desktop_cols));
        $gap = max(0, min(12, $gap));
        
        $mobile_class = "grid grid-cols-{$mobile_cols}";
        $tablet_class = "md:grid-cols-{$tablet_cols}";
        $desktop_class = "lg:grid-cols-{$desktop_cols}";
        $gap_class = "gap-{$gap}";
        
        // モバイル専用の追加クラス
        if (gi_is_mobile_device()) {
            $gap_class = "gap-" . max(2, $gap - 2); // モバイルでは狭めのギャップ
        }
        
        return "{$mobile_class} {$tablet_class} {$desktop_class} {$gap_class}";
    }
}

/**
 * カード統計情報のレンダリング（重複チェック追加）
 */
if (!function_exists('gi_render_card_statistics')) {
    function gi_render_card_statistics($stats) {
        if (empty($stats) || !is_array($stats)) {
            return '';
        }
        
        // デフォルト値の設定
        $defaults = array(
            'total_grants' => 0,
            'active_grants' => 0,
            'average_amount' => 0,
            'success_rate' => 0
        );
        $stats = wp_parse_args($stats, $defaults);
        
        ob_start();
        ?>
        <div class="card-statistics-mobile bg-white rounded-lg shadow-md p-4">
            <h5 class="font-medium text-gray-900 mb-3 text-sm flex items-center">
                <span class="text-lg mr-2">📊</span>
                統計情報
            </h5>
            
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-emerald-50 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-emerald-600">
                        <?php echo function_exists('gi_safe_number_format') 
                            ? gi_safe_number_format($stats['total_grants']) 
                            : number_format($stats['total_grants']); ?>
                    </div>
                    <div class="text-emerald-700 mt-1">総件数</div>
                </div>
                
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-green-600">
                        <?php echo function_exists('gi_safe_number_format') 
                            ? gi_safe_number_format($stats['active_grants']) 
                            : number_format($stats['active_grants']); ?>
                    </div>
                    <div class="text-green-700 mt-1">募集中</div>
                </div>
                
                <div class="bg-yellow-50 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-yellow-600">
                        <?php 
                        if (function_exists('gi_format_amount_man')) {
                            echo gi_format_amount_man($stats['average_amount']);
                        } else {
                            $man = $stats['average_amount'] / 10000;
                            echo number_format($man, 0) . '万';
                        }
                        ?>
                    </div>
                    <div class="text-yellow-700 mt-1">平均金額</div>
                </div>
                
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-lg font-bold text-blue-600">
                        <?php echo intval($stats['success_rate']); ?>%
                    </div>
                    <div class="text-blue-700 mt-1">平均採択率</div>
                </div>
            </div>
            
            <div class="mt-3 pt-3 border-t border-gray-200">
                <div class="text-xs text-gray-500 text-center">
                    最終更新: <?php echo date_i18n('Y/m/d H:i'); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * モバイル専用カードコンテナクラス生成
 */
if (!function_exists('gi_get_mobile_card_container_classes')) {
    function gi_get_mobile_card_container_classes() {
        if (gi_is_mobile_device()) {
            return 'mobile-grant-grid space-y-4';
        } elseif (gi_is_tablet()) {
            return 'tablet-grant-grid grid grid-cols-2 gap-4';
        } else {
            return 'desktop-grant-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6';
        }
    }
}

/**
 * モバイル専用スタイルエンキュー
 */
function gi_enqueue_mobile_styles() {
    // モバイルCSS
    if (gi_is_mobile_device()) {
        wp_enqueue_style(
            'gi-mobile-optimization',
            get_template_directory_uri() . '/assets/css/mobile-optimization.css',
            array(),
            GI_THEME_VERSION
        );
        
        // インラインスタイルでクリティカルCSS
        $critical_mobile_css = '
            @media (max-width: 768px) {
                .mobile-hidden { display: none !important; }
                .mobile-only { display: block !important; }
                .mobile-grant-grid { padding: 0 15px; }
                .grant-card-enhanced { margin-bottom: 20px; }
                body { -webkit-text-size-adjust: 100%; }
            }
        ';
        wp_add_inline_style('gi-mobile-optimization', $critical_mobile_css);
    }
    
    // タブレットCSS
    if (gi_is_tablet()) {
        wp_enqueue_style(
            'gi-tablet-optimization',
            get_template_directory_uri() . '/assets/css/tablet-optimization.css',
            array(),
            GI_THEME_VERSION
        );
    }
}
add_action('wp_enqueue_scripts', 'gi_enqueue_mobile_styles', 15);

/**
 * モバイル検出用JavaScript（改善版）
 */
function gi_mobile_detection_script() {
    ?>
    <script id="gi-mobile-detection">
    (function() {
        'use strict';
        
        // デバイス判定
        const checkDevice = function() {
            const width = window.innerWidth || document.documentElement.clientWidth;
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            
            const isMobile = width <= 768 || /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(userAgent);
            const isTablet = width > 768 && width <= 1024 && /iPad|Android.*Tablet|Tablet.*Android/i.test(userAgent);
            
            return {
                isMobile: isMobile,
                isTablet: isTablet,
                isDesktop: !isMobile && !isTablet,
                width: width
            };
        };
        
        // 初期判定
        const device = checkDevice();
        
        // Cookieに保存（1時間有効）
        const setCookie = function(name, value, hours) {
            const date = new Date();
            date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
            const expires = "; expires=" + date.toUTCString();
            document.cookie = name + "=" + value + expires + "; path=/; SameSite=Lax";
        };
        
        setCookie('gi_is_mobile', device.isMobile ? '1' : '0', 1);
        setCookie('gi_is_tablet', device.isTablet ? '1' : '0', 1);
        setCookie('gi_device_width', device.width, 1);
        
        // CSS クラス追加
        const html = document.documentElement;
        html.classList.remove('gi-mobile', 'gi-tablet', 'gi-desktop');
        
        if (device.isMobile) {
            html.classList.add('gi-mobile');
        } else if (device.isTablet) {
            html.classList.add('gi-tablet');
        } else {
            html.classList.add('gi-desktop');
        }
        
        // ビューポート設定
        let viewport = document.querySelector('meta[name="viewport"]');
        if (!viewport) {
            viewport = document.createElement('meta');
            viewport.name = 'viewport';
            document.head.appendChild(viewport);
        }
        
        if (device.isMobile) {
            viewport.content = 'width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes';
        } else {
            viewport.content = 'width=device-width, initial-scale=1.0';
        }
        
        // リサイズ時の再検出（デバウンス付き）
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                const newDevice = checkDevice();
                
                // デバイスタイプが変わった場合のみ処理
                if (newDevice.isMobile !== device.isMobile || newDevice.isTablet !== device.isTablet) {
                    // ページをリロード
                    if (confirm('画面サイズが変更されました。ページを再読み込みしますか？')) {
                        location.reload();
                    }
                }
            }, 250);
        });
        
        // タッチデバイス検出
        if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
            html.classList.add('touch-device');
        } else {
            html.classList.add('no-touch');
        }
        
        // 向き検出
        const checkOrientation = function() {
            if (window.orientation !== undefined) {
                if (Math.abs(window.orientation) === 90) {
                    html.classList.add('landscape');
                    html.classList.remove('portrait');
                } else {
                    html.classList.add('portrait');
                    html.classList.remove('landscape');
                }
            }
        };
        
        checkOrientation();
        window.addEventListener('orientationchange', checkOrientation);
        
    })();
    </script>
    <?php
}
add_action('wp_head', 'gi_mobile_detection_script', 1);

/**
 * モバイル用ボディクラス追加（拡張版）
 */
function gi_add_mobile_body_class($classes) {
    if (gi_is_mobile_device()) {
        $classes[] = 'gi-is-mobile';
        
        // 具体的なデバイスタイプも追加
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (stripos($user_agent, 'iPhone') !== false) {
            $classes[] = 'device-iphone';
        } elseif (stripos($user_agent, 'iPad') !== false) {
            $classes[] = 'device-ipad';
        } elseif (stripos($user_agent, 'Android') !== false) {
            $classes[] = 'device-android';
        }
    } elseif (gi_is_tablet()) {
        $classes[] = 'gi-is-tablet';
    } else {
        $classes[] = 'gi-is-desktop';
    }
    
    // タッチデバイス判定
    if (wp_is_mobile()) {
        $classes[] = 'touch-enabled';
    }
    
    // 画面幅によるクラス
    if (isset($_COOKIE['gi_device_width'])) {
        $width = intval($_COOKIE['gi_device_width']);
        if ($width < 576) {
            $classes[] = 'screen-xs';
        } elseif ($width < 768) {
            $classes[] = 'screen-sm';
        } elseif ($width < 992) {
            $classes[] = 'screen-md';
        } elseif ($width < 1200) {
            $classes[] = 'screen-lg';
        } else {
            $classes[] = 'screen-xl';
        }
    }
    
    return $classes;
}
add_filter('body_class', 'gi_add_mobile_body_class');

/**
 * モバイル用画像サイズ最適化
 */
function gi_mobile_image_sizes() {
    // モバイル用サムネイルサイズ
    add_image_size('mobile-card-thumb', 300, 200, true);
    add_image_size('mobile-hero', 400, 250, true);
    add_image_size('mobile-full', 768, 0, false);
    
    // タブレット用サイズ
    add_image_size('tablet-card-thumb', 400, 300, true);
    add_image_size('tablet-hero', 768, 400, true);
}
add_action('after_setup_theme', 'gi_mobile_image_sizes');

/**
 * デバイスに応じた画像サイズ選択
 */
function gi_get_device_image_size($default_size = 'medium') {
    if (gi_is_mobile_device()) {
        switch ($default_size) {
            case 'thumbnail':
                return 'mobile-card-thumb';
            case 'medium':
                return 'mobile-full';
            case 'large':
                return 'mobile-full';
            case 'full':
                return 'tablet-hero';
            default:
                return 'mobile-card-thumb';
        }
    } elseif (gi_is_tablet()) {
        switch ($default_size) {
            case 'thumbnail':
                return 'tablet-card-thumb';
            case 'medium':
                return 'tablet-hero';
            default:
                return 'tablet-card-thumb';
        }
    }
    
    return $default_size;
}

/**
 * モバイルでのページネーション最適化
 */
function gi_mobile_pagination_args($args) {
    if (gi_is_mobile_device()) {
        $args['prev_text'] = '<i class="fas fa-chevron-left"></i>';
        $args['next_text'] = '<i class="fas fa-chevron-right"></i>';
        $args['end_size'] = 1;
        $args['mid_size'] = 1;
        $args['type'] = 'array'; // 配列で返してカスタマイズしやすく
    } elseif (gi_is_tablet()) {
        $args['end_size'] = 1;
        $args['mid_size'] = 2;
    }
    return $args;
}
add_filter('gi_pagination_args', 'gi_mobile_pagination_args');

/**
 * モバイル用ページネーション表示関数
 */
if (!function_exists('gi_mobile_pagination')) {
    function gi_mobile_pagination($query = null) {
        global $wp_query;
        
        if (!$query) {
            $query = $wp_query;
        }
        
        if ($query->max_num_pages <= 1) {
            return;
        }
        
        $args = array(
            'total' => $query->max_num_pages,
            'current' => max(1, get_query_var('paged')),
            'prev_text' => '<span class="screen-reader-text">前へ</span><i class="fas fa-chevron-left"></i>',
            'next_text' => '<span class="screen-reader-text">次へ</span><i class="fas fa-chevron-right"></i>',
        );
        
        // モバイル用の調整を適用
        $args = apply_filters('gi_pagination_args', $args);
        
        if (gi_is_mobile_device()) {
            // モバイル用のシンプルな表示
            ?>
            <nav class="mobile-pagination flex justify-between items-center py-4">
                <?php
                $prev_link = get_previous_posts_link($args['prev_text']);
                $next_link = get_next_posts_link($args['next_text']);
                ?>
                
                <div class="prev-link">
                    <?php echo $prev_link ?: '<span class="disabled opacity-50">' . $args['prev_text'] . '</span>'; ?>
                </div>
                
                <div class="page-info text-sm text-gray-600">
                    <?php echo $args['current']; ?> / <?php echo $args['total']; ?>
                </div>
                
                <div class="next-link">
                    <?php echo $next_link ?: '<span class="disabled opacity-50">' . $args['next_text'] . '</span>'; ?>
                </div>
            </nav>
            <?php
        } else {
            // デスクトップ/タブレット用の通常表示
            echo '<nav class="pagination-wrapper">';
            echo paginate_links($args);
            echo '</nav>';
        }
    }
}

/**
 * モバイル表示時のメニュー項目数制限（改善版）
 */
function gi_mobile_menu_limit($items, $args) {
    // モバイルメニューの場合は制限しない
    if (isset($args->theme_location) && $args->theme_location === 'mobile') {
        return $items;
    }
    
    // プライマリメニューのモバイル表示を制限
    if (gi_is_mobile_device() && isset($args->theme_location) && $args->theme_location === 'primary') {
        // 優先度の高い項目のみ表示（最初の5項目）
        $limited_items = array_slice($items, 0, 5);
        
        // 「もっと見る」項目を追加
        if (count($items) > 5) {
            $more_item = new stdClass();
            $more_item->ID = 0;
            $more_item->title = 'もっと見る';
            $more_item->url = '#';
            $more_item->menu_item_parent = 0;
            $more_item->classes = array('mobile-more-menu');
            $limited_items[] = $more_item;
        }
        
        return $limited_items;
    }
    
    return $items;
}
add_filter('wp_nav_menu_objects', 'gi_mobile_menu_limit', 10, 2);

/**
 * モバイル用コンテンツ最適化
 */
function gi_optimize_mobile_content($content) {
    if (!gi_is_mobile_device() || is_admin()) {
        return $content;
    }
    
    // 大きなテーブルをレスポンシブに
    $content = str_replace('<table', '<div class="table-responsive"><table', $content);
    $content = str_replace('</table>', '</table></div>', $content);
    
    // 画像の最適化
    $content = preg_replace_callback('/<img([^>]+)>/i', function($matches) {
        $img_tag = $matches[0];
        
        // loading属性がない場合は追加
        if (strpos($img_tag, 'loading=') === false) {
            $img_tag = str_replace('<img', '<img loading="lazy"', $img_tag);
        }
        
        // クラスを追加
        if (strpos($img_tag, 'class=') !== false) {
            $img_tag = preg_replace('/class="([^"]*)"/', 'class="$1 mobile-optimized"', $img_tag);
        } else {
            $img_tag = str_replace('<img', '<img class="mobile-optimized"', $img_tag);
        }
        
        return $img_tag;
    }, $content);
    
    return $content;
}
add_filter('the_content', 'gi_optimize_mobile_content', 15);

/**
 * モバイル用メタタグ追加
 */
function gi_add_mobile_meta_tags() {
    ?>
    <!-- モバイル最適化メタタグ -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="HandheldFriendly" content="True">
    <meta name="MobileOptimized" content="320">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="format-detection" content="telephone=no">
    
    <?php if (gi_is_mobile_device()): ?>
    <!-- モバイル専用メタタグ -->
    <meta name="theme-color" content="#059669">
    <link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/apple-touch-icon.png">
    <?php endif; ?>
    <?php
}
add_action('wp_head', 'gi_add_mobile_meta_tags', 1);

/**
 * AMP対応チェック（将来の拡張用）
 */
if (!function_exists('gi_is_amp')) {
    function gi_is_amp() {
        return function_exists('is_amp_endpoint') && is_amp_endpoint();
    }
}

/**
 * モバイルパフォーマンス監視
 */
function gi_mobile_performance_monitor() {
    if (!gi_is_mobile_device() || !WP_DEBUG || !current_user_can('administrator')) {
        return;
    }
    ?>
    <script>
    // パフォーマンス監視
    window.addEventListener('load', function() {
        if (window.performance && window.performance.timing) {
            const timing = window.performance.timing;
            const loadTime = timing.loadEventEnd - timing.navigationStart;
            const domReadyTime = timing.domContentLoadedEventEnd - timing.navigationStart;
            
            console.log('Mobile Performance Metrics:');
            console.log('Page Load Time:', loadTime + 'ms');
            console.log('DOM Ready Time:', domReadyTime + 'ms');
            
            // 遅い場合は警告
            if (loadTime > 3000) {
                console.warn('Page load time exceeds 3 seconds on mobile');
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'gi_mobile_performance_monitor', 999);

/**
 * モバイル用のインラインCSS（クリティカルCSS）
 */
function gi_mobile_critical_css() {
    if (!gi_is_mobile_device()) {
        return;
    }
    ?>
    <style id="gi-mobile-critical-css">
        /* モバイル用クリティカルCSS */
        @media (max-width: 768px) {
            /* レイアウトの基本設定 */
            body {
                font-size: 16px;
                -webkit-text-size-adjust: 100%;
                -webkit-tap-highlight-color: transparent;
            }
            
            /* タッチ操作の最適化 */
            a, button, input, select, textarea {
                touch-action: manipulation;
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
            }
            
            /* スクロールの最適化 */
            .mobile-scroll-container {
                -webkit-overflow-scrolling: touch;
                overflow-y: auto;
            }
            
            /* 画像の最適化 */
            img {
                max-width: 100%;
                height: auto;
            }
            
            /* カードレイアウトの調整 */
            .grant-card-enhanced {
                margin: 0 10px 20px;
            }
            
            /* フォントサイズの調整 */
            h1 { font-size: 1.75rem; }
            h2 { font-size: 1.5rem; }
            h3 { font-size: 1.25rem; }
            
            /* パディングの調整 */
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            /* 非表示要素 */
            .desktop-only {
                display: none !important;
            }
        }
        
        /* タブレット用調整 */
        @media (min-width: 769px) and (max-width: 1024px) {
            .container {
                padding-left: 20px;
                padding-right: 20px;
            }
            
            .tablet-hidden {
                display: none !important;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'gi_mobile_critical_css', 5);

/**
 * Service Worker登録（PWA対応準備）
 */
function gi_register_service_worker() {
    if (!gi_is_mobile_device() || !is_ssl()) {
        return;
    }
    ?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('<?php echo get_template_directory_uri(); ?>/sw.js')
                .then(function(registration) {
                    console.log('ServiceWorker registration successful');
                })
                .catch(function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
        });
    }
    </script>
    <?php
}
// add_action('wp_footer', 'gi_register_service_worker'); // 必要に応じて有効化

/**
 * デバッグ情報表示（開発環境のみ）
 */
function gi_mobile_debug_info() {
    if (!WP_DEBUG || !current_user_can('administrator')) {
        return;
    }
    ?>
    <!-- Mobile Debug Info -->
    <div id="mobile-debug" style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; font-size: 11px; z-index: 99999; display: none;">
        <strong>Device Info:</strong><br>
        Mobile: <?php echo gi_is_mobile_device() ? 'Yes' : 'No'; ?><br>
        Tablet: <?php echo gi_is_tablet() ? 'Yes' : 'No'; ?><br>
        WP Mobile: <?php echo wp_is_mobile() ? 'Yes' : 'No'; ?><br>
        User Agent: <?php echo substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 50); ?>...
    </div>
    <script>
    if (window.location.hash === '#debug') {
        document.getElementById('mobile-debug').style.display = 'block';
    }
    </script>
    <?php
}
add_action('wp_footer', 'gi_mobile_debug_info', 9999);
