<?php

namespace Release\Helper;

use Release\Helper\FileUpdater;
use Release\Helper\InformationExtractor;
use Release\Helper\PullRequestHelper;
use Release\Helper\RepositoryHelper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ReleaseHelper
{
    public function __construct(
        SymfonyStyle $io,
        Filesystem $fs,
        RepositoryHelper $repositoryHelper,
        FileUpdater $fileUpdater,
        PullRequestHelper $pullRequestHelper
    ) {
        $this->io                = $io;
        $this->fs                = $fs;
        $this->repositoryHelper  = $repositoryHelper;
        $this->fileUpdater       = $fileUpdater;
        $this->pullRequestHelper = $pullRequestHelper;
    }

    public function release($owner, $repository, $releaseFolder, $branchName, $releaseBranch, $tagName)
    {
        if (!$this->io->confirm(
            sprintf('We are going to start the release of the %s. Do you want to continue?', $repository)
        )) {
            return false;
        }
        $repositoryInformations = InformationExtractor::extractRepositoryInformation($repository);
        $this->io->section(sprintf('Release of the version %s of %s/%s', $tagName, $owner, $repository));
        $this->io->text(sprintf(
            'We prepare the release folder for the %s %s %s edition',
            $repositoryInformations['product'],
            $repositoryInformations['edition'],
            $repositoryInformations['version']
        ));

        $editionFolder = $releaseFolder . '/' . $repository;

        $this->repositoryHelper->cloneRepository(
            $editionFolder,
            $owner,
            $repository,
            $branchName,
            $releaseBranch
        );
        $this->fileUpdater->updateFiles(
            $owner,
            $repository,
            $branchName,
            $editionFolder,
            $tagName
        );
        $pullRequest = $this->pullRequestHelper->createPullRequest(
            $editionFolder,
            $owner,
            $repository,
            $tagName,
            $branchName,
            $releaseBranch
        );

        if (false !== $pullRequest) {
            $merged = $this->pullRequestHelper->mergePullRequest($pullRequest);

            if (false !== $merged) {
                $this->repositoryHelper->deleteBranch($editionFolder, $releaseBranch);
            }
        } else {
            $merged = false;
        }

        $tagged = $this->repositoryHelper->tagRelease($editionFolder, $branchName, $tagName, $merged);
        $this->fileUpdater->cleanRequirements($repository, $editionFolder, $branchName);
        $this->io->section(sprintf(
            'Awesome, we just released the %s version of %s/%s',
            $tagName,
            $owner,
            $repository
        ));
        $this->io->text('Here is a little sum up of what we did');
        $this->io->listing([
            sprintf('We cloned the %s/%s repository into %s', $owner, $repository, $editionFolder),
            sprintf('We modified the release files to prepare the release'),
            sprintf('We pushed the modifications on branch %s', $releaseBranch),
            sprintf('We created the pull request number #%s', $pullRequest['number']),
            $merged ? 'Then we merged it' : 'But we didn\'t merged it',
            sprintf(
                'We %s the v%s version on branch %s',
                $tagged ? 'tagged' : 'didn\'t tag',
                $tagName,
                $branchName
            )
        ]);
    }
}
