<?php
/**
 * Grant Insight Perfect - 3. AJAX Functions File (Complete Enhanced Edition)
 *
 * サイトの動的な機能（検索、フィルタリング、お気に入りなど）を
 * 担当する全てのAJAX処理をここにまとめます。
 * 新しいカードデザインに対応した完全修正版です。
 * 4-helper-functions.phpのヘルパー関数を活用しています。
 *
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 【完全修正版】AJAX - 助成金読み込み処理（新カードデザイン・全フィルター対応版）
 * ACFフィールドと完全連携版
 */
function gi_ajax_load_grants() {
    // nonceチェック
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }

    // ===== パラメータ取得と検証 =====
    // 基本的な検索パラメータ
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    // タクソノミー関連
    $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true);
    $prefectures = json_decode(stripslashes($_POST['prefectures'] ?? '[]'), true);
    $tags = json_decode(stripslashes($_POST['tags'] ?? '[]'), true); // 🆕 助成金タグ
    
    // 金額フィルター
    $amount = sanitize_text_field($_POST['amount'] ?? '');
    
    // ステータス・難易度・採択率
    $status = json_decode(stripslashes($_POST['status'] ?? '[]'), true);
    $difficulty = json_decode(stripslashes($_POST['difficulty'] ?? '[]'), true);
    $success_rate = json_decode(stripslashes($_POST['success_rate'] ?? '[]'), true);
    
    // 🆕 ACFフィールド用の新規パラメータ
    $subsidy_rate = sanitize_text_field($_POST['subsidy_rate'] ?? '');
    $organization = sanitize_text_field($_POST['organization'] ?? '');
    $target_business = sanitize_text_field($_POST['target_business'] ?? '');
    $only_featured = sanitize_text_field($_POST['only_featured'] ?? '');
    
    // ソート・表示関連
    $sort = sanitize_text_field($_POST['sort'] ?? 'date_desc');
    $view = sanitize_text_field($_POST['view'] ?? 'grid');
    $page = max(1, intval($_POST['page'] ?? 1));
    $posts_per_page = 12;
    
    // デバッグログ
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AJAX Load Grants - Received params:');
        error_log('Search: ' . $search);
        error_log('Categories: ' . print_r($categories, true));
        error_log('Prefectures: ' . print_r($prefectures, true));
        error_log('Tags: ' . print_r($tags, true));
        error_log('Amount: ' . $amount);
        error_log('Status: ' . print_r($status, true));
        error_log('Subsidy Rate: ' . $subsidy_rate);
        error_log('Organization: ' . $organization);
        error_log('Target Business: ' . $target_business);
        error_log('Only Featured: ' . $only_featured);
    }
    
    // 配列検証（型チェック）
    if (!is_array($categories)) $categories = [];
    if (!is_array($prefectures)) $prefectures = [];
    if (!is_array($tags)) $tags = [];
    if (!is_array($status)) $status = [];
    if (!is_array($difficulty)) $difficulty = [];
    if (!is_array($success_rate)) $success_rate = [];
    
    // UIステータスをDBの値にマッピング（ヘルパー関数を使用）
    if (is_array($status)) {
        $status = array_map(function($s) { 
            return $s === 'active' ? 'open' : ($s === 'upcoming' ? 'upcoming' : $s); 
        }, $status);
    }

    // ===== WP_Queryの引数構築 =====
    $args = array(
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
        'post_status' => 'publish'
    );

    // ===== 検索クエリ（拡張版：ACFフィールドも検索対象） =====
    if (!empty($search)) {
        // 通常の検索（タイトル・本文）
        $args['s'] = $search;
        
        // さらにメタフィールドも検索対象に追加
        // 検索語句でメタフィールドも検索する場合
        $search_meta_query = array(
            'relation' => 'OR',
            array(
                'key' => 'ai_summary',
                'value' => $search,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'organization',
                'value' => $search,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'grant_target',
                'value' => $search,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'eligible_expenses',
                'value' => $search,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'required_documents',
                'value' => $search,
                'compare' => 'LIKE'
            )
        );
    }

    // ===== タクソノミークエリ =====
    $tax_query = array('relation' => 'AND');
    
    // カテゴリーフィルター
    if (!empty($categories)) {
        $tax_query[] = array(
            'taxonomy' => 'grant_category',
            'field' => 'slug',
            'terms' => $categories,
            'operator' => 'IN'
        );
    }
    
    // 都道府県フィルター
    if (!empty($prefectures)) {
        $tax_query[] = array(
            'taxonomy' => 'grant_prefecture',
            'field' => 'slug',
            'terms' => $prefectures,
            'operator' => 'IN'
        );
    }
    
    // 🆕 助成金タグフィルター
    if (!empty($tags)) {
        $tax_query[] = array(
            'taxonomy' => 'grant_tag',
            'field' => 'slug',
            'terms' => $tags,
            'operator' => 'IN'
        );
    }
    
    // タクソノミークエリをセット
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    // ===== メタクエリ（カスタムフィールド） =====
    $meta_query = array('relation' => 'AND');
    
    // 検索語句によるメタフィールド検索を追加
    if (!empty($search) && isset($search_meta_query)) {
        $meta_query[] = $search_meta_query;
    }
    
    // ステータスフィルター
    if (!empty($status)) {
        $meta_query[] = array(
            'key' => 'application_status',
            'value' => $status,
            'compare' => 'IN'
        );
    }
    
    // 難易度フィルター
    if (!empty($difficulty)) {
        $meta_query[] = array(
            'key' => 'grant_difficulty',
            'value' => $difficulty,
            'compare' => 'IN'
        );
    }
    
    // 採択率フィルター
    if (!empty($success_rate)) {
        $rate_query = array('relation' => 'OR');
        
        if (in_array('high', $success_rate, true)) {
            $rate_query[] = array(
                'key' => 'grant_success_rate',
                'value' => 70,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        
        if (in_array('medium', $success_rate, true)) {
            $rate_query[] = array(
                'key' => 'grant_success_rate',
                'value' => array(50, 69),
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            );
        }
        
        if (in_array('low', $success_rate, true)) {
            $rate_query[] = array(
                'key' => 'grant_success_rate',
                'value' => 50,
                'compare' => '<',
                'type' => 'NUMERIC'
            );
        }
        
        if(count($rate_query) > 1) {
            $meta_query[] = $rate_query;
        }
    }
    
    // 金額フィルター
    if (!empty($amount)) {
        switch ($amount) {
            case '0-100':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => 1000000,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                );
                break;
                
            case '100-500':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => array(1000001, 5000000),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                );
                break;
                
            case '500-1000':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => array(5000001, 10000000),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                );
                break;
                
            case '1000-3000':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => array(10000001, 30000000),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                );
                break;
                
            case '3000+':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => 30000000,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                );
                break;
                
            case '1000+':
                $meta_query[] = array(
                    'key' => 'max_amount_numeric',
                    'value' => 10000000,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                );
                break;
        }
    }
    
    // 🆕 補助率フィルター
    if (!empty($subsidy_rate)) {
        $meta_query[] = array(
            'key' => 'subsidy_rate',
            'value' => $subsidy_rate,
            'compare' => '='
        );
    }
    
    // 🆕 実施組織フィルター
    if (!empty($organization)) {
        $meta_query[] = array(
            'key' => 'organization',
            'value' => $organization,
            'compare' => 'LIKE'
        );
    }
    
    // 🆕 対象事業者フィルター
    if (!empty($target_business)) {
        $meta_query[] = array(
            'key' => 'grant_target',
            'value' => $target_business,
            'compare' => 'LIKE'
        );
    }
    
    // 🆕 注目の助成金フィルター
    if ($only_featured === 'true') {
        $meta_query[] = array(
            'key' => 'is_featured',
            'value' => '1',
            'compare' => '='
        );
    }
    
    // メタクエリをセット
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // ===== ソート順 =====
    switch ($sort) {
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
            
        case 'date_desc':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
            
        case 'amount_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'DESC';
            break;
            
        case 'amount_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'max_amount_numeric';
            $args['order'] = 'ASC';
            break;
            
        case 'deadline_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'deadline_date';
            $args['order'] = 'ASC';
            break;
            
        case 'deadline_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'deadline_date';
            $args['order'] = 'DESC';
            break;
            
        case 'success_rate_desc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'grant_success_rate';
            $args['order'] = 'DESC';
            break;
            
        case 'success_rate_asc':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'grant_success_rate';
            $args['order'] = 'ASC';
            break;
            
        case 'title_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
            
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
            
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
    }

    // ===== 以下、既存のコード（クエリ実行と結果処理） =====
    // ... 省略していない残りの部分


    $query = new WP_Query($args);
    $grants = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // ヘルパー関数を使用してカード表示に必要なデータを先に集める
            $grant_terms = get_the_terms($post_id, 'grant_category');
            $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
            
            $grant_data = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => get_the_excerpt(),
                'thumbnail' => get_the_post_thumbnail_url($post_id, 'gi-card-thumb'),
                'main_category' => (!is_wp_error($grant_terms) && !empty($grant_terms)) ? $grant_terms[0]->name : '',
                'prefecture' => (!is_wp_error($prefecture_terms) && !empty($prefecture_terms)) ? $prefecture_terms[0]->name : '',
                'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                'deadline' => function_exists('gi_get_formatted_deadline') ? gi_get_formatted_deadline($post_id) : gi_safe_get_meta($post_id, 'deadline_date', ''),
                'amount' => gi_safe_get_meta($post_id, 'max_amount', '-'),
                'amount_numeric' => gi_safe_get_meta($post_id, 'max_amount_numeric', 0),
                'deadline_timestamp' => gi_safe_get_meta($post_id, 'deadline_date', ''),
                'status' => function_exists('gi_map_application_status_ui') ? gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open')) : gi_safe_get_meta($post_id, 'application_status', 'open'),
                'difficulty' => gi_safe_get_meta($post_id, 'grant_difficulty', ''),
                'success_rate' => gi_safe_get_meta($post_id, 'grant_success_rate', 0),
                'subsidy_rate' => gi_safe_get_meta($post_id, 'subsidy_rate', ''),
                'target_business' => gi_safe_get_meta($post_id, 'target_business', ''),
            );
            
            // 集めたデータを使ってカードのHTMLを作る
            $html = '';
            if ($view === 'grid') {
                $html = gi_render_modern_grant_card($grant_data);
            } else {
                $html = gi_render_modern_grant_list_card($grant_data);
            }

            $grants[] = array(
                'id' => $post_id,
                'html' => $html
            );
        }
        wp_reset_postdata();
    }

    // ページネーション生成
    $pagination_html = '';
    if ($query->max_num_pages > 1) {
        ob_start();
        echo '<div class="flex items-center justify-center space-x-2">';
        
        // 前のページ
        if ($page > 1) {
            echo '<button class="pagination-btn px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-300" data-page="' . ($page - 1) . '">';
            echo '<i class="fas fa-chevron-left mr-1"></i>前へ';
            echo '</button>';
        }
        
        // ページ番号
        $start = max(1, $page - 2);
        $end = min($query->max_num_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active_class = ($i === $page) ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
            echo '<button class="pagination-btn px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg transition-colors ' . $active_class . '" data-page="' . $i . '">';
            echo $i;
            echo '</button>';
        }
        
        // 次のページ
        if ($page < $query->max_num_pages) {
            echo '<button class="pagination-btn px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-gray-700 dark:text-gray-300" data-page="' . ($page + 1) . '">';
            echo '次へ<i class="fas fa-chevron-right ml-1"></i>';
            echo '</button>';
        }
        
        echo '</div>';
        $pagination_html = ob_get_clean();
    }

    wp_send_json_success(array(
        'grants' => $grants,
        'found_posts' => $query->found_posts,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $query->max_num_pages,
            'total_posts' => $query->found_posts,
            'posts_per_page' => $posts_per_page,
            'html' => $pagination_html
        ),
        'query_info' => compact('search', 'categories', 'prefectures', 'amount', 'status', 'difficulty', 'success_rate', 'sort'),
        'view' => $view
    ));
}
add_action('wp_ajax_gi_load_grants', 'gi_ajax_load_grants');
add_action('wp_ajax_nopriv_gi_load_grants', 'gi_ajax_load_grants');

/**
 * 金額フォーマット用ヘルパー関数（万円表記）
 * 4-helper-functions.phpのgi_format_amount_man()を拡張
 */
function gi_format_amount_with_unit($amount) {
    if (empty($amount) || $amount === '-' || !is_numeric($amount)) {
        return '未定';
    }
    
    $amount_num = intval($amount);
    if ($amount_num >= 100000000) { // 1億円以上
        $oku = $amount_num / 100000000;
        if ($oku == floor($oku)) {
            return number_format($oku) . '億円';
        } else {
            return number_format($oku, 1) . '億円';
        }
    } elseif ($amount_num >= 10000) { // 1万円以上
        $man = $amount_num / 10000;
        if ($man == floor($man)) {
            return number_format($man) . '万円';
        } else {
            return number_format($man, 1) . '万円';
        }
    } else {
        return number_format($amount_num) . '円';
    }
}

/**
 * モダンなカードデザイン生成関数（グリッド表示用・ダークモード対応）
 */
function gi_render_modern_grant_card($grant_data) {
    $post_id = $grant_data['id'];
    $title = esc_html($grant_data['title']);
    $permalink = esc_url($grant_data['permalink']);
    $excerpt = esc_html($grant_data['excerpt']);
    $organization = esc_html($grant_data['organization']);
    
    // 4-helper-functions.phpのgi_format_amount_manを使用、または拡張版を使用
    $amount = gi_format_amount_with_unit($grant_data['amount_numeric'] ?: $grant_data['amount']);
    
    $deadline = esc_html($grant_data['deadline']);
    $status = esc_html($grant_data['status']);
    $prefecture = esc_html($grant_data['prefecture']);
    $category = esc_html($grant_data['main_category']);
    $success_rate = intval($grant_data['success_rate']);
    $difficulty = esc_html($grant_data['difficulty']);
    
    // ステータスに応じた色設定
    $status_color = '';
    $status_bg = '';
    $status_text = '';
    $status_bg_dark = '';
    switch($status) {
        case '募集中':
        case 'active':
            $status_bg = 'bg-emerald-50';
            $status_bg_dark = 'dark:bg-emerald-900/30';
            $status_text = 'text-emerald-700 dark:text-emerald-400';
            break;
        case '準備中':
        case 'upcoming':
            $status_bg = 'bg-blue-50';
            $status_bg_dark = 'dark:bg-blue-900/30';
            $status_text = 'text-blue-700 dark:text-blue-400';
            break;
        case '終了':
        case 'closed':
            $status_bg = 'bg-gray-50';
            $status_bg_dark = 'dark:bg-gray-800';
            $status_text = 'text-gray-700 dark:text-gray-400';
            break;
        default:
            $status_bg = 'bg-gray-50';
            $status_bg_dark = 'dark:bg-gray-800';
            $status_text = 'text-gray-700 dark:text-gray-400';
    }
    
    // 難易度に応じた表示
    $difficulty_display = '';
    switch($difficulty) {
        case 'easy':
            $difficulty_display = '<span class="text-green-600 dark:text-green-400">★</span>';
            break;
        case 'normal':
            $difficulty_display = '<span class="text-yellow-600 dark:text-yellow-400">★★</span>';
            break;
        case 'hard':
            $difficulty_display = '<span class="text-red-600 dark:text-red-400">★★★</span>';
            break;
        default:
            $difficulty_display = '<span class="text-gray-400 dark:text-gray-600">-</span>';
    }
    
    // 採択率の表示色
    $success_color = '';
    if ($success_rate >= 70) {
        $success_color = 'text-green-600 dark:text-green-400';
    } elseif ($success_rate >= 50) {
        $success_color = 'text-yellow-600 dark:text-yellow-400';
    } else {
        $success_color = 'text-red-600 dark:text-red-400';
    }
    
    return "
    <div class='grant-card-modern' style='width: 100%; height: 420px; position: relative;'>
        <div class='bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 dark:border-gray-700 overflow-hidden h-full flex flex-col'>
            
            <!-- ヘッダー部分（固定高さ） -->
            <div class='px-4 pt-4 pb-3' style='min-height: 80px;'>
                <!-- ステータスとお気に入り -->
                <div class='flex items-center justify-between mb-3'>
                    <span class='inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {$status_bg} {$status_bg_dark} {$status_text}'>
                        <span class='w-1.5 h-1.5 bg-current rounded-full mr-1.5'></span>
                        {$status}
                    </span>
                    <button class='favorite-btn text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition-colors p-1' data-post-id='{$post_id}'>
                        <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'></path>
                        </svg>
                    </button>
                </div>
                
                <!-- タイトル（2行固定） -->
                <h3 class='text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight' style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5rem;'>
                    <a href='{$permalink}' class='hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors'>
                        {$title}
                    </a>
                </h3>
            </div>
            
            <!-- 情報セクション（固定高さ） -->
            <div class='px-4 pb-3 flex-grow' style='min-height: 200px;'>
                <!-- カテゴリと地域 -->
                <div class='flex items-center gap-2 mb-3'>
                    <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'>
                        {$category}
                    </span>
                    <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'>
                        📍 {$prefecture}
                    </span>
                </div>
                
                <!-- 金額表示（メインフォーカス） -->
                <div class='bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-lg p-3 mb-3 border border-emerald-100 dark:border-emerald-800'>
                    <div class='text-xs text-gray-600 dark:text-gray-400 mb-1'>最大助成額</div>
                    <div class='text-xl font-bold text-emerald-700 dark:text-emerald-400'>
                        {$amount}
                    </div>
                </div>
                
                <!-- 詳細情報グリッド -->
                <div class='grid grid-cols-2 gap-2 text-xs'>
                    <div class='bg-gray-50 dark:bg-gray-700/50 rounded p-2'>
                        <div class='text-gray-500 dark:text-gray-400 mb-0.5'>締切</div>
                        <div class='font-medium text-gray-900 dark:text-gray-100 truncate'>{$deadline}</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-gray-700/50 rounded p-2'>
                        <div class='text-gray-500 dark:text-gray-400 mb-0.5'>採択率</div>
                        <div class='font-medium {$success_color}'>{$success_rate}%</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-gray-700/50 rounded p-2'>
                        <div class='text-gray-500 dark:text-gray-400 mb-0.5'>難易度</div>
                        <div class='font-medium'>{$difficulty_display}</div>
                    </div>
                    <div class='bg-gray-50 dark:bg-gray-700/50 rounded p-2'>
                        <div class='text-gray-500 dark:text-gray-400 mb-0.5'>実施機関</div>
                        <div class='font-medium text-gray-900 dark:text-gray-100 truncate' title='{$organization}'>{$organization}</div>
                    </div>
                </div>
            </div>
            
            <!-- フッター（固定高さ） -->
            <div class='px-4 pb-4 pt-3 border-t border-gray-100 dark:border-gray-700 mt-auto' style='min-height: 60px;'>
                <div class='flex items-center justify-between'>
                    <a href='{$permalink}' class='flex-1 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-center py-2 px-4 rounded-lg transition-all duration-200 text-sm font-medium shadow-sm hover:shadow'>
                        詳細を見る
                    </a>
                    <button class='ml-2 p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors share-btn' data-url='{$permalink}' data-title='{$title}'>
                        <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m9.032 4.026a9.001 9.001 0 01-7.432 0m9.032-4.026A9.001 9.001 0 0112 3c-4.474 0-8.268 3.12-9.032 7.326m0 0A9.001 9.001 0 0012 21c4.474 0 8.268-3.12 9.032-7.326'></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>";
}

/**
 * モダンなカードデザイン生成関数（リスト表示用・ダークモード対応）
 */
function gi_render_modern_grant_list_card($grant_data) {
    $post_id = $grant_data['id'];
    $title = esc_html($grant_data['title']);
    $permalink = esc_url($grant_data['permalink']);
    $excerpt = esc_html($grant_data['excerpt']);
    $organization = esc_html($grant_data['organization']);
    
    // 4-helper-functions.phpのgi_format_amount_manを使用、または拡張版を使用
    $amount = gi_format_amount_with_unit($grant_data['amount_numeric'] ?: $grant_data['amount']);
    
    $deadline = esc_html($grant_data['deadline']);
    $status = esc_html($grant_data['status']);
    $prefecture = esc_html($grant_data['prefecture']);
    $category = esc_html($grant_data['main_category']);
    $success_rate = intval($grant_data['success_rate']);
    $difficulty = esc_html($grant_data['difficulty']);
    
    // ステータスに応じた色設定
    $status_bg = '';
    $status_bg_dark = '';
    $status_text = '';
    switch($status) {
        case '募集中':
        case 'active':
            $status_bg = 'bg-emerald-50';
            $status_bg_dark = 'dark:bg-emerald-900/30';
            $status_text = 'text-emerald-700 dark:text-emerald-400';
            break;
        case '準備中':
        case 'upcoming':
            $status_bg = 'bg-blue-50';
            $status_bg_dark = 'dark:bg-blue-900/30';
            $status_text = 'text-blue-700 dark:text-blue-400';
            break;
        case '終了':
        case 'closed':
            $status_bg = 'bg-gray-50';
            $status_bg_dark = 'dark:bg-gray-800';
            $status_text = 'text-gray-700 dark:text-gray-400';
            break;
        default:
            $status_bg = 'bg-gray-50';
            $status_bg_dark = 'dark:bg-gray-800';
            $status_text = 'text-gray-700 dark:text-gray-400';
    }
    
    return "
    <div class='grant-list-modern bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 dark:border-gray-700 overflow-hidden mb-4' style='height: 180px;'>
        <div class='p-4 h-full flex'>
            <!-- 左側：メイン情報 -->
            <div class='flex-grow pr-4' style='flex: 1 1 70%;'>
                <div class='flex items-start justify-between mb-2'>
                    <div class='flex items-center gap-2'>
                        <span class='inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {$status_bg} {$status_bg_dark} {$status_text}'>
                            <span class='w-1.5 h-1.5 bg-current rounded-full mr-1.5'></span>
                            {$status}
                        </span>
                        <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400'>
                            {$category}
                        </span>
                        <span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'>
                            📍 {$prefecture}
                        </span>
                    </div>
                </div>
                
                <h3 class='text-base font-bold text-gray-900 dark:text-gray-100 mb-2 leading-tight' style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;'>
                    <a href='{$permalink}' class='hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors'>
                        {$title}
                    </a>
                </h3>
                
                <p class='text-xs text-gray-600 dark:text-gray-400 mb-3' style='display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;'>
                    {$excerpt}
                </p>
                
                <!-- 詳細情報 -->
                <div class='flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400'>
                    <span class='flex items-center'>
                        <svg class='w-3.5 h-3.5 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'></path>
                        </svg>
                        {$organization}
                    </span>
                    <span class='flex items-center'>
                        <svg class='w-3.5 h-3.5 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'></path>
                        </svg>
                        締切: {$deadline}
                    </span>
                    <span class='flex items-center'>
                        採択率: <span class='ml-1 font-semibold " . ($success_rate >= 70 ? 'text-green-600 dark:text-green-400' : ($success_rate >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400')) . "'>{$success_rate}%</span>
                    </span>
                </div>
            </div>
            
            <!-- 右側：金額とアクション -->
            <div class='flex flex-col items-end justify-between pl-4 border-l border-gray-100 dark:border-gray-700' style='flex: 0 0 200px;'>
                <div class='text-right mb-3'>
                    <div class='text-xs text-gray-500 dark:text-gray-400 mb-1'>最大助成額</div>
                    <div class='text-xl font-bold text-emerald-700 dark:text-emerald-400'>
                        {$amount}
                    </div>
                </div>
                
                <div class='flex flex-col gap-2 w-full'>
                    <a href='{$permalink}' class='bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-center py-2 px-4 rounded-lg transition-all duration-200 text-sm font-medium shadow-sm hover:shadow'>
                        詳細を見る
                    </a>
                    <div class='flex gap-2'>
                        <button class='flex-1 p-2 text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition-colors favorite-btn' data-post-id='{$post_id}'>
                            <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'></path>
                            </svg>
                        </button>
                        <button class='flex-1 p-2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors share-btn' data-url='{$permalink}' data-title='{$title}'>
                            <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m9.032 4.026a9.001 9.001 0 01-7.432 0'></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>";
}

/**
 * 【追加】デバッグ用AJAX関数 - 助成金データの確認用（ヘルパー関数使用）
 */
function gi_ajax_debug_grants() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // 助成金の投稿数を確認
    $grant_count = wp_count_posts('grant');
    
    // 最新の助成金10件を取得
    $recent_grants = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ));
    
    $grants_data = array();
    foreach ($recent_grants as $grant) {
        $grants_data[] = array(
            'id' => $grant->ID,
            'title' => $grant->post_title,
            'status' => get_post_status($grant->ID),
            'meta_fields' => array(
                'max_amount' => gi_safe_get_meta($grant->ID, 'max_amount', ''),
                'max_amount_numeric' => gi_safe_get_meta($grant->ID, 'max_amount_numeric', 0),
                'grant_difficulty' => gi_safe_get_meta($grant->ID, 'grant_difficulty', ''),
                'grant_success_rate' => gi_safe_get_meta($grant->ID, 'grant_success_rate', 0),
                'application_status' => gi_safe_get_meta($grant->ID, 'application_status', ''),
                'deadline_formatted' => function_exists('gi_get_formatted_deadline') ? gi_get_formatted_deadline($grant->ID) : gi_safe_get_meta($grant->ID, 'deadline_date', ''),
                'organization' => gi_safe_get_meta($grant->ID, 'organization', ''),
            ),
            'categories' => function_exists('gi_get_post_categories') ? gi_get_post_categories($grant->ID) : wp_get_post_terms($grant->ID, 'grant_category', array('fields' => 'names')),
        );
    }
    
    wp_send_json_success(array(
        'total_grants' => $grant_count,
        'recent_grants' => $grants_data,
        'template_path' => get_template_directory() . '/template-parts/grant-card-v4-enhanced.php',
        'template_exists' => file_exists(get_template_directory() . '/template-parts/grant-card-v4-enhanced.php'),
        'helper_functions_available' => array(
            'gi_safe_get_meta' => function_exists('gi_safe_get_meta'),
            'gi_get_formatted_deadline' => function_exists('gi_get_formatted_deadline'),
            'gi_map_application_status_ui' => function_exists('gi_map_application_status_ui'),
            'gi_get_post_categories' => function_exists('gi_get_post_categories'),
            'gi_format_amount_man' => function_exists('gi_format_amount_man'),
            'gi_format_amount_with_unit' => function_exists('gi_format_amount_with_unit'),
        )
    ));
}
add_action('wp_ajax_gi_debug_grants', 'gi_ajax_debug_grants');
add_action('wp_ajax_nopriv_gi_debug_grants', 'gi_ajax_debug_grants');

/**
 * AJAX - Search suggestions（ヘルパー関数使用）
 */
function gi_ajax_get_search_suggestions() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $query = sanitize_text_field($_POST['query'] ?? '');
    $suggestions = array();
    if ($query !== '') {
        $args = array(
            's' => $query,
            'post_type' => array('grant','tool','case_study','guide','grant_tip'),
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'fields' => 'ids'
        );
        $posts = get_posts($args);
        foreach ($posts as $pid) {
            $suggestions[] = array(
                'label' => get_the_title($pid),
                'value' => get_the_title($pid),
                'url' => function_exists('gi_safe_url') ? gi_safe_url(get_permalink($pid)) : get_permalink($pid),
                'type' => get_post_type($pid)
            );
        }
    }
    wp_send_json_success($suggestions);
}
add_action('wp_ajax_get_search_suggestions', 'gi_ajax_get_search_suggestions');
add_action('wp_ajax_nopriv_get_search_suggestions', 'gi_ajax_get_search_suggestions');

/**
 * AJAX - Advanced search（ヘルパー関数使用）
 */
function gi_ajax_advanced_search() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $keyword = sanitize_text_field($_POST['search_query'] ?? ($_POST['s'] ?? ''));
    $prefecture = sanitize_text_field($_POST['prefecture'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');
    $amount = sanitize_text_field($_POST['amount'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');

    $tax_query = array('relation' => 'AND');
    if ($prefecture) {
        $tax_query[] = array('taxonomy'=>'grant_prefecture','field'=>'slug','terms'=>array($prefecture),'operator'=>'IN');
    }
    if ($category) {
        $tax_query[] = array('taxonomy'=>'grant_category','field'=>'slug','terms'=>array($category),'operator'=>'IN');
    }

    $meta_query = array('relation' => 'AND');
    if ($amount) {
        switch ($amount) {
            case '0-100':
                $meta_query[] = array('key'=>'max_amount_numeric','value'=>1000000,'compare'=>'<=','type'=>'NUMERIC');
                break;
            case '100-500':
                $meta_query[] = array('key'=>'max_amount_numeric','value'=>array(1000000,5000000),'compare'=>'BETWEEN','type'=>'NUMERIC');
                break;
            case '500-1000':
                $meta_query[] = array('key'=>'max_amount_numeric','value'=>array(5000000,10000000),'compare'=>'BETWEEN','type'=>'NUMERIC');
                break;
            case '1000+':
                $meta_query[] = array('key'=>'max_amount_numeric','value'=>10000000,'compare'=>'>=','type'=>'NUMERIC');
                break;
        }
    }
    if ($status) {
        $status = $status === 'active' ? 'open' : $status;
        $meta_query[] = array('key'=>'application_status','value'=>array($status),'compare'=>'IN');
    }

    $args = array(
        'post_type' => 'grant',
        'post_status' => 'publish',
        'posts_per_page' => 6,
        's' => $keyword,
    );
    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;
    if (count($meta_query) > 1) $args['meta_query'] = $meta_query;

    $q = new WP_Query($args);
    $html = '';
    if ($q->have_posts()) {
        ob_start();
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">';
        while ($q->have_posts()) { 
            $q->the_post();
            $post_id = get_the_ID();
            
            // データ収集
            $grant_terms = get_the_terms($post_id, 'grant_category');
            $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
            
            $grant_data = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => get_the_excerpt(),
                'thumbnail' => get_the_post_thumbnail_url($post_id, 'gi-card-thumb'),
                'main_category' => (!is_wp_error($grant_terms) && !empty($grant_terms)) ? $grant_terms[0]->name : '',
                'prefecture' => (!is_wp_error($prefecture_terms) && !empty($prefecture_terms)) ? $prefecture_terms[0]->name : '',
                'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                'deadline' => function_exists('gi_get_formatted_deadline') ? gi_get_formatted_deadline($post_id) : gi_safe_get_meta($post_id, 'deadline_date', ''),
                'amount' => gi_safe_get_meta($post_id, 'max_amount', '-'),
                'amount_numeric' => gi_safe_get_meta($post_id, 'max_amount_numeric', 0),
                'deadline_timestamp' => gi_safe_get_meta($post_id, 'deadline_date', ''),
                'status' => function_exists('gi_map_application_status_ui') ? gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open')) : gi_safe_get_meta($post_id, 'application_status', 'open'),
                'difficulty' => gi_safe_get_meta($post_id, 'grant_difficulty', ''),
                'success_rate' => gi_safe_get_meta($post_id, 'grant_success_rate', 0),
                'subsidy_rate' => gi_safe_get_meta($post_id, 'subsidy_rate', ''),
                'target_business' => gi_safe_get_meta($post_id, 'target_business', ''),
            );
            
            echo gi_render_modern_grant_card($grant_data);
        }
        echo '</div>';
        $html = ob_get_clean();
        wp_reset_postdata();
    }
    wp_send_json_success(array(
        'html' => $html ?: '<p class="text-gray-500 dark:text-gray-400 text-center py-8">該当する助成金が見つかりませんでした。</p>',
        'count' => $q->found_posts
    ));
}
add_action('wp_ajax_advanced_search', 'gi_ajax_advanced_search');
add_action('wp_ajax_nopriv_advanced_search', 'gi_ajax_advanced_search');

/**
 * AJAX - Grant Insight top page search（ヘルパー関数使用）
 */
function gi_ajax_grant_insight_search() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'grant_insight_search_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'), 403);
    }

    $keyword    = sanitize_text_field($_POST['keyword'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? '');
    $orderby    = sanitize_text_field($_POST['orderby'] ?? 'relevance');
    $category   = sanitize_text_field($_POST['category'] ?? '');
    $amount_min = isset($_POST['amount_min']) ? intval($_POST['amount_min']) : 0;
    $amount_max = isset($_POST['amount_max']) ? intval($_POST['amount_max']) : 0;
    $deadline   = sanitize_text_field($_POST['deadline'] ?? '');
    $page       = max(1, intval($_POST['page'] ?? 1));
    $per_page = 12;

    $post_types = array('grant','tool','case_study','guide','grant_tip');
    if (!empty($post_type)) {
        $post_types = array($post_type);
    }

    $args = array(
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        's'              => $keyword,
        'paged'          => $page,
        'posts_per_page' => $per_page,
    );

    switch ($orderby) {
        case 'date':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'title':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'modified':
            $args['orderby'] = 'modified';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = 'relevance';
            $args['order']   = 'DESC';
            break;
    }

    $tax_query = array('relation' => 'AND');
    if (!empty($category)) {
        $tax_query[] = array(
            'taxonomy' => 'grant_category',
            'field'    => 'term_id',
            'terms'    => array(intval($category)),
        );
    }
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    $meta_query = array('relation' => 'AND');
    if (in_array('grant', $post_types, true) || $post_type === 'grant') {
        if ($amount_min > 0 || $amount_max > 0) {
            $meta_query[] = array(
                'key'     => 'max_amount_numeric',
                'value'   => $amount_max > 0 && $amount_min > 0 ? array($amount_min, $amount_max) : ($amount_max > 0 ? $amount_max : $amount_min),
                'compare' => ($amount_min > 0 && $amount_max > 0) ? 'BETWEEN' : ($amount_max > 0 ? '<=' : '>='),
                'type'    => 'NUMERIC',
            );
        }

        if (!empty($deadline)) {
            $todayYmd = intval(current_time('Ymd'));
            $targetYmd = $todayYmd;
            switch ($deadline) {
                case '1month':
                    $targetYmd = intval(date('Ymd', strtotime('+1 month', current_time('timestamp'))));
                    break;
                case '3months':
                    $targetYmd = intval(date('Ymd', strtotime('+3 months', current_time('timestamp'))));
                    break;
                case '6months':
                    $targetYmd = intval(date('Ymd', strtotime('+6 months', current_time('timestamp'))));
                    break;
                case '1year':
                    $targetYmd = intval(date('Ymd', strtotime('+1 year', current_time('timestamp'))));
                    break;
            }
            $meta_query[] = array(
                'key'     => 'deadline_date',
                'value'   => array($todayYmd, $targetYmd),
                'compare' => 'BETWEEN',
                'type'    => 'NUMERIC',
            );
        }
    }
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    $q = new WP_Query($args);

    $favorites = function_exists('gi_get_user_favorites') ? gi_get_user_favorites() : array();
    $posts = array();
    if ($q->have_posts()) {
        while ($q->have_posts()) { 
            $q->the_post();
            $pid = get_the_ID();
            $ptype = get_post_type($pid);
            $amount_yen = ($ptype === 'grant') ? intval(gi_safe_get_meta($pid, 'max_amount_numeric', 0)) : 0;
            $deadline_date = ($ptype === 'grant') ? gi_safe_get_meta($pid, 'deadline_date', '') : '';

            $posts[] = array(
                'id'        => $pid,
                'title'     => get_the_title($pid),
                'excerpt'   => function_exists('gi_safe_excerpt') ? gi_safe_excerpt(get_the_excerpt($pid), 100) : wp_trim_words(get_the_excerpt($pid), 20),
                'permalink' => function_exists('gi_safe_url') ? gi_safe_url(get_permalink($pid)) : get_permalink($pid),
                'thumbnail' => get_the_post_thumbnail_url($pid, 'medium'),
                'date'      => function_exists('gi_safe_date_format') ? gi_safe_date_format(get_the_date('Y-m-d', $pid)) : get_the_date('Y-m-d', $pid),
                'post_type' => $ptype,
                'amount'    => $amount_yen,
                'amount_formatted' => gi_format_amount_with_unit($amount_yen),
                'deadline'  => $deadline_date,
                'is_featured'=> false,
                'is_favorite'=> in_array($pid, $favorites, true),
            );
        }
        wp_reset_postdata();
    }

    $response = array(
        'posts' => $posts,
        'pagination' => array(
            'current_page' => $page,
            'total_pages'  => max(1, intval($q->max_num_pages)),
        ),
        'total' => intval($q->found_posts),
    );

    wp_send_json_success($response);
}
add_action('wp_ajax_grant_insight_search', 'gi_ajax_grant_insight_search');
add_action('wp_ajax_nopriv_grant_insight_search', 'gi_ajax_grant_insight_search');

/**
 * AJAX - Export search results as CSV（ヘルパー関数使用）
 */
function gi_ajax_grant_insight_export_results() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'grant_insight_search_nonce') && !wp_verify_nonce($nonce, 'gi_ajax_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'), 403);
    }

    $_POST['page'] = 1;
    $_POST['orderby'] = sanitize_text_field($_POST['orderby'] ?? 'date');

    $keyword   = sanitize_text_field($_POST['keyword'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? 'grant');
    $category  = sanitize_text_field($_POST['category'] ?? '');

    $args = array(
        'post_type'      => $post_type ? array($post_type) : array('grant'),
        'post_status'    => 'publish',
        's'              => $keyword,
        'posts_per_page' => 200, // cap export size
        'paged'          => 1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if (!empty($category)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'grant_category',
                'field'    => 'term_id',
                'terms'    => array(intval($category)),
            )
        );
    }

    $q = new WP_Query($args);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="grant_search_results_' . date('Y-m-d') . '.csv"');
    $fp = fopen('php://output', 'w');
    fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

    fputcsv($fp, array('ID','Title','Permalink','Post Type','Date','Amount(yen)','Deadline','Organization'));
    if ($q->have_posts()) {
        while ($q->have_posts()) { 
            $q->the_post();
            $pid = get_the_ID();
            $ptype = get_post_type($pid);
            $amount_yen = ($ptype === 'grant') ? intval(gi_safe_get_meta($pid, 'max_amount_numeric', 0)) : 0;
            $deadline_date = ($ptype === 'grant') ? gi_safe_get_meta($pid, 'deadline_date', '') : '';
            $organization = ($ptype === 'grant') ? gi_safe_get_meta($pid, 'organization', '') : '';
            
            fputcsv($fp, array(
                $pid,
                get_the_title($pid),
                function_exists('gi_safe_url') ? gi_safe_url(get_permalink($pid)) : get_permalink($pid),
                $ptype,
                function_exists('gi_safe_date_format') ? gi_safe_date_format(get_the_date('Y-m-d', $pid)) : get_the_date('Y-m-d', $pid),
                gi_format_amount_with_unit($amount_yen),
                function_exists('gi_safe_date_format') ? gi_safe_date_format($deadline_date, 'Y-m-d') : $deadline_date,
                $organization,
            ));
        }
        wp_reset_postdata();
    }
    fclose($fp);
    exit;
}
add_action('wp_ajax_grant_insight_export_results', 'gi_ajax_grant_insight_export_results');
add_action('wp_ajax_nopriv_grant_insight_export_results', 'gi_ajax_grant_insight_export_results');

/**
 * AJAX - Newsletter signup（ヘルパー関数使用）
 */
function gi_ajax_newsletter_signup() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $email = sanitize_email($_POST['email'] ?? '');
    if (!$email || !is_email($email)) {
        wp_send_json_error('メールアドレスが正しくありません');
    }
    $list = get_option('gi_newsletter_list', array());
    if (!is_array($list)) $list = array();
    if (!in_array($email, $list)) {
        $list[] = $email;
        update_option('gi_newsletter_list', $list);
    }
    wp_send_json_success(array(
        'message' => '登録しました',
        'email' => function_exists('gi_safe_escape') ? gi_safe_escape($email) : esc_html($email)
    ));
}
add_action('wp_ajax_newsletter_signup', 'gi_ajax_newsletter_signup');
add_action('wp_ajax_nopriv_newsletter_signup', 'gi_ajax_newsletter_signup');

/**
 * AJAX - Affiliate click tracking（ヘルパー関数使用）
 */
function gi_ajax_track_affiliate_click() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $url = function_exists('gi_safe_url') ? gi_safe_url($_POST['url'] ?? '') : esc_url($_POST['url'] ?? '');
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$url) wp_send_json_error('URLが無効です');
    
    $log = get_option('gi_affiliate_clicks', array());
    if (!is_array($log)) $log = array();
    $log[] = array(
        'time' => current_time('timestamp'), 
        'url' => $url, 
        'post_id' => $post_id, 
        'ip' => function_exists('gi_safe_escape') ? gi_safe_escape($_SERVER['REMOTE_ADDR'] ?? '') : esc_html($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => function_exists('gi_safe_escape') ? gi_safe_escape($_SERVER['HTTP_USER_AGENT'] ?? '') : esc_html($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    update_option('gi_affiliate_clicks', $log);
    wp_send_json_success(array('message' => 'ok'));
}
add_action('wp_ajax_track_affiliate_click', 'gi_ajax_track_affiliate_click');
add_action('wp_ajax_nopriv_track_affiliate_click', 'gi_ajax_track_affiliate_click');

/**
 * AJAX - Related grants (新カードデザイン対応・ヘルパー関数使用)
 */
function gi_ajax_get_related_grants() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'get_related_grants_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    $category_name = sanitize_text_field($_POST['category'] ?? '');
    $prefecture_name = sanitize_text_field($_POST['prefecture'] ?? '');

    $tax_query = array('relation' => 'AND');
    if ($category_name) {
        $term = get_term_by('name', $category_name, 'grant_category');
        if ($term) {
            $tax_query[] = array('taxonomy'=>'grant_category','field'=>'term_id','terms'=>array($term->term_id));
        }
    }
    if ($prefecture_name) {
        $term = get_term_by('name', $prefecture_name, 'grant_prefecture');
        if ($term) {
            $tax_query[] = array('taxonomy'=>'grant_prefecture','field'=>'term_id','terms'=>array($term->term_id));
        }
    }

    $args = array(
        'post_type' => 'grant',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'post__not_in' => array($post_id),
    );
    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;

    $q = new WP_Query($args);
    $html = '';
    if ($q->have_posts()) {
        ob_start();
        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';
        while ($q->have_posts()) { 
            $q->the_post();
            $post_id = get_the_ID();
            
            // データ収集
            $grant_terms = get_the_terms($post_id, 'grant_category');
            $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
            
            $grant_data = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'excerpt' => get_the_excerpt(),
                'thumbnail' => get_the_post_thumbnail_url($post_id, 'gi-card-thumb'),
                'main_category' => (!is_wp_error($grant_terms) && !empty($grant_terms)) ? $grant_terms[0]->name : '',
                'prefecture' => (!is_wp_error($prefecture_terms) && !empty($prefecture_terms)) ? $prefecture_terms[0]->name : '',
                'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                'deadline' => function_exists('gi_get_formatted_deadline') ? gi_get_formatted_deadline($post_id) : gi_safe_get_meta($post_id, 'deadline_date', ''),
                'amount' => gi_safe_get_meta($post_id, 'max_amount', '-'),
                'amount_numeric' => gi_safe_get_meta($post_id, 'max_amount_numeric', 0),
                'deadline_timestamp' => gi_safe_get_meta($post_id, 'deadline_date', ''),
                'status' => function_exists('gi_map_application_status_ui') ? gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open')) : gi_safe_get_meta($post_id, 'application_status', 'open'),
                'difficulty' => gi_safe_get_meta($post_id, 'grant_difficulty', ''),
                'success_rate' => gi_safe_get_meta($post_id, 'grant_success_rate', 0),
                'subsidy_rate' => gi_safe_get_meta($post_id, 'subsidy_rate', ''),
                'target_business' => gi_safe_get_meta($post_id, 'target_business', ''),
            );
            
            echo gi_render_modern_grant_card($grant_data);
        }
        echo '</div>';
        $html = ob_get_clean();
        wp_reset_postdata();
    }
    wp_send_json_success(array('html' => $html));
}
add_action('wp_ajax_get_related_grants', 'gi_ajax_get_related_grants');
add_action('wp_ajax_nopriv_get_related_grants', 'gi_ajax_get_related_grants');

/**
 * 【修正版】AJAX - お気に入り機能（新カードデザイン対応・ヘルパー関数使用）
 */
function gi_ajax_toggle_favorite() {
    $nonce_check1 = wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce');
    $nonce_check2 = wp_verify_nonce($_POST['nonce'] ?? '', 'grant_insight_search_nonce');
    
    if (!$nonce_check1 && !$nonce_check2) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    $post_id = intval($_POST['post_id']);
    $user_id = get_current_user_id();
    
    if (!$post_id || !get_post($post_id)) {
        wp_send_json_error('無効な投稿IDです');
    }
    
    if (!$user_id) {
        $cookie_name = 'gi_favorites';
        $favorites = isset($_COOKIE[$cookie_name]) ? array_filter(explode(',', $_COOKIE[$cookie_name])) : array();
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, array($post_id));
            $action = 'removed';
            $icon_class = 'far'; // 空のハート
        } else {
            $favorites[] = $post_id;
            $action = 'added';
            $icon_class = 'fas'; // 塗りつぶしハート
        }
        
        setcookie($cookie_name, implode(',', $favorites), time() + (86400 * 30), '/');
    } else {
        $favorites = function_exists('gi_get_user_favorites') ? gi_get_user_favorites($user_id) : (get_user_meta($user_id, 'gi_favorites', true) ?: array());
        
        if (in_array($post_id, $favorites)) {
            $favorites = array_diff($favorites, array($post_id));
            $action = 'removed';
            $icon_class = 'far';
        } else {
            $favorites[] = $post_id;
            $action = 'added';
            $icon_class = 'fas';
        }
        
        update_user_meta($user_id, 'gi_favorites', $favorites);
    }
    
    wp_send_json_success(array(
        'action' => $action,
        'post_id' => $post_id,
        'post_title' => function_exists('gi_safe_escape') ? gi_safe_escape(get_the_title($post_id)) : esc_html(get_the_title($post_id)),
        'count' => count($favorites),
        'is_favorite' => $action === 'added',
        'icon_class' => $icon_class,
        'message' => $action === 'added' ? 'お気に入りに追加しました' : 'お気に入りから削除しました'
    ));
}
add_action('wp_ajax_gi_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_gi_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_grant_insight_toggle_favorite', 'gi_ajax_toggle_favorite');
add_action('wp_ajax_nopriv_grant_insight_toggle_favorite', 'gi_ajax_toggle_favorite');

/**
 * AJAX - ビジネスツール読み込み処理（ダークモード対応）
 */
function gi_ajax_load_tools() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }

    $search = sanitize_text_field($_POST['keyword'] ?? '');
    $category = sanitize_text_field($_POST['category'] ?? '');
    $price_range = sanitize_text_field($_POST['price_range'] ?? '');
    $rating = sanitize_text_field($_POST['rating'] ?? '');
    $features = sanitize_text_field($_POST['features'] ?? '');
    $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'date');
    $sort_order = sanitize_text_field($_POST['sort_order'] ?? 'DESC');
    $posts_per_page = intval($_POST['posts_per_page'] ?? 12);
    $page = intval($_POST['page'] ?? 1);

    $args = array(
        'post_type' => 'tool',
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged' => $page,
    );

    if (!empty($search)) {
        $args['s'] = $search;
    }

    if (!empty($category)) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'tool_category',
                'field' => 'slug',
                'terms' => $category,
            ),
        );
    }

    $meta_query = array('relation' => 'AND');
    
    if (!empty($price_range)) {
        switch ($price_range) {
            case 'free':
                $meta_query[] = array(
                    'key' => 'price_free',
                    'value' => '1',
                    'compare' => '='
                );
                break;
            case '0-5000':
                $meta_query[] = array(
                    'key' => 'price_monthly',
                    'value' => 5000,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                );
                break;
            case '5001-20000':
                $meta_query[] = array(
                    'key' => 'price_monthly',
                    'value' => array(5001, 20000),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                );
                break;
            case '20001':
                $meta_query[] = array(
                    'key' => 'price_monthly',
                    'value' => 20001,
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                );
                break;
        }
    }

    if (!empty($rating)) {
        $meta_query[] = array(
            'key' => 'rating',
            'value' => floatval($rating),
            'compare' => '>=',
            'type' => 'DECIMAL'
        );
    }

    if (!empty($features)) {
        $meta_query[] = array(
            'key' => 'features',
            'value' => $features,
            'compare' => 'LIKE'
        );
    }

    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    
    switch ($sort_by) {
        case 'title':
            $args['orderby'] = 'title';
            break;
        case 'rating':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'rating';
            break;
        case 'price':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'price_monthly';
            break;
        case 'views':
            $args['orderby'] = 'meta_value_num';
            $args['meta_key'] = 'view_count';
            break;
        default: // date
            $args['orderby'] = 'date';
            break;
    }
    $args['order'] = $sort_order;

    $query = new WP_Query($args);
    $tools = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            $tools[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'permalink' => function_exists('gi_safe_url') ? gi_safe_url(get_permalink()) : get_permalink(),
                'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
                'excerpt' => function_exists('gi_safe_excerpt') ? gi_safe_excerpt(get_the_excerpt(), 80) : wp_trim_words(get_the_excerpt(), 15),
                'rating' => gi_safe_get_meta($post_id, 'rating', '4.5'),
                'price' => gi_safe_get_meta($post_id, 'price_monthly', '無料'),
                'price_free' => gi_safe_get_meta($post_id, 'price_free', '0'),
            );
        }
    }
    wp_reset_postdata();

    ob_start();
    if (!empty($tools)) {
        echo '<div class="search-results-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">';
        foreach ($tools as $tool) {
            $price_display = $tool['price_free'] === '1' ? '無料プランあり' : '¥' . (function_exists('gi_safe_number_format') ? gi_safe_number_format(intval($tool['price'])) : number_format(intval($tool['price']))) . '/月';
            if (!is_numeric($tool['price'])) {
                $price_display = function_exists('gi_safe_escape') ? gi_safe_escape($tool['price']) : esc_html($tool['price']);
            }
            ?>
            <div class="tool-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <?php if ($tool['thumbnail']) : ?>
                                <img src="<?php echo esc_url($tool['thumbnail']); ?>" alt="<?php echo function_exists('gi_safe_attr') ? gi_safe_attr($tool['title']) : esc_attr($tool['title']); ?>" class="w-full h-full object-cover rounded-xl">
                            <?php else : ?>
                                <i class="fas fa-tools text-white text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1 text-yellow-500">
                            <?php 
                            $rating = floatval($tool['rating']);
                            $full_stars = floor($rating);
                            $half_star = ($rating - $full_stars) >= 0.5;
                            
                            for ($i = 0; $i < $full_stars; $i++) {
                                echo '⭐';
                            }
                            if ($half_star) {
                                echo '⭐';
                            }
                            ?>
                            <span class="text-sm text-gray-600 dark:text-gray-400 ml-1">(<?php echo function_exists('gi_safe_escape') ? gi_safe_escape($tool['rating']) : esc_html($tool['rating']); ?>)</span>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                        <a href="<?php echo esc_url($tool['permalink']); ?>" class="hover:text-indigo-600 dark:hover:text-indigo-400"><?php echo function_exists('gi_safe_escape') ? gi_safe_escape($tool['title']) : esc_html($tool['title']); ?></a>
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3">
                        <?php echo $tool['excerpt']; ?>
                    </p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-400 px-3 py-1 rounded-full font-medium">
                            <?php echo $price_display; ?>
                        </span>
                        <a href="<?php echo esc_url($tool['permalink']); ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-semibold">
                            詳細を見る →
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-20">
                <div class="w-32 h-32 bg-gradient-to-r from-indigo-400 via-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-8">
                    <i class="fas fa-tools text-white text-4xl"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">該当するツールが見つかりませんでした</h3>
                <p class="text-gray-600 dark:text-gray-400 max-w-2xl mx-auto text-lg leading-relaxed">
                    検索条件を変更して再度お試しください。
                </p>
            </div>';
    }
    $html = ob_get_clean();

    wp_send_json_success(array(
        'html' => $html,
        'stats' => array(
            'total_found' => $query->found_posts,
            'current_page' => $page,
            'total_pages' => $query->max_num_pages,
        ),
    ));
}
add_action('wp_ajax_gi_load_tools', 'gi_ajax_load_tools');
add_action('wp_ajax_nopriv_gi_load_tools', 'gi_ajax_load_tools');

/**
 * AJAX - 申請のコツ読み込み処理（ダークモード対応）
 */
function gi_ajax_load_grant_tips() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }

    $args = array(
        'post_type'      => 'grant_tip',
        'posts_per_page' => 9,
        'paged'          => intval($_POST['page'] ?? 1),
        'post_status'    => 'publish',
    );

    if (!empty($_POST['s'])) {
        $args['s'] = sanitize_text_field($_POST['s']);
    }

    $tax_query = array();
    if (!empty($_POST['grant_tip_category'])) {
        $tax_query[] = array(
            'taxonomy' => 'grant_tip_category',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_POST['grant_tip_category']),
        );
    }
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $meta_query = array();
    if (!empty($_POST['difficulty'])) {
        $meta_query[] = array(
            'key'   => 'difficulty',
            'value' => sanitize_text_field($_POST['difficulty']),
            'compare' => '='
        );
    }
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'date_desc');
    if ($sort_by === 'popular') {
        $args['orderby'] = 'comment_count';
        $args['order']   = 'DESC';
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    $query = new WP_Query($args);

    ob_start();
    if ($query->have_posts()) {
        echo '<div class="search-results-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">';
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            ?>
            <div class="tip-card bg-white dark:bg-gray-800 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center">
                            <?php if (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('thumbnail', array('class' => 'w-full h-full object-cover rounded-xl')); ?>
                            <?php else : ?>
                                <i class="fas fa-lightbulb text-white text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 line-clamp-2">
                                <a href="<?php echo function_exists('gi_safe_url') ? gi_safe_url(get_permalink()) : esc_url(get_permalink()); ?>" class="hover:text-yellow-600 dark:hover:text-yellow-400"><?php echo function_exists('gi_safe_escape') ? gi_safe_escape(get_the_title()) : esc_html(get_the_title()); ?></a>
                            </h3>
                        </div>
                    </div>
                    
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-4 line-clamp-3">
                        <?php echo function_exists('gi_safe_excerpt') ? gi_safe_excerpt(get_the_excerpt(), 75) : wp_trim_words(get_the_excerpt(), 15); ?>
                    </p>
                    
                    <div class="flex items-center justify-between text-sm">
                        <span class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-400 px-3 py-1 rounded-full font-medium">
                            <?php echo function_exists('gi_safe_escape') ? gi_safe_escape(gi_safe_get_meta($post_id, 'difficulty', '初級')) : esc_html(gi_safe_get_meta($post_id, 'difficulty', '初級')); ?>
                        </span>
                        <a href="<?php echo function_exists('gi_safe_url') ? gi_safe_url(get_permalink()) : esc_url(get_permalink()); ?>" class="text-yellow-600 dark:text-yellow-400 hover:text-yellow-800 dark:hover:text-yellow-300 font-semibold">
                            詳細を見る →
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-20">
                <div class="w-32 h-32 bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 rounded-full flex items-center justify-center mx-auto mb-8">
                    <i class="fas fa-lightbulb text-white text-5xl"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">該当するコツが見つかりませんでした</h3>
                <p class="text-gray-600 dark:text-gray-400 max-w-2xl mx-auto text-lg leading-relaxed">
                    検索条件を変更して再度お試しください。
                </p>
            </div>';
    }
    $html = ob_get_clean();
    
    ob_start();
    if ($query->max_num_pages > 1) {
        echo paginate_links([
            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
            'format' => '?paged=%#%',
            'current' => max(1, $args['paged']),
            'total' => $query->max_num_pages,
            'prev_text' => '<i class="fas fa-chevron-left"></i>',
            'next_text' => '<i class="fas fa-chevron-right"></i>',
            'type' => 'list',
        ]);
    }
    $pagination = ob_get_clean();

    wp_reset_postdata();

    wp_send_json_success(array(
        'html' => $html,
        'pagination' => $pagination,
        'found_posts' => $query->found_posts
    ));
}
add_action('wp_ajax_gi_load_grant_tips', 'gi_ajax_load_grant_tips');
add_action('wp_ajax_nopriv_gi_load_grant_tips', 'gi_ajax_load_grant_tips');

/**
 * 【新機能】AJAX - カード統計情報取得
 */
function gi_ajax_get_card_statistics() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    // 統計情報の計算
    $total_grants = wp_count_posts('grant')->publish;
    
    $active_grants = get_posts(array(
        'post_type' => 'grant',
        'meta_query' => array(
            array(
                'key' => 'application_status',
                'value' => 'open',
                'compare' => '='
            )
        ),
        'fields' => 'ids'
    ));
    
    // 平均採択率の計算
    $success_rates = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => 'grant_success_rate',
                'value' => 0,
                'compare' => '>'
            )
        )
    ));
    
    $avg_success_rate = 0;
    if (!empty($success_rates)) {
        $total_rate = 0;
        foreach ($success_rates as $grant_id) {
            $rate = intval(gi_safe_get_meta($grant_id, 'grant_success_rate', 0));
            $total_rate += $rate;
        }
        $avg_success_rate = round($total_rate / count($success_rates));
    }
    
    // 平均助成金額の計算
    $amounts = get_posts(array(
        'post_type' => 'grant',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => 'max_amount_numeric',
                'value' => 0,
                'compare' => '>'
            )
        )
    ));
    
    $avg_amount = 0;
    if (!empty($amounts)) {
        $total_amount = 0;
        foreach ($amounts as $grant_id) {
            $amount = intval(gi_safe_get_meta($grant_id, 'max_amount_numeric', 0));
            $total_amount += $amount;
        }
        $avg_amount = round($total_amount / count($amounts));
    }
    
    wp_send_json_success(array(
        'total_grants' => $total_grants,
        'active_grants' => count($active_grants),
        'avg_success_rate' => $avg_success_rate,
        'avg_amount' => $avg_amount,
        'formatted_avg_amount' => gi_format_amount_with_unit($avg_amount),
        'prefecture_count' => wp_count_terms(array('taxonomy' => 'grant_prefecture', 'hide_empty' => false))
    ));
}
add_action('wp_ajax_gi_get_card_statistics', 'gi_ajax_get_card_statistics');
add_action('wp_ajax_nopriv_gi_get_card_statistics', 'gi_ajax_get_card_statistics');

/**
 * 【新機能】AJAX - お気に入り一覧取得
 */
function gi_ajax_get_favorites() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'gi_ajax_nonce')) {
        wp_send_json_error('セキュリティチェックに失敗しました');
    }
    
    $user_id = get_current_user_id();
    $favorites = array();
    
    if ($user_id) {
        $favorite_ids = get_user_meta($user_id, 'gi_favorites', true);
        $favorite_ids = $favorite_ids ?: array();
    } else {
        $cookie_name = 'gi_favorites';
        $favorite_ids = isset($_COOKIE[$cookie_name]) ? 
            array_filter(array_map('intval', explode(',', $_COOKIE[$cookie_name]))) : 
            array();
    }
    
    if (!empty($favorite_ids)) {
        $args = array(
            'post_type' => 'grant',
            'post__in' => $favorite_ids,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
            'post_status' => 'publish'
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $favorites[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'excerpt' => get_the_excerpt(),
                    'organization' => gi_safe_get_meta($post_id, 'organization', ''),
                    'amount' => gi_format_amount_with_unit(gi_safe_get_meta($post_id, 'max_amount_numeric', 0)),
                    'deadline' => gi_safe_get_meta($post_id, 'deadline_date', ''),
                    'status' => gi_safe_get_meta($post_id, 'application_status', ''),
                    'added_date' => get_the_date('Y-m-d')
                );
            }
            wp_reset_postdata();
        }
    }
    
    wp_send_json_success(array(
        'favorites' => $favorites,
        'count' => count($favorites),
        'user_type' => $user_id ? 'logged_in' : 'guest'
    ));
}
add_action('wp_ajax_gi_get_favorites', 'gi_ajax_get_favorites');
add_action('wp_ajax_nopriv_gi_get_favorites', 'gi_ajax_get_favorites');

/**
 * 【修正】JavaScriptデバッグ情報出力（ヘルパー関数使用）
 */
function gi_add_debug_js() {
    if (is_page_template('archive-grant.php') || is_post_type_archive('grant') || is_page('grants')) {
        ?>
        <script>
        // デバッグ用：AJAX通信の詳細ログ
        window.giDebug = {
            logAjaxCall: function(action, data, response) {
                console.group('Grant Insight AJAX Debug');
                console.log('Action:', action);
                console.log('Request Data:', data);
                console.log('Response:', response);
                console.groupEnd();
            },
            
            testGrantsExist: function() {
                fetch('<?php echo function_exists('gi_safe_url') ? gi_safe_url(admin_url('admin-ajax.php')) : esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'gi_debug_grants',
                        nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Grant Debug Info:', data);
                    if (data.success) {
                        console.log(`Total grants: ${data.data.total_grants.publish}`);
                        console.log(`Template exists: ${data.data.template_exists}`);
                        console.log('Recent grants:', data.data.recent_grants);
                        console.log('Helper functions:', data.data.helper_functions_available);
                    }
                })
                .catch(error => {
                    console.error('Debug test failed:', error);
                });
            }
        };
        
        // ページ読み込み完了後にデバッグ情報を出力
        document.addEventListener('DOMContentLoaded', function() {
            if (window.location.search.includes('debug=1')) {
                console.log('Grant Insight Debug Mode Enabled');
                window.giDebug.testGrantsExist();
            }
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'gi_add_debug_js');

/**
 * JavaScript用のAJAX設定出力
 */
function gi_ajax_javascript_config() {
    if (is_page_template('archive-grant.php') || is_post_type_archive('grant') || is_page('grants')) {
        ?>
        <script>
        // Grant Insight AJAX 設定
        window.giAjaxConfig = {
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>',
            debug: <?php echo WP_DEBUG ? 'true' : 'false'; ?>,
            version: '<?php echo wp_get_theme()->get('Version'); ?>',
            
            // エラーハンドリング
            handleError: function(error, action) {
                console.error('Grant Insight AJAX Error:', error);
                if (this.debug) {
                    console.log('Action:', action);
                    console.log('Error details:', error);
                }
            },
            
            // 成功時のログ
            logSuccess: function(data, action) {
                if (this.debug) {
                    console.log('Grant Insight AJAX Success:', action, data);
                }
            }
        };
        </script>
        <?php
    }
}
add_action('wp_footer', 'gi_ajax_javascript_config');