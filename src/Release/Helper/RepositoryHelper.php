<?php

namespace Release\Helper;

use Release\Helper\ProcessRunner;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RepositoryHelper
{
    protected $io;
    protected $fs;

    /**
     * @param SymfonyStyle $io
     * @param Fileystem $fs
     */
    public function __construct(SymfonyStyle $io, Filesystem $fs)
    {
        $this->io = $io;
        $this->fs = $fs;
    }

    /**
     * Clone given repository
     *
     * @param string $editionFolder
     * @param string $owner
     * @param string $repository
     * @param string $branch
     * @param string $releaseBranch
     *
     * @return boolean
     */
    public function cloneRepository($editionFolder, $owner, $repository, $branch, $releaseBranch)
    {
        try {
            $this->fs->mkdir($editionFolder);
        } catch (IOExceptionInterface $e) {
            $this->io->error(sprintf(
                'Creation of the release folder: Unable to create the %s folder',
                $editionFolder
            ));

            return false;
        }

        $this->io->text(sprintf(
            'Cloning %s/%s repository (this can take up to 5 minutes depending on your connection)',
            $owner,
            $repository
        ));
        try {
            ProcessRunner::runCommand(
                sprintf(
                    'git clone -b %s --single-branch git@github.com:%s/%s.git %s',
                    $branch,
                    $owner,
                    $repository,
                    $editionFolder
                ),
                720
            );
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf(
                'Unable to clone the %s/%s repository with the following error: %s',
                $owner,
                $repository,
                $e->getMessage()
            ));

            return false;
        }
        $this->io->success(sprintf(
            'Clone %s/%s repository',
            $owner,
            $repository
        ));

        try {
            ProcessRunner::runCommand(
                sprintf(
                    'cd %s && git checkout %s && git pull && git checkout -b %s',
                    $editionFolder,
                    $branch,
                    $releaseBranch
                )
            );
        } catch (ProcessFailedException $e) {
            $this->io->error(sprintf(
                'Unable to checkout the new branch %s with the following error: %s',
                $releaseBranch,
                $e->getMessage()
            ));

            return false;
        }
        $this->io->success(sprintf(
            'Checkout of the branch %s (from %s)',
            $releaseBranch,
            $branch
        ));

        return true;
    }

    /**
     * Delete the tag branch
     *
     * @param string $editionFolder
     * @param string $releaseBranch
     */
    public function deleteBranch($editionFolder, $releaseBranch)
    {
        ProcessRunner::runCommand(sprintf('cd %s && git push origin --delete %s', $editionFolder, $releaseBranch));
        $this->io->success(sprintf(
            'We now delete the merged branch'
        ));
    }

    /**
     * Tag the branch
     *
     * @param string $editionFolder
     * @param string $branch
     * @param string $tagName
     * @param string $merged
     *
     * @return boolean
     */
    public function tagRelease($editionFolder, $branch, $tagName, $merged)
    {
        $message = $merged ? 'We just merged the pull request on %s' : 'We didn\'t merged on the %s branch';
        if (!$this->io->confirm(
            sprintf(
                $message .
                "\n" .
                'Do you want to tag version %s from the %s branch now?',
                $branch,
                $tagName,
                $branch
            ), true)
        ) {
            return false;
        }

        ProcessRunner::runCommand(sprintf(
            'cd %s && git checkout %s && git pull && git tag -a v%s -m "Version %s" && git push origin v%s',
            $editionFolder,
            $branch,
            $tagName,
            $tagName,
            $tagName
        ));
        $this->io->success(sprintf(
            'Tag the %s branch with tag v%s',
            $branch,
            $tagName
        ));

        return true;
    }
}
