<?php

namespace Release\Helper;

use Github\Client;
use Github\Exception\RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParamChecker
{
    /**
     * @param SymfonyStyle $io
     * @param Client       $this->client
     */
    public function __construct(SymfonyStyle $io, Client $client)
    {
        $this->io     = $io;
        $this->client = $client;
    }

    /**
     * Check that the repository exists and that you have access to it
     * @param string $owner
     * @param string $repo
     *
     * @return array|boolean
     */
    public function checkRepo($owner, $repo)
    {
        try {
            $repository = $this->client->api('repo')->show($owner, $repo);
            $this->io->success(sprintf(
                'Verify access to %s/%s repository>',
                $owner,
                $repo
            ));
        } catch (RuntimeException $e) {
            $this->io->error(sprintf(
                'Verify access to %s/%s repository',
                $owner,
                $repo
            ));

            return false;
        }

        return $repository;
    }

    /**
     * Check that the branch name is valid
     *
     * @param string $branch
     *
     * @return array|boolean
     */
    public function checkBranch($owner, $repository, $branch)
    {
        $this->io->section('Check of parameters validity');
        if (!preg_match('/^\d+.\d+$/', $branch)) {
            $this->io->error('The provided branch name is not valid. Expected: 1.7');

            return false;
        }
        $this->io->success(sprintf(
            'Verify that branch name %s is valid',
            $branch
        ));

        $branches = array_values(array_filter($this->client->api('repo')->branches($owner, $repository), function ($candidate) use ($branch) {
            return $branch === $candidate['name'];
        }));
        if (count($branches) !== 1 || !isset($branches[0])) {
            $this->io->error(sprintf(
                'Verify %s branch existence: The branch %s doesn\'t exists',
                $branch,
                $branch
            ));

            return false;
        }
        $this->io->success(sprintf(
            'Verify %s branch existence',
            $branch
        ));

        return $branches[0]['name'];
    }
}
