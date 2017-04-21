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
    const REPOSITORY_PATTERN = '%s-%s-%s';
    const CHANGELOG_PATTERN = 'CHANGELOG-%s.md';
    const PIM_PARAMS_FILE = 'app/config/pim_parameters.yml';
    const PIM_VERSION_FILE = 'src/Pim%s/Bundle/CatalogBundle/Version.php';
    const COMPOSER_FILE = 'composer.json';

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
        $repositoryInformation = InformationExtractor::extractRepositoryInformation($repository);
        $this->io->section(sprintf('Release of the version %s of %s/%s', $tagName, $owner, $repository));
        $this->io->text(sprintf(
            'We prepare the release folder for the %s %s %s edition',
            $repositoryInformation['product'],
            $repositoryInformation['edition'],
            $repositoryInformation['version']
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

        $this->updateFilesForPullRequest($repositoryInformation, $owner, $branchName, $editionFolder, $tagName);

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

    /**
     * @param string $repositoryInformation
     * @param string $owner
     * @param string $branchName
     * @param string $editionFolder
     * @param string $tagName
     */
    protected function updateFilesForPullRequest($repositoryInformation, $owner, $branchName, $editionFolder, $tagName)
    {
        $devRepository = sprintf(
            self::REPOSITORY_PATTERN,
            $repositoryInformation[InformationExtractor::PRODUCT],
            $repositoryInformation[InformationExtractor::EDITION],
            'dev'
        );
        if ('standard' === $repositoryInformation[InformationExtractor::DISTRIBUTION]) {
            //We copy the changelog file from dev to standard
            $this->fileUpdater->copyFileFromRepo(
                $owner,
                $devRepository,
                $branchName,
                sprintf(self::CHANGELOG_PATTERN, $branchName),
                $editionFolder
            );

            //We copy the pim_parameters file from dev to standard
            $this->fileUpdater->copyFileFromRepo(
                $owner,
                $devRepository,
                $branchName,
                self::PIM_PARAMS_FILE,
                $editionFolder
            );
        }

        if ('dev' === $repositoryInformation[InformationExtractor::DISTRIBUTION]) {
            //We update the version file with the new patch name
            $versionFileLocation = sprintf(
                self::PIM_VERSION_FILE,
                ('community' === $repositoryInformation[InformationExtractor::EDITION] ? '' : 'Enterprise')
            );
            $this->fileUpdater->replaceInFile(
                $editionFolder,
                $versionFileLocation,
                '/\'\d+.\d+.\d+.*\';/',
                sprintf('\'%s\';', $tagName)
            );

            //We update the changelog title with the patch name and the date
            $changelogLocation = sprintf(self::CHANGELOG_PATTERN, $branchName);
            $this->fileUpdater->replaceInFile(
                $editionFolder,
                $changelogLocation,
                '/# \d+.\d+.(x|\*)/',
                sprintf('# %s (%s)', $tagName, date('Y-m-d'))
            );
        }

        //We update the community requirement to match the tag in the composer.json file
        $composerFileLocation = $editionFolder . '/' . self::COMPOSER_FILE;
        $this->fileUpdater->replaceInFile(
            $editionFolder,
            $composerFileLocation,
            '/"akeneo\/pim-community-dev": "\d+.\d+.x-dev@dev"/',
            sprintf('"akeneo/pim-community-dev": "~%s"', $tagName)
        );

        //We update the enterprise requirement to match the tag in the composer.json file
        $this->fileUpdater->replaceInFile(
            $editionFolder,
            $composerFileLocation,
            '/"akeneo\/pim-enterprise-dev": "\d+.\d+.x-dev@dev"/',
            sprintf('"akeneo/pim-enterprise-dev": "~%s"', $tagName)
        );
    }
}
