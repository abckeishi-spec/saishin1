<?php
/*
Template Name: 高度検索ページ - Tailwind CSS Play CDN対応版
*/

get_header(); ?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?></title>
    
    <!-- Tailwind CSS Play CDN -->

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'pulse-soft': 'pulseSoft 3s infinite',
                        'float': 'float 3s ease-in-out infinite',
                        'shimmer': 'shimmer 2s infinite',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(40px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        bounceGentle: {
                            '0%, 20%, 50%, 80%, 100%': { transform: 'translateY(0)' },
                            '40%': { transform: 'translateY(-10px)' },
                            '60%': { transform: 'translateY(-5px)' }
                        },
                        pulseSoft: {
                            '0%, 100%': { opacity: '1' },
                            '50%': { opacity: '0.7' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        shimmer: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(100%)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class('antialiased'); ?>>

<div class="search-page-container bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50 min-h-screen">
    
    <!-- Hero Section -->
    <section class="hero-section bg-gradient-to-br from-emerald-900 via-teal-900 to-cyan-900 text-white py-20 relative overflow-hidden">
        <div class="absolute inset-0 bg-black/20"></div>
        <div class="absolute top-10 left-10 w-20 h-20 bg-cyan-300/10 rounded-full animate-float"></div>
        <div class="absolute bottom-10 right-10 w-16 h-16 bg-emerald-400/10 rounded-full animate-bounce-gentle"></div>
        
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16 animate-fade-in">
                <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-full px-6 py-3 mb-6 border border-white/20">
                    <i class="fas fa-search text-emerald-300 text-2xl animate-pulse"></i>
                    <span class="text-lg font-bold tracking-wider">ADVANCED SEARCH</span>
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black mb-6 leading-tight">
                    <span class="bg-gradient-to-r from-emerald-300 to-cyan-300 bg-clip-text text-transparent">助成金を</span><br>
                    <span class="bg-gradient-to-r from-white to-teal-200 bg-clip-text text-transparent">詳細検索</span>
                </h1>
                <p class="text-xl md:text-2xl text-emerald-200 max-w-4xl mx-auto leading-relaxed">
                    あなたの事業に最適な助成金を見つけましょう。<br class="hidden md:block">
                    詳細な条件で絞り込み検索が可能です。
                </p>
            </div>
        </div>
    </section>

    <!-- Search Form Section -->
    <section class="py-16 lg:py-20 -mt-16 relative z-20">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                
                <!-- Search Form Container -->
                <div class="search-form-container bg-white rounded-3xl shadow-2xl p-8 lg:p-12 mb-8 border border-gray-100 animate-slide-up">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-black text-gray-900 mb-4">
                            <i class="fas fa-filter text-emerald-500 mr-3"></i>
                            詳細検索フォーム
                        </h2>
                        <p class="text-gray-600 text-lg">条件を指定して、最適な助成金を見つけましょう</p>
                    </div>
                    
                    <form id="advanced-search-form" class="space-y-8">
                        
                        <!-- キーワード検索 -->
                        <div class="search-field">
                            <label for="keyword" class="block text-lg font-bold text-gray-700 mb-4">
                                <i class="fas fa-search text-emerald-500 mr-2"></i>
                                キーワード検索
                            </label>
                            <div class="relative">
                                <input 
                                    type="text" 
                                    id="keyword" 
                                    name="keyword" 
                                    class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 transition-all duration-300 pr-12"
                                    placeholder="<?php echo esc_attr(get_theme_mod('search_placeholder_text', 'DX化、設備投資、人材育成など...')); ?>"
                                >
                                <div class="absolute right-4 top-1/2 transform -translate-y-1/2">
                                    <i class="fas fa-search text-gray-400 text-xl animate-pulse-soft"></i>
                                </div>
                                <div id="search-suggestions" class="absolute top-full left-0 right-0 bg-white border-2 border-gray-200 rounded-2xl shadow-2xl z-50 hidden mt-2 max-h-80 overflow-y-auto"></div>
                            </div>
                        </div>

                        <!-- Filter Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            
                            <!-- 都道府県選択 -->
                            <?php if (get_theme_mod('enable_prefecture_filter', 1)): ?>
                            <div class="search-field">
                                <label for="prefecture" class="block text-lg font-bold text-gray-700 mb-4">
                                    <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                                    都道府県
                                </label>
                                <select id="prefecture" name="prefecture" class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 bg-white">
                                    <option value="">すべての都道府県</option>
                                    <?php
                                    $prefectures = get_terms([
                                        'taxonomy' => 'grant_prefecture',
                                        'hide_empty' => false,
                                        'orderby' => 'name',
                                        'order' => 'ASC',
                                    ]);
                                    if(!is_wp_error($prefectures)):
                                        foreach ($prefectures as $prefecture):
                                    ?>
                                    <option value="<?php echo esc_attr($prefecture->slug); ?>">
                                        <?php echo esc_html($prefecture->name); ?>
                                    </option>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <!-- カテゴリ選択 -->
                            <?php if (get_theme_mod('enable_category_filter', 1)): ?>
                            <div class="search-field">
                                <label for="category" class="block text-lg font-bold text-gray-700 mb-4">
                                    <i class="fas fa-tags text-purple-500 mr-2"></i>
                                    カテゴリ
                                </label>
                                <select id="category" name="category" class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all duration-300 bg-white">
                                    <option value="">すべてのカテゴリ</option>
                                    <?php
                                    $categories = get_terms([
                                        'taxonomy' => 'grant_category',
                                        'hide_empty' => false,
                                        'orderby' => 'name',
                                        'order' => 'ASC',
                                    ]);
                                    if(!is_wp_error($categories)):
                                        foreach ($categories as $category):
                                    ?>
                                    <option value="<?php echo esc_attr($category->slug); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <!-- 申請状況 -->
                            <div class="search-field">
                                <label for="status" class="block text-lg font-bold text-gray-700 mb-4">
                                    <i class="fas fa-clock text-orange-500 mr-2"></i>
                                    申請状況
                                </label>
                                <select id="status" name="status" class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-orange-100 focus:border-orange-500 transition-all duration-300 bg-white">
                                    <option value="">すべて</option>
                                    <option value="open">📝 申請受付中</option>
                                    <option value="closed">⏰ 申請終了</option>
                                    <option value="upcoming">🔜 申請予定</option>
                                </select>
                            </div>

                            <!-- 助成金額 -->
                            <div class="search-field">
                                <label for="amount_min" class="block text-lg font-bold text-gray-700 mb-4">
                                    <i class="fas fa-yen-sign text-green-500 mr-2"></i>
                                    助成金額（最小）
                                </label>
                                <select id="amount_min" name="amount_min" class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-green-100 focus:border-green-500 transition-all duration-300 bg-white">
                                    <option value="">指定なし</option>
                                    <option value="100000">💰 10万円以上</option>
                                    <option value="500000">💎 50万円以上</option>
                                    <option value="1000000">🏆 100万円以上</option>
                                    <option value="5000000">👑 500万円以上</option>
                                    <option value="10000000">💸 1,000万円以上</option>
                                </select>
                            </div>

                            <!-- 難易度 -->
                            <div class="search-field">
                                <label for="difficulty" class="block text-lg font-bold text-gray-700 mb-4">
                                    <i class="fas fa-chart-bar text-red-500 mr-2"></i>
                                    申請難易度
                                </label>
                                <select id="difficulty" name="difficulty" class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:ring-4 focus:ring-red-100 focus:border-red-500 transition-all duration-300 bg-white">
                                    <option value="">すべて</option>
                                    <option value="easy">🟢 易しい</option>
                                    <option value="medium">🟡 普通</option>
                                    <option value="hard">🔴 難しい</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Buttons -->
                        <div class="text-center pt-8 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                <button type="submit" class="inline-flex items-center gap-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-4 px-8 rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 text-lg">
                                    <i class="fas fa-search text-xl"></i>
                                    検索する
                                </button>
                                <button type="button" id="reset-search" class="inline-flex items-center gap-3 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-4 px-8 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 text-lg">
                                    <i class="fas fa-undo text-xl"></i>
                                    リセット
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Search Results -->
                <div id="search-results" class="search-results animate-fade-in">
                    <div class="initial-message text-center py-20 bg-white rounded-3xl shadow-lg border border-gray-100">
                        <div class="w-32 h-32 bg-gradient-to-r from-emerald-100 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-8">
                            <i class="fas fa-search text-emerald-500 text-4xl animate-pulse-soft"></i>
                        </div>
                        <h3 class="text-3xl font-bold text-gray-700 mb-4">検索条件を入力してください</h3>
                        <p class="text-gray-500 text-lg max-w-2xl mx-auto leading-relaxed">
                            上記のフォームから条件を指定して、あなたのビジネスに最適な助成金を見つけましょう。
                        </p>
                    </div>
                </div>

                <!-- Loading Display -->
                <div id="search-loading" class="hidden text-center py-20 bg-white rounded-3xl shadow-lg border border-gray-100">
                    <div class="inline-flex items-center gap-4 mb-6">
                        <div class="animate-spin rounded-full h-16 w-16 border-4 border-emerald-200 border-t-emerald-600"></div>
                        <div class="text-2xl font-bold text-gray-700">検索中...</div>
                    </div>
                    <p class="text-gray-500 text-lg">最適な助成金を検索しています。しばらくお待ちください。</p>
                    <div class="flex justify-center space-x-2 mt-6">
                        <div class="w-3 h-3 bg-emerald-500 rounded-full animate-bounce"></div>
                        <div class="w-3 h-3 bg-teal-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-3 h-3 bg-cyan-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Searches -->
    <section class="popular-searches bg-gradient-to-r from-gray-100 to-gray-200 py-16 lg:py-20">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl lg:text-4xl font-black text-center text-gray-900 mb-12">
                <i class="fas fa-fire text-orange-500 mr-3"></i>
                人気の検索条件
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 max-w-6xl mx-auto">
                <?php
                $popular_searches = [
                    ['label' => 'IT・デジタル化', 'category' => 'it-digital', 'icon' => 'fas fa-laptop', 'color' => 'from-blue-500 to-indigo-600'],
                    ['label' => '東京都', 'prefecture' => 'tokyo', 'icon' => 'fas fa-building', 'color' => 'from-red-500 to-pink-600'],
                    ['label' => '研究開発', 'category' => 'research-development', 'icon' => 'fas fa-flask', 'color' => 'from-green-500 to-emerald-600'],
                    ['label' => '大阪府', 'prefecture' => 'osaka', 'icon' => 'fas fa-city', 'color' => 'from-purple-500 to-indigo-600'],
                    ['label' => '設備投資', 'category' => 'equipment-investment', 'icon' => 'fas fa-cogs', 'color' => 'from-yellow-500 to-orange-600'],
                    ['label' => '神奈川県', 'prefecture' => 'kanagawa', 'icon' => 'fas fa-map', 'color' => 'from-teal-500 to-cyan-600'],
                    ['label' => '人材育成', 'category' => 'human-resources', 'icon' => 'fas fa-users', 'color' => 'from-pink-500 to-rose-600'],
                    ['label' => '愛知県', 'prefecture' => 'aichi', 'icon' => 'fas fa-industry', 'color' => 'from-indigo-500 to-blue-600'],
                    ['label' => '海外展開', 'category' => 'overseas-expansion', 'icon' => 'fas fa-globe', 'color' => 'from-emerald-500 to-teal-600'],
                    ['label' => '福岡県', 'prefecture' => 'fukuoka', 'icon' => 'fas fa-mountain', 'color' => 'from-orange-500 to-red-600'],
                    ['label' => '創業支援', 'category' => 'startup-support', 'icon' => 'fas fa-rocket', 'color' => 'from-purple-500 to-pink-600'],
                    ['label' => '北海道', 'prefecture' => 'hokkaido', 'icon' => 'fas fa-snowflake', 'color' => 'from-cyan-500 to-blue-600'],
                ];
                
                foreach ($popular_searches as $search):
                ?>
                <button class="popular-search-btn group bg-white hover:bg-gray-50 border-2 border-gray-200 hover:border-gray-300 rounded-2xl p-4 text-center font-medium text-gray-700 hover:text-gray-900 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-lg"
                        data-category="<?php echo isset($search['category']) ? esc_attr($search['category']) : ''; ?>"
                        data-prefecture="<?php echo isset($search['prefecture']) ? esc_attr($search['prefecture']) : ''; ?>">
                    <div class="w-12 h-12 bg-gradient-to-r <?php echo $search['color']; ?> rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                        <i class="<?php echo $search['icon']; ?> text-white text-lg"></i>
                    </div>
                    <div class="text-sm font-bold"><?php echo esc_html($search['label']); ?></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('advanced-search-form');
    const searchResults = document.getElementById('search-results');
    const searchLoading = document.getElementById('search-loading');
    const resetButton = document.getElementById('reset-search');
    const keywordInput = document.getElementById('keyword');
    const suggestionsContainer = document.getElementById('search-suggestions');

    // Search form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }

    // Reset button
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            searchForm.reset();
            resetSearchResults();
        });
    }

    // Popular search buttons
    document.querySelectorAll('.popular-search-btn').forEach(button => {
        button.addEventListener('click', function() {
            const category = this.dataset.category;
            const prefecture = this.dataset.prefecture;
            
            if (category) {
                document.getElementById('category').value = category;
            }
            if (prefecture) {
                document.getElementById('prefecture').value = prefecture;
            }
            
            performSearch();
        });
    });

    // Search suggestions
    let searchTimeout;
    if (keywordInput) {
        keywordInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    fetchSearchSuggestions(query);
                }, 300);
            } else {
                suggestionsContainer.classList.add('hidden');
            }
        });
    }

    // Perform search function
    function performSearch() {
        const formData = new FormData(searchForm);
        
        searchLoading.classList.remove('hidden');
        searchResults.classList.add('hidden');
        
        // Mock search - replace with actual AJAX call
        setTimeout(() => {
            const mockResults = generateMockResults();
            displaySearchResults(mockResults);
        }, 1500);
    }

    // Generate mock results
    function generateMockResults() {
        const results = Array.from({length: 6}).map((_, i) => {
            const categories = ['IT・DX化', '設備投資', '人材育成', '研究開発', '海外展開', '創業支援'];
            const amounts = ['最大500万円', '最大1,000万円', '最大300万円', '最大2,000万円'];
            const statuses = ['申請受付中', '申請予定', '申請終了'];
            const difficulties = ['易しい', '普通', '難しい'];
            
            return {
                title: `サンプル助成金制度 ${i + 1}`,
                category: categories[i % categories.length],
                amount: amounts[i % amounts.length],
                status: statuses[i % statuses.length],
                difficulty: difficulties[i % difficulties.length],
                description: `この助成金は${categories[i % categories.length]}を支援する制度です。申請要件を満たした事業者に対して支援を行います。`
            };
        });
        
        return {
            count: results.length,
            results: results
        };
    }

    // Display search results
    function displaySearchResults(data) {
        searchLoading.classList.add('hidden');
        
        if (data.count > 0) {
            const resultsHtml = `
                <div class="search-results-header mb-8 bg-white rounded-2xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <p class="text-2xl font-bold text-gray-700">
                            <i class="fas fa-check-circle text-emerald-500 mr-2"></i>
                            ${data.count}件の助成金・補助金が見つかりました
                        </p>
                        <div class="text-sm text-gray-500">
                            検索条件で絞り込み済み
                        </div>
                    </div>
                </div>
                <div class="grants-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    ${data.results.map((result, index) => `
                        <div class="grant-card bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1" style="animation-delay: ${index * 0.1}s">
                            <div class="flex items-start justify-between mb-4">
                                <div class="w-12 h-12 bg-gradient-to-r from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-coins text-white text-lg"></i>
                                </div>
                                <span class="text-xs font-medium px-3 py-1 rounded-full ${
                                    result.status === '申請受付中' ? 'bg-green-100 text-green-800' :
                                    result.status === '申請予定' ? 'bg-blue-100 text-blue-800' :
                                    'bg-gray-100 text-gray-800'
                                }">
                                    ${result.status}
                                </span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">${result.title}</h3>
                            <p class="text-gray-600 text-sm mb-4 leading-relaxed">${result.description}</p>
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">カテゴリ</span>
                                    <span class="font-medium text-gray-700">${result.category}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">助成額</span>
                                    <span class="font-bold text-emerald-600">${result.amount}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">難易度</span>
                                    <span class="font-medium ${
                                        result.difficulty === '易しい' ? 'text-green-600' :
                                        result.difficulty === '普通' ? 'text-yellow-600' :
                                        'text-red-600'
                                    }">${result.difficulty}</span>
                                </div>
                            </div>
                            <button class="w-full bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300">
                                詳細を見る
                            </button>
                        </div>
                    `).join('')}
                </div>
            `;
            searchResults.innerHTML = resultsHtml;
        } else {
            searchResults.innerHTML = `
                <div class="no-results text-center py-20 bg-white rounded-3xl shadow-lg border border-gray-100">
                    <div class="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-8">
                        <i class="fas fa-search text-gray-400 text-4xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-700 mb-4">該当する助成金が見つかりませんでした</h3>
                    <p class="text-gray-500 text-lg">検索条件を変更して再度お試しください。</p>
                </div>
            `;
        }
        
        searchResults.classList.remove('hidden');
    }

    // Reset search results
    function resetSearchResults() {
        searchResults.innerHTML = `
            <div class="initial-message text-center py-20 bg-white rounded-3xl shadow-lg border border-gray-100">
                <div class="w-32 h-32 bg-gradient-to-r from-emerald-100 to-teal-100 rounded-full flex items-center justify-center mx-auto mb-8">
                    <i class="fas fa-search text-emerald-500 text-4xl animate-pulse-soft"></i>
                </div>
                <h3 class="text-3xl font-bold text-gray-700 mb-4">検索条件を入力してください</h3>
                <p class="text-gray-500 text-lg max-w-2xl mx-auto leading-relaxed">
                    上記のフォームから条件を指定して、あなたのビジネスに最適な助成金を見つけましょう。
                </p>
            </div>
        `;
    }

    // Fetch search suggestions
    function fetchSearchSuggestions(query) {
        // Mock suggestions - replace with actual AJAX call
        const mockSuggestions = [
            { value: 'IT導入補助金', label: 'IT導入補助金' },
            { value: 'ものづくり補助金', label: 'ものづくり補助金' },
            { value: '事業再構築補助金', label: '事業再構築補助金' },
            { value: '小規模事業者持続化補助金', label: '小規模事業者持続化補助金' }
        ];

        const filteredSuggestions = mockSuggestions.filter(item => 
            item.value.toLowerCase().includes(query.toLowerCase())
        );

        if (filteredSuggestions.length > 0) {
            displaySearchSuggestions(filteredSuggestions);
        } else {
            suggestionsContainer.classList.add('hidden');
        }
    }

    // Display search suggestions
    function displaySearchSuggestions(suggestions) {
        const html = suggestions.map(suggestion => `
            <div class="suggestion-item flex items-center gap-3 px-6 py-4 hover:bg-gradient-to-r hover:from-emerald-50 hover:to-teal-50 cursor-pointer transition-all duration-200 border-b border-gray-100 last:border-b-0" data-value="${suggestion.value}">
                <i class="fas fa-search text-emerald-500"></i>
                <span class="font-medium text-gray-800">${suggestion.label}</span>
            </div>
        `).join('');

        suggestionsContainer.innerHTML = html;
        suggestionsContainer.classList.remove('hidden');

        // Add click events
        suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                keywordInput.value = this.dataset.value;
                suggestionsContainer.classList.add('hidden');
                performSearch();
            });
        });
    }

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!keywordInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.classList.add('hidden');
        }
    });
});
</script>

<style>
/* Custom animations for grant cards */
.grant-card {
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.6s ease-out forwards;
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Custom scrollbar */
#search-suggestions::-webkit-scrollbar {
    width: 6px;
}

#search-suggestions::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

#search-suggestions::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

#search-suggestions::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .container { padding-left: 1rem; padding-right: 1rem; }
    .text-4xl { font-size: 2.5rem; }
    .text-6xl { font-size: 3.5rem; }
}
</style>

<?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>
