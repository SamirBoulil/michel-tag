<?php

namespace Release\Helper;

use Github\Client;
use Release\Helper\ProcessRunner;
use Symfony\Component\Console\Style\SymfonyStyle;

class PullRequestHelper
{
    protected $io;
    protected $client;

    /**
     * @param SymfonyStyle $io
     * @param Client       $client
     */
    public function __construct(SymfonyStyle $io, Client $client)
    {
        $this->io     = $io;
        $this->client = $client;
    }

    /**
     * @param string $editionFolder
     * @param string $owner
     * @param string $repository
     * @param string $tagName
     * @param string $branch
     * @param string $releaseBranch
     *
     * @return boolean|array
     */
    public function createPullRequest($editionFolder, $owner, $repository, $tagName, $branch, $releaseBranch)
    {
        $diff = ProcessRunner::runCommand(sprintf('cd %s && git diff HEAD', $editionFolder));
        $this->io->text([
            sprintf(
                'The modifications on the repository %s/%s are ready to be pushed:',
                $owner,
                $repository
            ),
            $diff,
            sprintf('You can review them in the folder %s', $editionFolder)
        ]);

        if (!$this->io->confirm('Do you want to proceed and create the pull request?', true)) {
            return false;
        }

        ProcessRunner::runCommand(sprintf('cd %s && git commit -m "Preparing version %s"', $editionFolder, $tagName));
        $this->io->success(sprintf(
            'Commit modifications on branch %s',
            $releaseBranch
        ));

        ProcessRunner::runCommand(sprintf('cd %s && git push -u origin %s', $editionFolder, $releaseBranch));
        $this->io->success(sprintf(
            'Push modifications on branch %s',
            $releaseBranch
        ));

        try {
            $pullRequest = $this->client->api('pull_request')->create($owner, $repository, [
                'base'  => $branch,
                'head'  => sprintf('%s:%s', $owner, $releaseBranch),
                'title' => sprintf('Preparing version %s', $tagName),
                'body'  => ''
            ]);
            $this->io->success(sprintf(
                'Create pull request on %s/%s',
                $owner,
                $repository
            ));
        } catch (\Exception $e) {
            $this->io->error(sprintf(
                'Create pull request on %s/%s: Unable to create the pull request. With message %s',
                $e->getMessage()
            ));

            return false;
        }

        return $pullRequest;
    }

    /**
     * Merge the release pull request
     *
     * @param array $pullRequest
     *
     * @return boolean|array
     */
    public function mergePullRequest($pullRequest)
    {
        if ($this->io->confirm(
            sprintf(
                'We just created a pull request on %s:' .
                "\n" .
                '%s' .
                "\n" .
                'Do you want to open it in your default browser to check that everything is alright?',
                $pullRequest['head']['repo']['full_name'],
                $pullRequest['html_url']
            ), true)
        ) {
            $openCommand = 'Darwin' === PHP_OS ? 'open' : 'xdg-open';
            ProcessRunner::runCommand(sprintf('%s "%s"', $openCommand, $pullRequest['html_url']));
        }

        if (!$this->io->confirm(sprintf('Perfect! Do you want me to merge it?'), true)) {
            $this->io->text('Ok, we will skip the merge (you can still do it by hand)');

            return false;
        }

        try {
            $merge = $this->client->api('pull_request')->merge(
                $pullRequest['base']['user']['login'],
                $pullRequest['base']['repo']['name'],
                $pullRequest['number'],
                sprintf(
                    'Merge pull request #%s from %s/%s',
                    $pullRequest['number'],
                    $pullRequest['head']['user']['login'],
                    $pullRequest['head']['ref']
                ),
                $pullRequest['head']['sha']
            );
        } catch (\Exception $e) {
            $this->io->error(sprintf(
                'Merge pull request #%s: Unable to merge the pull request. With message %s',
                $pullRequest['number'],
                $e->getMessage()
            ));

            return false;
        }

        $this->io->success(sprintf(
            'Merge pull request #%s',
            $pullRequest['number']
        ));

        return $merge;
    }
}
