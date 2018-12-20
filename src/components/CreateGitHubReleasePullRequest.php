<?php
namespace App\Components;

use Exception;
use App\Response;
use DateTimeImmutable;
use Github\Client as GithubClient;

/**
 * developからmaster宛にPullRequestを作成するやつ
 */
class CreateGitHubReleasePullRequest
{
    use Response;

    private static $now;

    private static $gitRepoOwner;
    private static $githubUserName;
    private static $githubPassword;

    private static $repository;
    private static $githubClient;

    /**
     * @param array $request
     * @throws Exception
     */
    public static function run(array $request)
    {
        self::init($request);

        try {
            $pullRequest = self::createReleasePullRequest($newRef);
            if (empty($pullRequest)) {
                throw new Exception('PullRequestの作成に失敗しました。');
            }
        } catch (Exception $e) {
            self::response("失敗しちゃいました！\n".$e->getMessage());
            exit;
        }

        self::response("プルリク作りました！\n".$pullRequest['html_url']);
    }

    /**
     * @param array $request
     * @throws Exception;
     */
    private static function init(array $request)
    {
        self::$now = new DateTimeImmutable();

        self::$gitRepoOwner   = getenv('GIT_REPO_OWNER');
        self::$githubUserName = getenv('GITHUB_USER_NAME');
        self::$githubPassword = getenv('GITHUB_PASSWORD');

        if (!isset(self::$gitRepoOwner, self::$githubUserName, self::$githubPassword)) {
            throw new Exception('CreateGitHubReleaseBranch::init() : .env設定して！');
        }

        self::$repository = $request['argument'];
        if (empty(self::$repository)) {
            self::response('リポジトリ名を指定してください！');
            exit;
        }

        self::$githubClient = new GithubClient();
        self::$githubClient
            ->authenticate(
                self::$githubUserName,
                self::$githubPassword,
                GithubClient::AUTH_HTTP_PASSWORD
            );
    }

    /**
     * develop → masterのPullRequest作成
     *
     * @return array pullrequest
     */
    private static function createReleasePullRequest()
    {
        $title = self::getPullRequestTitle();
        $pullRequest = self::$githubClient
            ->api('pull_request')
            ->create(self::$gitRepoOwner, self::$repository, [
                'base'  => 'master',
                'head'  => 'develop',
                'title' => $title,
                'body'  => '',
            ]);

        return $pullRequest;
    }

    /**
     * PullRequestのタイトル取得
     *
     * @return string title
     */
    private static function getPullRequestTitle()
    {
        $pullRequestTitle = $pullRequestTitleOriginal = 'release '.self::$now->format('Y-m-d');
        $pullRequestList = self::$githubClient
            ->api('pull_request')
            ->all(self::$gitRepoOwner, self::$repository, ['state' => 'all']);
        $pullRequestTitleList = array_column($pullRequestList, 'title');

        $releasePullRequestSuffix = '';
        while (1) {
            if (!in_array($pullRequestTitle, $pullRequestTitleList)) {
                break;
            }

            $releasePullRequestSuffix = empty($releasePullRequestSuffix)
                ? '_2'
                : '_'.((int)ltrim($releasePullRequestSuffix, '_') + 1);
            $pullRequestTitle = $pullRequestTitleOriginal.$releasePullRequestSuffix;
        }

        return $pullRequestTitle;
    }
}