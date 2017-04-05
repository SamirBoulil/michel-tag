<?php

namespace Release\Helper;

use Github\Client;
use Release\Helper\InformationExtractor;
use Release\Helper\ProcessRunner;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class FileUpdater
{
    const CHANGELOG_PATTERN = 'CHANGELOG-%s.md';
    const REPOSITORY_PATTERN = '%s-%s-%s';
    const PIM_PARAMS = 'app/config/pim_parameters.yml';
    const PIM_VERSION = 'src/Pim%s/Bundle/CatalogBundle/Version.php';
    const COMPOSER = 'composer.json';

    /**
     * @param SymfonyStyle $io
     * @param Filesystem   $fs
     * @param Client       $client
     */
    public function __construct(SymfonyStyle $io, Filesystem $fs, Client $client)
    {
        $this->io     = $io;
        $this->fs     = $fs;
        $this->client = $client;
    }

    /**
     * Update repository files to prepare the release
     *
     * @param string $owner
     * @param string $repository
     * @param string $branch
     * @param string $editionFolder
     * @param string $tagName
     *
     * @return void
     */
    public function updateFiles($owner, $repository, $branch, $editionFolder, $tagName)
    {
        $repositoryInformations = InformationExtractor::extractRepositoryInformation($repository);
        $devRepository = sprintf(
            self::REPOSITORY_PATTERN,
            $repositoryInformations[InformationExtractor::PRODUCT],
            $repositoryInformations[InformationExtractor::EDITION],
            'dev'
        );
        if ('standard' === $repositoryInformations[InformationExtractor::VERSION]) {
            //We copy the changelog file into the standard
            $changelogFile = $this->downloadRepoFile(
                $owner,
                $devRepository,
                $branch,
                sprintf(self::CHANGELOG_PATTERN, $branch),
                $editionFolder
            );
            $this->fs->dumpFile(
                sprintf('%s/%s', $editionFolder, sprintf(self::CHANGELOG_PATTERN, $branch)),
                $changelogFile
            );
            ProcessRunner::runCommand(
                sprintf('cd %s && git add %s', $editionFolder, sprintf(self::CHANGELOG_PATTERN, $branch))
            );

            //We copy the pim_parameters file into the standard
            $pimParamsFile = $this->downloadRepoFile($owner, $devRepository, $branch, self::PIM_PARAMS, $editionFolder);
            $this->fs->dumpFile(sprintf('%s/%s', $editionFolder, self::PIM_PARAMS), $pimParamsFile);
            ProcessRunner::runCommand(sprintf('cd %s && git add %s', $editionFolder, self::PIM_PARAMS));
        }

        if ('dev' === $repositoryInformations[InformationExtractor::VERSION]) {
            $versionFileLocation = sprintf(
                self::PIM_VERSION,
                ('community' === $repositoryInformations[InformationExtractor::EDITION] ? '' : 'Enterprise')
            );
            //We update the version file with the new patch name
            $versionFile = file_get_contents(
                $editionFolder .
                '/' .
                $versionFileLocation
            );
            $versionFile = preg_replace('/\'\d+.\d+.\d+.*\';/', sprintf('\'%s\';', $tagName), $versionFile);

            $this->fs->dumpFile(sprintf('%s/%s', $editionFolder, $versionFileLocation), $versionFile);
            ProcessRunner::runCommand(sprintf('cd %s && git add %s', $editionFolder, $versionFileLocation));

            //We update the changelog title with the patch name and the date
            $changelogFile = file_get_contents($editionFolder . '/' . sprintf(self::CHANGELOG_PATTERN, $branch));
            $changelogFile = preg_replace(
                '/# \d+.\d+.(x|\*)/',
                sprintf('# %s (%s)', $tagName, date('Y-m-d')),
                $changelogFile
            );

            $this->fs->dumpFile(
                sprintf('%s/%s', $editionFolder, sprintf(self::CHANGELOG_PATTERN, $branch)),
                $changelogFile
            );
            ProcessRunner::runCommand(
                sprintf('cd %s && git add %s', $editionFolder, sprintf(self::CHANGELOG_PATTERN, $branch))
            );
        }

        //We update the community requirement to match the tag in the composer.json file
        $composerFile = file_get_contents(sprintf('%s/%s', $editionFolder, self::COMPOSER));
        $composerFile = preg_replace(
            '/"akeneo\/pim-community-dev": "\d+.\d+.x-dev@dev"/',
            sprintf('"akeneo/pim-community-dev": "~%s"', $tagName),
            $composerFile
        );
        //We update the enterprise requirement to match the tag in the composer.json file
        $composerFile = preg_replace(
            '/"akeneo\/pim-enterprise-dev": "\d+.\d+.x-dev@dev"/',
            sprintf('"akeneo/pim-enterprise-dev": "~%s"', $tagName),
            $composerFile
        );
        $this->fs->dumpFile(sprintf('%s/%s', $editionFolder, self::COMPOSER), $composerFile);
        ProcessRunner::runCommand(sprintf('cd %s && git add %s', $editionFolder, self::COMPOSER));
    }

    /**
     * Clean composer requirements
     *
     * @param string $repository
     * @param string $editionFolder
     * @param string $branch
     */
    public function cleanRequirements($repository, $editionFolder, $branch)
    {
        $repositoryInformations = InformationExtractor::extractRepositoryInformation($repository);
        if ('dev' !== $repositoryInformations[InformationExtractor::VERSION] ||
            'community' !== $repositoryInformations[InformationExtractor::EDITION]
        ) {
            $composerFile = file_get_contents(sprintf('%s/%s', $editionFolder, self::COMPOSER));
            $composerFile = preg_replace(
                '/"akeneo\/pim-community-dev": "~\d+.\d+.\d+.*"/',
                sprintf('"akeneo/pim-community-dev": "%s.x-dev@dev"', $branch),
                $composerFile
            );
            $composerFile = preg_replace(
                '/"akeneo\/pim-enterprise-dev": "~\d+.\d+.\d+.*"/',
                sprintf('"akeneo/pim-enterprise-dev": "%s.x-dev@dev"', $branch),
                $composerFile
            );
            $this->fs->dumpFile(sprintf('%s/%s', $editionFolder, self::COMPOSER), $composerFile);
            ProcessRunner::runCommand(sprintf(
                'cd %s && git add %s && git commit -m "Retablishing use of pim-community-dev %s.x-dev@dev" && ' .
                'git push origin %s',
                $editionFolder,
                self::COMPOSER,
                $branch,
                $branch
            ));
            $this->io->success(sprintf(
                'Clean composer requirements on the %s branch',
                $branch
            ));
        }
    }

    /**
     * Download a file from a repository
     *
     * @param [string] $owner
     * @param [string] $repository
     * @param [string] $branch
     * @param [string] $path
     * @param [string] $destinationFolder
     *
     * @return string
     */
    protected function downloadRepoFile($owner, $repository, $branch, $path, $destinationFolder)
    {
        try {
            $file = $this->client->api('repo')->contents()->download(
                $owner,
                $repository,
                $path,
                $branch
            );
        } catch (RuntimeException $e) {
            $this->io->error(sprintf(
                'Error during the copy the %s file from %s/%s:%s with error: %s',
                $path,
                $owner,
                $repository,
                $branch,
                $e->getMessage()
            ));

            throw new \RuntimeException(sprintf('Unable to fetch %s file', $path));
        }
        $this->io->success(sprintf(
            'We copy the %s file from %s/%s:%s',
            $path,
            $owner,
            $repository,
            $branch
        ));

        return $file;
    }
}
