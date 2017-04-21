<?php

namespace Release\Helper;

use Github\Client;
use Release\Helper\InformationExtractor;
use Release\Helper\ProcessRunner;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class FileUpdater
{
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
     * Clean composer requirements
     *
     * @param string $repository
     * @param string $editionFolder
     * @param string $branch
     */
    public function cleanRequirements($repository, $editionFolder, $branch)
    {
        $repositoryInformation = InformationExtractor::extractRepositoryInformation($repository);
        if ('dev' !== $repositoryInformation[InformationExtractor::DISTRIBUTION] ||
            'community' !== $repositoryInformation[InformationExtractor::EDITION]
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
                'cd %s && git checkout %s && git add %s && git commit -m "Retablishing use of pim-community-dev %s.x-dev@dev" && ' .
                'git push origin %s',
                $editionFolder,
                $branch,
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
     * Download a file from the specified repo then add it to Git index.
     *
     * @param string $owner
     * @param string $repository
     * @param string $branch
     * @param string $filepath
     * @param string $destinationFolder
     */
    public function copyFileFromRepo($owner, $repository, $branch, $filepath, $destinationFolder)
    {
        try {
            $file = $this->client->api('repo')->contents()->download(
                $owner,
                $repository,
                $filepath,
                $branch
            );
        } catch (\Exception $e) {
            $this->io->error(sprintf(
                'Error during the copy of the file "%s" from %s/%s:%s with error "%s"',
                $filepath,
                $owner,
                $repository,
                $branch,
                $e->getMessage()
            ));

            throw new \RuntimeException(sprintf('Unable to fetch %s file', $filepath));
        }
        $this->io->success(sprintf(
            'We copy the file "%s" from %s/%s:%s',
            $filepath,
            $owner,
            $repository,
            $branch
        ));

        $this->fs->dumpFile($destinationFolder . '/' . $filepath, $file);
        ProcessRunner::runCommand(
            sprintf('cd %s && git add %s', $destinationFolder, $filepath)
        );
    }

    /**
     * Replace occurrences of a pattern in the specified file then add it to Git index.
     *
     * @param string $currentFolder
     * @param string $filepath
     * @param string $pattern
     * @param string $replacement
     */
    public function replaceInFile($currentFolder, $filepath, $pattern, $replacement)
    {
        $file = file_get_contents($currentFolder . '/' . $filepath);
        $file = preg_replace($pattern, $replacement, $file);

        $this->fs->dumpFile($currentFolder . '/' . $filepath, $file);
        ProcessRunner::runCommand(sprintf('cd %s && git add %s', $currentFolder, $filepath));
    }
}
