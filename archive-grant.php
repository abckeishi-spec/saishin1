<?php
/**
 * Grant Archive Template - Enhanced UX Edition v5.0
 * File: archive-grant.php
 * 
 * 完全にfunctions.phpおよび3-ajax-functions.phpと連携する最適化版
 * フィルターの即時反映とUI/UXの改善版
 * 
 * @package Grant_Insight_Perfect
 * @version 5.0.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// 必要な関数の存在確認
$required_functions = [
    'gi_safe_get_meta',
    'gi_get_formatted_deadline',
    'gi_map_application_status_ui',
    'gi_format_amount_man',
    'gi_get_user_favorites'
];

$missing_functions = array_filter($required_functions, function($func) {
    return !function_exists($func);
});

// URLパラメータから検索条件を取得（サニタイズ済み）
$search_params = [
    'search' => sanitize_text_field($_GET['s'] ?? ''),
    'category' => sanitize_text_field($_GET['category'] ?? ''),
    'prefecture' => sanitize_text_field($_GET['prefecture'] ?? ''),
    'amount' => sanitize_text_field($_GET['amount'] ?? ''),
    'status' => sanitize_text_field($_GET['status'] ?? ''),
    'difficulty' => sanitize_text_field($_GET['difficulty'] ?? ''),
    'success_rate' => sanitize_text_field($_GET['success_rate'] ?? ''),
    'sort' => sanitize_text_field($_GET['sort'] ?? 'date_desc'),
    'view' => sanitize_text_field($_GET['view'] ?? 'grid'),
    'page' => max(1, intval($_GET['paged'] ?? 1))
];

// 統計データ取得（functions.phpのヘルパー関数を使用）
$stats = function_exists('gi_get_cached_stats') ? gi_get_cached_stats() : [
    'total_grants' => wp_count_posts('grant')->publish ?? 0,
    'active_grants' => 0,
    'prefecture_count' => 0,
    'avg_success_rate' => 0
];

// お気に入りリスト取得
$user_favorites = function_exists('gi_get_user_favorites') ? gi_get_user_favorites() : [];

// 初期表示用クエリ
$initial_args = [
    'post_type' => 'grant',
    'posts_per_page' => 12,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
    'no_found_rows' => false
];

// 検索条件の適用
if (!empty($search_params['search'])) {
    $initial_args['s'] = $search_params['search'];
}

// タクソノミークエリ
$tax_query = ['relation' => 'AND'];
if (!empty($search_params['category'])) {
    $tax_query[] = [
        'taxonomy' => 'grant_category',
        'field' => 'slug',
        'terms' => $search_params['category']
    ];
}
if (!empty($search_params['prefecture'])) {
    $tax_query[] = [
        'taxonomy' => 'grant_prefecture',
        'field' => 'slug',
        'terms' => $search_params['prefecture']
    ];
}
if (count($tax_query) > 1) {
    $initial_args['tax_query'] = $tax_query;
}

// メタクエリ
$meta_query = ['relation' => 'AND'];
if (!empty($search_params['status'])) {
    $db_status = $search_params['status'] === 'active' ? 'open' : $search_params['status'];
    $meta_query[] = [
        'key' => 'application_status',
        'value' => $db_status,
        'compare' => '='
    ];
}
if (!empty($search_params['difficulty'])) {
    $meta_query[] = [
        'key' => 'grant_difficulty',
        'value' => $search_params['difficulty'],
        'compare' => '='
    ];
}
if (!empty($search_params['success_rate'])) {
    switch($search_params['success_rate']) {
        case 'high':
            $meta_query[] = [
                'key' => 'grant_success_rate',
                'value' => 70,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
            break;
        case 'medium':
            $meta_query[] = [
                'key' => 'grant_success_rate',
                'value' => [50, 69],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
            break;
        case 'low':
            $meta_query[] = [
                'key' => 'grant_success_rate',
                'value' => 50,
                'compare' => '<',
                'type' => 'NUMERIC'
            ];
            break;
    }
}
if (!empty($search_params['amount'])) {
    switch($search_params['amount']) {
        case '0-100':
            $meta_query[] = [
                'key' => 'max_amount_numeric',
                'value' => 1000000,
                'compare' => '<=',
                'type' => 'NUMERIC'
            ];
            break;
        case '100-500':
            $meta_query[] = [
                'key' => 'max_amount_numeric',
                'value' => [1000001, 5000000],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
            break;
        case '500-1000':
            $meta_query[] = [
                'key' => 'max_amount_numeric',
                'value' => [5000001, 10000000],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
            break;
        case '1000-3000':
            $meta_query[] = [
                'key' => 'max_amount_numeric',
                'value' => [10000001, 30000000],
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            ];
            break;
        case '3000+':
            $meta_query[] = [
                'key' => 'max_amount_numeric',
                'value' => 30000000,
                'compare' => '>=',
                'type' => 'NUMERIC'
            ];
            break;
    }
}
if (count($meta_query) > 1) {
    $initial_args['meta_query'] = $meta_query;
}

// ソート処理
switch($search_params['sort']) {
    case 'amount_desc':
        $initial_args['orderby'] = 'meta_value_num';
        $initial_args['meta_key'] = 'max_amount_numeric';
        $initial_args['order'] = 'DESC';
        break;
    case 'deadline_asc':
        $initial_args['orderby'] = 'meta_value';
        $initial_args['meta_key'] = 'deadline_date';
        $initial_args['order'] = 'ASC';
        break;
    case 'success_rate_desc':
        $initial_args['orderby'] = 'meta_value_num';
        $initial_args['meta_key'] = 'grant_success_rate';
        $initial_args['order'] = 'DESC';
        break;
    case 'date_asc':
        $initial_args['orderby'] = 'date';
        $initial_args['order'] = 'ASC';
        break;
    default:
        $initial_args['orderby'] = 'date';
        $initial_args['order'] = 'DESC';
}

// クエリ実行
$grants_query = new WP_Query($initial_args);

// プリフェッチ（パフォーマンス最適化） - 修正版
if ($grants_query->have_posts()) {
    $post_ids = array();
    foreach ($grants_query->posts as $post_obj) {
        if (is_object($post_obj) && property_exists($post_obj, 'ID')) {
            $post_ids[] = $post_obj->ID;
        }
    }
    
    if (!empty($post_ids)) {
        update_postmeta_cache($post_ids);
        update_object_term_cache($post_ids, array('grant_category', 'grant_prefecture'));
    }
}

// 全カテゴリとタクソノミーを取得（フィルター用）
$all_categories = get_terms([
    'taxonomy' => 'grant_category',
    'hide_empty' => false,
    'orderby' => 'count',
    'order' => 'DESC',
    'number' => 20
]);

$all_prefectures = get_terms([
    'taxonomy' => 'grant_prefecture',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
]);
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<style>
/* ===== CSS Variables ===== */
:root {
    --primary: #059669;
    --primary-dark: #047857;
    --primary-light: #10b981;
    --secondary: #3B82F6;
    --accent: #F59E0B;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --text-primary: #111827;
    --text-secondary: #6B7280;
    --text-muted: #9CA3AF;
    --bg-primary: #FFFFFF;
    --bg-secondary: #F9FAFB;
    --bg-tertiary: #F3F4F6;
    --border: #E5E7EB;
    --border-light: #F3F4F6;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --radius-sm: 0.375rem;
    --radius-md: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== Base Styles ===== */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

.grant-archive-wrapper {
    font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    color: var(--text-primary);
    background: var(--bg-secondary);
    min-height: 100vh;
    line-height: 1.6;
}

/* ===== Container ===== */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

@media (min-width: 640px) {
    .container { padding: 0 1.5rem; }
}

@media (min-width: 1024px) {
    .container { padding: 0 2rem; }
}

/* ===== Hero Section - シンプル版 ===== */
.hero-section {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 2.5rem 0 2rem;
    position: relative;
    overflow: hidden;
}

.hero-section::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: rgba(255, 255, 255, 0.2);
}

.hero-content {
    text-align: center;
}

.hero-title {
    font-size: clamp(1.75rem, 4vw, 2.5rem);
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.hero-subtitle {
    font-size: clamp(1rem, 2.5vw, 1.25rem);
    font-weight: 400;
    opacity: 0.95;
}

/* 統計情報セクションを削除 */

/* ===== Search Section ===== */
.search-section {
    background: var(--bg-primary);
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: var(--shadow-sm);
}

.search-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.search-bar {
    display: flex;
    align-items: center;
    background: var(--bg-secondary);
    border: 2px solid transparent;
    border-radius: var(--radius-xl);
    padding: 0.375rem;
    transition: var(--transition);
}

.search-bar:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.search-icon {
    padding: 0 0.75rem;
    color: var(--text-secondary);
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    padding: 0.625rem 0;
    font-size: 0.95rem;
    color: var(--text-primary);
    outline: none;
}

.search-input::placeholder {
    color: var(--text-muted);
}

.search-clear {
    padding: 0.5rem 0.625rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
}

.search-clear:hover {
    color: var(--danger);
}

.search-button {
    padding: 0.625rem 1.5rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-lg);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: var(--transition);
}

.search-button:hover {
    background: var(--primary-dark);
}

/* ===== Quick Filters ===== */
.quick-filters {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    scrollbar-width: thin;
}

.quick-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 9999px;
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
    cursor: pointer;
    transition: var(--transition);
}

.quick-filter-btn:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
}

.quick-filter-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* ===== Controls Bar ===== */
.controls-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.controls-left,
.controls-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.control-select {
    padding: 0.5rem 0.75rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.813rem;
    color: var(--text-primary);
    cursor: pointer;
    outline: none;
    transition: var(--transition);
}

.control-select:hover,
.control-select:focus {
    border-color: var(--primary);
}

.filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: 0.813rem;
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
    transition: var(--transition);
}

.filter-toggle-btn:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
}

.filter-toggle-btn.has-active-filters {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-count {
    padding: 0.125rem 0.375rem;
    background: white;
    color: var(--primary);
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 600;
    min-width: 1.125rem;
    text-align: center;
}

.filter-toggle-btn.has-active-filters .filter-count {
    background: white;
    color: var(--primary);
}

.view-switcher {
    display: flex;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: 0.25rem;
}

.view-btn {
    padding: 0.375rem 0.625rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
    border-radius: var(--radius-sm);
    font-size: 0.875rem;
}

.view-btn:hover {
    color: var(--text-primary);
}

.view-btn.active {
    background: var(--primary);
    color: white;
}

/* ===== Main Content ===== */
.main-content-section {
    padding: 1.5rem 0 3rem;
}

.content-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 1.5rem;
    align-items: start;
}

/* ===== Filter Sidebar ===== */
.filter-sidebar {
    position: sticky;
    top: 120px;
    max-height: calc(100vh - 140px);
    overflow-y: auto;
    scrollbar-width: thin;
}

.filter-panel {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    overflow: hidden;
}

.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
    background: var(--bg-secondary);
}

.filter-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.close-filter {
    display: none;
    padding: 0.375rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: var(--transition);
}

.filter-auto-apply {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.auto-apply-switch {
    position: relative;
    width: 36px;
    height: 20px;
    background: var(--border);
    border-radius: 999px;
    cursor: pointer;
    transition: var(--transition);
}

.auto-apply-switch.active {
    background: var(--primary);
}

.auto-apply-switch::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    transition: var(--transition);
}

.auto-apply-switch.active::after {
    transform: translateX(16px);
}

.filter-content {
    padding: 1rem;
}

.filter-group {
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-light);
}

.filter-group:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.filter-group-title {
    font-size: 0.813rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.625rem;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.filter-options {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.filter-option {
    display: flex;
    align-items: center;
    padding: 0.5rem 0.625rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.filter-option:hover {
    background: var(--bg-tertiary);
}

.filter-option.selected {
    background: rgba(5, 150, 105, 0.1);
    border: 1px solid var(--primary);
}

.filter-checkbox,
.filter-radio {
    width: 1rem;
    height: 1rem;
    margin-right: 0.625rem;
    accent-color: var(--primary);
}

.option-label {
    flex: 1;
    font-size: 0.813rem;
    color: var(--text-primary);
}

.option-count {
    padding: 0.125rem 0.375rem;
    background: var(--bg-tertiary);
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 500;
    color: var(--text-secondary);
}

/* ===== Main Content Area ===== */
.main-content {
    min-width: 0;
}

.results-header {
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.results-summary {
    display: flex;
    align-items: baseline;
    gap: 0.75rem;
}

.results-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

.results-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.loading-indicator {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    color: var(--text-secondary);
    font-size: 0.813rem;
}

.spinner {
    width: 1rem;
    height: 1rem;
    border: 2px solid var(--border);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ===== Active Filters Display ===== */
.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.active-filters-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 0.5rem;
    align-self: center;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    background: white;
    border: 1px solid var(--primary);
    color: var(--primary);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.filter-tag button {
    background: transparent;
    border: none;
    color: var(--primary);
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 0.875rem;
    height: 0.875rem;
    transition: var(--transition);
}

.filter-tag button:hover {
    color: var(--danger);
}

.clear-all-filters {
    padding: 0.375rem 0.625rem;
    background: transparent;
    border: 1px solid var(--danger);
    color: var(--danger);
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.clear-all-filters:hover {
    background: var(--danger);
    color: white;
}

/* ===== Grant Cards Grid/List ===== */
.grants-container {
    min-height: 400px;
}

.grants-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.grants-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

/* ===== Pagination ===== */
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.375rem;
    margin-top: 2rem;
}

.pagination-btn {
    min-width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 0.813rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
}

.pagination-btn:hover {
    background: var(--bg-tertiary);
    border-color: var(--primary);
}

.pagination-btn.active,
.pagination-btn.current {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ===== No Results ===== */
.no-results-container {
    text-align: center;
    padding: 3rem 1.5rem;
    background: var(--bg-primary);
    border-radius: var(--radius-lg);
    border: 1px dashed var(--border);
}

.no-results-content {
    max-width: 450px;
    margin: 0 auto;
}

.no-results-icon {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.no-results-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.no-results-desc {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.reset-search-btn {
    padding: 0.625rem 1.5rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: var(--transition);
}

.reset-search-btn:hover {
    background: var(--primary-dark);
}

/* ===== Responsive Design ===== */
@media (max-width: 1024px) {
    .content-layout {
        grid-template-columns: 1fr;
    }
    
    .filter-sidebar {
        position: static;
        max-height: none;
        margin-bottom: 1.5rem;
    }
    
    .grants-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

@media (max-width: 768px) {
    .hero-section {
        padding: 1.5rem 0 1.25rem;
    }
    
    .hero-title {
        font-size: 1.5rem;
    }
    
    .hero-subtitle {
        font-size: 0.875rem;
    }
    
    .search-section {
        position: relative;
        padding: 1rem 0;
    }
    
    .controls-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .controls-left,
    .controls-right {
        width: 100%;
        justify-content: space-between;
    }
    
    .view-switcher {
        display: none;
    }
    
    .filter-sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: var(--bg-primary);
        z-index: 1000;
        transition: transform 0.3s ease;
        overflow-y: auto;
    }
    
    .filter-sidebar.active {
        left: 0;
    }
    
    .close-filter {
        display: flex;
    }
    
    .filter-auto-apply {
        display: none;
    }
    
    .grants-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .results-summary {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .results-count {
        font-size: 1.25rem;
    }
}

/* ===== Custom Scrollbar ===== */
.filter-sidebar::-webkit-scrollbar,
.quick-filters::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}

.filter-sidebar::-webkit-scrollbar-track,
.quick-filters::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

.filter-sidebar::-webkit-scrollbar-thumb,
.quick-filters::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 2px;
}

.filter-sidebar::-webkit-scrollbar-thumb:hover,
.quick-filters::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* ===== Loading Overlay ===== */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: var(--radius-lg);
}

.loading-overlay .spinner {
    width: 2rem;
    height: 2rem;
}
</style>

<!-- Main Archive Template -->
<div class="grant-archive-wrapper">
    
    <!-- Hero Section - シンプル版 -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">助成金・補助金を探す</h1>
                <p class="hero-subtitle">
                    <?php 
                    if (!empty($search_params['search']) || !empty($search_params['category']) || !empty($search_params['prefecture'])) {
                        echo '検索条件に該当する助成金を表示中';
                    } else {
                        echo 'あなたのビジネスに最適な支援制度を見つけましょう';
                    }
                    ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="search-container">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="grant-search" 
                           class="search-input" 
                           placeholder="キーワード、助成金名、実施機関で検索..." 
                           value="<?php echo esc_attr($search_params['search']); ?>" 
                           autocomplete="off">
                    <button id="search-clear" class="search-clear" <?php echo empty($search_params['search']) ? 'style="display:none"' : ''; ?>>
                        <i class="fas fa-times"></i>
                    </button>
                    <button id="search-btn" class="search-button">
                        検索
                    </button>
                </div>

                <div class="quick-filters">
                    <button class="quick-filter-btn <?php echo empty($search_params['status']) ? 'active' : ''; ?>" data-filter="all">
                        すべて
                    </button>
                    <button class="quick-filter-btn <?php echo $search_params['status'] === 'active' ? 'active' : ''; ?>" data-filter="active">
                        <span style="display:inline-block;width:6px;height:6px;background:#10B981;border-radius:50%;"></span>
                        募集中
                    </button>
                    <button class="quick-filter-btn <?php echo $search_params['status'] === 'upcoming' ? 'active' : ''; ?>" data-filter="upcoming">
                        <span style="display:inline-block;width:6px;height:6px;background:#F59E0B;border-radius:50%;"></span>
                        募集予定
                    </button>
                    <button class="quick-filter-btn" data-filter="high-rate">
                        高採択率
                    </button>
                    <button class="quick-filter-btn" data-filter="large-amount">
                        高額支援
                    </button>
                    <button class="quick-filter-btn" data-filter="easy">
                        申請簡単
                    </button>
                </div>

                <div class="controls-bar">
                    <div class="controls-left">
                        <select id="sort-order" class="control-select">
                            <option value="date_desc" <?php selected($search_params['sort'], 'date_desc'); ?>>新着順</option>
                            <option value="date_asc" <?php selected($search_params['sort'], 'date_asc'); ?>>古い順</option>
                            <option value="amount_desc" <?php selected($search_params['sort'], 'amount_desc'); ?>>金額が高い順</option>
                            <option value="deadline_asc" <?php selected($search_params['sort'], 'deadline_asc'); ?>>締切が近い順</option>
                            <option value="success_rate_desc" <?php selected($search_params['sort'], 'success_rate_desc'); ?>>採択率順</option>
                        </select>

                        <button id="filter-toggle" class="filter-toggle-btn">
                            <i class="fas fa-filter"></i>
                            詳細フィルター
                            <span id="filter-count" class="filter-count" style="display:none">0</span>
                        </button>
                    </div>

                    <div class="controls-right">
                        <div class="view-switcher">
                            <button id="grid-view" class="view-btn <?php echo $search_params['view'] === 'grid' ? 'active' : ''; ?>" data-view="grid" title="グリッド表示">
                                <i class="fas fa-th"></i>
                            </button>
                            <button id="list-view" class="view-btn <?php echo $search_params['view'] === 'list' ? 'active' : ''; ?>" data-view="list" title="リスト表示">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content Section -->
    <section class="main-content-section">
        <div class="container">
            <div class="content-layout">
                
                <!-- Filter Sidebar -->
                <aside id="filter-sidebar" class="filter-sidebar">
                    <div class="filter-panel">
                        <div class="filter-header">
                            <h3 class="filter-title">
                                <i class="fas fa-sliders-h"></i>
                                フィルター
                            </h3>
                            <div class="filter-auto-apply">
                                <span>自動適用</span>
                                <div id="auto-apply-switch" class="auto-apply-switch active"></div>
                            </div>
                            <button id="close-filter" class="close-filter" aria-label="フィルターを閉じる">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="filter-content">
                            
                            <!-- 対象地域フィルター -->
                            <?php if (!empty($all_prefectures) && !is_wp_error($all_prefectures)): ?>
                            <div class="filter-group">
                                <h4 class="filter-group-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    対象地域
                                </h4>
                                <div class="filter-options">
                                    <?php 
                                    $prefecture_limit = 8;
                                    $prefectures_to_show = array_slice($all_prefectures, 0, $prefecture_limit);
                                    foreach ($prefectures_to_show as $prefecture): 
                                    ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="prefectures[]" 
                                               value="<?php echo esc_attr($prefecture->slug); ?>" 
                                               class="filter-checkbox prefecture-checkbox auto-apply"
                                               <?php checked(in_array($prefecture->slug, explode(',', $search_params['prefecture']))); ?>>
                                        <span class="option-label"><?php echo esc_html($prefecture->name); ?></span>
                                        <?php if ($prefecture->count > 0): ?>
                                        <span class="option-count"><?php echo esc_html($prefecture->count); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- カテゴリフィルター -->
                            <?php if (!empty($all_categories) && !is_wp_error($all_categories)): ?>
                            <div class="filter-group">
                                <h4 class="filter-group-title">
                                    <i class="fas fa-folder"></i>
                                    カテゴリ
                                </h4>
                                <div class="filter-options">
                                    <?php 
                                    // URLデコードして正しく表示
                                    $decoded_categories = array();
                                    foreach ($all_categories as $category) {
                                        $decoded_category = clone $category;
                                        $decoded_category->name = urldecode($category->name);
                                        $decoded_categories[] = $decoded_category;
                                    }
                                    
                                    $category_limit = 6;
                                    $categories_to_show = array_slice($decoded_categories, 0, $category_limit);
                                    foreach ($categories_to_show as $category): 
                                    ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="categories[]" 
                                               value="<?php echo esc_attr($category->slug); ?>" 
                                               class="filter-checkbox category-checkbox auto-apply"
                                               <?php checked(in_array($category->slug, explode(',', $search_params['category']))); ?>>
                                        <span class="option-label"><?php echo esc_html($category->name); ?></span>
                                        <?php if ($category->count > 0): ?>
                                        <span class="option-count"><?php echo esc_html($category->count); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 助成金額フィルター -->
                            <div class="filter-group">
                                <h4 class="filter-group-title">
                                    <i class="fas fa-yen-sign"></i>
                                    助成金額
                                </h4>
                                <div class="filter-options">
                                    <?php
                                    $amount_ranges = [
                                        '' => 'すべて',
                                        '0-100' => '〜100万円',
                                        '100-500' => '100〜500万円',
                                        '500-1000' => '500〜1000万円',
                                        '1000-3000' => '1000〜3000万円',
                                        '3000+' => '3000万円以上'
                                    ];
                                    foreach ($amount_ranges as $value => $label):
                                    ?>
                                    <label class="filter-option">
                                        <input type="radio" 
                                               name="amount" 
                                               value="<?php echo esc_attr($value); ?>" 
                                               class="filter-radio amount-radio auto-apply"
                                               <?php checked($search_params['amount'], $value); ?>>
                                        <span class="option-label"><?php echo esc_html($label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- 採択率フィルター -->
                            <div class="filter-group">
                                <h4 class="filter-group-title">
                                    <i class="fas fa-percentage"></i>
                                    採択率
                                </h4>
                                <div class="filter-options">
                                    <?php
                                    $success_rates = [
                                        'high' => ['label' => '70%以上', 'color' => '#10B981'],
                                        'medium' => ['label' => '50-69%', 'color' => '#F59E0B'],
                                        'low' => ['label' => '50%未満', 'color' => '#EF4444']
                                    ];
                                    foreach ($success_rates as $value => $data):
                                    ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="success_rate[]" 
                                               value="<?php echo esc_attr($value); ?>" 
                                               class="filter-checkbox success-rate-checkbox auto-apply"
                                               <?php checked(in_array($value, explode(',', $search_params['success_rate']))); ?>>
                                        <span class="option-label"><?php echo esc_html($data['label']); ?></span>
                                        <span style="width:8px;height:8px;background:<?php echo $data['color']; ?>;border-radius:50%;"></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- 申請難易度フィルター -->
                            <div class="filter-group">
                                <h4 class="filter-group-title">
                                    <i class="fas fa-signal"></i>
                                    申請難易度
                                </h4>
                                <div class="filter-options">
                                    <?php
                                    $difficulties = [
                                        'easy' => '易しい',
                                        'normal' => '普通',
                                        'hard' => '難しい'
                                    ];
                                    foreach ($difficulties as $value => $label):
                                    ?>
                                    <label class="filter-option">
                                        <input type="checkbox" 
                                               name="difficulty[]" 
                                               value="<?php echo esc_attr($value); ?>" 
                                               class="filter-checkbox difficulty-checkbox auto-apply"
                                               <?php checked(in_array($value, explode(',', $search_params['difficulty']))); ?>>
                                        <span class="option-label"><?php echo esc_html($label); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="main-content">
                    <div class="results-header">
                        <div class="results-info">
                            <div class="results-summary">
                                <span id="results-count" class="results-count"><?php echo $grants_query->found_posts; ?></span>
                                <span class="results-label">件の助成金</span>
                            </div>
                            <div id="loading-indicator" class="loading-indicator" style="display:none">
                                <div class="spinner"></div>
                                <span>更新中...</span>
                            </div>
                        </div>
                    </div>

                    <div id="active-filters" class="active-filters" style="display:none">
                        <span class="active-filters-label">適用中:</span>
                        <!-- フィルタータグがここに動的に追加される -->
                    </div>

                    <div id="grants-container" class="grants-container">
                        <div id="grants-display" class="grants-display">
                            <?php if ($grants_query->have_posts()): ?>
                                <div class="<?php echo $search_params['view'] === 'grid' ? 'grants-grid' : 'grants-list'; ?>">
                                    <?php
                                    while ($grants_query->have_posts()):
                                        $grants_query->the_post();
                                        $post_id = get_the_ID();
                                        
                                        // データ準備
                                        $grant_terms = get_the_terms($post_id, 'grant_category');
                                        $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
                                        
                                        $grant_data = [
                                            'id' => $post_id,
                                            'title' => get_the_title(),
                                            'permalink' => get_permalink(),
                                            'excerpt' => get_the_excerpt(),
                                            'thumbnail' => get_the_post_thumbnail_url($post_id, 'gi-card-thumb'),
                                            'main_category' => (!is_wp_error($grant_terms) && !empty($grant_terms)) ? $grant_terms[0]->name : '',
                                            'prefecture' => (!is_wp_error($prefecture_terms) && !empty($prefecture_terms)) ? $prefecture_terms[0]->name : '',
                                            'organization' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'organization', '') : get_post_meta($post_id, 'organization', true),
                                            'deadline' => function_exists('gi_get_formatted_deadline') ? gi_get_formatted_deadline($post_id) : get_post_meta($post_id, 'deadline_date', true),
                                            'amount' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'max_amount', '-') : get_post_meta($post_id, 'max_amount', true),
                                            'amount_numeric' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'max_amount_numeric', 0) : get_post_meta($post_id, 'max_amount_numeric', true),
                                            'deadline_timestamp' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'deadline_date', '') : get_post_meta($post_id, 'deadline_date', true),
                                            'status' => function_exists('gi_map_application_status_ui') ? 
                                                gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open')) : 
                                                get_post_meta($post_id, 'application_status', true),
                                            'difficulty' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'grant_difficulty', '') : get_post_meta($post_id, 'grant_difficulty', true),
                                            'success_rate' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'grant_success_rate', 0) : get_post_meta($post_id, 'grant_success_rate', true),
                                            'subsidy_rate' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'subsidy_rate', '') : get_post_meta($post_id, 'subsidy_rate', true),
                                            'target_business' => function_exists('gi_safe_get_meta') ? gi_safe_get_meta($post_id, 'target_business', '') : get_post_meta($post_id, 'target_business', true),
                                        ];
                                        
                                        // カード表示
                                        if ($search_params['view'] === 'grid') {
                                            if (function_exists('gi_render_modern_grant_card')) {
                                                echo gi_render_modern_grant_card($grant_data);
                                            }
                                        } else {
                                            if (function_exists('gi_render_modern_grant_list_card')) {
                                                echo gi_render_modern_grant_list_card($grant_data);
                                            }
                                        }
                                    endwhile;
                                    ?>
                                </div>
                                
                                <!-- Pagination -->
                                <div id="pagination-container" class="pagination-container">
                                    <?php
                                    echo paginate_links([
                                        'total' => $grants_query->max_num_pages,
                                        'current' => max(1, $search_params['page']),
                                        'format' => '?paged=%#%',
                                        'prev_text' => '<i class="fas fa-chevron-left"></i>',
                                        'next_text' => '<i class="fas fa-chevron-right"></i>',
                                        'type' => 'plain',
                                        'before_page_number' => '<span class="pagination-btn">',
                                        'after_page_number' => '</span>'
                                    ]);
                                    ?>
                                </div>
                                
                            <?php else: ?>
                                <div id="no-results" class="no-results-container">
                                    <div class="no-results-content">
                                        <i class="fas fa-search no-results-icon"></i>
                                        <h3 class="no-results-title">該当する助成金が見つかりませんでした</h3>
                                        <p class="no-results-desc">検索条件を変更して再度お試しください</p>
                                        <button id="reset-search" class="reset-search-btn">
                                            検索条件をリセット
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php wp_reset_postdata(); ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </section>
</div>

<script>
/**
 * Grant Archive JavaScript - Enhanced UX Edition
 */
(function() {
    'use strict';
    
    const GrantArchive = {
        // 設定
        config: {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('gi_ajax_nonce'); ?>',
            initialParams: <?php echo json_encode($search_params); ?>,
            autoApply: true // デフォルトで自動適用ON
        },
        
        // 状態管理
        state: {
            currentView: '<?php echo $search_params['view']; ?>',
            currentPage: <?php echo $search_params['page']; ?>,
            isLoading: false,
            filters: {
                search: '<?php echo esc_js($search_params['search']); ?>',
                categories: <?php echo json_encode(array_filter(explode(',', $search_params['category']))); ?>,
                prefectures: <?php echo json_encode(array_filter(explode(',', $search_params['prefecture']))); ?>,
                amount: '<?php echo esc_js($search_params['amount']); ?>',
                status: <?php echo json_encode(array_filter(explode(',', $search_params['status']))); ?>,
                difficulty: <?php echo json_encode(array_filter(explode(',', $search_params['difficulty']))); ?>,
                success_rate: <?php echo json_encode(array_filter(explode(',', $search_params['success_rate']))); ?>,
                sort: '<?php echo esc_js($search_params['sort']); ?>'
            }
        },
        
        // DOM要素のキャッシュ
        elements: {},
        
        // デバウンスタイマー
        debounceTimer: null,
        filterDebounceTimer: null,
        
        // 初期化
        init() {
            this.cacheElements();
            this.bindEvents();
            this.updateFilterCount();
            this.updateActiveFilters();
        },
        
        // DOM要素をキャッシュ
        cacheElements() {
            const elementIds = [
                'grant-search', 'search-btn', 'search-clear', 'sort-order',
                'filter-toggle', 'filter-sidebar', 'close-filter',
                'grid-view', 'list-view', 'reset-search',
                'results-count', 'loading-indicator',
                'active-filters', 'grants-container', 'grants-display',
                'pagination-container', 'filter-count', 'auto-apply-switch'
            ];
            
            elementIds.forEach(id => {
                this.elements[id.replace(/-/g, '_')] = document.getElementById(id);
            });
            
            this.elements.quickFilterBtns = document.querySelectorAll('.quick-filter-btn');
            this.elements.filterCheckboxes = document.querySelectorAll('.filter-checkbox');
            this.elements.filterRadios = document.querySelectorAll('.filter-radio');
        },
        
        // イベントバインディング
        bindEvents() {
            // 検索
            if (this.elements.grant_search) {
                this.elements.grant_search.addEventListener('input', (e) => {
                    this.state.filters.search = e.target.value;
                    this.elements.search_clear.style.display = e.target.value ? 'block' : 'none';
                    this.debounceSearch();
                });
                
                this.elements.grant_search.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.loadGrants();
                    }
                });
            }
            
            if (this.elements.search_btn) {
                this.elements.search_btn.addEventListener('click', () => this.loadGrants());
            }
            
            if (this.elements.search_clear) {
                this.elements.search_clear.addEventListener('click', () => {
                    this.elements.grant_search.value = '';
                    this.elements.search_clear.style.display = 'none';
                    this.state.filters.search = '';
                    this.loadGrants();
                });
            }
            
            // クイックフィルター
            this.elements.quickFilterBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const filter = e.currentTarget.dataset.filter;
                    this.applyQuickFilter(filter);
                });
            });
            
            // ソート
            if (this.elements.sort_order) {
                this.elements.sort_order.addEventListener('change', (e) => {
                    this.state.filters.sort = e.target.value;
                    this.loadGrants();
                });
            }
            
            // フィルターサイドバー
            if (this.elements.filter_toggle) {
                this.elements.filter_toggle.addEventListener('click', () => {
                    this.elements.filter_sidebar.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }
            
            if (this.elements.close_filter) {
                this.elements.close_filter.addEventListener('click', () => {
                    this.elements.filter_sidebar.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
            
            // 自動適用スイッチ
            if (this.elements.auto_apply_switch) {
                this.elements.auto_apply_switch.addEventListener('click', () => {
                    this.config.autoApply = !this.config.autoApply;
                    this.elements.auto_apply_switch.classList.toggle('active', this.config.autoApply);
                });
            }
            
            // ビュー切り替え
            if (this.elements.grid_view) {
                this.elements.grid_view.addEventListener('click', () => {
                    if (this.state.currentView !== 'grid') {
                        this.switchView('grid');
                    }
                });
            }
            
            if (this.elements.list_view) {
                this.elements.list_view.addEventListener('click', () => {
                    if (this.state.currentView !== 'list') {
                        this.switchView('list');
                    }
                });
            }
            
            // フィルターチェックボックス（自動適用対応）
            [...this.elements.filterCheckboxes, ...this.elements.filterRadios].forEach(input => {
                input.addEventListener('change', () => {
                    this.updateFilterState();
                    this.updateFilterCount();
                    
                    if (this.config.autoApply) {
                        // デバウンスで自動適用
                        this.debounceFilter();
                    }
                });
            });
            
            // リセット
            if (this.elements.reset_search) {
                this.elements.reset_search.addEventListener('click', () => {
                    this.clearFilters();
                    this.loadGrants();
                });
            }
            
            // ページネーション
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('pagination-btn') || e.target.closest('.pagination-btn')) {
                    const btn = e.target.classList.contains('pagination-btn') ? e.target : e.target.closest('.pagination-btn');
                    const page = btn.dataset.page;
                    if (page) {
                        this.state.currentPage = parseInt(page);
                        this.loadGrants();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            });
        },
        
        // フィルターのデバウンス
        debounceFilter() {
            clearTimeout(this.filterDebounceTimer);
            this.filterDebounceTimer = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },
        
        // デバウンス検索
        debounceSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.loadGrants();
            }, 500);
        },
        
        // フィルター状態を更新
        updateFilterState() {
            // カテゴリ
            this.state.filters.categories = Array.from(
                document.querySelectorAll('.category-checkbox:checked')
            ).map(cb => cb.value);
            
            // 都道府県
            this.state.filters.prefectures = Array.from(
                document.querySelectorAll('.prefecture-checkbox:checked')
            ).map(cb => cb.value);
            
            // ステータス
            this.state.filters.status = Array.from(
                document.querySelectorAll('.status-checkbox:checked')
            ).map(cb => cb.value);
            
            // 採択率
            this.state.filters.success_rate = Array.from(
                document.querySelectorAll('.success-rate-checkbox:checked')
            ).map(cb => cb.value);
            
            // 難易度
            this.state.filters.difficulty = Array.from(
                document.querySelectorAll('.difficulty-checkbox:checked')
            ).map(cb => cb.value);
            
            // 金額
            const amountRadio = document.querySelector('.amount-radio:checked');
            this.state.filters.amount = amountRadio ? amountRadio.value : '';
        },
        
        // クイックフィルター適用
        applyQuickFilter(filter) {
            // すべてのクイックフィルターボタンの状態を更新
            this.elements.quickFilterBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === filter);
            });
            
            // フィルターをリセット
            this.clearFilters(false);
            
            // 選択されたフィルターを適用
            switch(filter) {
                case 'all':
                    // すべて表示（フィルターなし）
                    break;
                case 'active':
                    this.state.filters.status = ['active'];
                    break;
                case 'upcoming':
                    this.state.filters.status = ['upcoming'];
                    break;
                case 'high-rate':
                    this.state.filters.success_rate = ['high'];
                    break;
                case 'large-amount':
                    this.state.filters.amount = '1000-3000';
                    break;
                case 'easy':
                    this.state.filters.difficulty = ['easy'];
                    break;
            }
            
            this.updateFilterCount();
            this.loadGrants();
        },
        
        // フィルター適用
        applyFilters() {
            this.updateFilterState();
            this.updateFilterCount();
            this.updateActiveFilters();
            this.loadGrants();
        },
        
        // フィルタークリア
        clearFilters(reload = true) {
            // フィルターステートをリセット
            this.state.filters = {
                search: '',
                categories: [],
                prefectures: [],
                amount: '',
                status: [],
                difficulty: [],
                success_rate: [],
                sort: this.state.filters.sort
            };
            
            // UI要素をリセット
            if (this.elements.grant_search) {
                this.elements.grant_search.value = '';
            }
            
            if (this.elements.search_clear) {
                this.elements.search_clear.style.display = 'none';
            }
            
            // チェックボックスをリセット
            this.elements.filterCheckboxes.forEach(cb => cb.checked = false);
            
            // ラジオボタンをリセット
            this.elements.filterRadios.forEach(rb => {
                rb.checked = rb.value === '';
            });
            
            // クイックフィルターをリセット
            this.elements.quickFilterBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.filter === 'all');
            });
            
            this.updateFilterCount();
            this.updateActiveFilters();
            
            if (reload) {
                this.loadGrants();
            }
        },
        
        // フィルター数を更新
        updateFilterCount() {
            const count = 
                this.state.filters.categories.length +
                this.state.filters.prefectures.length +
                (this.state.filters.amount ? 1 : 0) +
                this.state.filters.status.length +
                this.state.filters.difficulty.length +
                this.state.filters.success_rate.length;
            
            if (this.elements.filter_count) {
                this.elements.filter_count.textContent = count;
                this.elements.filter_count.style.display = count > 0 ? 'inline-block' : 'none';
            }
            
            // フィルターボタンのスタイルを更新
            if (this.elements.filter_toggle) {
                this.elements.filter_toggle.classList.toggle('has-active-filters', count > 0);
            }
        },
        
        // アクティブフィルター表示を更新
        updateActiveFilters() {
            if (!this.elements.active_filters) return;
            
            const tags = [];
            
            // カテゴリ
            this.state.filters.categories.forEach(cat => {
                // URLデコードして表示
                const decodedCat = decodeURIComponent(cat);
                tags.push(this.createFilterTag(decodedCat, 'category', cat));
            });
            
            // 都道府県
            this.state.filters.prefectures.forEach(pref => {
                tags.push(this.createFilterTag(pref, 'prefecture', pref));
            });
            
            // 金額
            if (this.state.filters.amount) {
                const amountLabels = {
                    '0-100': '〜100万円',
                    '100-500': '100〜500万円',
                    '500-1000': '500〜1000万円',
                    '1000-3000': '1000〜3000万円',
                    '3000+': '3000万円以上'
                };
                tags.push(this.createFilterTag(amountLabels[this.state.filters.amount], 'amount', this.state.filters.amount));
            }
            
            // ステータス
            this.state.filters.status.forEach(status => {
                const label = status === 'active' ? '募集中' : '募集予定';
                tags.push(this.createFilterTag(label, 'status', status));
            });
            
            // 採択率
            this.state.filters.success_rate.forEach(rate => {
                const labels = {
                    'high': '採択率70%以上',
                    'medium': '採択率50-69%',
                    'low': '採択率50%未満'
                };
                tags.push(this.createFilterTag(labels[rate], 'success_rate', rate));
            });
            
            // 難易度
            this.state.filters.difficulty.forEach(diff => {
                const labels = {
                    'easy': '難易度：易しい',
                    'normal': '難易度：普通',
                    'hard': '難易度：難しい'
                };
                tags.push(this.createFilterTag(labels[diff], 'difficulty', diff));
            });
            
            if (tags.length > 0) {
                this.elements.active_filters.innerHTML = `
                    <span class="active-filters-label">適用中:</span>
                    ${tags.join('')}
                    <button class="clear-all-filters" onclick="GrantArchive.clearFilters(); GrantArchive.loadGrants();">
                        すべてクリア
                    </button>
                `;
                this.elements.active_filters.style.display = 'flex';
            } else {
                this.elements.active_filters.style.display = 'none';
            }
        },
        
        // フィルタータグ作成
        createFilterTag(label, type, value) {
            return `
                <div class="filter-tag">
                    ${label}
                    <button onclick="GrantArchive.removeFilter('${type}', '${value}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        },
        
        // フィルター削除
        removeFilter(type, value) {
            switch(type) {
                case 'category':
                    this.state.filters.categories = this.state.filters.categories.filter(c => c !== value);
                    document.querySelectorAll('.category-checkbox').forEach(cb => {
                        if (cb.value === value) cb.checked = false;
                    });
                    break;
                case 'prefecture':
                    this.state.filters.prefectures = this.state.filters.prefectures.filter(p => p !== value);
                    document.querySelectorAll('.prefecture-checkbox').forEach(cb => {
                        if (cb.value === value) cb.checked = false;
                    });
                    break;
                case 'amount':
                    this.state.filters.amount = '';
                    document.querySelectorAll('.amount-radio').forEach(rb => {
                        rb.checked = rb.value === '';
                    });
                    break;
                case 'status':
                    this.state.filters.status = this.state.filters.status.filter(s => s !== value);
                    document.querySelectorAll('.status-checkbox').forEach(cb => {
                        if (cb.value === value) cb.checked = false;
                    });
                    break;
                case 'success_rate':
                    this.state.filters.success_rate = this.state.filters.success_rate.filter(r => r !== value);
                    document.querySelectorAll('.success-rate-checkbox').forEach(cb => {
                        if (cb.value === value) cb.checked = false;
                    });
                    break;
                case 'difficulty':
                    this.state.filters.difficulty = this.state.filters.difficulty.filter(d => d !== value);
                    document.querySelectorAll('.difficulty-checkbox').forEach(cb => {
                        if (cb.value === value) cb.checked = false;
                    });
                    break;
            }
            
            this.updateFilterCount();
            this.updateActiveFilters();
            this.loadGrants();
        },
        
        // ビュー切り替え
        switchView(view) {
            if (this.state.currentView === view) return;
            
            this.state.currentView = view;
            
            // ボタンの状態を更新
            this.elements.grid_view.classList.toggle('active', view === 'grid');
            this.elements.list_view.classList.toggle('active', view === 'list');
            
            // 表示を切り替え
            this.loadGrants();
        },
        
        // 助成金データ読み込み
        async loadGrants() {
            if (this.state.isLoading) return;
            
            this.state.isLoading = true;
            this.showLoading();
            
            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'gi_load_grants',
                        nonce: this.config.nonce,
                        search: this.state.filters.search,
                        categories: JSON.stringify(this.state.filters.categories),
                        prefectures: JSON.stringify(this.state.filters.prefectures),
                        amount: this.state.filters.amount,
                        status: JSON.stringify(this.state.filters.status),
                        difficulty: JSON.stringify(this.state.filters.difficulty),
                        success_rate: JSON.stringify(this.state.filters.success_rate),
                        sort: this.state.filters.sort,
                        view: this.state.currentView,
                        page: this.state.currentPage
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.renderGrants(data.data);
                    this.updateURL();
                } else {
                    this.showNoResults();
                }
            } catch (error) {
                console.error('Error loading grants:', error);
                this.showNoResults();
            } finally {
                this.state.isLoading = false;
                this.hideLoading();
            }
        },
        
        // 助成金表示を更新
        renderGrants(data) {
            const { grants, found_posts, pagination } = data;
            
            // 結果数を更新
            if (this.elements.results_count) {
                this.elements.results_count.textContent = found_posts;
            }
            
            // 助成金カードを表示
            if (grants && grants.length > 0) {
                const containerClass = this.state.currentView === 'grid' ? 'grants-grid' : 'grants-list';
                this.elements.grants_display.innerHTML = `
                    <div class="${containerClass}">
                        ${grants.map(grant => grant.html).join('')}
                    </div>
                `;
                
                this.elements.grants_container.style.display = 'block';
                
                // カードイベントを再バインド
                this.bindCardEvents();
            } else {
                this.showNoResults();
            }
            
            // ページネーションを更新
            if (pagination && pagination.html) {
                this.elements.pagination_container.innerHTML = pagination.html;
                this.bindPaginationEvents();
            }
        },
        
        // カードイベントをバインド
        bindCardEvents() {
            // お気に入りボタン
            document.querySelectorAll('.favorite-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const postId = btn.dataset.postId || btn.dataset.id;
                    this.toggleFavorite(postId, btn);
                });
            });
            
            // シェアボタン
            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = btn.dataset.url;
                    const title = btn.dataset.title || document.title;
                    this.shareGrant(url, title);
                });
            });
        },
        
        // ページネーションイベントをバインド
        bindPaginationEvents() {
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = parseInt(btn.dataset.page);
                    if (page && page !== this.state.currentPage) {
                        this.state.currentPage = page;
                        this.loadGrants();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            });
        },
        
        // お気に入り切り替え
        async toggleFavorite(postId, button) {
            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'gi_toggle_favorite',
                        nonce: this.config.nonce,
                        post_id: postId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const icon = button.querySelector('i');
                    if (icon) {
                        icon.className = data.data.is_favorite ? 'fas fa-heart' : 'far fa-heart';
                    }
                    
                    this.showNotification(data.data.message);
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
            }
        },
        
        // 助成金をシェア
        shareGrant(url, title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: url
                }).catch(err => console.log('Share failed:', err));
            } else {
                navigator.clipboard.writeText(url).then(() => {
                    this.showNotification('URLをコピーしました');
                }).catch(err => {
                    console.error('Copy failed:', err);
                });
            }
        },
        
        // 通知表示
        showNotification(message) {
            const existing = document.querySelector('.notification');
            if (existing) {
                existing.remove();
            }
            
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--primary);
                color: white;
                padding: 10px 20px;
                border-radius: 6px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                z-index: 10000;
                font-size: 0.875rem;
                animation: slideInUp 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutDown 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 2500);
        },
        
        // ローディング表示
        showLoading() {
            if (this.elements.loading_indicator) {
                this.elements.loading_indicator.style.display = 'flex';
            }
            
            // オーバーレイを追加
            const container = this.elements.grants_container;
            if (container && !container.querySelector('.loading-overlay')) {
                const overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="spinner"></div>';
                container.style.position = 'relative';
                container.appendChild(overlay);
            }
        },
        
        // ローディング非表示
        hideLoading() {
            if (this.elements.loading_indicator) {
                this.elements.loading_indicator.style.display = 'none';
            }
            
            // オーバーレイを削除
            const overlay = document.querySelector('.loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        },
        
        // 結果なし表示
        showNoResults() {
            this.elements.grants_display.innerHTML = `
                <div class="no-results-container">
                    <div class="no-results-content">
                        <i class="fas fa-search no-results-icon"></i>
                        <h3 class="no-results-title">該当する助成金が見つかりませんでした</h3>
                        <p class="no-results-desc">検索条件を変更して再度お試しください</p>
                        <button class="reset-search-btn" onclick="GrantArchive.clearFilters(); GrantArchive.loadGrants();">
                            検索条件をリセット
                        </button>
                    </div>
                </div>
            `;
        },
        
        // URLを更新
        updateURL() {
            const params = new URLSearchParams();
            
            if (this.state.filters.search) params.set('s', this.state.filters.search);
            if (this.state.filters.categories.length) params.set('category', this.state.filters.categories.join(','));
            if (this.state.filters.prefectures.length) params.set('prefecture', this.state.filters.prefectures.join(','));
            if (this.state.filters.amount) params.set('amount', this.state.filters.amount);
            if (this.state.filters.status.length) params.set('status', this.state.filters.status.join(','));
            if (this.state.filters.difficulty.length) params.set('difficulty', this.state.filters.difficulty.join(','));
            if (this.state.filters.success_rate.length) params.set('success_rate', this.state.filters.success_rate.join(','));
            if (this.state.filters.sort !== 'date_desc') params.set('sort', this.state.filters.sort);
            if (this.state.currentView !== 'grid') params.set('view', this.state.currentView);
            if (this.state.currentPage > 1) params.set('paged', this.state.currentPage);
            
            const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState({}, '', newURL);
        }
    };
    
    // グローバルに公開
    window.GrantArchive = GrantArchive;
    
    // DOM読み込み完了時に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => GrantArchive.init());
    } else {
        GrantArchive.init();
    }
})();

// アニメーション用CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutDown {
        from {
            transform: translateY(0);
            opacity: 1;
        }
        to {
            transform: translateY(20px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php get_footer(); ?>