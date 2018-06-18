<?php
namespace App;

use Exception;
use App\Components;

class Application
{
    use Response;

    private static $request;

    public function __construct()
    {
        self::setSlackRequest();
        self::middleware();
    }

    /**
     * // TODO: SlackRequestクラス作ろう
     * @return void
     */
    private static function setSlackRequest(): void
    {
        $request = [
            'token'        => $_POST['token'] ?? null,
            'channel_id'   => $_POST['channel_id'] ?? null,
            'user_name'    => $_POST['user_name'] ?? null,
            'trigger_word' => $_POST['trigger_word'] ?? null,
            'text'         => $_POST['text'] ?? null,
            'argument'     => trim(str_replace($_POST['trigger_word'], '', $_POST['text'])),
        ];
        // slackリマインダー機能による投稿の場合、自動で追加される末尾ピリオドを除去します
        if ($request['user_name'] === 'slackbot' && strpos($request['trigger_word'], 'リマインダー : ') === 0) {
            $request['argument'] = rtrim($request['argument'], '.');
        }

        self::$request = $request;
    }

    /**
     * リクエスト検査
     *
     * @return void
     */
    private static function middleware(): void
    {
        try {
            if (
                !isset(self::$request['token'], self::$request['channel_id'])
                || self::$request['token'] !== getenv('SLACK_WEBHOOK_TOKEN')
                || self::$request['channel_id'] !== getenv('SLACK_CHANNEL_ID')
            ) {
                throw new Exception('Invalid slack credentials');
            }
        } catch (Exception $e) {
            error_log('Application::middleware() : '.$e->getMessage());
            self::response('');
            exit;
        }
    }

    /**
     * コンポーネントの実行
     *
     * @param string $className
     * @return void
     */
    private static function run(string $className): void
    {
        try {
            if (!class_exists($className)) {
                throw new Exception('Undefined class: '.$className);
            }
            if (!method_exists($className, 'run')) {
                throw new Exception('Undefined run method: '.$className);
            }

            $className::run(self::$request);
        } catch (Exception $e) {
            error_log('Application::run() : '.$e->getMessage());
            self::response('');
            exit;
        }
    }

    /**
     * ディスパッチルーティング
     */
    public function dispatch()
    {
        switch (self::$request['trigger_word']) {
            case '委員長':
            case '月ノ美兎':
                self::run(Components\TsukinoMito::class);
                exit;
                break;
            case ':リリース作成':
                self::run(Components\CreateGitHubReleaseBranch::class);
                exit;
                break;
            case 'ﾚﾋﾞｭｰﾏｰﾝ':
                self::run(Components\NotificationReviewRequest::class);
                exit;
                break;
//            case 'foo':
//                self::run(Components\Bar::class);
//                exit;
//                break;
        }
        exit;
    }
}