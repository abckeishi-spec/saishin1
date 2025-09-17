<?php
/**
 * チャット履歴管理クラス（セッション削除・トランジェント使用版）
 * 
 * @package WordPress_AI_Chatbot
 * @version 2.0.0
 * @author 中澤圭志
 */

if (!defined('ABSPATH')) {
    exit;
}

class Chat_History {
    
    /**
     * プロパティ
     */
    private $session_key = 'ai_chat_history';
    private $max_history_length = 50;
    private $conversation_timeout = 3600; // 1時間
    private $user_id = null;
    private $session_id = null;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // ユーザーIDを取得
        $this->user_id = get_current_user_id();
        
        // セッションIDを初期化
        $this->init_session_id();
        
        // 古い会話履歴のクリーンアップをスケジュール
        $this->schedule_cleanup();
        
        // AJAXハンドラーの登録
        $this->register_ajax_handlers();
    }
    
    /**
     * セッションIDの初期化（Cookie使用）
     */
    private function init_session_id() {
        // Cookieからセッション IDを取得または生成
        if (isset($_COOKIE['ai_chat_session_id'])) {
            $this->session_id = sanitize_text_field($_COOKIE['ai_chat_session_id']);
        } else {
            $this->session_id = $this->generate_session_id();
            $this->set_session_cookie($this->session_id);
        }
    }
    
    /**
     * セッションIDを生成
     */
    private function generate_session_id() {
        return 'chat_' . wp_hash(uniqid('', true) . wp_rand());
    }
    
    /**
     * セッションCookieを設定
     */
    private function set_session_cookie($session_id) {
        $expire = time() + $this->conversation_timeout;
        $secure = is_ssl();
        
        setcookie(
            'ai_chat_session_id',
            $session_id,
            $expire,
            COOKIEPATH,
            COOKIE_DOMAIN,
            $secure,
            true // HttpOnly
        );
    }
    
    /**
     * セッションIDを取得
     */
    public function get_session_id() {
        return $this->session_id;
    }
    
    /**
     * トランジェントキーを生成
     */
    private function get_transient_key($user_id = null) {
        if ($user_id) {
            return 'ai_chat_user_' . $user_id;
        }
        return 'ai_chat_session_' . wp_hash($this->session_id);
    }
    
    /**
     * チャット履歴を取得
     */
    public function get_history($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        
        if ($user_id) {
            // ログインユーザーの場合はユーザーメタから取得
            $history = get_user_meta($user_id, 'ai_chat_history', true);
            
            // バックアップとしてトランジェントも確認
            if (empty($history)) {
                $transient_key = $this->get_transient_key($user_id);
                $history = get_transient($transient_key);
            }
        } else {
            // 非ログインユーザーの場合はトランジェントから取得
            $transient_key = $this->get_transient_key();
            $history = get_transient($transient_key);
        }
        
        // 配列でない場合は空配列を返す
        if (!is_array($history)) {
            $history = [];
        }
        
        // 有効期限切れのメッセージを除外
        $history = $this->filter_expired_messages($history);
        
        // タイムスタンプでソート
        usort($history, function($a, $b) {
            $time_a = strtotime($a['timestamp'] ?? 0);
            $time_b = strtotime($b['timestamp'] ?? 0);
            return $time_a - $time_b;
        });
        
        return $history;
    }
    
    /**
     * メッセージを追加
     */
    public function add_message($message, $type, $user_id = null) {
        if (empty($message)) {
            return false;
        }
        
        $user_id = $user_id ?: $this->user_id;
        $history = $this->get_history($user_id);
        
        // 新しいメッセージを作成
        $new_message = [
            'id' => uniqid('msg_'),
            'message' => wp_kses_post($message), // HTMLを許可しつつサニタイズ
            'type' => in_array($type, ['user', 'ai']) ? $type : 'user',
            'timestamp' => current_time('mysql'),
            'session_id' => $this->session_id,
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ];
        
        // 履歴に追加
        $history[] = $new_message;
        
        // 履歴の長さを制限
        if (count($history) > $this->max_history_length) {
            $history = array_slice($history, -$this->max_history_length);
        }
        
        // 保存
        $saved = $this->save_history($history, $user_id);
        
        // 最後のアクティビティ時刻を更新
        $this->update_last_activity($user_id);
        
        // フックを実行
        do_action('ai_chat_message_added', $new_message, $history);
        
        return $saved;
    }
    
    /**
     * 履歴を保存
     */
    private function save_history($history, $user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        
        if ($user_id) {
            // ログインユーザーの場合
            $saved = update_user_meta($user_id, 'ai_chat_history', $history);
            
            // バックアップとしてトランジェントにも保存
            $transient_key = $this->get_transient_key($user_id);
            set_transient($transient_key, $history, $this->conversation_timeout);
            
            return $saved;
        } else {
            // 非ログインユーザーの場合はトランジェントに保存
            $transient_key = $this->get_transient_key();
            return set_transient($transient_key, $history, $this->conversation_timeout);
        }
    }
    
    /**
     * チャット履歴をクリア
     */
    public function clear_history($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        
        if ($user_id) {
            // ユーザーメタを削除
            delete_user_meta($user_id, 'ai_chat_history');
            delete_user_meta($user_id, '_ai_chat_last_activity');
            
            // トランジェントも削除
            $transient_key = $this->get_transient_key($user_id);
            delete_transient($transient_key);
        } else {
            // トランジェントを削除
            $transient_key = $this->get_transient_key();
            delete_transient($transient_key);
        }
        
        // 新しいセッションIDを生成
        $this->session_id = $this->generate_session_id();
        $this->set_session_cookie($this->session_id);
        
        // フックを実行
        do_action('ai_chat_history_cleared', $user_id);
        
        return true;
    }
    
    /**
     * 会話の統計情報を取得
     */
    public function get_stats($user_id = null) {
        $history = $this->get_history($user_id);
        
        $stats = [
            'total_messages' => count($history),
            'user_messages' => 0,
            'ai_messages' => 0,
            'first_message_date' => null,
            'last_message_date' => null,
            'conversation_duration' => null,
            'average_response_time' => null,
            'total_characters' => 0,
            'session_count' => 0
        ];
        
        if (empty($history)) {
            return $stats;
        }
        
        $sessions = [];
        $response_times = [];
        $last_user_time = null;
        
        foreach ($history as $message) {
            // メッセージタイプ別カウント
            if ($message['type'] === 'user') {
                $stats['user_messages']++;
                $last_user_time = strtotime($message['timestamp']);
            } else {
                $stats['ai_messages']++;
                
                // レスポンス時間の計算
                if ($last_user_time) {
                    $response_time = strtotime($message['timestamp']) - $last_user_time;
                    $response_times[] = $response_time;
                    $last_user_time = null;
                }
            }
            
            // 文字数カウント
            $stats['total_characters'] += mb_strlen($message['message']);
            
            // セッション数カウント
            if (!empty($message['session_id'])) {
                $sessions[$message['session_id']] = true;
            }
        }
        
        // 最初と最後のメッセージ日時
        $first_message = reset($history);
        $last_message = end($history);
        
        $stats['first_message_date'] = $first_message['timestamp'];
        $stats['last_message_date'] = $last_message['timestamp'];
        
        // 会話期間（分）
        $first_time = strtotime($first_message['timestamp']);
        $last_time = strtotime($last_message['timestamp']);
        $stats['conversation_duration'] = round(($last_time - $first_time) / 60, 1);
        
        // 平均レスポンス時間（秒）
        if (!empty($response_times)) {
            $stats['average_response_time'] = round(array_sum($response_times) / count($response_times), 1);
        }
        
        // セッション数
        $stats['session_count'] = count($sessions);
        
        return $stats;
    }
    
    /**
     * 会話の要約を生成
     */
    public function get_conversation_summary($user_id = null, $use_ai = true) {
        $history = $this->get_history($user_id);
        
        if (empty($history)) {
            return '';
        }
        
        // AI要約を使用する場合
        if ($use_ai && class_exists('Gemini_AI')) {
            try {
                $gemini = new Gemini_AI();
                return $gemini->summarize_conversation($history);
            } catch (Exception $e) {
                error_log('AI summary generation failed: ' . $e->getMessage());
            }
        }
        
        // 簡易要約を生成
        $summary = [];
        $topics = [];
        
        foreach ($history as $message) {
            if ($message['type'] === 'user') {
                // ユーザーの質問から主要なキーワードを抽出
                $words = preg_split('/[\s、。！？]+/u', $message['message']);
                foreach ($words as $word) {
                    if (mb_strlen($word) > 2) {
                        $topics[] = $word;
                    }
                }
            }
        }
        
        // 頻出キーワードを要約として使用
        $topic_counts = array_count_values($topics);
        arsort($topic_counts);
        $top_topics = array_slice($topic_counts, 0, 5, true);
        
        if (!empty($top_topics)) {
            $summary[] = '主なトピック: ' . implode('、', array_keys($top_topics));
        }
        
        $summary[] = sprintf('メッセージ数: %d（ユーザー: %d、AI: %d）',
            count($history),
            $this->count_messages_by_type($history, 'user'),
            $this->count_messages_by_type($history, 'ai')
        );
        
        return implode("\n", $summary);
    }
    
    /**
     * エクスポート用のデータを取得
     */
    public function export_history($user_id = null, $format = 'json') {
        $history = $this->get_history($user_id);
        $stats = $this->get_stats($user_id);
        
        $export_data = [
            'export_info' => [
                'exported_at' => current_time('mysql'),
                'format' => $format,
                'user_id' => $user_id ?: 'guest',
                'session_id' => $this->session_id,
                'site_url' => home_url(),
                'theme_version' => wp_get_theme()->get('Version')
            ],
            'stats' => $stats,
            'messages' => $history
        ];
        
        switch ($format) {
            case 'json':
                return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
            case 'csv':
                return $this->convert_to_csv($export_data);
                
            case 'txt':
                return $this->convert_to_txt($export_data);
                
            case 'html':
                return $this->convert_to_html($export_data);
                
            default:
                return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * CSV形式に変換
     */
    private function convert_to_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        // BOM付きUTF-8（Excel対応）
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // ヘッダー行
        fputcsv($output, ['日時', '種類', 'メッセージ', 'セッションID']);
        
        // データ行
        foreach ($data['messages'] as $message) {
            $type = ($message['type'] === 'user') ? 'ユーザー' : 'AI';
            fputcsv($output, [
                $message['timestamp'],
                $type,
                $message['message'],
                $message['session_id'] ?? ''
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * テキスト形式に変換
     */
    private function convert_to_txt($data) {
        $txt = "=== AIチャット履歴エクスポート ===\n";
        $txt .= "エクスポート日時: {$data['export_info']['exported_at']}\n";
        $txt .= "サイトURL: {$data['export_info']['site_url']}\n";
        $txt .= "\n";
        $txt .= "=== 会話統計 ===\n";
        $txt .= "総メッセージ数: {$data['stats']['total_messages']}\n";
        $txt .= "ユーザーからのメッセージ: {$data['stats']['user_messages']}\n";
        $txt .= "AIからのメッセージ: {$data['stats']['ai_messages']}\n";
        $txt .= "会話期間: {$data['stats']['conversation_duration']} 分\n";
        
        if ($data['stats']['average_response_time']) {
            $txt .= "平均応答時間: {$data['stats']['average_response_time']} 秒\n";
        }
        
        $txt .= "\n=== メッセージ履歴 ===\n\n";
        
        foreach ($data['messages'] as $message) {
            $role = ($message['type'] === 'user') ? '👤 ユーザー' : '🤖 AI';
            $txt .= "[{$message['timestamp']}] {$role}:\n";
            $txt .= "{$message['message']}\n";
            $txt .= str_repeat('-', 50) . "\n\n";
        }
        
        return $txt;
    }
    
    /**
     * HTML形式に変換
     */
    private function convert_to_html($data) {
        $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIチャット履歴</title>
    <style>
        body { font-family: "Noto Sans JP", sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .header { background: #059669; color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .message { background: white; padding: 15px; border-radius: 10px; margin-bottom: 10px; }
        .message.user { border-left: 4px solid #3B82F6; }
        .message.ai { border-left: 4px solid #10B981; }
        .timestamp { color: #666; font-size: 0.9em; margin-bottom: 5px; }
        .content { line-height: 1.6; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AIチャット履歴</h1>
        <p>エクスポート日時: ' . esc_html($data['export_info']['exported_at']) . '</p>
    </div>
    
    <div class="stats">
        <h2>統計情報</h2>
        <ul>
            <li>総メッセージ数: ' . intval($data['stats']['total_messages']) . '</li>
            <li>ユーザーメッセージ: ' . intval($data['stats']['user_messages']) . '</li>
            <li>AIメッセージ: ' . intval($data['stats']['ai_messages']) . '</li>
            <li>会話時間: ' . floatval($data['stats']['conversation_duration']) . ' 分</li>
        </ul>
    </div>
    
    <div class="messages">';
        
        foreach ($data['messages'] as $message) {
            $type_class = $message['type'];
            $role = ($message['type'] === 'user') ? 'ユーザー' : 'AI';
            
            $html .= '
        <div class="message ' . esc_attr($type_class) . '">
            <div class="timestamp">' . esc_html($message['timestamp']) . ' - ' . esc_html($role) . '</div>
            <div class="content">' . nl2br(esc_html($message['message'])) . '</div>
        </div>';
        }
        
        $html .= '
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * 最後のアクティビティ時刻を更新
     */
    private function update_last_activity($user_id = null) {
        $user_id = $user_id ?: $this->user_id;
        
        if ($user_id) {
            update_user_meta($user_id, '_ai_chat_last_activity', current_time('mysql'));
        } else {
            // セッションクッキーの有効期限を更新
            $this->set_session_cookie($this->session_id);
        }
    }
    
    /**
     * 有効期限切れのメッセージをフィルタリング
     */
    private function filter_expired_messages($history) {
        if (empty($history)) {
            return [];
        }
        
        $cutoff_time = time() - $this->conversation_timeout;
        
        return array_filter($history, function($message) use ($cutoff_time) {
            $message_time = strtotime($message['timestamp'] ?? 0);
            return $message_time > $cutoff_time;
        });
    }
    
    /**
     * 古い会話履歴をクリーンアップ
     */
    public function cleanup_old_conversations() {
        global $wpdb;
        
        // 30日以上古いユーザーメタをクリーンアップ
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // 最後のアクティビティが30日以上前のユーザーの履歴を削除
        $old_users = $wpdb->get_col($wpdb->prepare("
            SELECT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_ai_chat_last_activity' 
            AND meta_value < %s
        ", $thirty_days_ago));
        
        foreach ($old_users as $user_id) {
            delete_user_meta($user_id, 'ai_chat_history');
            delete_user_meta($user_id, '_ai_chat_last_activity');
            
            // トランジェントも削除
            $transient_key = $this->get_transient_key($user_id);
            delete_transient($transient_key);
        }
        
        // 古いトランジェントの自動削除（WordPressが自動的に処理）
        // 追加の処理は不要
        
        return count($old_users);
    }
    
    /**
     * クリーンアップをスケジュール
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('ai_chat_cleanup_old_conversations')) {
            wp_schedule_event(time(), 'daily', 'ai_chat_cleanup_old_conversations');
        }
        
        add_action('ai_chat_cleanup_old_conversations', [$this, 'cleanup_old_conversations']);
    }
    
    /**
     * プライバシーに基づいてデータを削除（GDPR対応）
     */
    public function delete_user_data($user_id) {
        if (!$user_id) {
            return false;
        }
        
        // ユーザーメタを削除
        delete_user_meta($user_id, 'ai_chat_history');
        delete_user_meta($user_id, '_ai_chat_last_activity');
        
        // トランジェントを削除
        $transient_key = $this->get_transient_key($user_id);
        delete_transient($transient_key);
        
        // 削除ログを記録
        error_log("AI Chat: User {$user_id} data deleted for privacy compliance");
        
        // フックを実行
        do_action('ai_chat_user_data_deleted', $user_id);
        
        return true;
    }
    
    /**
     * チャット履歴が存在するかチェック
     */
    public function has_history($user_id = null) {
        $history = $this->get_history($user_id);
        return !empty($history);
    }
    
    /**
     * 最新のメッセージを取得
     */
    public function get_latest_message($user_id = null, $type = null) {
        $history = $this->get_history($user_id);
        
        if (empty($history)) {
            return null;
        }
        
        if ($type === null) {
            return end($history);
        }
        
        // 指定されたタイプの最新メッセージを取得
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i]['type'] === $type) {
                return $history[$i];
            }
        }
        
        return null;
    }
    
    /**
     * メッセージタイプ別のカウント
     */
    private function count_messages_by_type($history, $type) {
        return count(array_filter($history, function($message) use ($type) {
            return $message['type'] === $type;
        }));
    }
    
    /**
     * クライアントIPアドレスを取得
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // カンマ区切りの場合は最初のIPを取得
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                $ip = trim($ip);
                
                // IPアドレスの検証
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * AJAXハンドラーの登録
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_ai_chat_export_history', [$this, 'ajax_export_history']);
        add_action('wp_ajax_ai_chat_clear_history', [$this, 'ajax_clear_history']);
        add_action('wp_ajax_ai_chat_get_stats', [$this, 'ajax_get_stats']);
    }
    
    /**
     * AJAX: 履歴エクスポート
     */
    public function ajax_export_history() {
        // Nonceチェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_action')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        $user_id = get_current_user_id();
        
        $export_data = $this->export_history($user_id, $format);
        
        wp_send_json_success([
            'data' => $export_data,
            'format' => $format,
            'filename' => 'chat_history_' . date('YmdHis') . '.' . $format
        ]);
    }
    
    /**
     * AJAX: 履歴クリア
     */
    public function ajax_clear_history() {
        // Nonceチェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_action')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_id = get_current_user_id();
        $result = $this->clear_history($user_id);
        
        if ($result) {
            wp_send_json_success(['message' => '会話履歴をクリアしました']);
        } else {
            wp_send_json_error('履歴のクリアに失敗しました');
        }
    }
    
    /**
     * AJAX: 統計情報取得
     */
    public function ajax_get_stats() {
        // Nonceチェック
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ai_chat_action')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $user_id = get_current_user_id();
        $stats = $this->get_stats($user_id);
        
        wp_send_json_success($stats);
    }
    
    /**
     * インスタンスの破棄時の処理
     */
    public function __destruct() {
        // 必要に応じてクリーンアップ処理
    }
}

/**
 * グローバル関数: Chat_Historyインスタンスの取得
 */
if (!function_exists('gi_get_chat_history')) {
    function gi_get_chat_history() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new Chat_History();
        }
        
        return $instance;
    }
}
